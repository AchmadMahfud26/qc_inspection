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

// Check role - only admin and qc_inspector can edit
if (!in_array($_SESSION['user']['role'], ['admin', 'qc_inspector'])) {
    header('Location: ' . BASE_URL . '/inspection/after_welding/');
    exit;
}

$page_title = 'Edit After Welding Inspection';

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
                ih.product_id,
                ih.part_number,
                ih.serial_number,
                ih.production_order,
                ih.lot_number,
                ih.line,
                ih.shift,
                ih.remark
            FROM inspection_headers ih
            WHERE ih.id = $id AND ih.inspection_type = 'After Welding'";

$header_result = $db->query($header_sql);
if ($header_result->num_rows === 0) {
    header('Location: index.php?error=Inspeksi tidak ditemukan');
    exit;
}

$header = $header_result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['inspection_date', 'inspection_time', 'product_id', 'part_number', 'serial_number', 'line'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' harus diisi';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->begin_transaction();
            
            // Update inspection header
            $inspection_date = $_POST['inspection_date'];
            $inspection_time = $_POST['inspection_time'] ?? date('H:i:s');
            $product_id = (int)$_POST['product_id'];
            $part_number = $_POST['part_number'];
            $serial_number = $_POST['serial_number'];
            $production_order = $_POST['production_order'] ?? NULL;
            $lot_number = $_POST['lot_number'] ?? NULL;
            $line = $_POST['line'];
            $shift = $_POST['shift'] ?? NULL;
            $remark = $_POST['remark'] ?? NULL;
            
            $update_header_sql = "UPDATE inspection_headers 
                                 SET inspection_date = '$inspection_date',
                                     inspection_time = '$inspection_time',
                                     product_id = $product_id,
                                     part_number = '$part_number',
                                     serial_number = '$serial_number',
                                     production_order = " . (!empty($production_order) ? "'$production_order'" : "NULL") . ",
                                     lot_number = " . (!empty($lot_number) ? "'$lot_number'" : "NULL") . ",
                                     line = '$line',
                                     shift = " . (!empty($shift) ? "'$shift'" : "NULL") . ",
                                     remark = " . (!empty($remark) ? "'$remark'" : "NULL") . "
                                 WHERE id = $id";
            
            if (!$db->query($update_header_sql)) {
                throw new Exception('Error updating inspection header: ' . $db->error);
            }
            
            // Delete existing details
            $delete_details_sql = "DELETE FROM inspection_details WHERE inspection_header_id = $id";
            if (!$db->query($delete_details_sql)) {
                throw new Exception('Error deleting old details: ' . $db->error);
            }
            
            // Insert new inspection details
            $inspection_items = $_POST['inspection_item'];
            $results = $_POST['result'] ?? [];
            $defects = $_POST['defect'] ?? [];
            $defect_locations = $_POST['defect_location'] ?? [];
            $item_remarks = $_POST['item_remark'] ?? [];
            
            $ng_count = 0;
            
            foreach ($inspection_items as $key => $item_id) {
                if (empty($item_id)) continue;
                
                $item_id = (int)$item_id;
                $result = $results[$key] ?? 'OK';
                $defect_id = !empty($defects[$key]) ? (int)$defects[$key] : NULL;
                $defect_location = $defect_locations[$key] ?? NULL;
                $item_remark = $item_remarks[$key] ?? NULL;
                
                if ($result === 'NG') {
                    $ng_count++;
                }
                
                // Get inspection item details
                $item_sql = "SELECT standard, inspection_method FROM inspection_items WHERE id = $item_id";
                $item_result = $db->query($item_sql);
                $item_data = $item_result->fetch_assoc();
                
                $detail_sql = "INSERT INTO inspection_details 
                              (inspection_header_id, inspection_item_id, standard, method, 
                               result, defect_id, defect_location, remark)
                              VALUES 
                              ($id, $item_id, " .
                              (!empty($item_data['standard']) ? "'" . $item_data['standard'] . "'" : "NULL") . ", " .
                              (!empty($item_data['inspection_method']) ? "'" . $item_data['inspection_method'] . "'" : "NULL") . ",
                              '$result', " . ($defect_id ? $defect_id : "NULL") . ", " .
                              (!empty($defect_location) ? "'$defect_location'" : "NULL") . ", " .
                              (!empty($item_remark) ? "'$item_remark'" : "NULL") . ")";
                
                if (!$db->query($detail_sql)) {
                    throw new Exception('Error inserting inspection detail: ' . $db->error);
                }
                
                // Handle photo uploads
                if (isset($_FILES['defect_photo'][$key]) && $_FILES['defect_photo'][$key]['size'] > 0) {
                    $detail_id = $db->insert_id;
                    handlePhotoUpload($_FILES['defect_photo'][$key], $detail_id, $db);
                }
            }
            
            // Determine final result
            $final_result = ($ng_count > 0) ? 'NG' : 'PASS';
            
            // Update final result
            $update_result_sql = "UPDATE inspection_headers SET final_result = '$final_result' WHERE id = $id";
            if (!$db->query($update_result_sql)) {
                throw new Exception('Error updating final result: ' . $db->error);
            }
            
            // Log activity
            logActivity($db, $_SESSION['user']['id'], 'Update', 'After Welding Inspection', $header['inspection_no']);
            
            $db->commit();
            
            header('Location: view.php?id=' . $id . '&success=Inspeksi berhasil diperbarui');
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// Get products
$products_sql = "SELECT id, product_code, product_name FROM products WHERE status = 'active' ORDER BY product_name";
$products_result = $db->query($products_sql);
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Get inspection items for After Welding
$items_sql = "SELECT id, item_name, standard, inspection_method, sequence 
             FROM inspection_items 
             WHERE process_type = 'After Welding' AND status = 'active'
             ORDER BY sequence";
$items_result = $db->query($items_sql);
$inspection_items = $items_result->fetch_all(MYSQLI_ASSOC);

// Get defects
$defects_sql = "SELECT id, defect_code, defect_name FROM defects WHERE status = 'active' ORDER BY defect_name";
$defects_result = $db->query($defects_sql);
$defects = $defects_result->fetch_all(MYSQLI_ASSOC);

// Get current inspection details
$details_sql = "SELECT 
                id.id,
                id.inspection_item_id,
                id.result,
                id.defect_id,
                id.defect_location,
                id.remark
            FROM inspection_details id
            WHERE id.inspection_header_id = $id
            ORDER BY id.id";

$details_result = $db->query($details_sql);
$current_details = [];
while ($row = $details_result->fetch_assoc()) {
    $current_details[$row['inspection_item_id']] = $row;
}

// Get lines
$lines = ['LINE-01', 'LINE-02', 'LINE-03', 'LINE-04'];
$shifts = ['Shift 1', 'Shift 2', 'Shift 3'];

?>

<?php include '../../includes/header.php'; ?>

<div class="container-fluid pt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="page-title">Edit After Welding Inspection</h2>
            <p class="text-muted">No. Inspeksi: <?php echo htmlspecialchars($header['inspection_no']); ?></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <strong>Terjadi Kesalahan:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="inspectionForm">
        <!-- SECTION 1: INSPECTION HEADER -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Inspeksi</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tanggal Inspeksi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="inspection_date" 
                                   value="<?php echo $header['inspection_date']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Waktu Inspeksi <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="inspection_time" 
                                   value="<?php echo $header['inspection_time']; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Produk <span class="text-danger">*</span></label>
                            <select class="form-control" name="product_id" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                        <?php echo $product['id'] == $header['product_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Part Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="part_number" 
                                   value="<?php echo htmlspecialchars($header['part_number']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="serial_number" 
                                   value="<?php echo htmlspecialchars($header['serial_number']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Production Order</label>
                            <input type="text" class="form-control" name="production_order" 
                                   value="<?php echo htmlspecialchars($header['production_order'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Lot Number</label>
                            <input type="text" class="form-control" name="lot_number" 
                                   value="<?php echo htmlspecialchars($header['lot_number'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Line <span class="text-danger">*</span></label>
                            <select class="form-control" name="line" required>
                                <option value="">-- Pilih Line --</option>
                                <?php foreach ($lines as $line): ?>
                                    <option value="<?php echo htmlspecialchars($line); ?>" 
                                        <?php echo $line == $header['line'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($line); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Shift</label>
                            <select class="form-control" name="shift">
                                <option value="">-- Pilih Shift --</option>
                                <?php foreach ($shifts as $shift): ?>
                                    <option value="<?php echo htmlspecialchars($shift); ?>" 
                                        <?php echo $shift == $header['shift'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($shift); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-control" name="remark" rows="2"><?php echo htmlspecialchars($header['remark'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- SECTION 2: INSPECTION CHECKLIST -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-checklist"></i> Checklist Inspeksi After Welding</h5>
            </div>
            <div class="card-body">
                <?php if (count($inspection_items) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped" id="checklistTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="25%">Item Inspeksi</th>
                                    <th width="20%">Standard</th>
                                    <th width="15%">Metode</th>
                                    <th width="12%">Hasil</th>
                                    <th width="15%">Defect (jika NG)</th>
                                    <th width="20%">Lokasi Defect</th>
                                    <th width="15%">Foto (jika NG)</th>
                                    <th width="15%">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inspection_items as $key => $item): ?>
                                    <?php 
                                    $current_detail = $current_details[$item['id']] ?? null;
                                    $current_result = $current_detail['result'] ?? 'OK';
                                    $is_ng = $current_result === 'NG';
                                    ?>
                                    <tr class="inspection-row" data-item-id="<?php echo $item['id']; ?>">
                                        <td><?php echo $key + 1; ?></td>
                                        <td>
                                            <input type="hidden" name="inspection_item[]" value="<?php echo $item['id']; ?>">
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['standard'] ?? '-'); ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['inspection_method'] ?? '-'); ?></small>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm result-select" name="result[]" required>
                                                <option value="OK" <?php echo $current_result === 'OK' ? 'selected' : ''; ?>>OK</option>
                                                <option value="NG" <?php echo $current_result === 'NG' ? 'selected' : ''; ?>>NG</option>
                                                <option value="N/A" <?php echo $current_result === 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm defect-select" name="defect[]" <?php echo !$is_ng ? 'disabled' : ''; ?>>
                                                <option value="">-- Pilih Defect --</option>
                                                <?php foreach ($defects as $defect): ?>
                                                    <option value="<?php echo $defect['id']; ?>" 
                                                        <?php echo ($current_detail && $current_detail['defect_id'] == $defect['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($defect['defect_code'] . ' - ' . $defect['defect_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm defect-location" name="defect_location[]" 
                                                   value="<?php echo htmlspecialchars($current_detail['defect_location'] ?? ''); ?>" 
                                                   <?php echo !$is_ng ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="file" class="form-control form-control-sm defect-photo" name="defect_photo[]" 
                                                   accept="image/*" <?php echo !$is_ng ? 'disabled' : ''; ?>>
                                            <small class="form-text text-muted">Max 2MB</small>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="item_remark[]" 
                                                   value="<?php echo htmlspecialchars($current_detail['remark'] ?? ''); ?>" 
                                                   placeholder="Catatan...">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Tidak ada item inspeksi yang tersedia. Silakan tambahkan item inspeksi di Master Data terlebih dahulu.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 3: ACTIONS -->
        <div class="row mb-4">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </form>

</div>

<!-- JavaScript untuk enable/disable defect fields -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('checklistTable');
    
    if (table) {
        table.addEventListener('change', function(e) {
            if (e.target.classList.contains('result-select')) {
                const row = e.target.closest('tr');
                const result = e.target.value;
                
                const defectSelect = row.querySelector('.defect-select');
                const defectLocation = row.querySelector('.defect-location');
                const defectPhoto = row.querySelector('.defect-photo');
                
                if (result === 'NG') {
                    defectSelect.disabled = false;
                    defectLocation.disabled = false;
                    defectPhoto.disabled = false;
                    
                    defectSelect.required = true;
                    defectLocation.required = true;
                } else {
                    defectSelect.disabled = true;
                    defectLocation.disabled = true;
                    defectPhoto.disabled = true;
                    
                    defectSelect.required = false;
                    defectLocation.required = false;
                    
                    // Clear values
                    defectSelect.value = '';
                    defectLocation.value = '';
                    defectPhoto.value = '';
                }
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
