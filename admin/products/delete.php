<?php
// admin/products/delete.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login(); if (!has_role('admin')) { http_response_code(403); echo 'Akses ditolak.'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$token = $_POST['csrf_token'] ?? ''; if (!csrf_verify($token)) { set_flash('success','Token CSRF tidak valid.'); header('Location: index.php'); exit; }
$id = (int)($_POST['id'] ?? 0); if ($id<=0) { header('Location: index.php'); exit; }
$pdo = getPDO();
$stmt = $pdo->prepare('DELETE FROM products WHERE id = :id'); $stmt->execute([':id'=>$id]);
set_flash('success','Produk dihapus.'); log_activity($pdo, current_user()['id'], 'Delete Product id:'.$id, 'Product', (string)$id);
header('Location: index.php'); exit;
?>