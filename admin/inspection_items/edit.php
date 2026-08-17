<?php
// admin/inspection_items/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO(); $id = (int)($_GET['id'] ?? 0); if ($id<=0){ header('Location:index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM inspection_items WHERE id=:id'); $stmt->execute([':id'=>$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if(!$row){ header('Location:index.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { $errors[]='CSRF tidak valid.'; }
    $process_type = $_POST['process_type'] ?? 'After Welding'; $item_code = trim($_POST['item_code'] ?? ''); $item_name = trim($_POST['item_name'] ?? '');
    $standard = trim($_POST['standard'] ?? ''); $inspection_method = trim($_POST['inspection_method'] ?? ''); $sequence = (int)($_POST['sequence'] ?? 0); $status = $_POST['status'] ?? 'active';
    if ($item_code===''||$item_name==='') $errors[]='Item code dan nama wajib diisi.';
    if (empty($errors)){
        $stmt = $pdo->prepare('UPDATE inspection_items SET process_type=:proc, item_code=:code, item_name=:name, standard=:std, inspection_method=:method, sequence=:seq, status=:status WHERE id=:id');
        $stmt->execute([':proc'=>$process_type,':code'=>$item_code,':name'=>$item_name,':std'=>$standard,':method'=>$inspection_method,':seq'=>$sequence,':status'=>$status,':id'=>$id]);
        set_flash('success','Inspection item diperbarui'); log_activity($pdo, current_user()['id'], 'Update InspectionItem '.$item_code, 'InspectionItem', (string)$id);
        header('Location:index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<h4>Edit Inspection Item</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3"><label class="form-label">Process Type</label><select name="process_type" class="form-select"><option <?php echo ($row['process_type']==='After Welding')? 'selected':''; ?>>After Welding</option><option <?php echo ($row['process_type']==='After Painting')? 'selected':''; ?>>After Painting</option><option <?php echo ($row['process_type']==='Final Check')? 'selected':''; ?>>Final Check</option></select></div>
    <div class="mb-3"><label class="form-label">Item Code</label><input type="text" name="item_code" class="form-control" value="<?php echo esc($_POST['item_code'] ?? $row['item_code']); ?>" required></div>
    <div class="mb-3"><label class="form-label">Item Name</label><input type="text" name="item_name" class="form-control" value="<?php echo esc($_POST['item_name'] ?? $row['item_name']); ?>" required></div>
    <div class="mb-3"><label class="form-label">Standard</label><textarea name="standard" class="form-control"><?php echo esc($_POST['standard'] ?? $row['standard']); ?></textarea></div>
    <div class="mb-3"><label class="form-label">Inspection Method</label><input type="text" name="inspection_method" class="form-control" value="<?php echo esc($_POST['inspection_method'] ?? $row['inspection_method']); ?>"></div>
    <div class="mb-3"><label class="form-label">Sequence</label><input type="number" name="sequence" class="form-control" value="<?php echo esc($_POST['sequence'] ?? $row['sequence']); ?>"></div>
    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?php echo ($row['status']==='active')? 'selected':''; ?>>Active</option><option value="inactive" <?php echo ($row['status']==='inactive')? 'selected':''; ?>>Inactive</option></select></div>
    <div class="d-flex justify-content-end"><a href="index.php" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>