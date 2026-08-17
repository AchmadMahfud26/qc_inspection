<?php
// admin/customers/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO(); $id = (int)($_GET['id'] ?? 0); if ($id<=0) { header('Location:index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM customers WHERE id=:id'); $stmt->execute([':id'=>$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row){ header('Location:index.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { $errors[]='CSRF tidak valid.'; }
    $code = trim($_POST['customer_code'] ?? ''); $name = trim($_POST['customer_name'] ?? ''); $status = $_POST['status'] ?? 'active';
    if ($code===''||$name==='') $errors[]='Customer code dan nama wajib diisi.';
    if (empty($errors)){
        $stmt = $pdo->prepare('UPDATE customers SET customer_code=:code, customer_name=:name, status=:status WHERE id=:id');
        $stmt->execute([':code'=>$code,':name'=>$name,':status'=>$status,':id'=>$id]);
        set_flash('success','Customer diperbarui'); log_activity($pdo, current_user()['id'], 'Update Customer '.$code, 'Customer', (string)$id);
        header('Location:index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<h4>Edit Customer</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3"><label class="form-label">Customer Code</label><input type="text" name="customer_code" class="form-control" value="<?php echo esc($_POST['customer_code'] ?? $row['customer_code']); ?>" required></div>
    <div class="mb-3"><label class="form-label">Customer Name</label><input type="text" name="customer_name" class="form-control" value="<?php echo esc($_POST['customer_name'] ?? $row['customer_name']); ?>" required></div>
    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?php echo ($row['status']==='active')? 'selected':''; ?>>Active</option><option value="inactive" <?php echo ($row['status']==='inactive')? 'selected':''; ?>>Inactive</option></select></div>
    <div class="d-flex justify-content-end"><a href="index.php" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>