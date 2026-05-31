<?php
include_once __DIR__ . '/../db.php';

// --- 1. CAPTURE SELECTION PARAMETERS ---
$selectedOrg = $_GET['org'] ?? $_GET['institute'] ?? null;
$selectedDivision = $_GET['division'] ?? 'all';

$selectedOrg = is_string($selectedOrg) ? trim($selectedOrg) : null;
$selectedDivision = is_string($selectedDivision) ? trim($selectedDivision) : 'all';

// --- 2. HELPERS ---
function summary_h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function summary_clean($value): string {
    return strtoupper(trim((string)($value ?? '')));
}

function summary_first_number($value): float {
    $text = str_replace([',', '%'], '', (string)$value);
    if (preg_match('/-?\d+(?:\.\d+)?/', $text, $m)) {
        return (float)$m[0];
    }
    return 0.0;
}

// --- 3. BUILD FLAT PROJECT LIST FROM DB ---
$sql = "SELECT p.*, i.code AS _org_code, i.institution_name AS _org_name, d.division_name AS division,
       f.cum_fin_target AS q1_fin_target,
       f.actual_expenditure AS q1_fin_actual,
       qp.cumulative_quarterly_target AS q1_phys_target,
       qp.cumulative_quarterly_progress AS q1_phys_actual,
       qp.progress_percentage AS q1_quarterly_cum,
       cp.cumulative_overall_target AS q1_cum_target,
       cp.cumulative_overall_progress AS q1_cum_prog,
       cp.physical_progress_percentage AS q1_overall_prog_final
FROM projects p
LEFT JOIN institutions i ON p.institution_id = i.id
LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN financial_progress f ON p.id = f.project_id AND f.quarter = 'Q1'
LEFT JOIN quarterly_physical_progress qp ON p.id = qp.project_id AND qp.quarter = 'Q1'
LEFT JOIN cumulative_physical_status cp ON p.id = cp.project_id AND cp.quarter = 'Q1'";

$result = mysqli_query($conn, $sql);
$allProjects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['actual_exp'] = ['Q1' => $row['q1_fin_actual']];
        $row['phys_percent'] = [
            'Q1' => $row['q1_phys_actual'],
            'Q1_Target' => $row['q1_phys_target'],
            'Q1_Overall_Prog_Final' => $row['q1_overall_prog_final'],
            'Q1_Cum_Target' => $row['q1_cum_target'],
            'Q1_Cum_Prog' => $row['q1_cum_prog'],
            'Q1_Quarterly_Cum' => $row['q1_quarterly_cum']
        ];
        $allProjects[] = $row;
    }
}

// --- 4. INITIALIZE AGGREGATION ---
$projCount = 0;
$agg = [
    'alloc_orig' => 0.0, 'alloc_rev' => 0.0,
    'q1_fin_target' => 0.0, 'q1_fin_actual' => 0.0,
    'exp_2025' => 0.0, 'phys_2025_sum' => 0.0,
    'q1_phys_actual_sum' => 0.0, 'q1_phys_target_sum' => 0.0,
    'q1_qly_cum_sum' => 0.0, 'q1_overall_achieve_sum' => 0.0,
    'q1_overall_target_sum' => 0.0, 'q1_cum_phys_sum' => 0.0
];

$fundingSources = ['CAASL' => 0, 'AASL' => 0, 'SLPA' => 0, 'Gov' => 0];
$portsAlloc = ['SLPA' => 0.0, 'MSS' => 0.0, 'CSC' => 0.0]; // Updated labels
$aviationAlloc = ['AASL' => 0.0, 'CAASL' => 0.0];

