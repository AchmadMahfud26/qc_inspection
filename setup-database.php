<?php
/**
 * setup-database.php
 * Import database schema from SQL file
 * Access: http://localhost/qc_inspection/setup-database.php
 */

require_once 'config/config.php';

// Create connection to MySQL server (without database)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die('<div style="padding:20px;font-family:Arial;color:red;background:#ffebee;border-radius:4px;">
        <h2>❌ Connection Error</h2>
        <p>Connection failed: ' . $conn->connect_error . '</p>
        </div>');
}

// Read SQL file
$sql_file = __DIR__ . '/database/qc_inspections.sql';
if (!file_exists($sql_file)) {
    die('<div style="padding:20px;font-family:Arial;color:red;background:#ffebee;border-radius:4px;">
        <h2>❌ SQL File Not Found</h2>
        <p>Expected file: ' . $sql_file . '</p>
        </div>');
}

$sql_content = file_get_contents($sql_file);
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    if (!$conn->multi_query($statement)) {
        $error_count++;
        $errors[] = $conn->error . " | Statement: " . substr($statement, 0, 100);
    } else {
        // Clear results
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
        $success_count++;
    }
}

$conn->close();

// Display results
echo '<div style="padding:30px;font-family:Arial;max-width:800px;margin:50px auto;">';

if ($error_count === 0) {
    echo '<div style="background:#d4edda;color:#155724;border-radius:4px;padding:20px;margin-bottom:20px;">';
    echo '<h2>✅ Database Setup Successful!</h2>';
    echo '<p>Database and tables created successfully.</p>';
    echo '<p>Total statements executed: <strong>' . $success_count . '</strong></p>';
    echo '</div>';
    
    echo '<div style="background:#cce5ff;color:#004085;border-radius:4px;padding:20px;">';
    echo '<h3>📋 Next Steps:</h3>';
    echo '<ol>';
    echo '<li><a href="' . BASE_URL . '/sql-setup.php" style="color:#004085;font-weight:bold;">1. Setup Master Data (Inspection Items & Defects)</a></li>';
    echo '<li><a href="' . BASE_URL . '/admin/users/index.php" style="color:#004085;font-weight:bold;">2. Manage Users</a></li>';
    echo '<li><a href="' . BASE_URL . '/inspection/after_welding/" style="color:#004085;font-weight:bold;">3. Start After Welding Inspection</a></li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div style="background:#ffebee;color:#c62828;border-radius:4px;padding:20px;margin-bottom:20px;">';
    echo '<h2>⚠️ Setup Completed with Errors</h2>';
    echo '<p>Successful: <strong>' . $success_count . '</strong> | Errors: <strong>' . $error_count . '</strong></p>';
    
    if (!empty($errors)) {
        echo '<h3>Error Details:</h3>';
        echo '<pre style="background:#fff;border:1px solid #ccc;padding:10px;overflow-x:auto;border-radius:4px;">';
        foreach ($errors as $error) {
            echo htmlspecialchars($error) . "\n\n";
        }
        echo '</pre>';
    }
    
    echo '</div>';
}

echo '</div>';
?>
