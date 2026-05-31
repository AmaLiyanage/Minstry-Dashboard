<?php
include_once __DIR__ . '/../db.php';

// Capture filters from the sidebar URL parameters
$filterOrg = $_GET['org'] ?? 'All';
$filterDiv = $_GET['f_div'] ?? 'All';

function h($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Normalizes input strings into floats for calculation
 */
function parseNumber($value): float {
    if (is_numeric($value)) return (float)$value;
    $v = trim((string) ($value ?? ''));
    if ($v === '' || $v === '-' || $v === '—' || $v === '0%') return 0.0;
    $normalized = str_replace([',', 'Rs.', 'Rs', 'Mn', '%'], '', $v);
    return (float) $normalized;
}

/**
 * Extracts and FILTERS data based on sidebar selection
 * Supports Aviation, Ports (SLPA/MSS), and any others in sectors
 */
function buildFilteredDataset(array $allProjects, $orgFilter, $divFilter): array {
    $out = ['Ports' => [], 'Aviation' => [], 'JCT' => []];
    
    foreach ($allProjects as $p) {
        $code = trim($p['_org_code'] ?? '');
        if ($code === '') continue;
        
        if ($orgFilter !== 'All' && $orgFilter !== $code) continue;
        
        $pDiv = trim($p['division'] ?? 'General');
        if ($divFilter !== 'All' && $pDiv !== $divFilter) continue;
        
        $cat = 'Ports';
        if (in_array(strtoupper($code), ['AASL', 'CAASL'])) $cat = 'Aviation';
        if (strtoupper($code) === 'JCT') $cat = 'JCT';
        
        $out[$cat][$code][] = $p;
    }
    
    return $out;
}

/**
 * Aggregates statistics including Status Distribution
 */
function calculateDetailedStats(array $orgGroups): array {
    $stats = [
        'count' => 0, 'alloc_orig' => 0.0, 'alloc_rev' => 0.0,
        'q1_fin_target' => 0.0, 'q1_actual_exp' => 0.0,
        'q1_phys_actual_sum' => 0.0, 'q1_target_sum' => 0.0, 'q1_overall_prog_sum' => 0.0,
        'status_map' => ['On Track' => 0, 'Delayed' => 0, 'Completed' => 0, 'At Risk' => 0]
    ];

    foreach ($orgGroups as $projects) {
        foreach ($projects as $p) {
            $stats['count']++;
            $stats['alloc_orig'] += parseNumber($p['allocation_2026_original'] ?? 0);
            $stats['alloc_rev']  += parseNumber($p['allocation_2026_revised'] ?? 0);
            $stats['q1_fin_target'] += parseNumber($p['q1_fin_target'] ?? 0);
            $stats['q1_actual_exp'] += parseNumber($p['actual_exp']['Q1'] ?? 0);
            $stats['q1_phys_actual_sum'] += parseNumber($p['phys_percent']['Q1'] ?? 0);
            $stats['q1_target_sum']      += parseNumber($p['phys_percent']['Q1_Target'] ?? 0);
            $stats['q1_overall_prog_sum']+= parseNumber($p['phys_percent']['Q1_Overall_Prog_Final'] ?? 0);

            // Logic for status details
            $status = trim($p['timeline_status'] ?? '');
            if (stripos($status, 'Delayed') !== false) $stats['status_map']['Delayed']++;
            elseif (stripos($status, 'Track') !== false) $stats['status_map']['On Track']++;
            elseif (stripos($status, 'Completed') !== false) $stats['status_map']['Completed']++;
            elseif (stripos($status, 'Risk') !== false) $stats['status_map']['At Risk']++;
        }
    }

    $stats['avg_q1_phys'] = ($stats['count'] > 0) ? ($stats['q1_phys_actual_sum'] / $stats['count']) : 0;
    $stats['avg_overall_achievement'] = ($stats['count'] > 0) ? ($stats['q1_overall_prog_sum'] / $stats['count']) : 0;
    
    // Utilization logic
    $stats['fin_utilization'] = ($stats['q1_fin_target'] > 0) ? ($stats['q1_actual_exp'] / $stats['q1_fin_target']) * 100 : 0;

    return $stats;
}

$sql = "SELECT p.*, i.code AS _org_code, i.institution_name AS _org_name, d.division_name AS division,
       f.cum_fin_target AS q1_fin_target,
       f.actual_expenditure AS q1_fin_actual,
       qp.cumulative_quarterly_target AS q1_phys_target,
       qp.cumulative_quarterly_progress AS q1_phys_actual,
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
        $codeRaw = trim($row['_org_code'] ?: $row['_org_name'] ?? '');
        $code = strtoupper($codeRaw);
        if (strpos($code, 'AASL') !== false) $code = 'AASL';
        elseif (strpos($code, 'CAASL') !== false) $code = 'CAASL';
        elseif (strpos($code, 'SLPA') !== false) $code = 'SLPA';
        elseif (strpos($code, 'MSS') !== false || strpos($code, 'MERCHANT') !== false) $code = 'MSS';
        elseif (strpos($code, 'JCT') !== false) $code = 'JCT';
        elseif (strpos($code, 'CSC') !== false) $code = 'CSC';
        else $code = $codeRaw !== '' ? $codeRaw : 'UNKNOWN';
        
        $row['_org_code'] = $code;
        $row['actual_exp'] = ['Q1' => $row['q1_fin_actual']];
        $row['phys_percent'] = [
            'Q1' => $row['q1_phys_actual'],
            'Q1_Target' => $row['q1_phys_target'],
            'Q1_Overall_Prog_Final' => $row['q1_overall_prog_final'],
            'Q1_Cum_Target' => $row['q1_cum_target'],
            'Q1_Cum_Prog' => $row['q1_cum_prog']
        ];
        $allProjects[] = $row;
    }
}

