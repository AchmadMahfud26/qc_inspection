<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
require_login();

$pdo = getPDO();

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

$page_title = "Laporan Bulanan - " . date('F Y', strtotime($start_date));

// Query: Monthly Statistics
$sql_stats = "
    SELECT 
        COUNT(ih.id) as total_inspections,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(ih.id) * 100), 2) as pass_rate
    FROM inspection_headers ih
    WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
";

$stmt = $pdo->prepare($sql_stats);
$stmt->execute([$start_date, $end_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats['total_inspections']) {
    $stats = [
        'total_inspections' => 0,
        'pass_count' => 0,
        'ng_count' => 0,
        'hold_count' => 0,
        'pass_rate' => 0
    ];
}

// Query: Daily summary for the month
$sql_daily = "
    SELECT 
        DATE(ih.inspection_date) as inspection_date,
        COUNT(ih.id) as total,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
    FROM inspection_headers ih
    WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
    GROUP BY DATE(ih.inspection_date)
    ORDER BY inspection_date ASC
";

$stmt = $pdo->prepare($sql_daily);
$stmt->execute([$start_date, $end_date]);
$daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: By Inspection Type
$sql_by_type = "
    SELECT 
        ih.inspection_type,
        COUNT(ih.id) as total,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
    FROM inspection_headers ih
    WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
    GROUP BY ih.inspection_type
    ORDER BY ih.inspection_type
";

$stmt = $pdo->prepare($sql_by_type);
$stmt->execute([$start_date, $end_date]);
$by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: By Product
$sql_by_product = "
    SELECT 
        p.product_code,
        p.product_name,
        COUNT(ih.id) as total,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
    FROM inspection_headers ih
    LEFT JOIN products p ON ih.product_id = p.id
    WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total DESC
";

$stmt = $pdo->prepare($sql_by_product);
$stmt->execute([$start_date, $end_date]);
$by_product = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: By Line
$sql_by_line = "
    SELECT 
        ih.`line`,
        COUNT(ih.id) as total,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
    FROM inspection_headers ih
    WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
    GROUP BY ih.`line`
    ORDER BY total DESC
";

$stmt = $pdo->prepare($sql_by_line);
$stmt->execute([$start_date, $end_date]);
$by_line = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: Top Defects
$sql_defects = "
    SELECT 
        d.defect_name,
        COUNT(id_detail.id) as count
    FROM defects d
    LEFT JOIN inspection_details id_detail ON d.id = id_detail.defect_id
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
    AND id_detail.defect_id IS NOT NULL
    GROUP BY d.id
    ORDER BY count DESC
    LIMIT 10
";

$stmt = $pdo->prepare($sql_defects);
$stmt->execute([$start_date, $end_date]);
$defects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extra_head_content = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<style>
    .report-section { page-break-inside: avoid; margin-bottom: 30px; }
    .stat-card { text-align: center; padding: 20px; }
    @media print {
        .navbar,
        .sidebar-container,
        footer,
        .btn,
        .no-print,
        canvas {
            display: none !important;
        }
        body,
        .main-content {
            background: white !important;
        }
        .main-content {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
</style>
HTML;

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

            <!-- Report Toolbar -->
            <div class="d-flex justify-content-between align-items-center page-toolbar no-print">
                <h3 class="page-main-title"><i class="fas fa-file-alt"></i> Laporan Bulanan - <?php echo date('F Y', strtotime($start_date)); ?></h3>
                <div>
                    <form method="GET" class="d-inline me-2">
                        <input type="number" name="year" value="<?php echo $year; ?>" class="form-control d-inline" style="width: auto;" min="2020" required>
                        <select name="month" class="form-select d-inline" style="width: auto;" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                    </form>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    <a href="export_pdf.php?type=monthly&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                    <a href="export_excel.php?type=monthly&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                </div>
            </div>

            <!-- Report Header -->
            <div class="text-center mb-4">
                <h4>LAPORAN INSPEKSI BULANAN</h4>
                <p class="text-muted">Periode: <?php echo date('F Y', strtotime($start_date)); ?></p>
            </div>

            <!-- Summary Statistics -->
            <div class="row report-section">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Inspeksi</h6>
                            <h3><?php echo $stats['total_inspections']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">PASS</h6>
                            <h3><?php echo $stats['pass_count']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">NG</h6>
                            <h3><?php echo $stats['ng_count']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Pass Rate</h6>
                            <h3><?php echo $stats['pass_rate']; ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- OK vs NG Pie Chart -->
            <div class="row report-section">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Distribusi PASS vs NG</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="passNgChart" style="height: 250px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Trend -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-chart-line"></i> Trend Harian</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyTrendChart" style="height: 250px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- By Inspection Type -->
            <?php if (count($by_type) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-list"></i> Inspeksi Berdasarkan Jenis</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr class="table-light">
                                        <th>Jenis Inspeksi</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">PASS</th>
                                        <th class="text-center">NG</th>
                                        <th class="text-center">HOLD</th>
                                        <th class="text-center">Pass Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_type as $type): 
                                        $type_pass_rate = $type['total'] > 0 ? round(($type['pass'] / $type['total']) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['inspection_type']); ?></td>
                                        <td class="text-center"><strong><?php echo $type['total']; ?></strong></td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $type['pass']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $type['ng']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-warning"><?php echo $type['hold']; ?></span></td>
                                        <td class="text-center"><?php echo $type_pass_rate; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- By Product -->
            <?php if (count($by_product) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-box"></i> Inspeksi Berdasarkan Produk</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr class="table-light">
                                        <th>Produk</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">PASS</th>
                                        <th class="text-center">NG</th>
                                        <th class="text-center">HOLD</th>
                                        <th class="text-center">Pass Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_product as $prod): 
                                        $prod_pass_rate = $prod['total'] > 0 ? round(($prod['pass'] / $prod['total']) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($prod['product_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($prod['product_name']); ?></small>
                                        </td>
                                        <td class="text-center"><strong><?php echo $prod['total']; ?></strong></td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $prod['pass']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $prod['ng']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-warning"><?php echo $prod['hold']; ?></span></td>
                                        <td class="text-center"><?php echo $prod_pass_rate; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- By Line -->
            <?php if (count($by_line) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-industry"></i> Inspeksi Berdasarkan Line/Area</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr class="table-light">
                                        <th>Line/Area</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">PASS</th>
                                        <th class="text-center">NG</th>
                                        <th class="text-center">HOLD</th>
                                        <th class="text-center">Pass Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_line as $line): 
                                        $line_pass_rate = $line['total'] > 0 ? round(($line['pass'] / $line['total']) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($line['line']); ?></td>
                                        <td class="text-center"><strong><?php echo $line['total']; ?></strong></td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $line['pass']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $line['ng']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-warning"><?php echo $line['hold']; ?></span></td>
                                        <td class="text-center"><?php echo $line_pass_rate; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Defects -->
            <?php if (count($defects) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Top 10 Defect</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="topDefectsChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
<script>
    // PASS vs NG Pie Chart
    var passNgCanvas = document.getElementById('passNgChart');
    if (passNgCanvas) {
        var passNgCtx = passNgCanvas.getContext('2d');
        new Chart(passNgCtx, {
            type: 'doughnut',
            data: {
                labels: ['PASS', 'NG', 'HOLD'],
                datasets: [{
                    data: [<?php echo $stats['pass_count']; ?>, <?php echo $stats['ng_count']; ?>, <?php echo $stats['hold_count']; ?>],
                    backgroundColor: ['#198754', '#dc3545', '#ffc107'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Daily Trend Chart
    var dailyTrendCanvas = document.getElementById('dailyTrendChart');
    if (dailyTrendCanvas) {
        var dailyTrendCtx = dailyTrendCanvas.getContext('2d');
        new Chart(dailyTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($daily_data as $day): ?>
                        '<?php echo date('d', strtotime($day['inspection_date'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total',
                    data: [<?php echo implode(',', array_column($daily_data, 'total')); ?>],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Top Defects Chart
    var topDefectsCanvas = document.getElementById('topDefectsChart');
    if (topDefectsCanvas) {
        var topDefectsCtx = topDefectsCanvas.getContext('2d');
        new Chart(topDefectsCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($defects as $defect): ?>
                        '<?php echo addslashes(htmlspecialchars($defect['defect_name'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Jumlah Defect',
                    data: [<?php echo implode(',', array_column($defects, 'count')); ?>],
                    backgroundColor: '#dc3545',
                    borderColor: '#212529',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
