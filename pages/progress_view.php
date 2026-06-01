<?php
include_once __DIR__ . '/../db.php';

$filterSector = $_GET['f_sector'] ?? 'All';
$filterOrg    = $_GET['f_org'] ?? 'All';
$filterDiv    = $_GET['f_div'] ?? 'All';
$viewMode     = $_GET['v_mode'] ?? 'progress';

function pv_safe_num($value): float {
    if (is_numeric($value)) return (float)$value;
    $value = trim((string)($value ?? ''));
    if ($value === '') return 0.0;
    $value = str_replace([',', '%'], '', $value);
    if (preg_match('/-?\d+(?:\.\d+)?/', $value, $m)) {
        return (float)$m[0];
    }
    return 0.0;
}

function pv_arr_get($array, $keys, $default = '') {
    foreach ($keys as $key) {
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }
    }
    return $default;
}

// Updated to target the 2026 Q1 Target specifically
function pv_pick_alloc(array $project): float {
    return pv_safe_num($project['allocation_2026_revised'] ?? $project['allocation_2026_original'] ?? 0);
}

function pv_project_name(array $project): string {
    return trim((string)pv_arr_get($project, ['project_name', 'name'], 'Untitled Project'));
}

function pv_project_no(array $project): string {
    return trim((string)pv_arr_get($project, ['serial_no', 'no'], ''));
}

function pv_project_division(array $project): string {
    return trim((string)pv_arr_get($project, ['division'], ''));
}

function pv_project_location(array $project): string {
    return trim((string)pv_arr_get($project, ['location'], 'Not Specified'));
}

function pv_project_remarks(array $project): string {
    return trim((string)pv_arr_get($project, ['reasons_for_not_achieving_targets', 'remarks', 'group_remark'], 'No remarks provided.'));
}

// Updated: Targeting the specific Q1 Actual Physical Progress
function pv_project_q1_physical(array $project): float {
    $val = $project['phys_percent']['Q1'] ?? 0;
    return pv_safe_num($val);
}

// Updated: Targeting the specific Q1 Actual Financial Expenditure
function pv_project_q1_spent(array $project): float {
    $val = $project['actual_exp']['Q1'] ?? 0;
    return pv_safe_num($val);
}

// Updated: Targeting the specific Q1 Financial Target
function pv_project_q1_fin_target(array $project): float {
    $val = $project['q1_fin_target'] ?? 0;
    return pv_safe_num($val);
}

function pv_build_dataset(): array {
    global $conn;
    $dataset = [];

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
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $codeRaw = trim($row['_org_code'] ?: $row['_org_name'] ?? '');
            $code = strtoupper($codeRaw);
            if (strpos($code, 'CAASL') !== false) $code = 'CAASL';
            elseif (strpos($code, 'AASL') !== false) $code = 'AASL';
            elseif (strpos($code, 'SLPA') !== false) $code = 'SLPA';
            elseif (strpos($code, 'MSS') !== false || strpos($code, 'MERCHANT') !== false) $code = 'MSS';
            elseif (strpos($code, 'JCT') !== false) $code = 'JCT';
            elseif (strpos($code, 'CSC') !== false) $code = 'CSC';
            
            if ($code === '') continue;
            
            $cat = 'Ports';
            if (in_array($code, ['AASL', 'CAASL'])) $cat = 'Aviation';
            if ($code === 'JCT') $cat = 'JCT';
            
            if (!isset($dataset[$cat])) $dataset[$cat] = [];
            if (!isset($dataset[$cat][$code])) $dataset[$cat][$code] = [];
            
            $row['actual_exp'] = ['Q1' => $row['q1_fin_actual']];
            $row['phys_percent'] = [
                'Q1' => $row['q1_phys_actual'],
                'Q1_Target' => $row['q1_phys_target'],
                'Q1_Overall_Prog_Final' => $row['q1_overall_prog_final'],
                'Q1_Cum_Target' => $row['q1_cum_target'],
                'Q1_Cum_Prog' => $row['q1_cum_prog']
            ];
            
            $dataset[$cat][$code][] = $row;
        }
    }
    
    return $dataset;
}

function pv_build_query(array $extra = []): string {
    $base = [
        'page' => 'progress_view',
        'f_sector' => $_GET['f_sector'] ?? 'All',
        'f_org' => $_GET['f_org'] ?? 'All',
        'f_div' => $_GET['f_div'] ?? 'All',
        'v_mode' => $_GET['v_mode'] ?? 'progress',
    ];
    foreach ($extra as $k => $v) $base[$k] = $v;
    return 'index.php?' . http_build_query($base);
}

