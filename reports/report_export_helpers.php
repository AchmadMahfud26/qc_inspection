<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function report_abort_bad_request(string $message): void
{
    http_response_code(400);
    echo $message;
    exit;
}

function report_allowed_types(): array
{
    return ['daily', 'weekly', 'monthly', 'custom'];
}

function report_resolve_type(?string $type): string
{
    $type = strtolower(trim((string) $type));

    if (!in_array($type, report_allowed_types(), true)) {
        report_abort_bad_request('Jenis report tidak valid.');
    }

    return $type;
}

function report_resolve_date(?string $value, string $fallback = 'now'): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return date('Y-m-d', strtotime($fallback));
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        report_abort_bad_request('Format tanggal harus YYYY-MM-DD.');
    }

    return $value;
}

function report_resolve_year(?string $value): int
{
    $year = filter_var($value, FILTER_VALIDATE_INT);
    if ($year === false || $year < 2020 || $year > 2100) {
        report_abort_bad_request('Tahun tidak valid.');
    }

    return $year;
}

function report_resolve_month(?string $value): int
{
    $month = filter_var($value, FILTER_VALIDATE_INT);
    if ($month === false || $month < 1 || $month > 12) {
        report_abort_bad_request('Bulan tidak valid.');
    }

    return $month;
}

function report_resolve_week(?string $value): int
{
    $week = filter_var($value, FILTER_VALIDATE_INT);
    if ($week === false || $week < 1 || $week > 53) {
        report_abort_bad_request('Minggu tidak valid.');
    }

    return $week;
}

function report_fetch_one(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [];
    }

    return $row;
}

function report_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function report_normalize_stats(array $stats): array
{
    $base = [
        'total_inspections' => 0,
        'pass_count' => 0,
        'ng_count' => 0,
        'hold_count' => 0,
        'pass_rate' => 0,
    ];

    $stats = array_merge($base, $stats);
    foreach ($base as $key => $default) {
        if ($stats[$key] === null || $stats[$key] === '') {
            $stats[$key] = $default;
        }
    }

    $stats['total_inspections'] = (int) $stats['total_inspections'];
    $stats['pass_count'] = (int) $stats['pass_count'];
    $stats['ng_count'] = (int) $stats['ng_count'];
    $stats['hold_count'] = (int) $stats['hold_count'];
    $stats['pass_rate'] = round((float) $stats['pass_rate'], 2);

    return $stats;
}

function report_pass_rate(int $pass, int $total): float
{
    if ($total <= 0) {
        return 0.0;
    }

    return round(($pass / $total) * 100, 2);
}

function report_summary_items(array $stats): array
{
    return [
        ['label' => 'Total Inspeksi', 'value' => (string) $stats['total_inspections']],
        ['label' => 'PASS', 'value' => (string) $stats['pass_count']],
        ['label' => 'NG', 'value' => (string) $stats['ng_count']],
        ['label' => 'HOLD', 'value' => (string) $stats['hold_count']],
        ['label' => 'Pass Rate', 'value' => number_format((float) $stats['pass_rate'], 2) . '%'],
    ];
}

function report_product_label(?string $productCode, ?string $productName): string
{
    $productCode = trim((string) $productCode);
    $productName = trim((string) $productName);

    if ($productCode !== '' && $productName !== '') {
        return $productCode . ' - ' . $productName;
    }

    if ($productCode !== '') {
        return $productCode;
    }

    if ($productName !== '') {
        return $productName;
    }

    return '-';
}

function report_lookup_product(PDO $pdo, int $productId): string
{
    $product = report_fetch_one(
        $pdo,
        'SELECT product_code, product_name FROM products WHERE id = ? LIMIT 1',
        [$productId]
    );

    return $product ? report_product_label($product['product_code'] ?? '', $product['product_name'] ?? '') : '-';
}

