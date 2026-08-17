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

// Build WHERE clause
$conditions = [
    "DATE(ih.inspection_date) >= '" . $db->real_escape_string(date('Y-m-d', strtotime($start_date))) . "'",
    "DATE(ih.inspection_date) <= '" . $db->real_escape_string(date('Y-m-d', strtotime($end_date))) . "'"
];

$where_clause = implode(" AND ", $conditions);

// Query: Product Quality Summary
$sql_products = "
    SELECT 
        p.id,
        p.product_code,
        p.product_name,
        COUNT(ih.id) as total_inspections,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(ih.id) * 100), 2) as pass_rate
    FROM products p
    LEFT JOIN inspection_headers ih ON p.id = ih.product_id
    WHERE p.status = 'active' AND ($where_clause)
    GROUP BY p.id
    ORDER BY total_inspections DESC
";

$result_products = $db->query($sql_products);
$products_data = [];
while ($row = $result_products->fetch_assoc()) {
    $products_data[] = $row;
}

// Query: Defects by Product
$sql_defects_by_product = "
    SELECT 
        p.product_code,
        p.product_name,
        d.defect_name,
        COUNT(id_detail.id) as count
    FROM products p
    LEFT JOIN inspection_headers ih ON p.id = ih.product_id
    LEFT JOIN inspection_details id_detail ON ih.id = id_detail.inspection_header_id
    LEFT JOIN defects d ON id_detail.defect_id = d.id
    WHERE p.status = 'active' AND id_detail.defect_id IS NOT NULL AND ($where_clause)
    GROUP BY p.id, d.id
    ORDER BY p.product_code, count DESC
";

$result_defects_by_product = $db->query($sql_defects_by_product);
$defects_by_product = [];
while ($row = $result_defects_by_product->fetch_assoc()) {
    if (!isset($defects_by_product[$row['product_code']])) {
        $defects_by_product[$row['product_code']] = [
            'product_name' => $row['product_name'],
            'defects' => []
        ];
    }
    $defects_by_product[$row['product_code']]['defects'][] = $row;
}

// Query: Product Quality Trend (daily)
$sql_product_trend = "
    SELECT 
        p.product_code,
        p.product_name,
        DATE(ih.inspection_date) as inspection_date,
        COUNT(ih.id) as total,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count
    FROM products p
    LEFT JOIN inspection_headers ih ON p.id = ih.product_id AND $where_clause
    WHERE p.status = 'active'
    GROUP BY p.id, DATE(ih.inspection_date)
    ORDER BY p.product_code, inspection_date
";

$result_product_trend = $db->query($sql_product_trend);
$product_trend = [];
while ($row = $result_product_trend->fetch_assoc()) {
    $product_trend[] = $row;
}

$page_title = "Analisa Produk";
$extra_head_content = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
HTML;

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
            <div class="header-top">
                <h1><i class="fas fa-boxes"></i> Analisa Produk</h1>
            </div>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h2>Analisa Produk</h2>
                        <p>Analisa kualitas berdasarkan produk yang diinspeksi, trend, dan defect per produk</p>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter & Pencarian</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Dari Tanggal</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Product Quality Summary Cards -->
                <div class="row mb-4">
                    <?php foreach ($products_data as $product): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">
                                        <i class="fas fa-boxes"></i> 
                                        <?php echo htmlspecialchars($product['product_code']); ?>
                                    </h6>
                                    <p class="card-text small text-muted">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </p>
                                    <hr>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <strong><?php echo $product['total_inspections']; ?></strong>
                                            <div class="small">Total</div>
                                        </div>
                                        <div class="col-4">
                                            <strong class="text-success"><?php echo $product['pass_count']; ?></strong>
                                            <div class="small">PASS</div>
                                        </div>
                                        <div class="col-4">
                                            <strong class="text-danger"><?php echo $product['ng_count']; ?></strong>
                                            <div class="small">NG</div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small>Pass Rate:</small>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $product['pass_rate']; ?>%">
                                                <?php echo $product['pass_rate']; ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Product Pass Rate Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-percent"></i> Pass Rate per Produk</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="passRateChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Product Quality Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-doughnut"></i> Distribusi Inspeksi per Produk</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="productDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Product Quality Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-table"></i> Detail Kualitas Produk</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Total Inspeksi</th>
                                        <th>PASS</th>
                                        <th>NG</th>
                                        <th>HOLD</th>
                                        <th>Pass Rate (%)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products_data as $product): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['product_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($product['product_name']); ?></small>
                                            </td>
                                            <td><?php echo $product['total_inspections']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $product['pass_count']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $product['ng_count']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $product['hold_count']; ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <?php 
                                                        $rate = $product['pass_rate'];
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
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Defects by Product -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-bug"></i> Defect per Produk</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Defect</th>
                                        <th>Jumlah</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $totalDefects = 0;
                                        foreach ($defects_by_product as $productCode => $data) {
                                            foreach ($data['defects'] as $defect) {
                                                $totalDefects += $defect['count'];
                                            }
                                        }
                                    ?>
                                    <?php foreach ($defects_by_product as $productCode => $data): ?>
                                        <?php foreach ($data['defects'] as $index => $defect): ?>
                                            <tr>
                                                <?php if ($index === 0): ?>
                                                    <td rowspan="<?php echo count($data['defects']); ?>">
                                                        <strong><?php echo htmlspecialchars($productCode); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($data['product_name']); ?></small>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars($defect['defect_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $defect['count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $percentage = $totalDefects > 0 ? round(($defect['count'] / $totalDefects) * 100, 2) : 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-danger" style="width: <?php echo $percentage; ?>%">
                                                            <?php echo $percentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    <?php if (empty($defects_by_product)): ?>
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
        // Pass Rate Chart
        var passRateCtx = document.getElementById('passRateChart').getContext('2d');
        var passRateChart = new Chart(passRateCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($products_data as $product): ?>
                        '<?php echo addslashes(htmlspecialchars($product['product_code'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Pass Rate (%)',
                    data: [
                        <?php foreach ($products_data as $product): ?>
                            <?php echo $product['pass_rate']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#28a745',
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

        // Product Distribution Chart
        var distributionCtx = document.getElementById('productDistributionChart').getContext('2d');
        var distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($products_data as $product): ?>
                        '<?php echo addslashes(htmlspecialchars($product['product_code'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($products_data as $product): ?>
                            <?php echo $product['total_inspections']; ?>,
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