$dataset = pv_build_dataset();
$sectorMap = [];
$orgDivisionMap = [];

foreach ($dataset as $sectorName => $orgs) {
    $sectorMap[$sectorName] = [];
    foreach ($orgs as $orgName => $projects) {
        $sectorMap[$sectorName][] = $orgName;
        $orgDivisionMap[$orgName] = [];
        foreach ($projects as $project) {
            $div = pv_project_division($project);
            if ($div !== '') {
                $orgDivisionMap[$orgName][$div] = ($orgDivisionMap[$orgName][$div] ?? 0) + 1;
            }
        }
        ksort($orgDivisionMap[$orgName]);
    }
    sort($sectorMap[$sectorName]);
}
ksort($sectorMap);

if ($viewMode === 'progress') {
    $titles = ['Exemplary Performance', 'On-Track Progress', 'Critical Attention'];
    $subs = ['> 70% Q1 Target', '40% - 70% Q1 Target', '< 40% Q1 Target'];
} else {
    $titles = ['Efficient Spend', 'Moderate Variance', 'High Variance'];
    $subs = ['Spent ≤ Target', 'Var < 15%', 'Var > 15%'];
}

$col1 = []; $col2 = []; $col3 = [];

foreach ($dataset as $sectorName => $orgs) {
    if ($filterSector !== 'All' && $filterSector !== $sectorName) continue;
    foreach ($orgs as $orgName => $projects) {
        if ($filterOrg !== 'All' && $filterOrg !== $orgName) continue;
        foreach ($projects as $project) {
            $divName = pv_project_division($project);
            if ($filterDiv !== 'All' && $divName !== $filterDiv) continue;

            $q1PhysActual = pv_project_q1_physical($project);
            $q1PhysTarget = pv_safe_num($project['phys_percent']['Q1_Target'] ?? 1);
            if($q1PhysTarget == 0) $q1PhysTarget = 1;
            
            // Percentage of Q1 Target Achieved
            $physAchievement = round(($q1PhysActual / $q1PhysTarget) * 100, 1);

            $spent = pv_project_q1_spent($project);
            $finTarget = pv_project_q1_fin_target($project);
            
            // Financial Variance
            $finUsage = $finTarget > 0 ? round(($spent / $finTarget) * 100, 1) : 0.0;
            $variance = round($finUsage - $physAchievement, 1);

            $projectData = [
                'no' => pv_project_no($project),
                'name' => pv_project_name($project),
                'org' => $orgName,
                'div' => $divName,
                'progress' => $q1PhysActual,
                'achievement' => $physAchievement,
                'spent' => $spent,
                'fin_target' => $finTarget,
                'location' => pv_project_location($project),
                'remarks' => pv_project_remarks($project),
                'status' => $project['timeline_status'] ?? 'On Track'
            ];

            if ($viewMode === 'progress') {
                if ($physAchievement >= 70) $col1[] = $projectData;
                elseif ($physAchievement >= 40) $col2[] = $projectData;
                else $col3[] = $projectData;
            } else {
                if ($variance <= 0) $col1[] = $projectData;
                elseif ($variance < 15) $col2[] = $projectData;
                else $col3[] = $projectData;
            }
        }
    }
}
?>

<div id="pageLoader" class="loader-overlay">
    <div class="spinner"></div>
    <span class="loader-text">Analyzing Q1 Board...</span>
</div>

