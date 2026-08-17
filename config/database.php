<?php
// config/database.php
// Database configuration for QC INSPECTION

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Create PDO instance and expose via getPDO()
function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // In production, hide details
        die('Database connection failed: ' . $e->getMessage());
    }
}

// helper to run simple queries (returns PDOStatement)
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

?>