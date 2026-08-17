<?php
// admin/users/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
if (!has_role('admin')) {
    http_response_code(403);
    echo 'Akses ditolak. Hanya Admin yang dapat mengakses.';
    exit;
}

$pdo = getPDO();
$stmt = $pdo->query('SELECT id, name, username, role, employee_id, department, status, created_at FROM users ORDER BY id DESC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Manajemen User</h4>
    <a href="create.php" class="btn btn-success">Tambah User</a>
</div>

<?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><?php echo esc($msg); ?></div>
<?php endif; ?>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Role</th>
            <th>Employee ID</th>
            <th>Department</th>
            <th>Status</th>
            <th>Created</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($users as $u): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo esc($u['name']); ?></td>
                <td><?php echo esc($u['username']); ?></td>
                <td><?php echo esc($u['role']); ?></td>
                <td><?php echo esc($u['employee_id']); ?></td>
                <td><?php echo esc($u['department']); ?></td>
                <td><?php echo esc($u['status']); ?></td>
                <td><?php echo esc($u['created_at']); ?></td>
                <td>
                    <a href="edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                    <?php if ($u['status'] === 'active'): ?>
                        <form method="post" action="delete.php" style="display:inline-block;" onsubmit="return confirm('Nonaktifkan user ini?');">
                            <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button class="btn btn-sm btn-danger">Nonaktifkan</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