function report_get_daily_data(PDO $pdo, array $input): array
{
    $reportDate = report_resolve_date($input['date'] ?? null, 'now');

    $stats = report_normalize_stats(report_fetch_one(
        $pdo,
        "
        SELECT
            COUNT(id) as total_inspections,
            SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
            SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
            SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
            ROUND((SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) / NULLIF(COUNT(id), 0) * 100), 2) as pass_rate
        FROM inspection_headers
        WHERE DATE(inspection_date) = ?
        ",
        [$reportDate]
    ));

    $byType = report_fetch_all(
        $pdo,
        "
        SELECT
            inspection_type,
            COUNT(id) as total,
            SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers
        WHERE DATE(inspection_date) = ?
        GROUP BY inspection_type
        ORDER BY inspection_type
        ",
        [$reportDate]
    );

    $defects = report_fetch_all(
        $pdo,
        "
        SELECT
            d.defect_name,
            COUNT(id_detail.id) as count
        FROM defects d
        INNER JOIN inspection_details id_detail ON d.id = id_detail.defect_id
        INNER JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
        WHERE DATE(ih.inspection_date) = ?
        GROUP BY d.id, d.defect_name
        ORDER BY count DESC, d.defect_name ASC
        LIMIT 5
        ",
        [$reportDate]
    );

    $inspections = report_fetch_all(
        $pdo,
        "
        SELECT
            ih.inspection_no,
            ih.inspection_type,
            ih.inspection_date,
            ih.inspection_time,
            p.product_code,
            p.product_name,
            ih.final_result,
            u.username as inspector,
            ih.`line`,
            ih.shift
        FROM inspection_headers ih
        LEFT JOIN products p ON ih.product_id = p.id
        LEFT JOIN users u ON ih.inspector_id = u.id
        WHERE DATE(ih.inspection_date) = ?
        ORDER BY ih.inspection_date DESC, ih.inspection_time DESC, ih.inspection_no DESC
        ",
        [$reportDate]
    );

    $totalDefects = array_sum(array_map(static function (array $row): int {
        return (int) $row['count'];
    }, $defects));

    $sections = [];

    if ($byType) {
        $rows = [];
        foreach ($byType as $row) {
            $rows[] = [
                (string) $row['inspection_type'],
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
                number_format(report_pass_rate((int) $row['pass'], (int) $row['total']), 2) . '%',
            ];
        }

        $sections[] = [
            'title' => 'Inspeksi Berdasarkan Jenis',
            'headers' => ['Jenis Inspeksi', 'Total', 'PASS', 'NG', 'HOLD', 'Pass Rate'],
            'rows' => $rows,
        ];
    }

    if ($defects) {
        $rows = [];
        foreach ($defects as $index => $row) {
            $percentage = $totalDefects > 0 ? round(((int) $row['count'] / $totalDefects) * 100, 2) : 0;
            $rows[] = [
                (string) ($index + 1),
                (string) $row['defect_name'],
                (string) $row['count'],
                number_format($percentage, 2) . '%',
            ];
        }

        $sections[] = [
            'title' => 'Top Defect',
            'headers' => ['No', 'Defect', 'Jumlah', 'Persentase'],
            'rows' => $rows,
        ];
    }

    if ($inspections) {
        $rows = [];
        foreach ($inspections as $row) {
            $rows[] = [
                (string) $row['inspection_no'],
                (string) $row['inspection_type'],
                report_product_label($row['product_code'] ?? '', $row['product_name'] ?? ''),
                (string) ($row['line'] ?? '-'),
                (string) ($row['shift'] ?? '-'),
                (string) ($row['inspector'] ?? '-'),
                !empty($row['inspection_time']) ? date('H:i', strtotime((string) $row['inspection_time'])) : '-',
                (string) ($row['final_result'] ?? '-'),
            ];
        }

        $sections[] = [
            'title' => 'Detail Inspeksi',
            'headers' => ['No Inspeksi', 'Tipe', 'Produk', 'Line', 'Shift', 'Inspector', 'Jam', 'Hasil'],
            'rows' => $rows,
        ];
    }

    return [
        'title' => 'LAPORAN INSPEKSI HARIAN',
        'subtitle' => 'Tanggal: ' . date('d M Y', strtotime($reportDate)),
        'filename' => 'qc_report_harian_' . $reportDate,
        'orientation' => 'L',
        'summary' => report_summary_items($stats),
        'sections' => $sections,
    ];
}

