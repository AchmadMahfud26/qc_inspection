<?php
// admin/customers/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO(); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { $errors[]='CSRF tidak valid.'; }
    $code = trim($_POST['customer_code'] ?? ''); $name = trim($_POST['customer_name'] ?? ''); $status = $_POST['status'] ?? 'active';
    if ($code===''||$name==='') $errors[]='Customer code dan nama wajib diisi.';
    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO customers (customer_code, customer_name, status) VALUES (:code,:name,:status)');
        $stmt->execute([':code'=>$code,':name'=>$name,':status'=>$status]);
        set_flash('success','Customer ditambahkan'); log_activity($pdo, current_user()['id'], 'Create Customer '.$code, 'Customer', (string)$pdo->lastInsertId());
        header('Location: index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<h4>Tambah Customer</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3"><label class="form-label">Customer Code</label><input type="text" name="customer_code" class="form-control" value="<?php echo esc($_POST['customer_code'] ?? ''); ?>" required></div>
    <div class="mb-3"><label class="form-label">Customer Name</label><input type="text" name="customer_name" class="form-control" value="<?php echo esc($_POST['customer_name'] ?? ''); ?>" required></div>
    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    <div class="d-flex justify-content-end"><a href="index.php" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>