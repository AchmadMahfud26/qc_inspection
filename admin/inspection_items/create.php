<?php
// admin/inspection_items/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO(); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { $errors[]='CSRF tidak valid.'; }
    $process_type = $_POST['process_type'] ?? 'After Welding'; $item_code = trim($_POST['item_code'] ?? ''); $item_name = trim($_POST['item_name'] ?? '');
    $standard = trim($_POST['standard'] ?? ''); $inspection_method = trim($_POST['inspection_method'] ?? ''); $sequence = (int)($_POST['sequence'] ?? 0); $status = $_POST['status'] ?? 'active';
    if ($item_code===''||$item_name==='') $errors[]='Item code dan nama wajib diisi.';
    if (empty($errors)){
        $stmt = $pdo->prepare('INSERT INTO inspection_items (process_type, item_code, item_name, standard, inspection_method, sequence, status) VALUES (:proc,:code,:name,:std,:method,:seq,:status)');
        $stmt->execute([':proc'=>$process_type,':code'=>$item_code,':name'=>$item_name,':std'=>$standard,':method'=>$inspection_method,':seq'=>$sequence,':status'=>$status]);
        set_flash('success','Inspection item ditambahkan'); log_activity($pdo, current_user()['id'], 'Create InspectionItem '.$item_code, 'InspectionItem', (string)$pdo->lastInsertId());
        header('Location:index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<h4>Tambah Inspection Item</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3"><label class="form-label">Process Type</label><select name="process_type" class="form-select"><option>After Welding</option><option>After Painting</option><option>Final Check</option></select></div>
    <div class="mb-3"><label class="form-label">Item Code</label><input type="text" name="item_code" class="form-control" value="<?php echo esc($_POST['item_code'] ?? ''); ?>" required></div>
    <div class="mb-3"><label class="form-label">Item Name</label><input type="text" name="item_name" class="form-control" value="<?php echo esc($_POST['item_name'] ?? ''); ?>" required></div>
    <div class="mb-3"><label class="form-label">Standard</label><textarea name="standard" class="form-control"><?php echo esc($_POST['standard'] ?? ''); ?></textarea></div>
    <div class="mb-3"><label class="form-label">Inspection Method</label><input type="text" name="inspection_method" class="form-control" value="<?php echo esc($_POST['inspection_method'] ?? ''); ?>"></div>
    <div class="mb-3"><label class="form-label">Sequence</label><input type="number" name="sequence" class="form-control" value="<?php echo esc($_POST['sequence'] ?? '0'); ?>"></div>
    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    <div class="d-flex justify-content-end"><a href="index.php" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>