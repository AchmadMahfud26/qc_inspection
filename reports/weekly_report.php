<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
require_login();

$pdo = getPDO();

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$week = isset($_GET['week']) ? intval($_GET['week']) : date('W');

// Calculate week start and end dates
$week_start = new DateTime();
$week_start->setISODate($year, $week, 1); // Monday
$week_start->format('Y-m-d');

$week_end = clone $week_start;
$week_end->modify('+6 days'); // Sunday

$start_date = $week_start->format('Y-m-d');
$end_date = $week_end->format('Y-m-d');

$page_title = "Laporan Mingguan - Minggu ke-" . $week . " Tahun " . $year;

// Query: Weekly Statistics
$sql_stats = "
    SELECT 
        COUNT(id) as total_inspections,
        SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(id) * 100), 2) as pass_rate
    FROM inspection_headers
    WHERE DATE(inspection_date) BETWEEN ? AND ?
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

// Query: Daily trend data
$sql_daily = "
    SELECT 
        DATE(inspection_date) as inspection_date,
        COUNT(id) as total,
        SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
        SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng,
        SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
    FROM inspection_headers
    WHERE DATE(inspection_date) BETWEEN ? AND ?
    GROUP BY DATE(inspection_date)
    ORDER BY inspection_date ASC
";

$stmt = $pdo->prepare($sql_daily);
$stmt->execute([$start_date, $end_date]);
$daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: Top Defects for the week
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

// Query: By Inspection Type
$sql_by_type = "
    SELECT 
        inspection_type,
        COUNT(id) as total,
        SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
        SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng,
        SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
    FROM inspection_headers
    WHERE DATE(inspection_date) BETWEEN ? AND ?
    GROUP BY inspection_type
    ORDER BY inspection_type
";

$stmt = $pdo->prepare($sql_by_type);
$stmt->execute([$start_date, $end_date]);
$by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <h3 class="page-main-title"><i class="fas fa-file-alt"></i> Laporan Mingguan - Minggu ke-<?php echo $week; ?> Tahun <?php echo $year; ?></h3>
                <div>
                    <form method="GET" class="d-inline me-2">
                        <input type="number" name="year" value="<?php echo $year; ?>" class="form-control d-inline" style="width: auto;" min="2020" required>
                        <input type="number" name="week" value="<?php echo $week; ?>" class="form-control d-inline" style="width: auto;" min="1" max="53" required>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                    </form>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    <a href="export_pdf.php?type=weekly&year=<?php echo $year; ?>&week=<?php echo $week; ?>" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                    <a href="export_excel.php?type=weekly&year=<?php echo $year; ?>&week=<?php echo $week; ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                </div>
            </div>

            <!-- Report Header -->
            <div class="text-center mb-4">
                <h4>LAPORAN INSPEKSI MINGGUAN</h4>
                <p class="text-muted">Periode: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
            </div>

            <!-- Statistics Cards -->
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

            <!-- Daily Trend Chart -->
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-line"></i> Trend Inspeksi Harian</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyTrendChart" style="height: 300px;"></canvas>
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
    // Daily Trend Chart
    var dailyTrendCanvas = document.getElementById('dailyTrendChart');
    if (dailyTrendCanvas) {
        var dailyTrendCtx = dailyTrendCanvas.getContext('2d');
        new Chart(dailyTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($daily_data as $day): ?>
                        '<?php echo date('d M', strtotime($day['inspection_date'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total',
                    data: [<?php echo implode(',', array_column($daily_data, 'total')); ?>],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4
                }, {
                    label: 'PASS',
                    data: [<?php echo implode(',', array_column($daily_data, 'pass')); ?>],
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 3
                }, {
                    label: 'NG',
                    data: [<?php echo implode(',', array_column($daily_data, 'ng')); ?>],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
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