function report_get_weekly_data(PDO $pdo, array $input): array
{
    $year = report_resolve_year((string) ($input['year'] ?? date('Y')));
    $week = report_resolve_week((string) ($input['week'] ?? date('W')));

    $weekStart = new DateTime();
    $weekStart->setISODate($year, $week, 1);
    $startDate = $weekStart->format('Y-m-d');

    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');
    $endDate = $weekEnd->format('Y-m-d');

    $stats = report_normalize_stats(report_fetch_one(
        $pdo,
        "
        SELECT
            COUNT(id) as total_inspections,
            SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
            SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
            SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
            ROUND((SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) / NULLIF(COUNT(id), 0) * 100), 2) as pass_rate
        FROM inspection_headers
        WHERE DATE(inspection_date) BETWEEN ? AND ?
        ",
        [$startDate, $endDate]
    ));

    $dailyData = report_fetch_all(
        $pdo,
        "
        SELECT
            DATE(inspection_date) as inspection_date,
            COUNT(id) as total,
            SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers
        WHERE DATE(inspection_date) BETWEEN ? AND ?
        GROUP BY DATE(inspection_date)
        ORDER BY inspection_date ASC
        ",
        [$startDate, $endDate]
    );

    $defects = report_fetch_all(
        $pdo,
        "
        SELECT
            d.defect_name,
            COUNT(id_detail.id) as count
        FROM defects d
        INNER JOIN inspection_details id_detail ON d.id = id_detail.defect_id
        INNER JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
        WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
        GROUP BY d.id, d.defect_name
        ORDER BY count DESC, d.defect_name ASC
        LIMIT 10
        ",
        [$startDate, $endDate]
    );

    $byType = report_fetch_all(
        $pdo,
        "
        SELECT
            inspection_type,
            COUNT(id) as total,
            SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers
        WHERE DATE(inspection_date) BETWEEN ? AND ?
        GROUP BY inspection_type
        ORDER BY inspection_type
        ",
        [$startDate, $endDate]
    );

    $sections = [];

    if ($dailyData) {
        $rows = [];
        foreach ($dailyData as $row) {
            $rows[] = [
                date('d M Y', strtotime((string) $row['inspection_date'])),
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
            ];
        }

        $sections[] = [
            'title' => 'Trend Inspeksi Harian',
            'headers' => ['Tanggal', 'Total', 'PASS', 'NG', 'HOLD'],
            'rows' => $rows,
        ];
    }

    if ($byType) {
        $rows = [];
        foreach ($byType as $row) {
            $rows[] = [
                (string) $row['inspection_type'],
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
                number_format(report_pass_rate((int) $row['pass'], (int) $row['total']), 2) . '%',
            ];
        }

        $sections[] = [
            'title' => 'Inspeksi Berdasarkan Jenis',
            'headers' => ['Jenis Inspeksi', 'Total', 'PASS', 'NG', 'HOLD', 'Pass Rate'],
            'rows' => $rows,
        ];
    }

    if ($defects) {
        $rows = [];
        foreach ($defects as $index => $row) {
            $rows[] = [
                (string) ($index + 1),
                (string) $row['defect_name'],
                (string) $row['count'],
            ];
        }

        $sections[] = [
            'title' => 'Top 10 Defect',
            'headers' => ['No', 'Defect', 'Jumlah'],
            'rows' => $rows,
        ];
    }

    return [
        'title' => 'LAPORAN INSPEKSI MINGGUAN',
        'subtitle' => 'Periode: ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)),
        'filename' => sprintf('qc_report_mingguan_%d_week_%02d', $year, $week),
        'orientation' => 'L',
        'summary' => report_summary_items($stats),
        'sections' => $sections,
    ];
}

