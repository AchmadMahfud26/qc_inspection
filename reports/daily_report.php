<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
require_login();

$report_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$page_title = "Laporan Harian - " . date('d M Y', strtotime($report_date));

$pdo = getPDO();

// Query: Daily Statistics
$sql_stats = "
    SELECT 
        COUNT(id) as total_inspections,
        SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) as ng_count,
        SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) as hold_count,
        ROUND((SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) / COUNT(id) * 100), 2) as pass_rate
    FROM inspection_headers
    WHERE DATE(inspection_date) = ?
";

$stmt = $pdo->prepare($sql_stats);
$stmt->execute([$report_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats['total_inspections']) {
    $stats = [
        'total_inspections' => 0,
        'pass_count' => 0,
        'ng_count' => 0,
        'hold_count' => 0,
        'pass_rate' => 0
    ];
}

// Query: Inspections by Type
$sql_by_type = "
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
";

$stmt = $pdo->prepare($sql_by_type);
$stmt->execute([$report_date]);
$by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: Top Defects
$sql_defects = "
    SELECT 
        d.defect_name,
        COUNT(id_detail.id) as count
    FROM defects d
    LEFT JOIN inspection_details id_detail ON d.id = id_detail.defect_id
    LEFT JOIN inspection_headers ih ON id_detail.inspection_header_id = ih.id
    WHERE DATE(ih.inspection_date) = ?
    AND id_detail.defect_id IS NOT NULL
    GROUP BY d.id
    ORDER BY count DESC
    LIMIT 5
";

$stmt = $pdo->prepare($sql_defects);
$stmt->execute([$report_date]);
$defects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query: Inspections List
$sql_inspections = "
    SELECT 
        ih.inspection_no,
        ih.inspection_type,
        ih.inspection_date,
        ih.inspection_time,
        ih.product_id,
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
    ORDER BY ih.inspection_date DESC
";

$stmt = $pdo->prepare($sql_inspections);
$stmt->execute([$report_date]);
$inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extra_head_content = <<<'HTML'
<style>
    .report-header { page-break-after: avoid; }
    .report-section { page-break-inside: avoid; margin-bottom: 30px; }
    .stat-card { text-align: center; padding: 20px; }
    @media print {
        .navbar,
        .sidebar-container,
        footer,
        .btn,
        .no-print {
            display: none !important;
        }
        body,
        .main-content {
            background: white !important;
        }
        .main-content {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
</style>
HTML;

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

            <!-- Report Toolbar -->
            <div class="d-flex justify-content-between align-items-center page-toolbar no-print">
                <h3 class="page-main-title"><i class="fas fa-file-alt"></i> Laporan Harian - <?php echo date('d M Y', strtotime($report_date)); ?></h3>
                <div>
                    <form method="GET" class="d-inline me-2">
                        <input type="date" name="date" value="<?php echo $report_date; ?>" class="form-control d-inline" style="width: auto;" required>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                    </form>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    <a href="export_pdf.php?type=daily&date=<?php echo $report_date; ?>" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                    <a href="export_excel.php?type=daily&date=<?php echo $report_date; ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
                </div>
            </div>

            <!-- Report Content -->
            <div class="report-header">
                <div class="text-center mb-4">
                    <h4>LAPORAN INSPEKSI HARIAN</h4>
                    <p class="text-muted">Tanggal: <?php echo date('d M Y', strtotime($report_date)); ?></p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row report-section">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Inspeksi</h6>
                            <h3><?php echo $stats['total_inspections']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">PASS</h6>
                            <h3><?php echo $stats['pass_count']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">NG</h6>
                            <h3><?php echo $stats['ng_count']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">HOLD</h6>
                            <h3><?php echo $stats['hold_count']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pass Rate -->
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Pass Rate</h6>
                    </div>
                    <div class="card-body">
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $stats['pass_rate']; ?>%;" 
                                 aria-valuenow="<?php echo $stats['pass_rate']; ?>" aria-valuemin="0" aria-valuemax="100">
                                <strong><?php echo $stats['pass_rate']; ?>%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inspections by Type -->
            <?php if (count($by_type) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-list"></i> Inspeksi Berdasarkan Jenis</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr class="table-light">
                                        <th>Jenis Inspeksi</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">PASS</th>
                                        <th class="text-center">NG</th>
                                        <th class="text-center">HOLD</th>
                                        <th class="text-center">Pass Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_type as $type): 
                                        $type_pass_rate = $type['total'] > 0 ? round(($type['pass'] / $type['total']) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['inspection_type']); ?></td>
                                        <td class="text-center"><strong><?php echo $type['total']; ?></strong></td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $type['pass']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $type['ng']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-warning"><?php echo $type['hold']; ?></span></td>
                                        <td class="text-center">
                                            <small><?php echo $type_pass_rate; ?>%</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Defects -->
            <?php if (count($defects) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Top Defect</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr class="table-light">
                                        <th>Defect</th>
                                        <th class="text-center">Jumlah</th>
                                        <th class="text-center">Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_defects = array_sum(array_column($defects, 'count'));
                                    foreach ($defects as $index => $defect): 
                                        $percentage = $total_defects > 0 ? round(($defect['count'] / $total_defects) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary me-2"><?php echo $index + 1; ?></span>
                                            <?php echo htmlspecialchars($defect['defect_name']); ?>
                                        </td>
                                        <td class="text-center"><strong><?php echo $defect['count']; ?></strong></td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-danger" style="width: <?php echo $percentage; ?>%;">
                                                    <small><?php echo $percentage; ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Detailed Inspections List -->
            <?php if (count($inspections) > 0): ?>
            <div class="report-section">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-list"></i> Detail Inspeksi</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" style="font-size: 0.85rem;">
                                <thead>
                                    <tr class="table-light">
                                        <th>ID Inspeksi</th>
                                        <th>Tipe</th>
                                        <th>Produk</th>
                                        <th>Line</th>
                                        <th>Shift</th>
                                        <th>Inspector</th>
                                        <th>Jam</th>
                                        <th>Hasil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inspections as $insp): ?>
                                    <tr>
                                        <td><small><?php echo htmlspecialchars($insp['inspection_no']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($insp['inspection_type']); ?></small></td>
                                        <td>
                                            <small>
                                                <?php echo htmlspecialchars($insp['product_code']); ?><br>
                                                <em><?php echo htmlspecialchars($insp['product_name']); ?></em>
                                            </small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($insp['line']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($insp['shift']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($insp['inspector']); ?></small></td>
                                        <td><small><?php echo !empty($insp['inspection_time']) ? date('H:i', strtotime($insp['inspection_time'])) : '-'; ?></small></td>
                                        <td>
                                            <?php 
                                            $result_class = '';
                                            if ($insp['final_result'] == 'PASS') $result_class = 'bg-success';
                                            elseif ($insp['final_result'] == 'NG') $result_class = 'bg-danger';
                                            else $result_class = 'bg-warning';
                                            ?>
                                            <span class="badge <?php echo $result_class; ?>"><?php echo htmlspecialchars($insp['final_result']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="report-section text-center text-muted mt-5 no-print">
                <small>Laporan dibuat pada: <?php echo date('d M Y H:i:s'); ?></small>
            </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
