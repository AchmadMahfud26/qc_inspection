<?php
session_start();

// Include config
require_once '../../config/config.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check login
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Initialize pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$sql = "SELECT 
            ih.id,
            ih.inspection_no,
            ih.inspection_date,
            ih.inspection_time,
            p.product_code,
            p.product_name,
            ih.part_number,
            ih.serial_number,
            ih.line,
            ih.shift,
            u.name as inspector_name,
            ih.final_result,
            ih.created_at
        FROM inspection_headers ih
        LEFT JOIN products p ON ih.product_id = p.id
        LEFT JOIN users u ON ih.inspector_id = u.id
        WHERE ih.inspection_type = 'Final Check'
        ORDER BY ih.inspection_date DESC, ih.inspection_time DESC
        LIMIT $offset, $limit";

$result = $db->query($sql);
if (!$result) {
    die('Query Error: ' . $db->error);
}

$inspections = $result->fetch_all(MYSQLI_ASSOC);

// Get total records
$total_sql = "SELECT COUNT(*) as total FROM inspection_headers WHERE inspection_type = 'Final Check'";
$total_result = $db->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Page title
$page_title = 'Final Check Inspection';
?>

<?php include '../../includes/header.php'; ?>

<div class="container-fluid pt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="page-title">Final Check Inspection</h2>
            <p class="text-muted">Data inspeksi produk sebelum pengiriman ke customer</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if (in_array($_SESSION['user']['role'], ['admin', 'qc_inspector'])): ?>
                <a href="<?php echo BASE_URL; ?>/inspection/final_check/create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Inspeksi Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Total Inspeksi</h6>
                    <h3><?php echo number_format($total_records); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">PASS</h6>
                    <?php
                    $pass_sql = "SELECT COUNT(*) as total FROM inspection_headers WHERE inspection_type = 'Final Check' AND final_result = 'PASS'";
                    $pass_result = $db->query($pass_sql);
                    $pass_row = $pass_result->fetch_assoc();
                    ?>
                    <h3 class="text-success"><?php echo number_format($pass_row['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">NG</h6>
                    <?php
                    $ng_sql = "SELECT COUNT(*) as total FROM inspection_headers WHERE inspection_type = 'Final Check' AND final_result = 'NG'";
                    $ng_result = $db->query($ng_sql);
                    $ng_row = $ng_result->fetch_assoc();
                    ?>
                    <h3 class="text-danger"><?php echo number_format($ng_row['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">HOLD</h6>
                    <?php
                    $hold_sql = "SELECT COUNT(*) as total FROM inspection_headers WHERE inspection_type = 'Final Check' AND final_result = 'HOLD'";
                    $hold_result = $db->query($hold_sql);
                    $hold_row = $hold_result->fetch_assoc();
                    ?>
                    <h3 class="text-warning"><?php echo number_format($hold_row['total']); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>No. Inspeksi</th>
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
                                        <?php echo date('d/m/Y H:i', strtotime($insp['inspection_date'] . ' ' . $insp['inspection_time'])); ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($insp['product_code'] ?? '-'); ?></small><br>
                                        <?php echo htmlspecialchars($insp['product_name'] ?? '-'); ?>
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
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $insp['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (in_array($_SESSION['user']['role'], ['admin', 'qc_inspector'])): ?>
                                                <a href="edit.php?id=<?php echo $insp['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $insp['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Yakin hapus data ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
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
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1">First</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
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
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                            }
                        }

                        if ($end_page < $total_pages) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>">Last</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
