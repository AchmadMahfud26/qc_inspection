<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
require_login();

$pdo = getPDO();

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : '';
$inspection_type = isset($_GET['inspection_type']) ? $_GET['inspection_type'] : '';
$line = isset($_GET['line']) ? $_GET['line'] : '';

$page_title = "Laporan Custom";

// Build WHERE clause
$conditions = [
    "DATE(ih.inspection_date) BETWEEN ? AND ?"
];
$params = [$start_date, $end_date];

if (!empty($product_id)) {
    $conditions[] = "ih.product_id = ?";
    $params[] = $product_id;
}

if (!empty($inspection_type)) {
    $conditions[] = "ih.inspection_type = ?";
    $params[] = $inspection_type;
}

if (!empty($line)) {
    $conditions[] = "ih.`line` = ?";
    $params[] = $line;
}

$where_clause = implode(" AND ", $conditions);

// Query: Custom Statistics
$sql_stats = "
    SELECT 
        COUNT(ih.id) as total_inspections,
        SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(ih.id) * 100), 2) as pass_rate
    FROM inspection_headers ih
    WHERE $where_clause
";

$stmt = $pdo->prepare($sql_stats);
$stmt->execute($params);
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

// Get filter options
$sql_products = "SELECT id, product_code, product_name FROM products WHERE status = 'active' ORDER BY product_code";
$stmt = $pdo->prepare($sql_products);
$stmt->execute();
$products_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_types = "SELECT DISTINCT inspection_type FROM inspection_headers ORDER BY inspection_type";
$stmt = $pdo->prepare($sql_types);
$stmt->execute();
$types_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_lines = "SELECT DISTINCT `line` FROM inspection_headers ORDER BY `line`";
$stmt = $pdo->prepare($sql_lines);
$stmt->execute();
$lines_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: Inspections List
$sql_inspections = "
    SELECT 
        ih.inspection_no,
        ih.inspection_type,
        ih.inspection_date,
        ih.product_id,
        p.product_code,
        p.product_name,
        ih.final_result,
        u.username as inspector,
        ih.`line`,
        ih.shift,
        COUNT(id_detail.id) as defect_count
    FROM inspection_headers ih
    LEFT JOIN products p ON ih.product_id = p.id
    LEFT JOIN users u ON ih.inspector_id = u.id
    LEFT JOIN inspection_details id_detail ON ih.id = id_detail.inspection_header_id
    WHERE " . $where_clause . "
    GROUP BY ih.id
    ORDER BY ih.inspection_date DESC
";

$stmt = $pdo->prepare($sql_inspections);
$stmt->execute($params);
$inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extra_head_content = <<<'HTML'
<style>
    .stat-card { text-align: center; padding: 20px; }
    @media print {
        .navbar,
        .sidebar-container,
        footer,
        .btn,
        .no-print {
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

            <h3 class="page-main-title mb-4"><i class="fas fa-file-alt"></i> Laporan Custom</h3>

            <!-- Filter Section -->
            <div class="card mb-4 shadow-sm no-print">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> Filter & Pencarian</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Dari Tanggal:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">Sampai Tanggal:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="product_id" class="form-label">Produk:</label>
                            <select class="form-select" id="product_id" name="product_id">
                                <option value="">-- Semua Produk --</option>
                                <?php foreach ($products_list as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>" <?php echo $product_id == $prod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prod['product_code']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="inspection_type" class="form-label">Jenis Inspeksi:</label>
                            <select class="form-select" id="inspection_type" name="inspection_type">
                                <option value="">-- Semua Jenis --</option>
                                <?php foreach ($types_list as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['inspection_type']); ?>" <?php echo $inspection_type == $type['inspection_type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['inspection_type']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="line" class="form-label">Line/Area:</label>
                            <select class="form-select" id="line" name="line">
                                <option value="">-- Semua Line --</option>
                                <?php foreach ($lines_list as $ln): ?>
                                <option value="<?php echo htmlspecialchars($ln['line']); ?>" <?php echo $line == $ln['line'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ln['line']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Cari</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h5>Hasil Laporan: <strong><?php echo count($inspections); ?></strong> Inspeksi</h5>
                </div>
                <div>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    <a href="export_pdf.php?type=custom&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&product_id=<?php echo $product_id; ?>&inspection_type=<?php echo $inspection_type; ?>&line=<?php echo $line; ?>" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                    <a href="export_excel.php?type=custom&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&product_id=<?php echo $product_id; ?>&inspection_type=<?php echo $inspection_type; ?>&line=<?php echo $line; ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
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

            <!-- Detailed Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-table"></i> Detail Inspeksi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover">
                            <thead>
                                <tr class="table-light">
                                    <th>ID Inspeksi</th>
                                    <th>Tipe</th>
                                    <th>Tanggal</th>
                                    <th>Produk</th>
                                    <th>Line</th>
                                    <th>Shift</th>
                                    <th>Inspector</th>
                                    <th>Defect</th>
                                    <th>Hasil</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($inspections) > 0): ?>
                                    <?php foreach ($inspections as $insp): ?>
                                    <tr>
                                        <td><small><?php echo htmlspecialchars($insp['inspection_no']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($insp['inspection_type']); ?></small></td>
                                        <td><small><?php echo date('d M Y', strtotime($insp['inspection_date'])); ?></small></td>
                                        <td>
                                            <small>
                                                <strong><?php echo htmlspecialchars($insp['product_code']); ?></strong><br>
                                                <em><?php echo htmlspecialchars($insp['product_name']); ?></em>
                                            </small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($insp['line']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($insp['shift']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($insp['inspector']); ?></small></td>
                                        <td><small><?php echo $insp['defect_count']; ?></small></td>
                                        <td>
                                            <?php 
                                            $result_class = '';
                                            if ($insp['final_result'] == 'PASS') $result_class = 'bg-success';
                                            elseif ($insp['final_result'] == 'NG') $result_class = 'bg-danger';
                                            else $result_class = 'bg-warning';
                                            ?>
                                            <span class="badge <?php echo $result_class; ?>"><?php echo htmlspecialchars($insp['final_result']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox"></i> Tidak ada data inspeksi yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
