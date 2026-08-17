<?php
session_start();

// Include config
require_once '../../config/config.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check login
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Check role
if (!in_array($_SESSION['user']['role'], ['admin', 'qc_inspector'])) {
    header('Location: ' . BASE_URL . '/inspection/after_painting/');
    exit;
}

// Get inspection ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header('Location: index.php?error=Invalid inspection ID');
    exit;
}

try {
    $db->begin_transaction();
    
    // Get inspection header info
    $header_sql = "SELECT inspection_no FROM inspection_headers WHERE id = $id AND inspection_type = 'Final Check'";
    $header_result = $db->query($header_sql);
    
    if ($header_result->num_rows === 0) {
        throw new Exception('Inspeksi tidak ditemukan');
    }
    
    $header = $header_result->fetch_assoc();
    $inspection_no = $header['inspection_no'];
    
    // Get all defect photo IDs to delete files
    $photos_sql = "SELECT dp.id, dp.file_path 
                  FROM defect_photos dp
                  JOIN inspection_details id ON dp.inspection_detail_id = id.id
                  WHERE id.inspection_header_id = $id";
    $photos_result = $db->query($photos_sql);
    
    // Delete photo files from server
    while ($photo = $photos_result->fetch_assoc()) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . str_replace(BASE_URL . '/', '', $photo['file_path']);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete from database (cascade will handle defect_photos and inspection_details)
    $delete_sql = "DELETE FROM inspection_headers WHERE id = $id AND inspection_type = 'Final Check'";
    if (!$db->query($delete_sql)) {
        throw new Exception('Error deleting inspection: ' . $db->error);
    }
    
    // Log activity
    logActivity($db, $_SESSION['user']['id'], 'Delete', 'Final Check Inspection', $inspection_no);
    
    $db->commit();
    
    header('Location: index.php?success=Inspeksi berhasil dihapus');
    exit;
    
} catch (Exception $e) {
    $db->rollback();
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>


