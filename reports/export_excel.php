<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/report_export_helpers.php';

require_login();

$pdo = getPDO();
$type = report_resolve_type($_GET['type'] ?? '');
$report = report_get_export_data($pdo, $type, $_GET);
$filename = $report['filename'] . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo report_build_export_html($report, 'excel');
