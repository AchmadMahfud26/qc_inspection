<?php
// admin/products/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }

$pdo = getPDO();
$stmt = $pdo->query('SELECT p.*, c.customer_name FROM products p LEFT JOIN customers c ON p.customer_id = c.id ORDER BY p.id DESC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Master Produk</h4>
    <a href="create.php" class="btn btn-success">Tambah Produk</a>
</div>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>No</th>
            <th>Product Code</th>
            <th>Nama Produk</th>
            <th>Type</th>
            <th>Model</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php $i=1; foreach ($rows as $r): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo esc($r['product_code']); ?></td>
                <td><?php echo esc($r['product_name']); ?></td>
                <td><?php echo esc($r['product_type']); ?></td>
                <td><?php echo esc($r['model']); ?></td>
                <td><?php echo esc($r['customer_name']); ?></td>
                <td><?php echo esc($r['status']); ?></td>
                <td>
                    <a href="edit.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                    <form method="post" action="delete.php" style="display:inline-block;" onsubmit="return confirm('Hapus produk ini?');">
                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