function report_get_monthly_data(PDO $pdo, array $input): array
{
    $year = report_resolve_year((string) ($input['year'] ?? date('Y')));
    $month = report_resolve_month((string) ($input['month'] ?? date('m')));

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    $stats = report_normalize_stats(report_fetch_one(
        $pdo,
        "
        SELECT
            COUNT(id) as total_inspections,
            SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
            SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
            SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
            ROUND((SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) / NULLIF(COUNT(id), 0) * 100), 2) as pass_rate
        FROM inspection_headers
        WHERE DATE(inspection_date) BETWEEN ? AND ?
        ",
        [$startDate, $endDate]
    ));

    $dailyData = report_fetch_all(
        $pdo,
        "
        SELECT
            DATE(ih.inspection_date) as inspection_date,
            COUNT(ih.id) as total,
            SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers ih
        WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
        GROUP BY DATE(ih.inspection_date)
        ORDER BY inspection_date ASC
        ",
        [$startDate, $endDate]
    );

    $byType = report_fetch_all(
        $pdo,
        "
        SELECT
            ih.inspection_type,
            COUNT(ih.id) as total,
            SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers ih
        WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
        GROUP BY ih.inspection_type
        ORDER BY ih.inspection_type
        ",
        [$startDate, $endDate]
    );

    $byProduct = report_fetch_all(
        $pdo,
        "
        SELECT
            p.product_code,
            p.product_name,
            COUNT(ih.id) as total,
            SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers ih
        LEFT JOIN products p ON ih.product_id = p.id
        WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
        GROUP BY p.id, p.product_code, p.product_name
        ORDER BY total DESC, p.product_code ASC
        ",
        [$startDate, $endDate]
    );

    $byLine = report_fetch_all(
        $pdo,
        "
        SELECT
            ih.`line`,
            COUNT(ih.id) as total,
            SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass,
            SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng,
            SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold
        FROM inspection_headers ih
        WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
        GROUP BY ih.`line`
        ORDER BY total DESC, ih.`line` ASC
        ",
        [$startDate, $endDate]
    );

    $defects = report_fetch_all(
        $pdo,
        "
        SELECT
            d.defect_name,
            COUNT(id_detail.id) as count
        FROM defects d
        INNER JOIN inspection_details id_detail ON d.id = id_detail.defect_id
        INNER JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
        WHERE DATE(ih.inspection_date) BETWEEN ? AND ?
        GROUP BY d.id, d.defect_name
        ORDER BY count DESC, d.defect_name ASC
        LIMIT 10
        ",
        [$startDate, $endDate]
    );

    $sections = [];

    if ($dailyData) {
        $rows = [];
        foreach ($dailyData as $row) {
            $rows[] = [
                date('d M Y', strtotime((string) $row['inspection_date'])),
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
            ];
        }

        $sections[] = [
            'title' => 'Trend Harian',
            'headers' => ['Tanggal', 'Total', 'PASS', 'NG', 'HOLD'],
            'rows' => $rows,
        ];
    }

    if ($byType) {
        $rows = [];
        foreach ($byType as $row) {
            $rows[] = [
                (string) $row['inspection_type'],
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
                number_format(report_pass_rate((int) $row['pass'], (int) $row['total']), 2) . '%',
            ];
        }

        $sections[] = [
            'title' => 'Inspeksi Berdasarkan Jenis',
            'headers' => ['Jenis Inspeksi', 'Total', 'PASS', 'NG', 'HOLD', 'Pass Rate'],
            'rows' => $rows,
        ];
    }

    if ($byProduct) {
        $rows = [];
        foreach ($byProduct as $row) {
            $rows[] = [
                (string) ($row['product_code'] ?? '-'),
                (string) ($row['product_name'] ?? '-'),
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
                number_format(report_pass_rate((int) $row['pass'], (int) $row['total']), 2) . '%',
            ];
        }

        $sections[] = [
            'title' => 'Inspeksi Berdasarkan Produk',
            'headers' => ['Kode Produk', 'Nama Produk', 'Total', 'PASS', 'NG', 'HOLD', 'Pass Rate'],
            'rows' => $rows,
        ];
    }

    if ($byLine) {
        $rows = [];
        foreach ($byLine as $row) {
            $rows[] = [
                (string) ($row['line'] ?? '-'),
                (string) $row['total'],
                (string) $row['pass'],
                (string) $row['ng'],
                (string) $row['hold'],
                number_format(report_pass_rate((int) $row['pass'], (int) $row['total']), 2) . '%',
            ];
        }

        $sections[] = [
            'title' => 'Inspeksi Berdasarkan Line/Area',
            'headers' => ['Line/Area', 'Total', 'PASS', 'NG', 'HOLD', 'Pass Rate'],
            'rows' => $rows,
        ];
    }

    if ($defects) {
        $rows = [];
        foreach ($defects as $index => $row) {
            $rows[] = [
                (string) ($index + 1),
                (string) $row['defect_name'],
                (string) $row['count'],
            ];
        }

        $sections[] = [
            'title' => 'Top 10 Defect',
            'headers' => ['No', 'Defect', 'Jumlah'],
            'rows' => $rows,
        ];
    }

    return [
        'title' => 'LAPORAN INSPEKSI BULANAN',
        'subtitle' => 'Periode: ' . date('F Y', strtotime($startDate)),
        'filename' => sprintf('qc_report_bulanan_%d_%02d', $year, $month),
        'orientation' => 'L',
        'summary' => report_summary_items($stats),
        'sections' => $sections,
    ];
}

