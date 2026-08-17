<?php
// admin/inspection_items/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
$pdo = getPDO();
$rows = $pdo->query('SELECT * FROM inspection_items ORDER BY process_type, sequence ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Master Inspection Item</h4>
    <a href="create.php" class="btn btn-success">Tambah Item</a>
</div>
<table class="table table-striped table-bordered">
    <thead><tr><th>No</th><th>Process</th><th>Item Code</th><th>Item Name</th><th>Standard</th><th>Method</th><th>Sequence</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
        <?php $i=1; foreach($rows as $r): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo esc($r['process_type']); ?></td>
                <td><?php echo esc($r['item_code']); ?></td>
                <td><?php echo esc($r['item_name']); ?></td>
                <td><?php echo esc($r['standard']); ?></td>
                <td><?php echo esc($r['inspection_method']); ?></td>
                <td><?php echo esc($r['sequence']); ?></td>
                <td><?php echo esc($r['status']); ?></td>
                <td>
                    <a href="edit.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                    <form method="post" action="delete.php" style="display:inline-block;" onsubmit="return confirm('Hapus item?');">
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