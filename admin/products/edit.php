<?php
// admin/products/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id'); $stmt->execute([':id'=>$id]); $product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) { header('Location: index.php'); exit; }
$customers = $pdo->query('SELECT id, customer_name FROM customers WHERE status = "active"')->fetchAll(PDO::FETCH_ASSOC);
$errors = [];

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
        $stmt = $pdo->prepare('UPDATE products SET product_code=:code, product_name=:name, product_type=:type, model=:model, customer_id=:cid, status=:status WHERE id=:id');
        $stmt->execute([':code'=>$product_code,':name'=>$product_name,':type'=>$product_type,':model'=>$model,':cid'=>$customer_id,':status'=>$status,':id'=>$id]);
        set_flash('success','Produk berhasil diperbarui');
        log_activity($pdo, current_user()['id'], 'Update Product: '.$product_code, 'Product', (string)$id);
        header('Location: index.php'); exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h4>Edit Produk</h4>
<?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div><?php endif; ?>

<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
    <div class="mb-3">
        <label class="form-label">Product Code</label>
        <input type="text" name="product_code" class="form-control" value="<?php echo esc($_POST['product_code'] ?? $product['product_code']); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Nama Produk</label>
        <input type="text" name="product_name" class="form-control" value="<?php echo esc($_POST['product_name'] ?? $product['product_name']); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Type</label>
        <input type="text" name="product_type" class="form-control" value="<?php echo esc($_POST['product_type'] ?? $product['product_type']); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Model</label>
        <input type="text" name="model" class="form-control" value="<?php echo esc($_POST['model'] ?? $product['model']); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-select">
            <option value="">-- Pilih Customer --</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ((isset($_POST['customer_id'])?$_POST['customer_id']:$product['customer_id'])==$c['id'])? 'selected':''; ?>><?php echo esc($c['customer_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" <?php echo (($product['status'] ?? '') === 'active')? 'selected':''; ?>>Active</option>
            <option value="inactive" <?php echo (($product['status'] ?? '') === 'inactive')? 'selected':''; ?>>Inactive</option>
        </select>
    </div>
    <div class="d-flex justify-content-end">
        <a href="index.php" class="btn btn-secondary me-2">Batal</a>
        <button class="btn btn-primary" type="submit">Simpan</button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
