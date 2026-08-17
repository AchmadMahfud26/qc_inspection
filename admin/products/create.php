<?php
// admin/products/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO();
$errors = [];
$customers = $pdo->query('SELECT id, customer_name FROM customers WHERE status = "active"')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) { $errors[] = 'CSRF tidak valid.'; }

    $product_code = trim($_POST['product_code'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $product_type = trim($_POST['product_type'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $customer_id = $_POST['customer_id'] ?? null;
    $status = $_POST['status'] ?? 'active';

    if ($product_code === '' || $product_name === '') $errors[] = 'Product code dan nama wajib diisi.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO products (product_code, product_name, product_type, model, customer_id, status, created_at) VALUES (:code,:name,:type,:model,:cid,:status,NOW())');
        $stmt->execute([':code'=>$product_code,':name'=>$product_name,':type'=>$product_type,':model'=>$model,':cid'=>$customer_id,':status'=>$status]);
        set_flash('success','Produk berhasil ditambahkan');
        log_activity($pdo, current_user()['id'], 'Create Product: '.$product_code, 'Product', (string)$pdo->lastInsertId());
        header('Location: index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h4>Tambah Produk</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>

<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3">
        <label class="form-label">Product Code</label>
        <input type="text" name="product_code" class="form-control" value="<?php echo esc($_POST['product_code'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Nama Produk</label>
        <input type="text" name="product_name" class="form-control" value="<?php echo esc($_POST['product_name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Type</label>
        <input type="text" name="product_type" class="form-control" value="<?php echo esc($_POST['product_type'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Model</label>
        <input type="text" name="model" class="form-control" value="<?php echo esc($_POST['model'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-select">
            <option value="">-- Pilih Customer --</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo (isset($_POST['customer_id']) && $_POST['customer_id']==$c['id'])? 'selected':''; ?>><?php echo esc($c['customer_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>
    <div class="d-flex justify-content-end">
        <a href="index.php" class="btn btn-secondary me-2">Batal</a>
        <button class="btn btn-primary" type="submit">Simpan</button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
