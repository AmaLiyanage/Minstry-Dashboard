<?php
include_once __DIR__ . '/../db.php';

// --- 1. CAPTURE SELECTION PARAMETERS ---
$selectedOrg = $_GET['org'] ?? $_GET['institute'] ?? null;
$selectedDivision = $_GET['division'] ?? 'all';
$selectedQuarter = $_GET['quarter'] ?? 'Q1';

// Sanitize inputs
$selectedOrg = is_string($selectedOrg) ? trim($selectedOrg) : null;
$selectedDivision = is_string($selectedDivision) ? trim($selectedDivision) : 'all';
$selectedQuarter = in_array(strtoupper($selectedQuarter), ['Q1', 'Q2', 'Q3', 'Q4']) ? strtoupper($selectedQuarter) : 'Q1';

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
// Adjusted query to pull targets explicitly matching the selected quarter context
$sql = "SELECT p.*, i.code AS _org_code, i.institution_name AS _org_name, d.division_name AS division,
       f.cum_fin_target AS fin_target,
       f.actual_expenditure AS fin_actual,
       qp.cumulative_quarterly_target AS phys_target,
       qp.cumulative_quarterly_progress AS phys_actual,
       qp.progress_percentage AS quarterly_cum,
       cp.cumulative_overall_target AS cum_target,
       cp.cumulative_overall_progress AS cum_prog,
       cp.physical_progress_percentage AS overall_prog_final
FROM projects p
LEFT JOIN institutions i ON p.institution_id = i.id
LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN financial_progress f ON p.id = f.project_id AND f.quarter = '$selectedQuarter'
LEFT JOIN quarterly_physical_progress qp ON p.id = qp.project_id AND qp.quarter = '$selectedQuarter'
LEFT JOIN cumulative_physical_status cp ON p.id = cp.project_id AND cp.quarter = '$selectedQuarter'";

$result = mysqli_query($conn, $sql);
$allProjects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['actual_exp'] = [$selectedQuarter => $row['fin_actual']];
        $row['phys_percent'] = [
            $selectedQuarter => $row['phys_actual'],
            'Target' => $row['phys_target'],
            'Overall_Prog_Final' => $row['overall_prog_final'],
            'Cum_Target' => $row['cum_target'],
            'Cum_Prog' => $row['cum_prog'],
            'Quarterly_Cum' => $row['quarterly_cum']
        ];
        $allProjects[] = $row;
    }
}

// --- 4. INITIALIZE AGGREGATION ---
$projCount = 0;
$agg = [
    'alloc_orig' => 0.0, 'alloc_rev' => 0.0,
    'fin_target' => 0.0, 'fin_actual' => 0.0,
    'phys_actual_sum' => 0.0, 'phys_target_sum' => 0.0,
    'qly_cum_sum' => 0.0, 'overall_achieve_sum' => 0.0,
    'overall_target_sum' => 0.0, 'cum_phys_sum' => 0.0
];

$fundingSources = ['CAASL' => 0, 'AASL' => 0, 'SLPA' => 0, 'Gov' => 0];
$portsAlloc = ['SLPA' => 0.0, 'MSS' => 0.0, 'CSC' => 0.0]; 
$aviationAlloc = ['AASL' => 0.0, 'CAASL' => 0.0];

// --- 5. DATA PROCESSING LOOP ---
foreach ($allProjects as $project) {
    $orgCodeRaw = strtoupper(trim($project['_org_code'] ?: $project['_org_name'] ?? ''));
    $orgCode = $orgCodeRaw;
    if (strpos($orgCodeRaw, 'CAASL') !== false) $orgCode = 'CAASL';
    elseif (strpos($orgCodeRaw, 'AASL') !== false) $orgCode = 'AASL';
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
        
        if ($orgCode === 'AASL') {
            if ($selDivClean === 'CIVIL' && (strpos($divRaw, 'CE') !== false)) $matchDiv = true;
            elseif ($selDivClean === 'MECH' && (strpos($divRaw, 'ME') !== false)) $matchDiv = true;
            elseif ($divRaw === $selDivClean) $matchDiv = true;
        } 
        elseif ($orgCode === 'SLPA' && $selDivClean === 'ELECTRICAL & ELECTRONIC') {
            if (in_array($divRaw, ['ELECTRICAL & ELECTRONIC', 'ELECTRICAL & ELECTRONICS ENG. DIV.', 'EE'])) $matchDiv = true;
            else if ($divRaw === $selDivClean) $matchDiv = true;
        }
        elseif ($divRaw === $selDivClean) {
            $matchDiv = true;
        }
    }

    if (!$matchDiv) continue;

    $projCount++;
    $agg['alloc_orig'] += summary_first_number($project['allocation_2026_original'] ?? 0);
    $agg['alloc_rev']  += summary_first_number($project['allocation_2026_revised'] ?? 0);
    
    $agg['fin_target'] += summary_first_number($project['fin_target'] ?? 0);
    $agg['fin_actual'] += summary_first_number($project['actual_exp'][$selectedQuarter] ?? 0);

    $phys = $project['phys_percent'] ?? [];
    $agg['phys_actual_sum'] += summary_first_number($phys[$selectedQuarter] ?? 0);
    $agg['phys_target_sum'] += summary_first_number($phys['Target'] ?? 0);
    $agg['overall_achieve_sum'] += summary_first_number($phys['Overall_Prog_Final'] ?? 0);
    $agg['overall_target_sum']  += summary_first_number($phys['Cum_Target'] ?? 0);
    $agg['cum_phys_sum']        += summary_first_number($phys['Cum_Prog'] ?? 0);
    $agg['qly_cum_sum']         += summary_first_number($phys['Quarterly_Cum'] ?? 0);

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
$avg_quarter_phys = $agg['phys_actual_sum'] / $divisor;

