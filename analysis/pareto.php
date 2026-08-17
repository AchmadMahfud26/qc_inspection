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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;

// Build WHERE clause
$conditions = [
    "DATE(ih.inspection_date) >= '" . $db->real_escape_string(date('Y-m-d', strtotime($start_date))) . "'",
    "DATE(ih.inspection_date) <= '" . $db->real_escape_string(date('Y-m-d', strtotime($end_date))) . "'"
];

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Query: Get defects with counts
$sql_defects = "
    SELECT 
        d.id,
        d.defect_name,
        COUNT(id_detail.id) as total_count,
        COUNT(DISTINCT ih.id) as affected_inspections
    FROM defects d
    LEFT JOIN inspection_details id_detail ON d.id = id_detail.defect_id
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    $where_clause AND id_detail.defect_id IS NOT NULL
    GROUP BY d.id
    ORDER BY total_count DESC
    LIMIT $limit
";

$result_defects = $db->query($sql_defects);
$defects_data = [];
$total_defects = 0;

while ($row = $result_defects->fetch_assoc()) {
    $defects_data[] = $row;
    $total_defects += $row['total_count'];
}

// Calculate cumulative percentage
$cumulative = 0;
$pareto_data = [];
foreach ($defects_data as $defect) {
    $cumulative += $defect['total_count'];
    $percentage = $total_defects > 0 ? round(($defect['total_count'] / $total_defects) * 100, 2) : 0;
    $cumulative_percentage = $total_defects > 0 ? round(($cumulative / $total_defects) * 100, 2) : 0;
    
    $pareto_data[] = [
        'defect_name' => $defect['defect_name'],
        'total_count' => $defect['total_count'],
        'percentage' => $percentage,
        'cumulative_count' => $cumulative,
        'cumulative_percentage' => $cumulative_percentage,
        'affected_inspections' => $defect['affected_inspections']
    ];
}

// Calculate total inspections with defects
$sql_total_inspections = "
    SELECT COUNT(DISTINCT ih.id) as total FROM inspection_headers ih
    LEFT JOIN inspection_details id_detail ON ih.id = id_detail.inspection_header_id
    $where_clause AND id_detail.defect_id IS NOT NULL
";

$result_total = $db->query($sql_total_inspections);
$total_inspections_row = $result_total->fetch_assoc();
$total_inspections = $total_inspections_row['total'];

