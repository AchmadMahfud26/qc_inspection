<?php
// admin/defects/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO(); $id = (int)($_GET['id'] ?? 0); if ($id<=0) { header('Location:index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM defects WHERE id=:id'); $stmt->execute([':id'=>$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row){ header('Location:index.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { $errors[]='CSRF tidak valid.'; }
    $defect_code = trim($_POST['defect_code'] ?? ''); $defect_name = trim($_POST['defect_name'] ?? ''); $category = trim($_POST['category'] ?? '');
    $process = trim($_POST['process'] ?? ''); $severity = $_POST['severity'] ?? 'medium'; $description = trim($_POST['description'] ?? ''); $status = $_POST['status'] ?? 'active';
    if ($defect_code===''||$defect_name==='') $errors[]='Defect code dan nama wajib diisi.';
    if (empty($errors)){
        $stmt = $pdo->prepare('UPDATE defects SET defect_code=:code, defect_name=:name, category=:cat, process=:proc, severity=:sev, description=:desc, status=:status WHERE id=:id');
        $stmt->execute([':code'=>$defect_code,':name'=>$defect_name,':cat'=>$category,':proc'=>$process,':sev'=>$severity,':desc'=>$description,':status'=>$status,':id'=>$id]);
        set_flash('success','Defect diperbarui'); log_activity($pdo, current_user()['id'], 'Update Defect '.$defect_code, 'Defect', (string)$id);
        header('Location:index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<h4>Edit Defect</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3"><label class="form-label">Defect Code</label><input type="text" name="defect_code" class="form-control" value="<?php echo esc($_POST['defect_code'] ?? $row['defect_code']); ?>" required></div>
    <div class="mb-3"><label class="form-label">Defect Name</label><input type="text" name="defect_name" class="form-control" value="<?php echo esc($_POST['defect_name'] ?? $row['defect_name']); ?>" required></div>
    <div class="mb-3"><label class="form-label">Category</label><input type="text" name="category" class="form-control" value="<?php echo esc($_POST['category'] ?? $row['category']); ?>"></div>
    <div class="mb-3"><label class="form-label">Process</label><input type="text" name="process" class="form-control" value="<?php echo esc($_POST['process'] ?? $row['process']); ?>"></div>
    <div class="mb-3"><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="low" <?php echo ($row['severity']==='low')? 'selected':''; ?>>low</option><option value="medium" <?php echo ($row['severity']==='medium')? 'selected':''; ?>>medium</option><option value="high" <?php echo ($row['severity']==='high')? 'selected':''; ?>>high</option></select></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"><?php echo esc($_POST['description'] ?? $row['description']); ?></textarea></div>
    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?php echo ($row['status']==='active')? 'selected':''; ?>>Active</option><option value="inactive" <?php echo ($row['status']==='inactive')? 'selected':''; ?>>Inactive</option></select></div>
    <div class="d-flex justify-content-end"><a href="index.php" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>