<?php
// config/db.php
// Database connection

require_once __DIR__ . '/config.php';

// MySQLi Connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($db->connect_error) {
    die('Connection Error: ' . $db->connect_error);
}

// Set charset to utf8mb4
$db->set_charset('utf8mb4');

// Enable error reporting for development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>