$page_title = "Pareto Defect";
$extra_head_content = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
HTML;

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
            <div class="header-top">
                <h1><i class="fas fa-chart-line"></i> Pareto Defect</h1>
            </div>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h2>Diagram Pareto - Analisis Defect</h2>
                        <p>Identifikasi 20% defect yang menyebabkan 80% dari masalah kualitas (Prinsip Pareto)</p>
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
                            <div class="col-md-2">
                                <label class="form-label">Tampilkan</label>
                                <select name="limit" class="form-select">
                                    <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 Top Defects</option>
                                    <option value="15" <?php echo $limit == 15 ? 'selected' : ''; ?>>15 Top Defects</option>
                                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20 Top Defects</option>
                                    <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 Top Defects</option>
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

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <h6 class="card-title text-primary">Total Defect</h6>
                                <p class="card-text display-6 text-primary"><?php echo $total_defects; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-left-success">
                            <div class="card-body">
                                <h6 class="card-title text-success">Inspeksi Terdampak</h6>
                                <p class="card-text display-6 text-success"><?php echo $total_inspections; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-left-info">
                            <div class="card-body">
                                <h6 class="card-title text-info">Rata-rata Defect/Inspeksi</h6>
                                <p class="card-text display-6 text-info">
                                    <?php echo $total_inspections > 0 ? round($total_defects / $total_inspections, 2) : 0; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-left-warning">
                            <div class="card-body">
                                <h6 class="card-title text-warning">20% Threshold</h6>
                                <p class="card-text display-6 text-warning">
                                    <?php 
                                        $threshold_20 = round($total_defects * 0.2);
                                        echo $threshold_20;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pareto Chart -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Diagram Pareto - Top Defects</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="paretoChart" style="max-height: 400px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pareto Analysis Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-table"></i> Detail Analisis Pareto</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Ranking</th>
                                        <th>Defect</th>
                                        <th>Jumlah</th>
                                        <th>Persentase</th>
                                        <th>Kumulatif</th>
                                        <th>% Kumulatif</th>
                                        <th>Inspeksi Terdampak</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $ranking = 1; foreach ($pareto_data as $item): ?>
                                        <tr <?php echo $item['cumulative_percentage'] <= 80 ? 'class="table-light"' : ''; ?>>
                                            <td>
                                                <span class="badge bg-primary">#<?php echo $ranking; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['defect_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $item['total_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-danger" style="width: <?php echo $item['percentage']; ?>%">
                                                        <?php echo $item['percentage']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo $item['cumulative_count']; ?></strong>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <?php 
                                                        $cum_color = $item['cumulative_percentage'] <= 80 ? 'success' : 'secondary';
                                                    ?>
                                                    <div class="progress-bar bg-<?php echo $cum_color; ?>" style="width: <?php echo $item['cumulative_percentage']; ?>%">
                                                        <?php echo $item['cumulative_percentage']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo $item['affected_inspections']; ?> inspeksi
                                            </td>
                                            <td>
                                                <?php if ($item['cumulative_percentage'] <= 80): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-bullseye"></i> VITAL FEW
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-list"></i> TRIVIAL MANY
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php $ranking++; ?>
                                    <?php endforeach; ?>
                                    <?php if (count($pareto_data) === 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Tidak ada data defect</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pareto Insights -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Insights & Rekomendasi</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($pareto_data) > 0): ?>
                            <div class="alert alert-info" role="alert">
                                <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Analisis Pareto</h6>
                                <p class="mb-2">
                                    Berdasarkan Prinsip Pareto (80/20), sekitar <strong>80% dari masalah kualitas</strong> 
                                    disebabkan oleh hanya <strong>20% dari defect yang ada</strong>.
                                </p>
                                <p class="mb-2">
                                    Pada periode ini, defect <strong>"Vital Few"</strong> (hijau) yang perlu menjadi fokus utama adalah:
                                </p>
                                <ul class="mb-0">
                                    <?php 
                                        $vital_count = 0;
                                        foreach ($pareto_data as $item): 
                                            if ($item['cumulative_percentage'] <= 80 && $vital_count < 5):
                                                $vital_count++;
                                    ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($item['defect_name']); ?></strong>
                                            (<?php echo $item['total_count']; ?> kasus, <?php echo $item['percentage']; ?>%)
                                        </li>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    ?>
                                </ul>
                            </div>

                            <div class="alert alert-success" role="alert">
                                <h6 class="alert-heading"><i class="fas fa-check-circle"></i> Rekomendasi</h6>
                                <ul class="mb-0">
                                    <li>Fokuskan upaya quality improvement pada <strong>Vital Few defects</strong></li>
                                    <li>Investigasi root cause dari defect yang paling sering terjadi</li>
                                    <li>Buat action plan untuk mengurangi top 5 defects</li>
                                    <li>Monitor tren defect setiap minggu untuk mengukur efektivitas improvement</li>
                                    <li>Lakukan training ulang kepada operator tentang top defects</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning" role="alert">
                                Tidak ada data defect pada periode yang dipilih. Silahkan sesuaikan filter tanggal dan coba lagi.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

    <script>
        // Pareto Chart
        var paretoCtx = document.getElementById('paretoChart').getContext('2d');
        
        var chart = new Chart(paretoCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($pareto_data as $item): ?>
                        '<?php echo addslashes(htmlspecialchars(substr($item['defect_name'], 0, 15))); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        type: 'bar',
                        label: 'Jumlah Defect',
                        data: [
                            <?php foreach ($pareto_data as $item): ?>
                                <?php echo $item['total_count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: '#dc3545',
                        borderColor: '#212529',
                        borderWidth: 1,
                        borderRadius: 5,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: '% Kumulatif',
                        data: [
                            <?php foreach ($pareto_data as $item): ?>
                                <?php echo $item['cumulative_percentage']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: '#0d6efd',
                        yAxisID: 'y1',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    annotation: {
                        annotations: {
                            line1: {
                                type: 'line',
                                yMin: 80,
                                yMax: 80,
                                borderColor: 'rgb(255, 193, 7)',
                                borderWidth: 2,
                                borderDash: [5, 5]
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Jumlah Defect'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: '% Kumulatif'
                        },
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
