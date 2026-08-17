<?php
session_start();

// Include config
require_once './config/config.php';
require_once './config/db.php';
require_once './includes/functions.php';

// Check login
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Initialize pagination & filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_result = isset($_GET['result']) ? $_GET['result'] : '';
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_product = isset($_GET['product']) ? (int)$_GET['product'] : '';

// Build WHERE clause
$where_conditions = [];

if (!empty($filter_type)) {
    $where_conditions[] = "ih.inspection_type = '" . $db->real_escape_string($filter_type) . "'";
}

if (!empty($filter_result)) {
    $where_conditions[] = "ih.final_result = '" . $db->real_escape_string($filter_result) . "'";
}

if (!empty($filter_start_date)) {
    $where_conditions[] = "DATE(ih.inspection_date) >= '" . $db->real_escape_string($filter_start_date) . "'";
}

if (!empty($filter_end_date)) {
    $where_conditions[] = "DATE(ih.inspection_date) <= '" . $db->real_escape_string($filter_end_date) . "'";
}

if (!empty($filter_product)) {
    $where_conditions[] = "ih.product_id = " . $filter_product;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Build main query
$sql = "SELECT 
            ih.id,
            ih.inspection_no,
            ih.inspection_type,
            ih.inspection_date,
            ih.inspection_time,
            p.product_code,
            p.product_name,
            ih.part_number,
            ih.serial_number,
            ih.line,
            u.name as inspector_name,
            ih.final_result,
            ih.created_at
        FROM inspection_headers ih
        LEFT JOIN products p ON ih.product_id = p.id
        LEFT JOIN users u ON ih.inspector_id = u.id
        $where_clause
        ORDER BY ih.inspection_date DESC, ih.inspection_time DESC
        LIMIT $offset, $limit";

$result = $db->query($sql);
if (!$result) {
    die('Query Error: ' . $db->error);
}

$inspections = $result->fetch_all(MYSQLI_ASSOC);

// Get total records
$total_sql = "SELECT COUNT(*) as total FROM inspection_headers ih
              LEFT JOIN products p ON ih.product_id = p.id
              LEFT JOIN users u ON ih.inspector_id = u.id
              $where_clause";
$total_result = $db->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Get statistics
$stats = [];
$types = ['After Welding', 'After Painting', 'Final Check'];
foreach ($types as $type) {
    $type_sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
                    SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
                    SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count
                 FROM inspection_headers 
                 WHERE inspection_type = '$type'";
    $type_result = $db->query($type_sql);
    $stats[$type] = $type_result->fetch_assoc();
}

// Get products for filter dropdown
$products_sql = "SELECT id, product_code, product_name FROM products WHERE status = 'active' ORDER BY product_name";
$products_result = $db->query($products_sql);
$products = $products_result->fetch_all(MYSQLI_ASSOC);

$page_title = 'Data Inspection';
?>

<?php include './includes/header.php'; ?>

<div class="container-fluid pt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="page-title">Data Inspection</h2>
            <p class="text-muted">Lihat semua data inspeksi dari After Welding, After Painting, dan Final Check</p>
        </div>
    </div>

    <!-- Statistics Cards by Type -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h5 class="mb-3"><i class="fas fa-chart-bar"></i> Statistik Inspeksi</h5>
        </div>
        <?php foreach ($stats as $type => $stat): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <?php echo $type; ?>
                        </h6>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Total</small>
                                <h5 class="mb-0"><?php echo number_format($stat['total']); ?></h5>
                            </div>
                            <div class="col-6">
                                <small class="text-success d-block">PASS</small>
                                <h5 class="text-success mb-0"><?php echo number_format($stat['pass_count'] ?? 0); ?></h5>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <small class="text-danger d-block">NG</small>
                                <h5 class="text-danger mb-0"><?php echo number_format($stat['ng_count'] ?? 0); ?></h5>
                            </div>
                            <div class="col-6">
                                <small class="text-warning d-block">HOLD</small>
                                <h5 class="text-warning mb-0"><?php echo number_format($stat['hold_count'] ?? 0); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter & Pencarian</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Jenis Inspeksi</label>
                    <select class="form-control" name="type">
                        <option value="">-- Semua --</option>
                        <option value="After Welding" <?php echo $filter_type === 'After Welding' ? 'selected' : ''; ?>>After Welding</option>
                        <option value="After Painting" <?php echo $filter_type === 'After Painting' ? 'selected' : ''; ?>>After Painting</option>
                        <option value="Final Check" <?php echo $filter_type === 'Final Check' ? 'selected' : ''; ?>>Final Check</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasil</label>
                    <select class="form-control" name="result">
                        <option value="">-- Semua --</option>
                        <option value="PASS" <?php echo $filter_result === 'PASS' ? 'selected' : ''; ?>>PASS</option>
                        <option value="NG" <?php echo $filter_result === 'NG' ? 'selected' : ''; ?>>NG</option>
                        <option value="HOLD" <?php echo $filter_result === 'HOLD' ? 'selected' : ''; ?>>HOLD</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Produk</label>
                    <select class="form-control" name="product">
                        <option value="">-- Semua --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo $filter_product == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Cari
                    </button>
                </div>
            </form>
            <?php if (!empty($filter_type) || !empty($filter_result) || !empty($filter_start_date) || !empty($filter_end_date) || !empty($filter_product)): ?>
                <div class="mt-3">
                    <a href="data_inspection.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filter
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-table"></i> Data Inspeksi (Total: <?php echo number_format($total_records); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>No. Inspeksi</th>
                            <th>Jenis</th>
                            <th>Tanggal</th>
                            <th>Produk</th>
                            <th>Part Number</th>
                            <th>Serial Number</th>
                            <th>Line</th>
                            <th>Inspector</th>
                            <th>Hasil</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($inspections) > 0): ?>
                            <?php foreach ($inspections as $insp): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($insp['inspection_no']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $type_abbr = str_replace(' ', '', strtoupper(substr($insp['inspection_type'], 0, 2)));
                                        if ($insp['inspection_type'] === 'After Welding') $type_abbr = 'AW';
                                        elseif ($insp['inspection_type'] === 'After Painting') $type_abbr = 'AP';
                                        elseif ($insp['inspection_type'] === 'Final Check') $type_abbr = 'FC';
                                        ?>
                                        <span class="badge bg-info"><?php echo $type_abbr; ?></span>
                                        <small><?php echo htmlspecialchars($insp['inspection_type']); ?></small>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($insp['inspection_date'] . ' ' . $insp['inspection_time'])); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars($insp['product_code'] ?? '-'); ?></small><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($insp['product_name'] ?? '-'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($insp['part_number'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($insp['serial_number'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($insp['line'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($insp['inspector_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($insp['final_result'] === 'PASS'): ?>
                                            <span class="badge bg-success">PASS</span>
                                        <?php elseif ($insp['final_result'] === 'NG'): ?>
                                            <span class="badge bg-danger">NG</span>
                                        <?php elseif ($insp['final_result'] === 'HOLD'): ?>
                                            <span class="badge bg-warning">HOLD</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">UNKNOWN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php
                                            $type_path = '';
                                            if ($insp['inspection_type'] === 'After Welding') $type_path = 'after_welding';
                                            elseif ($insp['inspection_type'] === 'After Painting') $type_path = 'after_painting';
                                            elseif ($insp['inspection_type'] === 'Final Check') $type_path = 'final_check';
                                            ?>
                                            <a href="<?php echo BASE_URL; ?>/inspection/<?php echo $type_path; ?>/view.php?id=<?php echo $insp['id']; ?>" 
                                               class="btn btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    Tidak ada data inspeksi
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?><?php echo !empty($filter_result) ? '&result=' . urlencode($filter_result) : ''; ?>">First</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?><?php echo !empty($filter_result) ? '&result=' . urlencode($filter_result) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i === $page) {
                                echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                            } else {
                                $query_string = "?page=" . $i;
                                if (!empty($filter_type)) $query_string .= '&type=' . urlencode($filter_type);
                                if (!empty($filter_result)) $query_string .= '&result=' . urlencode($filter_result);
                                if (!empty($filter_product)) $query_string .= '&product=' . $filter_product;
                                echo '<li class="page-item"><a class="page-link" href="' . $query_string . '">' . $i . '</a></li>';
                            }
                        }

                        if ($end_page < $total_pages) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?><?php echo !empty($filter_result) ? '&result=' . urlencode($filter_result) : ''; ?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?><?php echo !empty($filter_result) ? '&result=' . urlencode($filter_result) : ''; ?>">Last</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include './includes/footer.php'; ?>
