<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$user = current_user();
$user_id = $user['id'] ?? null;
log_activity($pdo, $user_id, 'Logout', 'Auth', null);

// clear session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: /qc_inspection/auth/login.php');
exit;
?>