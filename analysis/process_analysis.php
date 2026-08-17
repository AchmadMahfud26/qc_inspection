<?php
session_start();
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$inspection_type = isset($_GET['inspection_type']) ? $_GET['inspection_type'] : '';

// Build WHERE clause
$conditions = [
    "DATE(ih.inspection_date) >= '" . $db->real_escape_string(date('Y-m-d', strtotime($start_date))) . "'",
    "DATE(ih.inspection_date) <= '" . $db->real_escape_string(date('Y-m-d', strtotime($end_date))) . "'"
];

if (!empty($inspection_type)) {
    $conditions[] = "ih.inspection_type = '" . $db->real_escape_string($inspection_type) . "'";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Query: Line/Area Quality Summary
$sql_lines = "
    SELECT 
        ih.`line`,
        COUNT(ih.id) as total_inspections,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(ih.id) * 100), 2) as pass_rate
    FROM inspection_headers ih
    $where_clause
    GROUP BY ih.`line`
    ORDER BY pass_rate DESC
";

$result_lines = $db->query($sql_lines);
$lines_data = [];
while ($row = $result_lines->fetch_assoc()) {
    $lines_data[] = $row;
}

// Query: Inspection Type Quality
$sql_types = "
    SELECT 
        ih.inspection_type,
        COUNT(ih.id) as total_inspections,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(ih.id) * 100), 2) as pass_rate
    FROM inspection_headers ih
    $where_clause
    GROUP BY ih.inspection_type
    ORDER BY pass_rate DESC
";

$result_types = $db->query($sql_types);
$types_data = [];
while ($row = $result_types->fetch_assoc()) {
    $types_data[] = $row;
}

// Query: Top Defects by Line
$sql_defects_by_line = "
    SELECT 
        ih.`line`,
        d.defect_name,
        COUNT(id_detail.id) as count
    FROM inspection_headers ih
    LEFT JOIN inspection_details id_detail ON ih.id = id_detail.inspection_header_id
    LEFT JOIN defects d ON id_detail.defect_id = d.id
    $where_clause AND id_detail.defect_id IS NOT NULL
    GROUP BY ih.`line`, d.id
    ORDER BY ih.`line`, count DESC
";

$result_defects_by_line = $db->query($sql_defects_by_line);
$defects_by_line = [];
while ($row = $result_defects_by_line->fetch_assoc()) {
    if (!isset($defects_by_line[$row['line']])) {
        $defects_by_line[$row['line']] = [];
    }
    $defects_by_line[$row['line']][] = $row;
}

// Query: Quality Trend by Line (daily)
$sql_line_trend = "
    SELECT 
        ih.`line`,
        DATE(ih.inspection_date) as inspection_date,
        COUNT(ih.id) as total,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count
    FROM inspection_headers ih
    $where_clause
    GROUP BY ih.`line`, DATE(ih.inspection_date)
    ORDER BY ih.`line`, inspection_date
";

$result_line_trend = $db->query($sql_line_trend);
$line_trend = [];
while ($row = $result_line_trend->fetch_assoc()) {
    $line_trend[] = $row;
}

// Get inspection types list for filter
$inspection_types = ['After Welding', 'After Painting', 'Final Check'];