// --- 5. DATA PROCESSING LOOP ---
foreach ($allProjects as $project) {
    $orgCodeRaw = strtoupper(trim($project['_org_code'] ?: $project['_org_name'] ?? ''));
    $orgCode = $orgCodeRaw;
    if (strpos($orgCodeRaw, 'AASL') !== false) $orgCode = 'AASL';
    elseif (strpos($orgCodeRaw, 'CAASL') !== false) $orgCode = 'CAASL';
    elseif (strpos($orgCodeRaw, 'SLPA') !== false) $orgCode = 'SLPA';
    elseif (strpos($orgCodeRaw, 'MSS') !== false || strpos($orgCodeRaw, 'MERCHANT') !== false) $orgCode = 'MSS';
    elseif (strpos($orgCodeRaw, 'JCT') !== false) $orgCode = 'JCT';
    elseif (strpos($orgCodeRaw, 'CSC') !== false) $orgCode = 'CSC';
    
    if ($selectedOrg && $selectedOrg !== 'all' && strcasecmp($selectedOrg, $orgCode) !== 0) {
        continue;
    }
    
    $divRaw = summary_clean($project['division'] ?? '');
    $matchDiv = ($selectedDivision === 'all');
    
    if (!$matchDiv) {
        $selDivClean = summary_clean($selectedDivision);
        
        // Aviation specific logic
        if ($orgCode === 'AASL') {
            if ($selDivClean === 'CIVIL' && (strpos($divRaw, 'CE') !== false)) $matchDiv = true;
            elseif ($selDivClean === 'MECH' && (strpos($divRaw, 'ME') !== false)) $matchDiv = true;
            elseif ($divRaw === $selDivClean) $matchDiv = true;
        } 
        // Consolidated Electrical logic for SLPA
        elseif ($orgCode === 'SLPA' && $selDivClean === 'ELECTRICAL & ELECTRONIC') {
            if (in_array($divRaw, ['ELECTRICAL & ELECTRONIC', 'ELECTRICAL & ELECTRONICS ENG. DIV.', 'EE'])) $matchDiv = true;
            else if ($divRaw === $selDivClean) $matchDiv = true;
        }
        // Standard matching for other Port/Division entries
        elseif ($divRaw === $selDivClean) {
            $matchDiv = true;
        }
    }

    if (!$matchDiv) continue;

    $projCount++;
    $agg['alloc_orig'] += summary_first_number($project['allocation_2026_original'] ?? 0);
    $agg['alloc_rev']  += summary_first_number($project['allocation_2026_revised'] ?? 0);
    
    $agg['q1_fin_target'] += summary_first_number($project['q1_fin_target'] ?? 0);
    $agg['q1_fin_actual'] += summary_first_number($project['actual_exp']['Q1'] ?? 0);

    $phys = $project['phys_percent'] ?? [];
    $agg['q1_phys_actual_sum'] += summary_first_number($phys['Q1'] ?? 0);
    $agg['q1_phys_target_sum'] += summary_first_number($phys['Q1_Target'] ?? 0);
    $agg['q1_overall_achieve_sum'] += summary_first_number($phys['Q1_Overall_Prog_Final'] ?? 0);
    $agg['q1_overall_target_sum']  += summary_first_number($phys['Q1_Cum_Target'] ?? 0);
    $agg['q1_cum_phys_sum']        += summary_first_number($phys['Q1_Cum_Prog'] ?? 0);
    $agg['q1_qly_cum_sum']         += summary_first_number($phys['Q1_Quarterly_Cum'] ?? 0);

    // Track funding source
    $fs = summary_clean($project['funding_source'] ?? '');
    if(strpos($fs, 'CAASL') !== false) $fundingSources['CAASL']++;
    elseif(strpos($fs, 'AASL') !== false) $fundingSources['AASL']++;
    elseif(strpos($fs, 'SLPA') !== false) $fundingSources['SLPA']++;
    else $fundingSources['Gov']++;

    if (isset($portsAlloc[$orgCode])) $portsAlloc[$orgCode] += summary_first_number($project['allocation_2026_original'] ?? 0);
    if (isset($aviationAlloc[$orgCode])) $aviationAlloc[$orgCode] += summary_first_number($project['allocation_2026_original'] ?? 0);
}

$divisor = max(1, $projCount);
$avg_q1_phys = $agg['q1_phys_actual_sum'] / $divisor;

$isAviation = in_array(strtoupper($selectedOrg), ['AASL', 'CAASL']);
$isPorts = in_array(strtoupper($selectedOrg), ['SLPA', 'MSS', 'MS', 'CSC']);
$isGlobal = ($selectedOrg === 'all' || !$selectedOrg);

$scopeLabel = ($selectedOrg && $selectedOrg !== 'all') ? strtoupper($selectedOrg) : 'Consolidated Overview';
if($selectedDivision !== 'all') $scopeLabel .= " : " . $selectedDivision;
?>

