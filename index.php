<?php
// index.php - Dashboard
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$pdo = getPDO();

// Totals
$today = date('Y-m-d');
$start_month = date('Y-m-01');

$total_today_stmt = $pdo->prepare('SELECT COUNT(*) FROM inspection_headers WHERE inspection_date = :today');
$total_today_stmt->execute([':today' => $today]);
$total_today = (int)$total_today_stmt->fetchColumn();

$total_month_stmt = $pdo->prepare('SELECT COUNT(*) FROM inspection_headers WHERE inspection_date BETWEEN :start AND :end');
$total_month_stmt->execute([':start' => $start_month, ':end' => $today]);
$total_month = (int)$total_month_stmt->fetchColumn();

$total_ok_stmt = $pdo->query("SELECT COUNT(*) FROM inspection_headers WHERE final_result = 'PASS'");
$total_ok = (int)$total_ok_stmt->fetchColumn();

$total_ng_stmt = $pdo->query("SELECT COUNT(*) FROM inspection_headers WHERE final_result = 'NG'");
$total_ng = (int)$total_ng_stmt->fetchColumn();

$total_products_stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE status = "active"');
$total_products = (int)$total_products_stmt->fetchColumn();

$total_defect_stmt = $pdo->query('SELECT COUNT(*) FROM inspection_details WHERE defect_id IS NOT NULL');
$total_defect = (int)$total_defect_stmt->fetchColumn();

$ok_rate = ($total_today > 0 || ($total_ok + $total_ng) > 0) ? round(($total_ok / max(1, ($total_ok + $total_ng))) * 100, 2) : 0;
$ng_rate = 100 - $ok_rate;

// Inspection trend - last 14 days
$days = [];
$counts = [];
$interval = 13; // past 14 days including today
$start_date = date('Y-m-d', strtotime("-{$interval} days"));
$end_date = $today;
$stmt = $pdo->prepare('SELECT inspection_date, COUNT(*) AS cnt FROM inspection_headers WHERE inspection_date BETWEEN :start AND :end GROUP BY inspection_date ORDER BY inspection_date');
$stmt->execute([':start' => $start_date, ':end' => $end_date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$map = [];
foreach ($rows as $r) {
    $map[$r['inspection_date']] = (int)$r['cnt'];
}
for ($i = 0; $i <= $interval; $i++) {
    $d = date('Y-m-d', strtotime("-" . ($interval - $i) . " days"));
    $days[] = $d;
    $counts[] = $map[$d] ?? 0;
}

// OK vs NG overall
$okng_stmt = $pdo->query("SELECT final_result, COUNT(*) as cnt FROM inspection_headers WHERE final_result IS NOT NULL GROUP BY final_result");
$okng_rows = $okng_stmt->fetchAll(PDO::FETCH_ASSOC);
$okng_map = ['PASS' => 0, 'NG' => 0, 'HOLD' => 0];
foreach ($okng_rows as $r) {
    $okng_map[$r['final_result']] = (int)$r['cnt'];
}

// Top defects
$top_defect_stmt = $pdo->query('SELECT d.defect_name, COUNT(*) as cnt FROM inspection_details id JOIN defects d ON id.defect_id = d.id GROUP BY id.defect_id ORDER BY cnt DESC LIMIT 10');
$top_defects = $top_defect_stmt->fetchAll(PDO::FETCH_ASSOC);

// Pareto data (all defects)
$pareto_stmt = $pdo->query('SELECT d.defect_name, COUNT(*) AS cnt FROM inspection_details id JOIN defects d ON id.defect_id = d.id GROUP BY id.defect_id ORDER BY cnt DESC');
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

// pass data to view
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<h3>Dashboard QC INSPECTION</h3>
<div class="row mt-3">
    <div class="col-md-3">
        <div class="card shadow-sm card-stats card-stats-today">
            <div class="card-body">
                <h6>Total Inspection Hari Ini</h6>
                <h3><?php echo esc((string)$total_today); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm card-stats card-stats-month">
            <div class="card-body">
                <h6>Total Inspection Bulan Ini</h6>
                <h3><?php echo esc((string)$total_month); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm card-stats card-stats-ok">
            <div class="card-body">
                <h6>Total OK</h6>
                <h3><?php echo esc((string)$total_ok); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm card-stats card-stats-ng">
            <div class="card-body">
                <h6>Total NG</h6>
                <h3><?php echo esc((string)$total_ng); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm card-stats card-stats-rate">
            <div class="card-body">
                <h6>OK Rate</h6>
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
                        <h6>Inspection Trend (14 hari)</h6>
                        <canvas id="trendChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid-pareto">
                <div class="card pareto-card card-pareto">
                    <div class="card-body">
                        <h6>Defect Pareto</h6>
                        <c anvas id="paretoChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid-right">
                <div class="card mb-3 card-okng">
                    <div class="card-body">
                        <h6>OK vs NG</h6>
                        <canvas id="okngChart" height="140"></canvas>
                    </div>
                </div>

                <div class="card card-topdefect">
                    <div class="card-body">
                        <h6>Top Defect</h6>
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
                        <h6>Ringkasan</h6>
                        <p>Total Produk Aktif: <strong><?php echo esc((string)$total_products); ?></strong></p>
                        <p>Total Defect Terlapor: <strong><?php echo esc((string)$total_defect); ?></strong></p>
                        <p>NG Rate: <strong><?php echo esc($ng_rate . '%'); ?></strong></p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Charts scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendLabels = <?php echo json_encode($days); ?>;
const trendData = <?php echo json_encode($counts); ?>;
const okngData = <?php echo json_encode([$okng_map['PASS'], $okng_map['NG']]); ?>;
const paretoLabels = <?php echo json_encode($pareto_labels); ?>;
const paretoCounts = <?php echo json_encode($pareto_counts); ?>;
const paretoCumulative = <?php echo json_encode($cumulative); ?>;

// Trend chart
const ctxTrend = document.getElementById('trendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Jumlah Inspection',
            data: trendData,
            borderColor: 'rgba(54, 162, 235, 1)',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        scales: { x: { ticks: { maxRotation: 0 } } },
        responsive: true
    }
});

// OK vs NG pie
const ctxOkNg = document.getElementById('okngChart').getContext('2d');
new Chart(ctxOkNg, {
    type: 'doughnut',
    data: {
        labels: ['PASS','NG'],
        datasets: [{
            data: okngData,
            backgroundColor: ['#28a745','#dc3545']
        }]
    },
    options: { responsive: true }
});

// Pareto: combined bar (counts) + line (cumulative %)
const ctxPareto = document.getElementById('paretoChart').getContext('2d');
new Chart(ctxPareto, {
    data: {
        labels: paretoLabels,
        datasets: [
            {
                type: 'bar',
                label: 'Jumlah',
                data: paretoCounts,
                backgroundColor: 'rgba(255,99,132,0.6)'
            },
            {
                type: 'line',
                label: 'Kumulatif %',
                data: paretoCumulative,
                yAxisID: 'percentAxis',
                borderColor: 'rgba(54,162,235,1)',
                backgroundColor: 'rgba(54,162,235,0.2)',
                tension: 0.3,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
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