function report_get_custom_data(PDO $pdo, array $input): array
{
    $startDate = report_resolve_date($input['start_date'] ?? null, 'first day of this month');
    $endDate = report_resolve_date($input['end_date'] ?? null, 'now');
    $productId = trim((string) ($input['product_id'] ?? ''));
    $inspectionType = trim((string) ($input['inspection_type'] ?? ''));
    $line = trim((string) ($input['line'] ?? ''));

    if ($startDate > $endDate) {
        report_abort_bad_request('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.');
    }

    $conditions = ['DATE(ih.inspection_date) BETWEEN ? AND ?'];
    $params = [$startDate, $endDate];

    if ($productId !== '') {
        $productIdInt = filter_var($productId, FILTER_VALIDATE_INT);
        if ($productIdInt === false) {
            report_abort_bad_request('Produk tidak valid.');
        }
        $conditions[] = 'ih.product_id = ?';
        $params[] = $productIdInt;
    }

    if ($inspectionType !== '') {
        $conditions[] = 'ih.inspection_type = ?';
        $params[] = $inspectionType;
    }

    if ($line !== '') {
        $conditions[] = 'ih.`line` = ?';
        $params[] = $line;
    }

    $whereClause = implode(' AND ', $conditions);

    $stats = report_normalize_stats(report_fetch_one(
        $pdo,
        "
        SELECT
            COUNT(ih.id) as total_inspections,
            SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
            SUM(CASE WHEN ih.final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
            SUM(CASE WHEN ih.final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
            ROUND((SUM(CASE WHEN ih.final_result = 'PASS' THEN 1 ELSE 0 END) / NULLIF(COUNT(ih.id), 0) * 100), 2) as pass_rate
        FROM inspection_headers ih
        WHERE {$whereClause}
        ",
        $params
    ));

    $inspections = report_fetch_all(
        $pdo,
        "
        SELECT
            ih.inspection_no,
            ih.inspection_type,
            ih.inspection_date,
            p.product_code,
            p.product_name,
            ih.final_result,
            u.username as inspector,
            ih.`line`,
            ih.shift,
            COUNT(id_detail.id) as defect_count
        FROM inspection_headers ih
        LEFT JOIN products p ON ih.product_id = p.id
        LEFT JOIN users u ON ih.inspector_id = u.id
        LEFT JOIN inspection_details id_detail ON ih.id = id_detail.inspection_header_id
        WHERE {$whereClause}
        GROUP BY
            ih.id,
            ih.inspection_no,
            ih.inspection_type,
            ih.inspection_date,
            p.product_code,
            p.product_name,
            ih.final_result,
            u.username,
            ih.`line`,
            ih.shift
        ORDER BY ih.inspection_date DESC, ih.inspection_no DESC
        ",
        $params
    );

    $filterRows = [
        ['Dari Tanggal', date('d M Y', strtotime($startDate))],
        ['Sampai Tanggal', date('d M Y', strtotime($endDate))],
        ['Produk', $productId !== '' ? report_lookup_product($pdo, (int) $productId) : 'Semua Produk'],
        ['Jenis Inspeksi', $inspectionType !== '' ? $inspectionType : 'Semua Jenis'],
        ['Line/Area', $line !== '' ? $line : 'Semua Line'],
    ];

    $sections = [[
        'title' => 'Ringkasan Filter',
        'headers' => ['Filter', 'Nilai'],
        'rows' => $filterRows,
    ]];

    if ($inspections) {
        $rows = [];
        foreach ($inspections as $row) {
            $rows[] = [
                (string) $row['inspection_no'],
                (string) $row['inspection_type'],
                date('d M Y', strtotime((string) $row['inspection_date'])),
                report_product_label($row['product_code'] ?? '', $row['product_name'] ?? ''),
                (string) ($row['line'] ?? '-'),
                (string) ($row['shift'] ?? '-'),
                (string) ($row['inspector'] ?? '-'),
                (string) $row['defect_count'],
                (string) ($row['final_result'] ?? '-'),
            ];
        }

        $sections[] = [
            'title' => 'Detail Inspeksi',
            'headers' => ['No Inspeksi', 'Tipe', 'Tanggal', 'Produk', 'Line', 'Shift', 'Inspector', 'Defect', 'Hasil'],
            'rows' => $rows,
        ];
    }

    return [
        'title' => 'LAPORAN CUSTOM INSPEKSI',
        'subtitle' => 'Periode: ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)),
        'filename' => 'qc_report_custom_' . $startDate . '_sd_' . $endDate,
        'orientation' => 'L',
        'summary' => report_summary_items($stats),
        'sections' => $sections,
    ];
}

function report_get_export_data(PDO $pdo, string $type, array $input): array
{
    switch ($type) {
        case 'daily':
            return report_get_daily_data($pdo, $input);
        case 'weekly':
            return report_get_weekly_data($pdo, $input);
        case 'monthly':
            return report_get_monthly_data($pdo, $input);
        case 'custom':
            return report_get_custom_data($pdo, $input);
    }

    report_abort_bad_request('Jenis report tidak didukung.');
}

function report_build_export_html(array $report, string $outputFormat = 'pdf'): string
{
    $isExcel = $outputFormat === 'excel';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #212529;
        }
        h1, h2, h3 {
            margin: 0;
        }
        .report-header {
            text-align: center;
            margin-bottom: 18px;
        }
        .report-header h2 {
            font-size: 20px;
            margin-bottom: 6px;
        }
        .report-header p {
            margin: 0;
            color: #6c757d;
        }
        .summary-table,
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .summary-table td,
        .summary-table th,
        .report-table td,
        .report-table th {
            border: 1px solid #adb5bd;
            padding: 7px 8px;
            vertical-align: top;
        }
        .summary-table th,
        .report-table th {
            background: #e9ecef;
            text-align: left;
        }
        .summary-table .value {
            font-weight: bold;
            text-align: center;
        }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin: 18px 0 8px;
        }
        .empty-note {
            border: 1px solid #dee2e6;
            padding: 12px;
            color: #6c757d;
            margin-bottom: 18px;
        }
        <?php if ($isExcel): ?>
        .section {
            margin-bottom: 12px;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="report-header">
        <h2><?php echo esc((string) $report['title']); ?></h2>
        <p><?php echo esc((string) $report['subtitle']); ?></p>
    </div>

    <table class="summary-table">
        <thead>
            <tr>
                <?php foreach ($report['summary'] as $item): ?>
                <th><?php echo esc((string) $item['label']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php foreach ($report['summary'] as $item): ?>
                <td class="value"><?php echo esc((string) $item['value']); ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <?php foreach ($report['sections'] as $section): ?>
    <div class="section">
        <div class="section-title"><?php echo esc((string) $section['title']); ?></div>
        <?php if (!empty($section['rows'])): ?>
        <table class="report-table">
            <thead>
                <tr>
                    <?php foreach ($section['headers'] as $header): ?>
                    <th><?php echo esc((string) $header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($section['rows'] as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?php echo nl2br(esc((string) $cell)); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-note">Tidak ada data untuk section ini.</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div style="margin-top: 24px; color: #6c757d; font-size: 11px;">
        Laporan dibuat pada <?php echo esc(date('d M Y H:i:s')); ?>
    </div>
</body>
</html>
    <?php

    return (string) ob_get_clean();
}
