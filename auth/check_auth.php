<?php
// auth/check_auth.php
require_once __DIR__ . '/../includes/functions.php';

// require login and optionally check role
function require_role(array $roles = [])
{
    if (!is_logged_in()) {
        header('Location: /qc_inspection/auth/login.php');
        exit;
    }
    if (!empty($roles)) {
        $u = current_user();
        if (!in_array($u['role'], $roles, true)) {
            // simple 403
            http_response_code(403);
            echo "<h1>403 - Access denied</h1>";
            echo "<p>Anda tidak memiliki akses ke halaman ini.</p>";
            exit;
        }
    }
}

?>