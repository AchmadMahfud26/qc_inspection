<?php
// index.php - Dashboard
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$pdo = getPDO();

// Period filter
$today = date('Y-m-d');
$start_month = date('Y-m-01');
$end_month = date('Y-m-t');
$period = isset($_GET['period']) ? (string) $_GET['period'] : 'month';
$allowed_periods = ['today', 'week', 'month', 'custom'];
if (!in_array($period, $allowed_periods, true)) {
    $period = 'month';
}

$filter_start_date = isset($_GET['start_date']) ? (string) $_GET['start_date'] : $start_month;
$filter_end_date = isset($_GET['end_date']) ? (string) $_GET['end_date'] : $today;

$is_valid_date = static function (string $date): bool {
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
};

switch ($period) {
    case 'today':
        $range_start = $today;
        $range_end = $today;
        $period_label = 'Hari Ini';
        break;
    case 'week':
        $range_start = date('Y-m-d', strtotime('monday this week'));
        $range_end = date('Y-m-d', strtotime('sunday this week'));
        $period_label = 'Minggu Ini';
        break;
    case 'custom':
        $range_start = $is_valid_date($filter_start_date) ? $filter_start_date : $start_month;
        $range_end = $is_valid_date($filter_end_date) ? $filter_end_date : $today;
        if ($range_start > $range_end) {
            $temp = $range_start;
            $range_start = $range_end;
            $range_end = $temp;
        }
        $period_label = 'Custom';
        break;
    case 'month':
    default:
        $range_start = $start_month;
        $range_end = $end_month;
        $period_label = 'Bulan Ini';
        break;
}

$range_text = $range_start === $range_end
    ? date('d M Y', strtotime($range_start))
    : date('d M Y', strtotime($range_start)) . ' - ' . date('d M Y', strtotime($range_end));

$trend_line_color = 'rgba(54, 162, 235, 1)';
$trend_fill_color = 'rgba(54, 162, 235, 0.18)';

switch ($period) {
    case 'today':
        $trend_line_color = 'rgba(13, 110, 253, 1)';
        $trend_fill_color = 'rgba(13, 110, 253, 0.15)';
        break;
    case 'week':
        $trend_line_color = 'rgba(25, 135, 84, 1)';
        $trend_fill_color = 'rgba(25, 135, 84, 0.16)';
        break;
    case 'custom':
        $trend_line_color = 'rgba(111, 66, 193, 1)';
        $trend_fill_color = 'rgba(111, 66, 193, 0.16)';
        break;
}

// Totals
$total_range_stmt = $pdo->prepare('SELECT COUNT(*) FROM inspection_headers WHERE inspection_date BETWEEN :start AND :end');
$total_range_stmt->execute([':start' => $range_start, ':end' => $range_end]);
$total_range = (int)$total_range_stmt->fetchColumn();

$monthly_result_stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN final_result = 'PASS' THEN 1 ELSE 0 END) AS total_ok,
        SUM(CASE WHEN final_result = 'NG' THEN 1 ELSE 0 END) AS total_ng,
        SUM(CASE WHEN final_result = 'HOLD' THEN 1 ELSE 0 END) AS total_hold
    FROM inspection_headers
    WHERE inspection_date BETWEEN :start AND :end
");
$monthly_result_stmt->execute([':start' => $range_start, ':end' => $range_end]);
$monthly_results = $monthly_result_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$total_ok = (int)($monthly_results['total_ok'] ?? 0);
$total_ng = (int)($monthly_results['total_ng'] ?? 0);
$total_hold = (int)($monthly_results['total_hold'] ?? 0);

$total_products_stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE status = "active"');
$total_products = (int)$total_products_stmt->fetchColumn();

$total_defect_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM inspection_details id
    INNER JOIN inspection_headers ih ON ih.id = id.inspection_header_id
    WHERE id.defect_id IS NOT NULL
    AND ih.inspection_date BETWEEN :start AND :end
");
$total_defect_stmt->execute([':start' => $range_start, ':end' => $range_end]);
$total_defect = (int)$total_defect_stmt->fetchColumn();

$ok_rate = ($total_range > 0 && ($total_ok + $total_ng + $total_hold) > 0) ? round(($total_ok / max(1, ($total_ok + $total_ng + $total_hold))) * 100, 2) : 0;
$ng_rate = ($total_range > 0 && ($total_ok + $total_ng + $total_hold) > 0) ? round(($total_ng / max(1, ($total_ok + $total_ng + $total_hold))) * 100, 2) : 0;