$dataset = buildFilteredDataset($allProjects, $filterOrg, $filterDiv);
$mergedAll = array_merge($dataset['Aviation'], $dataset['Ports'], $dataset['JCT']);
$minStats = calculateDetailedStats($mergedAll);
$instList = array_keys($mergedAll);
sort($instList);
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        --blue-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        --gold-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --navy-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .report-viewport { padding: 30px; background: #f1f5f9; min-height: 100vh; animation: fadeIn 0.5s ease-in-out; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .report-header { 
        background: var(--primary-gradient); 
        color: white; padding: 30px; border-radius: 16px; 
        display: flex; flex-direction: column; align-items: flex-start;
        margin-bottom: 30px; box-shadow: var(--card-shadow);
    }

    .title-area h2 { font-size: 24px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
    .title-area p { font-size: 14px; opacity: 0.8; }

    .section-tag { 
        font-size: 14px; font-weight: 800; color: #475569; 
        text-transform: uppercase; letter-spacing: 1px; 
        margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }

    .output-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; margin-bottom: 40px; }
    
    .output-card { padding: 25px; border-radius: 16px; color: white; box-shadow: var(--card-shadow); position: relative; overflow: hidden; }

    .output-card.blue { background: var(--blue-gradient); }
    .output-card.gold { background: var(--gold-gradient); }
    .output-card.navy { background: var(--navy-gradient); }

    .card-label { font-size: 12px; font-weight: 700; text-transform: uppercase; opacity: 0.9; margin-bottom: 15px; display: block; }
    .card-data { font-size: 36px; font-weight: 800; }

    .mini-stat-white { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.15); font-size: 14px; }
    .mini-stat-white:last-child { border-bottom: none; }

    .institution-collapse { background: white; border-radius: 16px; margin-bottom: 25px; overflow: hidden; border: 1px solid #e2e8f0; }
    .inst-bar { background: #f8fafc; padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .inst-name { font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
    
    .division-grid { padding: 25px; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; background: white; }
    
    .division-card { background: #fdfdfd; border: 1px solid #f1f5f9; padding: 20px; border-radius: 12px; }
    .division-card h6 { color: #334155; font-size: 15px; font-weight: 700; margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }

    .d-info-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; color: #64748b; }
    .d-info-row strong { color: #1e293b; }

    /* New styles for the Health bar */
    .health-container { display: flex; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin-bottom: 10px; }
    .health-segment { height: 100%; }
</style>

<div class="report-viewport">
    <div class="report-header">
        <div class="title-area">
            <h2><i class="fa-solid fa-chart-pie"></i> Executive Analytics: <?= h($filterOrg) ?></h2>
            <p><i class="fa-solid fa-calendar-day"></i> Snapshot Year: 2026 | Cycle: <strong>Q1 (Jan–Mar)</strong></p>
            <?php if($filterDiv !== 'All'): ?>
                <div style="margin-top:10px; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:20px; display:inline-block; font-size:12px;">
                    <i class="fa-solid fa-filter"></i> Focused Division: <strong><?= h($filterDiv) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($minStats['count'] === 0): ?>
        <div style="text-align: center; padding: 80px; background: white; border-radius: 20px;">
            <i class="fa-solid fa-box-open" style="font-size: 60px; margin-bottom: 20px; color: #cbd5e1;"></i>
            <h3 style="color: #64748b;">No matching analytics found for this selection.</h3>
        </div>
    <?php else: ?>
        <div class="section-tag"><i class="fa-solid fa-vault"></i> 1. Financial Progress (Overall)</div>
        <div class="output-row">
            <div class="output-card blue">
                <span class="card-label">Q1 Expenditure (Mn)</span>
                <div class="mini-stat-white"><i class="fa-solid fa-bullseye"></i> <span>Target:</span> <strong><?= number_format($minStats['q1_fin_target'], 2) ?></strong></div>
                <div class="mini-stat-white"><i class="fa-solid fa-check-double"></i> <span>Actual:</span> <strong><?= number_format($minStats['q1_actual_exp'], 2) ?></strong></div>
                <div class="mini-stat-white" style="margin-top:10px; border-top: 1px solid rgba(255,255,255,0.3)">
                    <span>Target Utilization:</span> <strong><?= number_format($minStats['fin_utilization'], 1) ?>%</strong>
                </div>
            </div>
            
            <div class="output-card gold">
                <span class="card-label">Weighted Physical Achievement</span>
                <div class="card-data"><?= number_format($minStats['avg_overall_achievement'], 1) ?>%</div>
                <div style="margin-top:10px; font-size:12px; background:rgba(0,0,0,0.1); padding:8px; border-radius:8px; color: #fff;">
                    <i class="fa-solid fa-layer-group"></i> Total Projects: <?= $minStats['count'] ?>
                </div>
            </div>
            
            <div class="output-card navy">
                <span class="card-label">Project Health Distribution</span>
                <div class="health-container">
                    <?php 
                    $colors = ['On Track' => '#10b981', 'Delayed' => '#ef4444', 'Completed' => '#3b82f6', 'At Risk' => '#f59e0b'];
                    foreach($minStats['status_map'] as $status => $count):
                        if($count > 0):
                            $width = ($count / $minStats['count']) * 100;
                    ?>
                        <div class="health-segment" style="width:<?= $width ?>%; background:<?= $colors[$status] ?>;"></div>
                    <?php endif; endforeach; ?>
                </div>
                <div style="font-size: 11px; display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">
                    <?php foreach($minStats['status_map'] as $status => $count): ?>
                        <span><i class="fa-solid fa-circle" style="color:<?= $colors[$status] ?>; font-size:8px;"></i> <?= $status ?>: <?= $count ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section-tag"><i class="fa-solid fa-diagram-project"></i> 2. Detailed Institutional Breakdown</div>
        <?php foreach ($instList as $instCode): 
            $sectorIcon = 'fa-ship';
            if (isset($dataset['Aviation'][$instCode])) $sectorIcon = 'fa-plane-up';
            if (isset($dataset['JCT'][$instCode])) $sectorIcon = 'fa-boxes-stacked';

            $instProjects = $mergedAll[$instCode] ?? [];
            $iS = calculateDetailedStats([$instCode => $instProjects]);
            
            $divWise = [];
            foreach ($instProjects as $p) {
                $div = trim($p['division'] ?? 'General');
                $divWise[$div][] = $p;
            }
            ksort($divWise);
        ?>
            <div class="institution-collapse">
                <div class="inst-bar">
                    <span class="inst-name">
                        <i class="fa-solid <?= $sectorIcon ?>" style="color: #2563eb;"></i>
                        <?= h($instCode) ?> - <small style="color:#64748b"><?= count($instProjects) ?> Projects</small>
                    </span>
                    <span style="font-size: 13px; color: #1e293b; font-weight: 700; background: #e0f2fe; padding: 5px 15px; border-radius: 50px;">
                        Achievement: <?= number_format($iS['avg_overall_achievement'], 1) ?>%
                    </span>
                </div>
                <div class="division-grid">
                    <?php foreach ($divWise as $dName => $dProjects): 
                        $dS = calculateDetailedStats(['temp' => $dProjects]); ?>
                        <div class="division-card">
                            <h6><?= h($dName) ?></h6>
                            <div class="d-info-row">
                                <span>Q1 Target:</span>
                                <strong><?= number_format($dS['q1_target_sum'] / max(1,$dS['count']), 1) ?>%</strong>
                            </div>
                            <div class="d-info-row">
                                <span>Q1 Actual:</span>
                                <strong style="color: #2563eb;"><?= number_format($dS['avg_q1_phys'], 1) ?>%</strong>
                            </div>
                            <div class="d-info-row" style="margin-top:10px; padding-top:5px; border-top:1px solid #f1f5f9">
                                <span>Exp. (Mn):</span>
                                <strong><?= number_format($dS['q1_actual_exp'], 2) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>