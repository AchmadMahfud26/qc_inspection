<?php
// config/config.php
// Application configuration

// Base URL
define('BASE_URL', 'http://localhost/qc_inspection');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'qc_inspections');

// Other settings
define('ITEMS_PER_PAGE', 10);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB

// Timezone
date_default_timezone_set('Asia/Jakarta');
?>
