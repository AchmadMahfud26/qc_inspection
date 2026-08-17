<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// jika sudah login redirect ke index
if (is_logged_in()) {
    header('Location: /qc_inspection/index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($token)) {
        $errors[] = 'CSRF token tidak valid.';
    }

    if (empty($username) || empty($password)) {
        $errors[] = 'Username dan password wajib diisi.';
    }

    if (empty($errors)) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u AND status = "active" LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            // login success
            login_user($user);
            log_activity($pdo, (int)$user['id'], 'Login', 'Auth', null);
            header('Location: /qc_inspection/index.php');
            exit;
        } else {
            $errors[] = 'Username atau password salah.';
        }
    }
}

// tampilkan form
?>

<?php require_once __DIR__ . '/../includes/header_simple.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title mb-3">QC INSPECTION - Login</h4>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?>
                                    <li><?php echo esc($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo esc(csrf_token()); ?>">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo esc($_POST['username'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" type="submit">Login</button>
                            <a href="/" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_simple.php'; ?>