// Inspection trend by selected period
$trend_labels = [];
$trend_counts = [];
$trend_chart_title = 'Inspection Trend ' . $period_label;
$trend_dataset_label = 'Jumlah Inspection';

if ($period === 'today') {
    $stmt = $pdo->prepare("
        SELECT
            LPAD(HOUR(COALESCE(inspection_time, '00:00:00')), 2, '0') AS hour_key,
            COUNT(*) AS cnt
        FROM inspection_headers
        WHERE inspection_date = :date
        GROUP BY HOUR(COALESCE(inspection_time, '00:00:00'))
        ORDER BY hour_key
    ");
    $stmt->execute([':date' => $range_start]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $r) {
        $map[$r['hour_key']] = (int)$r['cnt'];
    }
    for ($hour = 0; $hour < 24; $hour++) {
        $hour_key = str_pad((string)$hour, 2, '0', STR_PAD_LEFT);
        $trend_labels[] = $hour_key . ':00';
        $trend_counts[] = $map[$hour_key] ?? 0;
    }
    $trend_chart_title = 'Inspection Trend per Jam ' . $period_label;
    $trend_dataset_label = 'Jumlah Inspection per Jam';
} else {
    $stmt = $pdo->prepare('SELECT inspection_date, COUNT(*) AS cnt FROM inspection_headers WHERE inspection_date BETWEEN :start AND :end GROUP BY inspection_date ORDER BY inspection_date');
    $stmt->execute([':start' => $range_start, ':end' => $range_end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $r) {
        $map[$r['inspection_date']] = (int)$r['cnt'];
    }
    for ($cursor = strtotime($range_start); $cursor <= strtotime($range_end); $cursor = strtotime('+1 day', $cursor)) {
        $date_key = date('Y-m-d', $cursor);
        if ($period === 'week') {
            $trend_labels[] = date('D', $cursor);
        } elseif ($period === 'month') {
            $trend_labels[] = date('d', $cursor);
        } else {
            $trend_labels[] = date('d M', $cursor);
        }
        $trend_counts[] = $map[$date_key] ?? 0;
    }
}

// Result distribution for selected period
$okng_stmt = $pdo->prepare("
    SELECT final_result, COUNT(*) as cnt
    FROM inspection_headers
    WHERE inspection_date BETWEEN :start AND :end
    AND final_result IS NOT NULL
    GROUP BY final_result
");
$okng_stmt->execute([':start' => $range_start, ':end' => $range_end]);
$okng_rows = $okng_stmt->fetchAll(PDO::FETCH_ASSOC);
$okng_map = ['PASS' => 0, 'NG' => 0, 'HOLD' => 0];
foreach ($okng_rows as $r) {
    $okng_map[$r['final_result']] = (int)$r['cnt'];
}

// Top defects for selected period
$top_defect_stmt = $pdo->prepare("
    SELECT d.defect_name, COUNT(*) as cnt
    FROM inspection_details id
    INNER JOIN inspection_headers ih ON ih.id = id.inspection_header_id
    INNER JOIN defects d ON id.defect_id = d.id
    WHERE ih.inspection_date BETWEEN :start AND :end
    GROUP BY id.defect_id, d.defect_name
    ORDER BY cnt DESC
    LIMIT 10
");
$top_defect_stmt->execute([':start' => $range_start, ':end' => $range_end]);
$top_defects = $top_defect_stmt->fetchAll(PDO::FETCH_ASSOC);

// Pareto data for selected period
$pareto_stmt = $pdo->prepare("
    SELECT d.defect_name, COUNT(*) AS cnt
    FROM inspection_details id
    INNER JOIN inspection_headers ih ON ih.id = id.inspection_header_id
    INNER JOIN defects d ON id.defect_id = d.id
    WHERE ih.inspection_date BETWEEN :start AND :end
    GROUP BY id.defect_id, d.defect_name
    ORDER BY cnt DESC
");
$pareto_stmt->execute([':start' => $range_start, ':end' => $range_end]);
$pareto_rows = $pareto_stmt->fetchAll(PDO::FETCH_ASSOC);
$pareto_total = 0;
foreach ($pareto_rows as $r) $pareto_total += (int)$r['cnt'];
$pareto_labels = [];
$pareto_counts = [];
$pareto_pct = [];
$cumulative = [];
$sum = 0;
foreach ($pareto_rows as $r) {
    $pareto_labels[] = $r['defect_name'];
    $cnt = (int)$r['cnt'];
    $pareto_counts[] = $cnt;
    $pct = $pareto_total > 0 ? round(($cnt / $pareto_total) * 100, 2) : 0;
    $pareto_pct[] = $pct;
    $sum += $pct;
    $cumulative[] = round($sum, 2);
}

$top_defect_name = !empty($top_defects) ? (string)$top_defects[0]['defect_name'] : 'Tidak ada defect';
$top_defect_count = !empty($top_defects) ? (int)$top_defects[0]['cnt'] : 0;
$trend_peak = !empty($trend_counts) ? max($trend_counts) : 0;
$trend_points_with_data = count(array_filter($trend_counts, static function ($value) {
    return (int)$value > 0;
}));
$result_total = $total_ok + $total_ng + $total_hold;

// pass data to view
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<h3 class="page-main-title mb-3">Dashboard QC INSPECTION</h3>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label for="period" class="form-label">Periode Dashboard</label>
                <select name="period" id="period" class="form-select">
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 dashboard-custom-date <?php echo $period === 'custom' ? '' : 'd-none'; ?>">
                <label for="start_date" class="form-label">Dari Tanggal</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo esc($range_start); ?>">
            </div>
            <div class="col-lg-3 col-md-6 dashboard-custom-date <?php echo $period === 'custom' ? '' : 'd-none'; ?>">
                <label for="end_date" class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo esc($range_end); ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="dashboard-filter-actions">
                    <button type="submit" class="btn btn-primary dashboard-filter-submit">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                    <a href="/qc_inspection/index.php" class="btn btn-outline-secondary dashboard-filter-reset" title="Reset Filter" aria-label="Reset Filter">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </form>
        <div class="mt-3 text-muted">
            <small><strong>Periode aktif:</strong> <?php echo esc($period_label); ?> (<?php echo esc($range_text); ?>)</small>
        </div>
    </div>
</div>
<div class="row mt-3">
    <div class="col-xl-2 col-md-4">
        <div class="card shadow-sm card-stats card-stats-today">
            <div class="card-body">
                <h6>Total Inspection <?php echo esc($period_label); ?></h6>
                <h3><?php echo esc((string)$total_range); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card shadow-sm card-stats card-stats-month">
            <div class="card-body">
                <h6>Total Defect <?php echo esc($period_label); ?></h6>
                <h3><?php echo esc((string)$total_defect); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card shadow-sm card-stats card-stats-ok">
            <div class="card-body">
                <h6>Total OK <?php echo esc($period_label); ?></h6>
                <h3><?php echo esc((string)$total_ok); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card shadow-sm card-stats card-stats-ng">
            <div class="card-body">
                <h6>Total NG <?php echo esc($period_label); ?></h6>
                <h3><?php echo esc((string)$total_ng); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card shadow-sm card-stats card-stats-hold">
            <div class="card-body">
                <h6>Total HOLD <?php echo esc($period_label); ?></h6>
                <h3><?php echo esc((string)$total_hold); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card shadow-sm card-stats card-stats-rate">
            <div class="card-body">
                <h6>OK Rate <?php echo esc($period_label); ?></h6>
                <h3><?php echo esc($ok_rate . '%'); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="dashboard-grid">
            <div class="grid-trend">
                <div class="card card-trend">
                    <div class="card-body">
                        <div class="dashboard-card-header">
                            <h6><?php echo esc($trend_chart_title); ?></h6>
                            <div class="dashboard-mini-stats">
                                <span class="mini-stat-chip mini-stat-chip-primary">Peak <?php echo esc((string)$trend_peak); ?></span>
                                <span class="mini-stat-chip mini-stat-chip-neutral"><?php echo esc((string)$trend_points_with_data); ?> titik aktif</span>
                            </div>
                        </div>
                        <canvas id="trendChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid-pareto">
                <div class="card pareto-card card-pareto">
                    <div class="card-body">
                        <div class="dashboard-card-header">
                            <h6>Defect Pareto <?php echo esc($period_label); ?></h6>
                            <div class="dashboard-mini-stats">
                                <span class="mini-stat-chip mini-stat-chip-danger"><?php echo esc((string)$pareto_total); ?> defect</span>
                                <span class="mini-stat-chip mini-stat-chip-info"><?php echo esc((string)count($pareto_labels)); ?> kategori</span>
                            </div>
                        </div>
                        <canvas id="paretoChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid-right">
                <div class="card mb-3 card-okng">
                    <div class="card-body">
                        <div class="dashboard-card-header">
                            <h6>Distribusi Hasil <?php echo esc($period_label); ?></h6>
                            <div class="dashboard-mini-stats">
                                <span class="mini-stat-chip mini-stat-chip-success">PASS <?php echo esc((string)$total_ok); ?></span>
                                <span class="mini-stat-chip mini-stat-chip-danger">NG <?php echo esc((string)$total_ng); ?></span>
                                <span class="mini-stat-chip mini-stat-chip-warning">HOLD <?php echo esc((string)$total_hold); ?></span>
                            </div>
                        </div>
                        <canvas id="okngChart" height="140"></canvas>
                    </div>
                </div>

                <div class="card card-topdefect">
                    <div class="card-body">
                        <div class="dashboard-card-header">
                            <h6>Top Defect <?php echo esc($period_label); ?></h6>
                            <div class="dashboard-mini-stats">
                                <span class="mini-stat-chip mini-stat-chip-danger"><?php echo esc($top_defect_name); ?></span>
                                <span class="mini-stat-chip mini-stat-chip-neutral"><?php echo esc((string)$top_defect_count); ?> kasus</span>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php if (empty($top_defects)): ?>
                                <li class="list-group-item">Tidak ada defect tercatat.</li>
                            <?php else: ?>
                                <?php foreach ($top_defects as $d): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo esc($d['defect_name']); ?>
                                        <span class="badge bg-danger rounded-pill"><?php echo esc((string)$d['cnt']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="card card-summary">
                    <div class="card-body">
                        <div class="dashboard-card-header">
                            <h6>Ringkasan</h6>
                            <div class="dashboard-mini-stats">
                                <span class="mini-stat-chip mini-stat-chip-primary"><?php echo esc((string)$total_products); ?> produk</span>
                                <span class="mini-stat-chip mini-stat-chip-info"><?php echo esc((string)$result_total); ?> hasil</span>
                            </div>
                        </div>
                        <div class="dashboard-summary-list">
                            <div class="dashboard-summary-item">
                                <span class="summary-label">Total Produk Aktif</span>
                                <strong><?php echo esc((string)$total_products); ?></strong>
                            </div>
                            <div class="dashboard-summary-item">
                                <span class="summary-label">Periode Aktif</span>
                                <strong><?php echo esc($range_text); ?></strong>
                            </div>
                            <div class="dashboard-summary-item">
                                <span class="summary-label">Total HOLD <?php echo esc($period_label); ?></span>
                                <strong><?php echo esc((string)$total_hold); ?></strong>
                            </div>
                            <div class="dashboard-summary-item">
                                <span class="summary-label">NG Rate <?php echo esc($period_label); ?></span>
                                <strong><?php echo esc($ng_rate . '%'); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Charts scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendLabels = <?php echo json_encode($trend_labels); ?>;
const trendData = <?php echo json_encode($trend_counts); ?>;
const okngData = <?php echo json_encode([$okng_map['PASS'], $okng_map['NG'], $okng_map['HOLD']]); ?>;
const paretoLabels = <?php echo json_encode($pareto_labels); ?>;
const paretoCounts = <?php echo json_encode($pareto_counts); ?>;
const paretoCumulative = <?php echo json_encode($cumulative); ?>;
const trendLineColor = <?php echo json_encode($trend_line_color); ?>;
const trendFillColor = <?php echo json_encode($trend_fill_color); ?>;
const activePeriodLabel = <?php echo json_encode($period_label); ?>;
const activeRangeText = <?php echo json_encode($range_text); ?>;

// Toggle custom date inputs
const periodSelect = document.getElementById('period');
const customDateFields = document.querySelectorAll('.dashboard-custom-date');
if (periodSelect) {
    const toggleCustomDates = function () {
        const showCustom = periodSelect.value === 'custom';
        customDateFields.forEach(function (element) {
            element.classList.toggle('d-none', !showCustom);
        });
    };
    periodSelect.addEventListener('change', toggleCustomDates);
    toggleCustomDates();
}

// Trend chart
const trendCanvas = document.getElementById('trendChart');
if (trendCanvas) {
    const ctxTrend = trendCanvas.getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: <?php echo json_encode($trend_dataset_label); ?>,
                data: trendData,
                borderColor: trendLineColor,
                backgroundColor: trendFillColor,
                borderWidth: 3,
                tension: 0.3,
                fill: true,
                pointRadius: trendData.length === 1 ? 5 : 3,
                pointHoverRadius: 6,
                pointBackgroundColor: trendLineColor
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function(items) {
                            return items.length ? items[0].label : '';
                        },
                        label: function(context) {
                            return ' Inspection: ' + context.parsed.y;
                        },
                        afterBody: function() {
                            return 'Periode: ' + activeRangeText;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// OK vs NG pie
const okngCanvas = document.getElementById('okngChart');
if (okngCanvas) {
    const ctxOkNg = okngCanvas.getContext('2d');
    new Chart(ctxOkNg, {
        type: 'doughnut',
        data: {
            labels: ['PASS','NG','HOLD'],
            datasets: [{
                data: okngData,
                backgroundColor: ['#28a745','#dc3545','#ffc107'],
                borderColor: '#ffffff',
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce(function(sum, value) { return sum + value; }, 0);
                            const value = context.parsed || 0;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : '0.00';
                            return ' ' + context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Pareto: combined bar (counts) + line (cumulative %)
const paretoCanvas = document.getElementById('paretoChart');
if (paretoCanvas) {
    const ctxPareto = paretoCanvas.getContext('2d');
    new Chart(ctxPareto, {
        data: {
            labels: paretoLabels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Jumlah',
                    data: paretoCounts,
                    backgroundColor: 'rgba(255,99,132,0.6)',
                    borderRadius: 6,
                    borderSkipped: false
                },
                {
                    type: 'line',
                    label: 'Kumulatif %',
                    data: paretoCumulative,
                    yAxisID: 'percentAxis',
                    borderColor: 'rgba(54,162,235,1)',
                    backgroundColor: 'rgba(54,162,235,0.2)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: false,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === 'Kumulatif %') {
                                return ' Kumulatif: ' + context.parsed.y + '%';
                            }
                            return ' Jumlah defect: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: 8
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        stepSize: 1
                    }
                },
                percentAxis: {
                    type: 'linear',
                    position: 'right',
                    min: 0,
                    max: 100,
                    ticks: { callback: function(v){ return v + '%'; } }
                }
            }
        }
    });
}

// Align Pareto card with Top Defect while staying below Trend
(function(){
    function alignPareto(){
        var mq = window.matchMedia('(min-width: 992px)');
        var pareto = document.querySelector('.card-pareto');
        var topDefect = document.querySelector('.card-topdefect');
        var trend = document.querySelector('.card-trend');
        if (!pareto || !topDefect || !trend) return;

        // Temporarily disable transition to avoid visible "lifting" animation
        var prevTransition = pareto.style.transition || '';
        pareto.style.transition = 'none';

        // reset transform so measurements are accurate
        pareto.style.transform = 'none';
        pareto.style.marginTop = '';

        if (!mq.matches) {
            // re-enable transition and exit for small screens
            pareto.style.transition = prevTransition;
            return; // only on desktop
        }

        var pRect = pareto.getBoundingClientRect();
        var tRect = topDefect.getBoundingClientRect();
        var trRect = trend.getBoundingClientRect();
        var scrollY = window.scrollY || window.pageYOffset;
        var desiredTop = tRect.top + scrollY; // align with top defect top
        var minTop = trRect.bottom + scrollY + 12; // must be below trend bottom
        var finalTop = Math.max(minTop, desiredTop);
        var delta = finalTop - (pRect.top + scrollY);

        // apply transform instantly (no transition)
        pareto.style.transform = 'translateY(' + delta + 'px)';

        // force reflow then restore transition so future changes animate smoothly
        /* eslint-disable no-unused-expressions */
        pareto.offsetHeight; // force reflow
        /* eslint-enable no-unused-expressions */
        pareto.style.transition = prevTransition;
    }
    // run after small delay to allow charts/layout
    window.addEventListener('load', function(){ setTimeout(alignPareto, 120); });
    window.addEventListener('resize', function(){ setTimeout(alignPareto, 80); });
})();

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
