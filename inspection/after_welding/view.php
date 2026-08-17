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

// Get inspection ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header('Location: index.php?error=Invalid inspection ID');
    exit;
}

// Get inspection header
$header_sql = "SELECT 
                ih.id,
                ih.inspection_no,
                ih.inspection_date,
                ih.inspection_time,
                ih.inspection_type,
                p.product_code,
                p.product_name,
                ih.part_number,
                ih.serial_number,
                ih.production_order,
                ih.lot_number,
                ih.line,
                ih.shift,
                u.name as inspector_name,
                ih.final_result,
                ih.remark,
                ih.created_at,
                ih.updated_at
            FROM inspection_headers ih
            LEFT JOIN products p ON ih.product_id = p.id
            LEFT JOIN users u ON ih.inspector_id = u.id
            WHERE ih.id = $id AND ih.inspection_type = 'After Welding'";

$header_result = $db->query($header_sql);
if ($header_result->num_rows === 0) {
    header('Location: index.php?error=Inspeksi tidak ditemukan');
    exit;
}

$header = $header_result->fetch_assoc();

// Get inspection details
$details_sql = "SELECT 
                id.id,
                id.inspection_item_id,
                id.standard,
                id.method,
                id.result,
                id.status,
                id.defect_id,
                id.defect_location,
                id.remark,
                ii.item_name,
                d.defect_code,
                d.defect_name
            FROM inspection_details id
            LEFT JOIN inspection_items ii ON id.inspection_item_id = ii.id
            LEFT JOIN defects d ON id.defect_id = d.id
            WHERE id.inspection_header_id = $id
            ORDER BY ii.sequence";

$details_result = $db->query($details_sql);
$details = $details_result->fetch_all(MYSQLI_ASSOC);

$page_title = 'View After Welding Inspection - ' . htmlspecialchars($header['inspection_no']);
?>

<?php include '../../includes/header.php'; ?>

<div class="container-fluid pt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="page-title"><?php echo htmlspecialchars($header['inspection_no']); ?></h2>
            <p class="text-muted">Detail Inspeksi After Welding</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <?php if (in_array($_SESSION['user']['role'], ['admin', 'qc_inspector'])): ?>
                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data ini?')">
                    <i class="fas fa-trash"></i> Hapus
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECTION 1: INSPECTION HEADER INFO -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Inspeksi</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>No. Inspeksi:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['inspection_no']); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Tanggal:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo date('d/m/Y H:i', strtotime($header['inspection_date'] . ' ' . $header['inspection_time'])); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Produk:</strong>
                        </div>
                        <div class="col-sm-8">
                            <small class="text-muted"><?php echo htmlspecialchars($header['product_code'] ?? '-'); ?></small><br>
                            <?php echo htmlspecialchars($header['product_name'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Part Number:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['part_number'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Serial Number:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['serial_number'] ?? '-'); ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Production Order:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['production_order'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Lot Number:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['lot_number'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Line:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['line'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Shift:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['shift'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <strong>Inspector:</strong>
                        </div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($header['inspector_name'] ?? '-'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($header['remark'])): ?>
                <div class="row">
                    <div class="col-sm-2">
                        <strong>Catatan:</strong>
                    </div>
                    <div class="col-sm-10">
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($header['remark'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECTION 2: FINAL RESULT -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <h6 class="text-muted mb-3">Hasil Akhir Inspeksi</h6>
                    <?php if ($header['final_result'] === 'PASS'): ?>
                        <h2 class="text-success"><i class="fas fa-check-circle"></i> PASS</h2>
                    <?php elseif ($header['final_result'] === 'NG'): ?>
                        <h2 class="text-danger"><i class="fas fa-times-circle"></i> NG</h2>
                    <?php elseif ($header['final_result'] === 'HOLD'): ?>
                        <h2 class="text-warning"><i class="fas fa-pause-circle"></i> HOLD</h2>
                    <?php else: ?>
                        <h2 class="text-secondary">-</h2>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: INSPECTION DETAILS -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-checklist"></i> Detail Inspeksi</h5>
        </div>
        <div class="card-body">
            <?php if (count($details) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Item Inspeksi</th>
                                <th>Standard</th>
                                <th>Metode</th>
                                <th>Hasil</th>
                                <th>Defect</th>
                                <th>Lokasi</th>
                                <th>Catatan</th>
                                <th>Foto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $key => $detail): ?>
                                <tr>
                                    <td><?php echo $key + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($detail['item_name'] ?? '-'); ?></strong></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($detail['standard'] ?? '-'); ?></small></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($detail['method'] ?? '-'); ?></small></td>
                                    <td>
                                        <?php if ($detail['result'] === 'OK'): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php elseif ($detail['result'] === 'NG'): ?>
                                            <span class="badge bg-danger">NG</span>
                                        <?php elseif ($detail['result'] === 'N/A'): ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($detail['defect_code'])): ?>
                                            <small><?php echo htmlspecialchars($detail['defect_code'] . ' - ' . $detail['defect_name']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($detail['defect_location'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($detail['remark'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $photo_sql = "SELECT file_path FROM defect_photos WHERE inspection_detail_id = " . $detail['id'];
                                        $photo_result = $db->query($photo_sql);
                                        if ($photo_result && $photo_result->num_rows > 0): ?>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#photoModal<?php echo $detail['id']; ?>">
                                                <i class="fas fa-image"></i> View
                                            </button>
                                            
                                            <div class="modal fade" id="photoModal<?php echo $detail['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h6 class="modal-title">Foto Defect - <?php echo htmlspecialchars($detail['item_name']); ?></h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <?php
                                                            while ($photo = $photo_result->fetch_assoc()): ?>
                                                                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($photo['file_path']); ?>" 
                                                                     class="img-fluid" alt="Defect Photo">
                                                            <?php endwhile; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Tidak ada detail inspeksi
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECTION 4: AUDIT INFO -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fas fa-clock"></i> Created: <?php echo date('d/m/Y H:i:s', strtotime($header['created_at'])); ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        <i class="fas fa-sync"></i> Updated: <?php echo $header['updated_at'] ? date('d/m/Y H:i:s', strtotime($header['updated_at'])) : '-'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