$page_title = "Analisa Proses";
$extra_head_content = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
HTML;

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
            <div class="header-top">
                <h1><i class="fas fa-cog"></i> Analisa Proses</h1>
            </div>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h2>Analisa Proses & Line</h2>
                        <p>Analisa kualitas berdasarkan line/area produksi dan jenis inspeksi</p>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter & Pencarian</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Dari Tanggal</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jenis Inspeksi</label>
                                <select name="inspection_type" class="form-select">
                                    <option value="">-- Semua Jenis --</option>
                                    <?php foreach ($inspection_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $inspection_type == $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Inspection Type Quality Cards -->
                <div class="mb-4">
                    <h5 class="mb-3"><i class="fas fa-tasks"></i> Kualitas per Jenis Inspeksi</h5>
                    <div class="row">
                        <?php foreach ($types_data as $type): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-left-info">
                                    <div class="card-body">
                                        <h6 class="card-title text-info">
                                            <i class="fas fa-check-double"></i> 
                                            <?php echo htmlspecialchars($type['inspection_type']); ?>
                                        </h6>
                                        <hr>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <strong><?php echo $type['total_inspections']; ?></strong>
                                                <div class="small">Total</div>
                                            </div>
                                            <div class="col-4">
                                                <strong class="text-success"><?php echo $type['pass_count']; ?></strong>
                                                <div class="small">PASS</div>
                                            </div>
                                            <div class="col-4">
                                                <strong class="text-danger"><?php echo $type['ng_count']; ?></strong>
                                                <div class="small">NG</div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <small>Pass Rate:</small>
                                            <div class="progress" style="height: 20px;">
                                                <?php 
                                                    $rate = $type['pass_rate'];
                                                    $color = $rate >= 95 ? 'success' : ($rate >= 90 ? 'info' : 'warning');
                                                ?>
                                                <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $rate; ?>%">
                                                    <?php echo $rate; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Line Pass Rate Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Pass Rate per Line/Area</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="linePassRateChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Line Inspection Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie"></i> Distribusi Inspeksi per Line</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="lineDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Line Quality Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-table"></i> Detail Kualitas per Line/Area</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Line/Area</th>
                                        <th>Total Inspeksi</th>
                                        <th>PASS</th>
                                        <th>NG</th>
                                        <th>HOLD</th>
                                        <th>Pass Rate (%)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lines_data as $line): ?>
                                        <tr>
                                            <td>
                                                <strong><i class="fas fa-map-marker-alt"></i> 
                                                <?php echo htmlspecialchars($line['line']); ?></strong>
                                            </td>
                                            <td><?php echo $line['total_inspections']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $line['pass_count']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $line['ng_count']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $line['hold_count']; ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <?php 
                                                        $rate = $line['pass_rate'];
                                                        $color = $rate >= 95 ? 'success' : ($rate >= 90 ? 'info' : 'warning');
                                                    ?>
                                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $rate; ?>%">
                                                        <?php echo $rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($rate >= 95): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Excellent</span>
                                                <?php elseif ($rate >= 90): ?>
                                                    <span class="badge bg-info"><i class="fas fa-check-circle"></i> Good</span>
                                                <?php elseif ($rate >= 80): ?>
                                                    <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i> Fair</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Poor</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($lines_data) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Tidak ada data inspeksi</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Defects by Line -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bug"></i> Top Defect per Line/Area</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="defectsByLineAccordion">
                            <?php $index = 0; foreach ($defects_by_line as $lineId => $defects): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#line<?php echo $index; ?>">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <strong><?php echo htmlspecialchars($lineId); ?></strong>
                                            <span class="badge bg-secondary ms-2"><?php echo count($defects); ?> defect</span>
                                        </button>
                                    </h2>
                                    <div id="line<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#defectsByLineAccordion">
                                        <div class="accordion-body p-0">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Defect</th>
                                                        <th>Jumlah</th>
                                                        <th>Persentase</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                        $totalDefectsLine = array_sum(array_map(function($d) { return $d['count']; }, $defects));
                                                    ?>
                                                    <?php foreach (array_slice($defects, 0, 5) as $defect): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($defect['defect_name']); ?></td>
                                                            <td>
                                                                <span class="badge bg-danger"><?php echo $defect['count']; ?></span>
                                                            </td>
                                                            <td>
                                                                <?php $pct = $totalDefectsLine > 0 ? round(($defect['count'] / $totalDefectsLine) * 100, 2) : 0; ?>
                                                                <div class="progress" style="height: 20px;">
                                                                    <div class="progress-bar bg-danger" style="width: <?php echo $pct; ?>%">
                                                                        <?php echo $pct; ?>%
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php $index++; ?>
                            <?php endforeach; ?>
                            <?php if (empty($defects_by_line)): ?>
                                <div class="text-center text-muted py-4">Tidak ada data defect</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

    <script>
        // Line Pass Rate Chart
        var linePassRateCtx = document.getElementById('linePassRateChart').getContext('2d');
        var linePassRateChart = new Chart(linePassRateCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($lines_data as $line): ?>
                        '<?php echo addslashes(htmlspecialchars($line['line'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Pass Rate (%)',
                    data: [
                        <?php foreach ($lines_data as $line): ?>
                            <?php echo $line['pass_rate']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#17a2b8',
                    borderColor: '#212529',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Line Distribution Chart
        var lineDistributionCtx = document.getElementById('lineDistributionChart').getContext('2d');
        var lineDistributionChart = new Chart(lineDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($lines_data as $line): ?>
                        '<?php echo addslashes(htmlspecialchars($line['line'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($lines_data as $line): ?>
                            <?php echo $line['total_inspections']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#0d6efd', '#0dcaf0', '#198754', '#ffc107', '#fd7e14', '#dc3545',
                        '#6f42c1', '#e83e8c', '#20c997', '#6c757d'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right'
                    }
                }
            }
        });
    </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
