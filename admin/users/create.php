<?php
// admin/users/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
if (!has_role('admin')) {
    http_response_code(403);
    echo 'Akses ditolak.';
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

    if ($name === '' || $username === '' || $password === '') {
        $errors[] = 'Nama, username dan password wajib diisi.';
    }

    if (empty($errors)) {
        $pdo = getPDO();
        // check username unique
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Username sudah digunakan.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, username, password, role, employee_id, department, status) VALUES (:name, :username, :password, :role, :employee_id, :department, :status)";
            $s = $pdo->prepare($sql);
            $s->execute([
                ':name' => $name,
                ':username' => $username,
                ':password' => $hash,
                ':role' => $role,
                ':employee_id' => $employee_id,
                ':department' => $department,
                ':status' => $status
            ]);
            set_flash('success', 'User berhasil ditambahkan.');
            log_activity($pdo, current_user()['id'], 'Create User: ' . $username, 'User', (string)$pdo->lastInsertId());
            header('Location: index.php');
            exit;
        }
    }
}

?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h4>Tambah User</h4>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.esc($e).'</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">

    <div class="mb-3">
        <label class="form-label">Nama</label>
        <input type="text" name="name" class="form-control" value="<?php echo esc($_POST['name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?php echo esc($_POST['username'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
            <option value="admin">Admin</option>
            <option value="qc_inspector" selected>QC Inspector</option>
            <option value="supervisor">Supervisor QC</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Employee ID</label>
        <input type="text" name="employee_id" class="form-control" value="<?php echo esc($_POST['employee_id'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Department</label>
        <input type="text" name="department" class="form-control" value="<?php echo esc($_POST['department'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" selected>Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <a href="index.php" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary" type="submit">Simpan</button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