<style>
    .summary-wrapper { padding: 15px 25px; max-width: 100%; margin: 0; font-family: 'Inter', sans-serif; background: #f8fafc; }
    
    .sum-header { 
        background: #1e3a5f; color: white; padding: 15px 20px; border-radius: 12px; 
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; 
    }
    
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .kpi-card { background: white; padding: 20px 25px; border-radius: 15px; border-left: 6px solid #1e3a5f; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; }
    .kpi-card i { font-size: 32px; color: #1e3a5f; }
    .kpi-card div { display: flex; flex-direction: column; }
    .kpi-card small { font-size: 11px; font-weight: 800; color: #718096; text-transform: uppercase; margin-bottom: 4px; }
    .kpi-card h2 { margin: 0; font-size: 28px; color: #1e3a5f; font-weight: 900; }

    .detail-matrix { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .matrix-box { background: white; padding: 18px 22px; border-radius: 15px; border: 1px solid #e2e8f0; display: flex; align-items: flex-start; gap: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
    .matrix-box i { font-size: 20px; margin-top: 5px; }
    .matrix-box small { font-size: 10px; font-weight: 800; color: #0ea5e9; display: block; margin-bottom: 8px; text-transform: uppercase; }
    .m-val { font-size: 20px; font-weight: 900; color: #1e293b; margin-bottom: 5px;}
    .m-sub { font-size: 13px; color: #64748b; font-weight: 600; }

    .standing-bar { 
        background: #fff; padding: 15px 25px; border: 1px solid #e2e8f0; border-radius: 12px; 
        margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }

    .chart-container-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
        gap: 12px; 
        width: 100%;
    }
    .chart-card { 
        background: white; padding: 12px; border-radius: 12px; 
        border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        display: flex; flex-direction: column;
    }
    .chart-card h4 { font-size: 10px; text-align: center; color: #1e3a5f; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .canvas-wrap { height: 130px; width: 100%; position: relative; }
</style>

<div class="summary-wrapper">
    <div class="sum-header">
        <h3 style="margin:0; font-size: 18px;"><i class="fa fa-chart-pie"></i> <?= summary_h($scopeLabel) ?></h3>
        <a href="index.php?page=home" style="color:white; text-decoration:none; font-size:12px; background:rgba(255,255,255,0.2); padding:6px 15px; border-radius:8px; font-weight:bold; transition: 0.2s;">BACK</a>
    </div>

    <!-- Top KPI Row -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <i class="fa fa-briefcase"></i>
            <div><small>Scope Projects</small><h2><?= (int)$projCount ?></h2></div>
        </div>
        <div class="kpi-card" style="border-left-color: #f59e0b;">
            <i class="fa fa-coins" style="color:#f59e0b;"></i>
            <div><small>Q1 Actual Exp</small><h2><?= number_format($agg['q1_fin_actual'], 1) ?>M</h2></div>
        </div>
        <div class="kpi-card" style="border-left-color: #10b981;">
            <i class="fa fa-chart-line" style="color:#10b981;"></i>
            <div><small>Q1 Phys. Avg</small><h2><?= number_format($avg_q1_phys, 1) ?>%</h2></div>
        </div>
    </div>

    <!-- Performance Details Matrix -->
    <div class="detail-matrix">
        <div class="matrix-box">
            <i class="fa fa-bullseye" style="color:#0ea5e9;"></i>
            <div>
                <small>Q1 ACTUAL / TARGET</small>
                <div class="m-val" style="color:#0ea5e9;"><?= number_format($avg_q1_phys, 2) ?>% / <?= number_format($agg['q1_phys_target_sum']/$divisor, 2) ?>%</div>
                <div class="m-sub">Quarterly Cum: <?= number_format($agg['q1_qly_cum_sum']/$divisor, 2) ?>%</div>
            </div>
        </div>
        <div class="matrix-box">
            <i class="fa fa-money-bill-wave" style="color:#f59e0b;"></i>
            <div>
                <small style="color:#f59e0b;">FINANCIAL EXP (Q1)</small>
                <div class="m-val">Rs. <?= number_format($agg['q1_fin_actual'], 1) ?>M</div>
                <div class="m-sub">Q1 Target: <?= number_format($agg['q1_fin_target'], 1) ?>M</div>
            </div>
        </div>
        <div class="matrix-box">
            <i class="fa fa-file-invoice-dollar" style="color:#1e3a5f;"></i>
            <div>
                <small style="color:#1e3a5f;">ALLOCATION 2026</small>
                <div class="m-val">Orig: <?= number_format($agg['alloc_orig'], 0) ?>M</div>
                <div class="m-sub">Rev: <?= number_format($agg['alloc_rev'], 0) ?>M</div>
            </div>
        </div>
    </div>

    <!-- Overall Standing Bar -->
    <div class="standing-bar">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fa fa-award" style="font-size:22px; color:#1e3a5f;"></i>
            <small style="font-weight:800; color:#1e3a5f; font-size:10px; text-transform:uppercase;">Overall Standing</small>
        </div>
        <div class="m-val" style="font-size:18px; margin:0;">Achievement: <?= number_format($agg['q1_overall_achieve_sum']/$divisor, 1) ?>%</div>
        <div class="m-sub" style="font-weight:800; color:#1e3a5f;"><i class="fa fa-crosshairs"></i> TARGET: <?= number_format($agg['q1_overall_target_sum']/$divisor, 2) ?>%</div>
        <div class="m-sub"><i class="fa fa-tasks"></i> Phys: <?= number_format($agg['q1_cum_phys_sum']/$divisor, 2) ?>%</div>
    </div>

    <!-- Chart Row -->
    <div class="chart-container-row">
        <div class="chart-card">
            <h4>Progress Trend</h4>
            <div class="canvas-wrap"><canvas id="trendChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h4>Funding Sources</h4>
            <div class="canvas-wrap"><canvas id="sourceChart"></canvas></div>
        </div>

        <?php if ($isGlobal || $isPorts): ?>
        <div class="chart-card">
            <h4>Ports Budget</h4>
            <div class="canvas-wrap"><canvas id="portsChart"></canvas></div>
        </div>
        <?php endif; ?>

        <?php if ($isGlobal || $isAviation): ?>
        <div class="chart-card">
            <h4>Aviation Budget</h4>
            <div class="canvas-wrap"><canvas id="aviaChart"></canvas></div>
        </div>
        <?php endif; ?>

        <div class="chart-card">
            <h4>Financial Achievement</h4>
            <div class="canvas-wrap"><canvas id="finChart"></canvas></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const commonOpts = { 
        maintainAspectRatio: false, 
        plugins: { legend: { display: false } }, 
        scales: { 
            y: { beginAtZero: true, ticks: { font: { size: 8 } }, grid: { color: '#f1f5f9' } }, 
            x: { ticks: { font: { size: 8 } }, grid: { display: false } } 
        } 
    };

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: ['Tgt', 'Act'], datasets: [{ data: [<?= $agg['q1_phys_target_sum']/$divisor ?>, <?= $avg_q1_phys ?>], borderColor: '#0ea5e9', borderWidth: 2, pointRadius: 4, fill: true, backgroundColor: 'rgba(14, 165, 233, 0.05)' }] },
        options: commonOpts
    });

    new Chart(document.getElementById('sourceChart'), {
        type: 'doughnut',
        data: { labels: ['CAASL', 'AASL', 'SLPA', 'Gov'], datasets: [{ data: <?= json_encode(array_values($fundingSources)) ?>, backgroundColor: ['#1e3a5f', '#10b981', '#f59e0b', '#cbd5e1'], borderWidth: 1 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 8 } } } }, cutout: '65%' }
    });

    <?php if ($isGlobal || $isPorts): ?>
    new Chart(document.getElementById('portsChart'), {
        type: 'bar',
        data: { labels: ['SLPA', 'MSS', 'CSC'], datasets: [{ data: <?= json_encode(array_values($portsAlloc)) ?>, backgroundColor: '#1e3a5f', borderRadius: 4 }] },
        options: commonOpts
    });
    <?php endif; ?>

    <?php if ($isGlobal || $isAviation): ?>
    new Chart(document.getElementById('aviaChart'), {
        type: 'bar',
        data: { labels: ['AASL', 'CAASL'], datasets: [{ data: <?= json_encode(array_values($aviationAlloc)) ?>, backgroundColor: '#10b981', borderRadius: 4 }] },
        options: commonOpts
    });
    <?php endif; ?>

    new Chart(document.getElementById('finChart'), {
        type: 'pie',
        data: { labels: ['Spent', 'Rem'], datasets: [{ data: [<?= $agg['q1_fin_actual'] ?>, <?= max(0, $agg['q1_fin_target'] - $agg['q1_fin_actual']) ?>], backgroundColor: ['#f59e0b', '#f1f5f9'], borderWidth: 1 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 8 } } } } }
    });
})();
</script>