<style>
    :root {
        --dark-blue: #1a365d;
        --slate-blue: #2d3748;
        --accent-blue: #2b6cb0;
        --muted-blue: #4a5568;
        --bg-gray: #f4f7f9;
    }
    .master-layout { display:flex; gap:20px; padding:25px; min-height:95vh; background:var(--bg-gray); font-family:'Segoe UI',sans-serif; align-items:flex-start; }
    .sidebar-nav { width:300px; position:sticky; top:20px; flex-shrink:0; max-height:calc(100vh - 40px); overflow-y:auto; }
    .side-card { background:white; border-radius:12px; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
    .side-title { font-size:11px; font-weight:800; color:var(--dark-blue); text-transform:uppercase; margin-bottom:10px; display:block; border-bottom:2px solid #edf2f7; padding-bottom:5px; }
    .method-toggle { display:flex; background:#edf2f7; padding:4px; border-radius:8px; margin-bottom:15px; }
    .method-btn { flex:1; text-align:center; padding:8px; font-size:11px; font-weight:800; text-decoration:none; color:var(--muted-blue); border-radius:6px; }
    .method-btn.active { background:white; color:var(--dark-blue); box-shadow:0 2px 5px rgba(0,0,0,0.1); }
    .nav-item { display:block; padding:10px 15px; margin-bottom:4px; border-radius:8px; text-decoration:none; color:var(--slate-blue); font-size:12.5px; font-weight:700; border:1px solid transparent; transition:0.2s; }
    .nav-item:hover { background:#f0f4f8; color:var(--dark-blue); }
    .nav-item.active { background:var(--dark-blue); color:white; }
    .division-list { margin-left:15px; padding-left:10px; border-left:2px dashed #cbd5e0; margin-bottom:10px; }
    .div-item { display:block; padding:6px 10px; font-size:11px; text-decoration:none; color:var(--muted-blue); font-weight:600; border-radius:4px; }
    .div-item:hover { background:#e2e8f0; color:var(--dark-blue); }
    .div-item.active { background:#cbd5e0; color:var(--dark-blue); font-weight:800; }
    .board-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; flex-grow:1; width: 100%; }
    .column-box { background:#ebf1f5; border-radius:15px; padding:15px; min-height:600px; }
    .p-card { background:white; border-radius:12px; padding:15px; margin-bottom:15px; border-left:6px solid #ccc; cursor:pointer; transition:0.2s; box-shadow:0 2px 5px rgba(0,0,0,0.03); display:flex; gap:12px; border: 1px solid #e2e8f0; }
    .p-card:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.1); }
    .p-num { background:var(--dark-blue); color:white; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; flex-shrink:0; }
    .p-name { font-size:13px; font-weight:700; color:#1a202c; line-height:1.4; display:block; margin-bottom:10px; }
    .p-org-tag { display:block; font-size:10px; font-weight:800; color:var(--accent-blue); text-transform:uppercase; margin-bottom:4px; }
    .loader-overlay { display:none; position:fixed; inset:0; background:rgba(255,255,255,0.85); backdrop-filter:blur(3px); z-index:9999; flex-direction:column; justify-content:center; align-items:center; }
    .spinner { width:45px; height:45px; border:5px solid #f3f3f3; border-top:5px solid var(--dark-blue); border-radius:50%; animation:spin 0.8s linear infinite; }
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:1000; backdrop-filter:blur(2px); }
    .modal-content { position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:white; width:500px; border-radius:15px; padding:30px; }
    .m-label { width:120px; font-weight:800; color:var(--muted-blue); font-size:11px; text-transform:uppercase; }
    .m-val { flex:1; font-size:14px; color:var(--dark-blue); font-weight:700; }
    @keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }
</style>

<div class="master-layout">
    <div class="sidebar-nav">
        <div class="side-card">
            <span class="side-title">Analysis Mode</span>
            <div class="method-toggle">
                <a href="<?= pv_build_query(['v_mode' => 'progress']) ?>" class="method-btn <?= $viewMode === 'progress' ? 'active' : '' ?>">Q1 Achievement</a>
                <a href="<?= pv_build_query(['v_mode' => 'variance']) ?>" class="method-btn <?= $viewMode === 'variance' ? 'active' : '' ?>">Financial Variance</a>
            </div>

            <span class="side-title">Sector Filter</span>
            <a href="<?= pv_build_query(['f_sector' => 'All', 'f_org' => 'All', 'f_div' => 'All']) ?>" class="nav-item <?= $filterSector === 'All' ? 'active' : '' ?>">Global Dashboard</a>
            <?php foreach ($sectorMap as $sName => $orgs): ?>
                <a href="<?= pv_build_query(['f_sector' => $sName, 'f_org' => 'All', 'f_div' => 'All']) ?>" class="nav-item <?= $filterSector === $sName ? 'active' : '' ?>"><?= htmlspecialchars($sName) ?></a>
            <?php endforeach; ?>

            <span class="side-title" style="margin-top:20px">Institutions</span>
            <?php foreach ($sectorMap as $sName => $orgs): ?>
                <?php if ($filterSector === 'All' || $filterSector === $sName): ?>
                    <?php foreach ($orgs as $orgName): ?>
                        <a href="<?= pv_build_query(['f_sector' => $sName, 'f_org' => $orgName, 'f_div' => 'All']) ?>" class="nav-item <?= $filterOrg === $orgName ? 'active' : '' ?>"><?= htmlspecialchars($orgName) ?></a>
                        <?php if ($filterOrg === $orgName && !empty($orgDivisionMap[$orgName])): ?>
                            <div class="division-list">
                                <?php foreach ($orgDivisionMap[$orgName] as $divName => $count): ?>
                                    <a href="<?= pv_build_query(['f_sector' => $sName, 'f_org' => $orgName, 'f_div' => $divName]) ?>" class="div-item <?= $filterDiv === $divName ? 'active' : '' ?>"><?= htmlspecialchars($divName) ?> (<?= $count ?>)</a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="board-grid">
        <?php
        $sections = [
            ['t' => $titles[0], 's' => $subs[0], 'd' => $col1, 'c' => '#28a745'],
            ['t' => $titles[1], 's' => $subs[1], 'd' => $col2, 'c' => '#f6ad55'],
            ['t' => $titles[2], 's' => $subs[2], 'd' => $col3, 'c' => '#e53e3e'],
        ];
        foreach ($sections as $s): ?>
            <div class="column-box">
                <div style="text-align:center; margin-bottom:20px;">
                    <h3 style="margin:0; font-size:14px; color:var(--dark-blue); font-weight:800;"><?= $s['t'] ?></h3>
                    <span style="font-size:10px; font-weight:800; color:<?= $s['c'] ?>; background:white; padding:2px 8px; border-radius:10px; display:inline-block; margin-top:5px;"><?= $s['s'] ?> &bull; <?= count($s['d']) ?> Projects</span>
                </div>
                <?php foreach ($s['d'] as $idx => $p): ?>
                    <div class="p-card" style="border-left-color:<?= $s['c'] ?>" onclick='openModal(<?= json_encode($p) ?>)'>
                        <div class="p-num"><?= $idx + 1 ?></div>
                        <div style="flex:1">
                            <span class="p-org-tag"><?= $p['org'] ?></span>
                            <span class="p-name"><?= $p['name'] ?></span>
                            <div style="height:4px; background:#edf2f7; border-radius:4px; overflow:hidden;">
                                <div style="width:<?= min(100, $p['achievement']) ?>%; height:100%; background:<?= $s['c'] ?>"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:6px;">
                                <span style="font-size:10px; font-weight:800; color:var(--dark-blue)"><?= $p['progress'] ?> (Actual Q1)</span>
                                <span style="font-size:9px; font-weight:700; color:var(--muted-blue)"><?= $p['achievement'] ?>% of Target</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="projectModal" class="modal-overlay" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <h3 id="m_name" style="margin:0 0 20px 0; font-size:16px; color:var(--dark-blue); border-bottom:3px solid #f1f5f9; padding-bottom:12px;"></h3>
        <div style="display:flex; margin-bottom:10px;"><div class="m-label">Institution</div><div class="m-val" id="m_org"></div></div>
        <div style="display:flex; margin-bottom:10px;"><div class="m-label">Q1 Progress</div><div class="m-val" id="m_phys"></div></div>
        <div style="display:flex; margin-bottom:10px;"><div class="m-label">Q1 Spent</div><div class="m-val" id="m_spent"></div></div>
        <div style="display:flex; margin-bottom:20px;"><div class="m-label">Q1 Fin Target</div><div class="m-val" id="m_fin_target"></div></div>
        <div id="m_rem" style="background:#f8fafc; padding:15px; border-radius:10px; font-size:12px; line-height:1.6; font-style:italic;"></div>
        <button onclick="closeModal()" style="margin-top:25px; width:100%; padding:12px; background:var(--dark-blue); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:800;">CLOSE DETAILS</button>
    </div>
</div>

<script>
    function openModal(data) {
        document.getElementById('m_name').innerText = data.name;
        document.getElementById('m_org').innerText = data.org + (data.div ? ' (' + data.div + ')' : '');
        document.getElementById('m_phys').innerText = data.progress + '% (' + data.achievement + '% of Q1 Target)';
        document.getElementById('m_spent').innerText = 'Rs. ' + data.spent + ' Mn';
        document.getElementById('m_fin_target').innerText = 'Rs. ' + data.fin_target + ' Mn';
        document.getElementById('m_rem').innerText = data.remarks || 'No remarks provided.';
        document.getElementById('projectModal').style.display = 'block';
    }
    function closeModal() { document.getElementById('projectModal').style.display = 'none'; }
</script>