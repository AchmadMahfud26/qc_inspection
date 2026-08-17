<?php
// admin/users/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
if (!has_role('admin')) {
    http_response_code(403);
    echo 'Akses ditolak.';
    exit;
}

$pdo = getPDO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, username, role, employee_id, department, status FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        $errors[] = 'CSRF token tidak valid.';
    }

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'qc_inspector';
    $employee_id = trim($_POST['employee_id'] ?? null);
    $department = trim($_POST['department'] ?? null);
    $status = $_POST['status'] ?? 'active';

    if ($name === '' || $username === '') {
        $errors[] = 'Nama dan username wajib diisi.';
    }

    if (empty($errors)) {
        // check username unique (exclude current)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u AND id != :id');
        $stmt->execute([':u' => $username, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Username sudah digunakan oleh user lain.';
        } else {
            $params = [
                ':name' => $name,
                ':username' => $username,
                ':role' => $role,
                ':employee_id' => $employee_id,
                ':department' => $department,
                ':status' => $status,
                ':id' => $id
            ];
            $sql = 'UPDATE users SET name = :name, username = :username, role = :role, employee_id = :employee_id, department = :department, status = :status WHERE id = :id';
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = 'UPDATE users SET name = :name, username = :username, password = :password, role = :role, employee_id = :employee_id, department = :department, status = :status WHERE id = :id';
                $params[':password'] = $hash;
            }
            $s = $pdo->prepare($sql);
            $s->execute($params);
            set_flash('success', 'User berhasil diperbarui.');
            log_activity($pdo, current_user()['id'], 'Update User: ' . $username, 'User', (string)$id);
            header('Location: index.php');
            exit;
        }
    }
}

?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h4>Edit User</h4>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">

    <div class="mb-3">
        <label class="form-label">Nama</label>
        <input type="text" name="name" class="form-control" value="<?php echo esc($_POST['name'] ?? $user['name']); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?php echo esc($_POST['username'] ?? $user['username']); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Password (kosongkan jika tidak ingin mengganti)</label>
        <input type="password" name="password" class="form-control">
    </div>
    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
            <option value="admin" <?php echo (($user['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
            <option value="qc_inspector" <?php echo (($user['role'] ?? '') === 'qc_inspector') ? 'selected' : ''; ?>>QC Inspector</option>
            <option value="supervisor" <?php echo (($user['role'] ?? '') === 'supervisor') ? 'selected' : ''; ?>>Supervisor QC</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Employee ID</label>
        <input type="text" name="employee_id" class="form-control" value="<?php echo esc($_POST['employee_id'] ?? $user['employee_id']); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Department</label>
        <input type="text" name="department" class="form-control" value="<?php echo esc($_POST['department'] ?? $user['department']); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" <?php echo (($user['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo (($user['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
        </select>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <a href="index.php" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary" type="submit">Simpan</button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
