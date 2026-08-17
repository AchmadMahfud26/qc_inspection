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
$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : '';
$line = isset($_GET['line']) ? $_GET['line'] : '';

// Build WHERE clause
$conditions = [
    "DATE(ih.inspection_date) >= '" . $db->real_escape_string(date('Y-m-d', strtotime($start_date))) . "'",
    "DATE(ih.inspection_date) <= '" . $db->real_escape_string(date('Y-m-d', strtotime($end_date))) . "'"
];

if (!empty($product_id)) {
    $conditions[] = "ih.product_id = '" . $db->real_escape_string($product_id) . "'";
}

if (!empty($line)) {
    $conditions[] = "ih.`line` = '" . $db->real_escape_string($line) . "'";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Query: Top Defects
$sql_defects = "
    SELECT 
        d.id,
        d.defect_name,
        COUNT(id_detail.id) as total_defects,
        COUNT(DISTINCT ih.id) as affected_inspections
    FROM defects d
    LEFT JOIN inspection_details id_detail ON d.id = id_detail.defect_id
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    WHERE (id_detail.defect_id IS NOT NULL) " . ($where_clause ? "AND " . substr($where_clause, 6) : "") . "
    GROUP BY d.id
    ORDER BY total_defects DESC
    LIMIT 10
";

$result_defects = $db->query($sql_defects);
$defects_data = [];
while ($row = $result_defects->fetch_assoc()) {
    $defects_data[] = $row;
}

// Query: Defects by Product
$sql_product_defects = "
    SELECT 
        p.product_code,
        p.product_name,
        d.defect_name,
        COUNT(id_detail.id) as count
    FROM defects d
    LEFT JOIN inspection_details id_detail ON d.id = id_detail.defect_id
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    LEFT JOIN products p ON ih.product_id = p.id
    WHERE (id_detail.defect_id IS NOT NULL) " . ($where_clause ? "AND " . substr($where_clause, 6) : "") . "
    GROUP BY p.id, d.id
    ORDER BY p.product_code, COUNT(id_detail.id) DESC
";

$result_product_defects = $db->query($sql_product_defects);
$product_defects_data = [];
while ($row = $result_product_defects->fetch_assoc()) {
    $product_defects_data[] = $row;
}

// Query: Defects by Inspection Type
$sql_type_defects = "
    SELECT 
        ih.inspection_type,
        d.defect_name,
        COUNT(id_detail.id) as count
    FROM defects d
    LEFT JOIN inspection_details id_detail ON d.id = id_detail.defect_id
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    WHERE (id_detail.defect_id IS NOT NULL) " . ($where_clause ? "AND " . substr($where_clause, 6) : "") . "
    GROUP BY ih.inspection_type, d.id
    ORDER BY ih.inspection_type, COUNT(id_detail.id) DESC
";

$result_type_defects = $db->query($sql_type_defects);
$type_defects_data = [];
while ($row = $result_type_defects->fetch_assoc()) {
    $type_defects_data[] = $row;
}

// Query: Defect trend over time (daily)
$sql_trend = "
    SELECT 
        DATE(ih.inspection_date) as inspection_date,
        COUNT(id_detail.id) as total_defects
    FROM inspection_details id_detail
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    WHERE (id_detail.defect_id IS NOT NULL) " . ($where_clause ? "AND " . substr($where_clause, 6) : "") . "
    GROUP BY DATE(ih.inspection_date)
    ORDER BY inspection_date ASC
";

$result_trend = $db->query($sql_trend);
$trend_data = [];
while ($row = $result_trend->fetch_assoc()) {
    $trend_data[] = $row;
}

// Get products list for filter
$sql_products = "SELECT id, product_code, product_name FROM products WHERE status = 'active' ORDER BY product_code";
$result_products = $db->query($sql_products);
$products_list = [];
while ($row = $result_products->fetch_assoc()) {
    $products_list[] = $row;
}

// Get lines list for filter
$sql_lines = "SELECT DISTINCT `line` FROM inspection_headers ORDER BY `line`";
$result_lines = $db->query($sql_lines);
$lines_list = [];
while ($row = $result_lines->fetch_assoc()) {
    $lines_list[] = $row;
}

$page_title = "Analisa Defect";
$extra_head_content = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
HTML;

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
            <div class="header-top">
                <h1><i class="fas fa-bug"></i> Analisa Defect</h1>
            </div>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h2>Analisa Defect</h2>
                        <p>Analisa detail tentang defect yang terjadi, frekuensi, trend, dan produk yang terdampak</p>
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
                                <label class="form-label">Produk</label>
                                <select name="product_id" class="form-select">
                                    <option value="">-- Semua Produk --</option>
                                    <?php foreach ($products_list as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Line/Area</label>
                                <select name="line" class="form-select">
                                    <option value="">-- Semua Line --</option>
                                    <?php foreach ($lines_list as $line): ?>
                                        <option value="<?php echo htmlspecialchars($line['line']); ?>" <?php echo $line == $line['line'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($line['line']); ?>
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

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Top Defects Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Top 10 Defect</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="topDefectsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Defects Trend Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line"></i> Trend Defect Per Hari</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Defects by Inspection Type -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tasks"></i> Defect berdasarkan Jenis Inspeksi</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="typeDefectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Defect Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-table"></i> Detail Defect</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Defect</th>
                                        <th>Total Defect</th>
                                        <th>Inspeksi Terdampak</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($defects_data) > 0): ?>
                                        <?php foreach ($defects_data as $defect): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($defect['defect_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $defect['total_defects']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo $defect['affected_inspections']; ?> inspeksi
                                                </td>
                                                <td>
                                                    <?php if ($defect['total_defects'] > 5): ?>
                                                        <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Kritis</span>
                                                    <?php elseif ($defect['total_defects'] > 2): ?>
                                                        <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i> Perhatian</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Normal</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Tidak ada data defect</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

    <script>
        // Top Defects Chart
        var topDefectsCtx = document.getElementById('topDefectsChart').getContext('2d');
        var topDefectsChart = new Chart(topDefectsCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($defects_data as $defect): ?>
                        '<?php echo addslashes(htmlspecialchars($defect['defect_name'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Jumlah Defect',
                    data: [
                        <?php foreach ($defects_data as $defect): ?>
                            <?php echo $defect['total_defects']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#dc3545', '#fd7e14', '#ffc107', '#20c997', '#17a2b8',
                        '#0d6efd', '#6610f2', '#e83e8c', '#212529', '#6c757d'
                    ],
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
                        beginAtZero: true
                    }
                }
            }
        });

        // Trend Chart
        var trendCtx = document.getElementById('trendChart').getContext('2d');
        var trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($trend_data as $trend): ?>
                        '<?php echo date('d/m', strtotime($trend['inspection_date'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Jumlah Defect',
                    data: [
                        <?php foreach ($trend_data as $trend): ?>
                            <?php echo $trend['total_defects']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#dc3545'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Defects by Type Chart
        var typeDefectsCtx = document.getElementById('typeDefectsChart').getContext('2d');
        
        // Process data for stacked bar chart
        var types = [];
        var defectNames = [];
        var typeDefectsData = {};
        
        <?php foreach ($type_defects_data as $item): ?>
            types.push('<?php echo addslashes($item['inspection_type']); ?>');
            if (!defectNames.includes('<?php echo addslashes($item['defect_name']); ?>')) {
                defectNames.push('<?php echo addslashes($item['defect_name']); ?>');
            }
            if (!typeDefectsData['<?php echo addslashes($item['inspection_type']); ?>']) {
                typeDefectsData['<?php echo addslashes($item['inspection_type']); ?>'] = {};
            }
            typeDefectsData['<?php echo addslashes($item['inspection_type']); ?>']['<?php echo addslashes($item['defect_name']); ?>'] = <?php echo $item['count']; ?>;
        <?php endforeach; ?>
        
        types = [...new Set(types)];
        
        var colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#17a2b8', '#0d6efd', '#6610f2', '#e83e8c', '#212529', '#6c757d'];
        var datasets = [];
        
        defectNames.forEach(function(defect, index) {
            var data = [];
            types.forEach(function(type) {
                data.push(typeDefectsData[type] && typeDefectsData[type][defect] ? typeDefectsData[type][defect] : 0);
            });
            datasets.push({
                label: defect,
                data: data,
                backgroundColor: colors[index % colors.length],
                borderColor: '#212529',
                borderWidth: 1
            });
        });
        
        var typeDefectsChart = new Chart(typeDefectsCtx, {
            type: 'bar',
            data: {
                labels: types,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