$isAviation = in_array(strtoupper($selectedOrg), ['AASL', 'CAASL']);
$isPorts = in_array(strtoupper($selectedOrg), ['SLPA', 'MSS', 'MS', 'CSC']);
$isGlobal = ($selectedOrg === 'all' || !$selectedOrg);

$scopeLabel = ($selectedOrg && $selectedOrg !== 'all') ? strtoupper($selectedOrg) : 'Consolidated Overview';
if($selectedDivision !== 'all') $scopeLabel .= " : " . $selectedDivision;
?>

<style>
    .summary-wrapper { padding: 15px 25px; max-width: 100%; margin: 0; font-family: 'Inter', sans-serif; background: #f8fafc; }
    
    .sum-header { 
        background: #1a365d; color: white; padding: 15px 20px; border-radius: 12px; 
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; 
    }
    .controls-wrapper { display: flex; align-items: center; gap: 15px; }
    .quarter-select { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; background: white; color: #1a365d; font-weight: 700; outline: none; cursor: pointer; font-size: 13px; }
    
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .kpi-card { background: white; padding: 20px 25px; border-radius: 15px; border-left: 6px solid #1a365d; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; }
    .kpi-card i { font-size: 32px; color: #1a365d; }
    .kpi-card div { display: flex; flex-direction: column; }
    .kpi-card small { font-size: 11px; font-weight: 800; color: #4a5568; text-transform: uppercase; margin-bottom: 4px; }
    .kpi-card h2 { margin: 0; font-size: 28px; color: #1a365d; font-weight: 900; }

    .detail-matrix { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .matrix-box { background: white; padding: 18px 22px; border-radius: 15px; border: 1px solid #e2e8f0; display: flex; align-items: flex-start; gap: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
    .matrix-box i { font-size: 20px; margin-top: 5px; }
    .matrix-box small { font-size: 10px; font-weight: 800; color: #2b6cb0; display: block; margin-bottom: 8px; text-transform: uppercase; }
    .m-val { font-size: 20px; font-weight: 900; color: #1a202c; margin-bottom: 5px;}
    .m-sub { font-size: 13px; color: #4a5568; font-weight: 600; }

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
    .chart-card h4 { font-size: 10px; text-align: center; color: #1a365d; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .canvas-wrap { height: 130px; width: 100%; position: relative; }
</style>

<div class="summary-wrapper">
    <div class="sum-header">
        <h3 style="margin:0; font-size: 18px;"><i class="fa fa-chart-pie"></i> <?= summary_h($scopeLabel) ?></h3>
        
        <div class="controls-wrapper">
            <select class="quarter-select" onchange="switchQuarter(this.value)">
                <option value="Q1" <?= $selectedQuarter === 'Q1' ? 'selected' : '' ?>>Quarter 1 (Q1)</option>
                <option value="Q2" <?= $selectedQuarter === 'Q2' ? 'selected' : '' ?>>Quarter 2 (Q2)</option>
                <option value="Q3" <?= $selectedQuarter === 'Q3' ? 'selected' : '' ?>>Quarter 3 (Q3)</option>
                <option value="Q4" <?= $selectedQuarter === 'Q4' ? 'selected' : '' ?>>Quarter 4 (Q4)</option>
            </select>
            <a href="index.php?page=home" style="color:white; text-decoration:none; font-size:12px; background:rgba(255,255,255,0.2); padding:6px 15px; border-radius:8px; font-weight:bold;">BACK</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <i class="fa fa-briefcase"></i>
            <div><small>Scope Projects</small><h2><?= (int)$projCount ?></h2></div>
        </div>
        <div class="kpi-card" style="border-left-color: #f59e0b;">
            <i class="fa fa-coins" style="color:#f59e0b;"></i>
            <div><small><?= $selectedQuarter ?> Phy Actual Exp</small><h2><?= number_format($agg['fin_actual'], 1) ?>M</h2></div>
        </div>
        <div class="kpi-card" style="border-left-color: #10b981;">
            <i class="fa fa-chart-line" style="color:#10b981;"></i>
            <div><small><?= $selectedQuarter ?> Phys. Avg</small><h2><?= number_format($avg_quarter_phys, 1) ?>%</h2></div>
        </div>
    </div>

    <div class="detail-matrix">
        <div class="matrix-box">
            <i class="fa fa-bullseye" style="color:#0ea5e9;"></i>
            <div>
                <small><?= $selectedQuarter ?> Phy ACTUAL / TARGET</small>
                <div class="m-val" style="color:#2b6cb0;"><?= number_format($avg_quarter_phys, 2) ?>% / <?= number_format($agg['phys_target_sum']/$divisor, 2) ?>%</div>
                <div class="m-sub">Quarterly Cum: <?= number_format($agg['qly_cum_sum']/$divisor, 2) ?>%</div>
            </div>
        </div>
        <div class="matrix-box">
            <i class="fa fa-money-bill-wave" style="color:#f59e0b;"></i>
            <div>
                <small style="color:#f59e0b;">FINANCIAL EXP (<?= $selectedQuarter ?>)</small>
                <div class="m-val">Rs. <?= number_format($agg['fin_actual'], 1) ?>M</div>
                <div class="m-sub"><?= $selectedQuarter ?> Target: <?= number_format($agg['fin_target'], 1) ?>M</div>
            </div>
        </div>
        <div class="matrix-box">
            <i class="fa fa-file-invoice-dollar" style="color:#1a365d;"></i>
            <div>
                <small style="color:#1a365d;">ALLOCATION 2026</small>
                <div class="m-val">Orig: <?= number_format($agg['alloc_orig'], 0) ?>M</div>
                <div class="m-sub">Rev: <?= number_format($agg['alloc_rev'], 0) ?>M</div>
            </div>
        </div>
    </div>

    <div class="standing-bar">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fa fa-award" style="font-size:22px; color:#1a365d;"></i>
            <small style="font-weight:800; color:#1a365d; font-size:10px; text-transform:uppercase;">Overall Standing (<?= $selectedQuarter ?>)</small>
        </div>
        <div class="m-val" style="font-size:18px; margin:0;">Achievement: <?= number_format($agg['overall_achieve_sum']/$divisor, 1) ?>%</div>
        <div class="m-sub" style="font-weight:800; color:#1a365d;"><i class="fa fa-crosshairs"></i> TARGET: <?= number_format($agg['overall_target_sum']/$divisor, 2) ?>%</div>
        <div class="m-sub"><i class="fa fa-tasks"></i> Phys Avg: <?= number_format($agg['cum_phys_sum']/$divisor, 2) ?>%</div>
    </div>

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
function switchQuarter(quarter) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('quarter', quarter);
    window.location.search = urlParams.toString();
}

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
        data: { labels: ['Tgt', 'Act'], datasets: [{ data: [<?= $agg['phys_target_sum']/$divisor ?>, <?= $avg_quarter_phys ?>], borderColor: '#0ea5e9', borderWidth: 2, pointRadius: 4, fill: true, backgroundColor: 'rgba(14, 165, 233, 0.05)' }] },
        options: commonOpts
    });

    new Chart(document.getElementById('sourceChart'), {
        type: 'doughnut',
        data: { labels: ['CAASL', 'AASL', 'SLPA', 'Gov'], datasets: [{ data: <?= json_encode(array_values($fundingSources)) ?>, backgroundColor: ['#1a365d', '#10b981', '#f59e0b', '#cbd5e1'], borderWidth: 1 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 8 } } } }, cutout: '65%' }
    });

    <?php if ($isGlobal || $isPorts): ?>
    new Chart(document.getElementById('portsChart'), {
        type: 'bar',
        data: { labels: ['SLPA', 'MSS', 'CSC'], datasets: [{ data: <?= json_encode(array_values($portsAlloc)) ?>, backgroundColor: '#1a365d', borderRadius: 4 }] },
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
        data: { labels: ['Spent', 'Rem'], datasets: [{ data: [<?= $agg['fin_actual'] ?>, <?= max(0, $agg['fin_target'] - $agg['fin_actual']) ?>], backgroundColor: ['#f59e0b', '#f1f5f9'], borderWidth: 1 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 8 } } } } }
    });
})();
</script>