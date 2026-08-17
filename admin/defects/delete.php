<?php
// admin/defects/delete.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { set_flash('success','Token CSRF tidak valid.'); header('Location: index.php'); exit; }
$id = (int)($_POST['id'] ?? 0); if ($id<=0) { header('Location: index.php'); exit; }
$pdo = getPDO();
$stmt = $pdo->prepare('DELETE FROM defects WHERE id = :id'); $stmt->execute([':id'=>$id]);
set_flash('success','Defect dihapus.'); log_activity($pdo, current_user()['id'], 'Delete Defect id:'.$id, 'Defect', (string)$id);
header('Location: index.php'); exit;
?>