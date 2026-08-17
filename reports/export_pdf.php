<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/report_export_helpers.php';

require_login();

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    report_abort_bad_request('Library PDF belum tersedia. Jalankan composer install terlebih dahulu.');
}

require_once $autoloadPath;

$pdo = getPDO();
$type = report_resolve_type($_GET['type'] ?? '');
$report = report_get_export_data($pdo, $type, $_GET);
$html = report_build_export_html($report, 'pdf');

$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qc_inspection_mpdf';
if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    throw new RuntimeException('Gagal membuat temporary directory untuk PDF.');
}

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => (string) ($report['orientation'] ?? 'P'),
    'tempDir' => $tempDir,
    'margin_top' => 12,
    'margin_right' => 10,
    'margin_bottom' => 12,
    'margin_left' => 10,
]);

$mpdf->SetTitle((string) $report['title']);
$mpdf->WriteHTML($html);
$mpdf->Output($report['filename'] . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
