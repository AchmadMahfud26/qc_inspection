<?php
// admin/users/delete.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
if (!has_role('admin')) {
    http_response_code(403);
    echo 'Akses ditolak.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!csrf_verify($token)) {
    set_flash('success', 'Token CSRF tidak valid.');
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$pdo = getPDO();
// protect from deactivating yourself
if (current_user()['id'] == $id) {
    set_flash('success', 'Tidak dapat menonaktifkan user yang sedang login.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('UPDATE users SET status = "inactive" WHERE id = :id');
$stmt->execute([':id' => $id]);
set_flash('success', 'User dinonaktifkan.');
log_activity($pdo, current_user()['id'], 'Deactivate User id:' . $id, 'User', (string)$id);
header('Location: index.php');
exit;

?>