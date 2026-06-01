<?php
include_once __DIR__ . '/../db.php';

function e($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }

// 1. CAPTURE SIDEBAR PARAMETERS FROM URL
$selectedOrg = $_GET['org'] ?? 'JCT'; 
$selectedDiv = $_GET['division'] ?? 'all';

$sql = "SELECT p.*, i.code AS institution_code, i.institution_name, d.division_name AS division, c.category_name AS category,
       f.cum_fin_target AS q1_fin_target,
       f.actual_expenditure AS q1_actual_exp,
       qp.cumulative_quarterly_target AS q1_phys_target,
       qp.cumulative_quarterly_progress AS q1_phys_actual,
       cp.cumulative_overall_target AS q1_cum_target,
       cp.cumulative_overall_progress AS q1_cum_prog,
       cp.physical_progress_percentage AS q1_overall_prog_final
FROM projects p
LEFT JOIN institutions i ON p.institution_id = i.id
LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN financial_progress f ON p.id = f.project_id AND f.quarter = 'Q1'
LEFT JOIN quarterly_physical_progress qp ON p.id = qp.project_id AND qp.quarter = 'Q1'
LEFT JOIN cumulative_physical_status cp ON p.id = cp.project_id AND cp.quarter = 'Q1'
WHERE UPPER(TRIM(i.code)) = 'JCT' OR UPPER(TRIM(i.institution_name)) LIKE '%JCT%'";

$result = mysqli_query($conn, $sql);
$projects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['institution_code'] = 'JCT';
        $row['actual_exp'] = ['Q1' => $row['q1_actual_exp']];
        $row['phys_percent'] = [
            'Q1' => $row['q1_phys_actual'],
            'Q1_Target' => $row['q1_phys_target'],
            'Q1_Overall_Prog_Final' => $row['q1_overall_prog_final'],
            'Q1_Cum_Target' => $row['q1_cum_target']
        ];
        $projects[] = $row;
    }
}

$statuses = []; $divisions = [];
// We explicitly define the 3 JCT categories as requested
$categories = ['New', 'Continuous', 'Extension'];

foreach ($projects as $project) {
    $status = trim((string)($project['timeline_status'] ?? 'Unknown'));
    $div = trim((string)($project['division'] ?? ''));
    if ($status !== 'Unknown' && $status !== '') $statuses[$status] = true;
    if ($div !== '') $divisions[$div] = true;
}
$statuses = array_values(array_keys($statuses)); sort($statuses);
$divisions = array_values(array_keys($divisions)); sort($divisions);

$projectsJson = json_encode($projects, JSON_UNESCAPED_UNICODE);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --phys-blue: #0ea5e9;
        --fin-orange: #f59e0b;
        --bg: #f1f5f9;
        --primary-dark: #1e3a5f;
        --text-main: #0f172a;
        --jct-primary: #7c2d12; 
    }

    body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: var(--text-main); }

    .dashboard-header {
        background: linear-gradient(135deg, var(--jct-primary) 0%, #431407 100%); 
        color: white; padding: 20px 40px; border-radius: 20px; margin-bottom: 25px;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .dashboard-header h1 { margin: 0; font-size: 20px; font-weight: 900; letter-spacing: 1px; }

    .floating-filter-btn {
        position: fixed; bottom: 30px; right: 30px; z-index: 999;
        background: var(--jct-primary); color: white; border: none;
        padding: 15px 25px; border-radius: 50px; cursor: pointer; font-weight: 800;
        box-shadow: 0 10px 25px rgba(124, 45, 18, 0.4); display: flex; align-items: center; gap: 10px;
        transition: 0.2s;
    }

    .filter-drawer {
        position: fixed; top: 0; right: -400px; width: 350px; height: 100vh;
        background: #fff; z-index: 10001; box-shadow: -10px 0 40px rgba(0,0,0,0.15);
        transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column;
    }
    .filter-drawer.open { right: 0; }
    .drawer-content { flex: 1; overflow-y: auto; padding: 30px 25px; }
    .drawer-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); z-index: 10000;
        display: none; backdrop-filter: blur(4px);
    }
    .drawer-overlay.show { display: block; }

    .field-group { margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px; }
    .field-group label { font-size: 11px; font-weight: 900; color: var(--jct-primary); text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
    .select-input, .search-input { padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; font-size: 14px; outline: none; font-weight: 600; width: 100%; box-sizing: border-box;}

    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; padding-bottom: 100px; }

    .project-card {
        position: relative; background: #fff; border-radius: 24px; padding: 22px;
        border: 2px solid #e2e8f0; transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        display: flex; flex-direction: column; gap: 10px; overflow: hidden;
    }
    .project-card:hover { transform: translateY(-5px); border-color: var(--jct-primary); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }

    .inst-badge { position: absolute; top: 18px; right: 18px; padding: 5px 12px; border-radius: 8px; font-size: 10px; font-weight: 900; color: white; display: flex; align-items: center; gap: 5px; background: var(--jct-primary); }

    .st-pill { font-size: 10px; font-weight: 900; padding: 5px 12px; border-radius: 8px; text-transform: uppercase; width: fit-content; display: flex; align-items: center; gap: 6px; }
    .st-completed { background: #15803d; color: #fff; }
    .st-ontrack { background: #0369a1; color: #fff; }
    .st-delayed { background: #b91c1c; color: #fff; }

    .card-title { font-size: 17px; font-weight: 900; color: var(--jct-primary); margin: 5px 0; line-height: 1.3; padding-right: 75px; min-height: 44px; }
    
    .card-meta-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 5px; }
    .meta-tag { background: #fff7ed; padding: 4px 10px; border-radius: 6px; font-size: 9px; font-weight: 800; color: var(--jct-primary); border: 1px solid #ffedd5; display: flex; align-items: center; gap: 5px; }

    .main-progress-group { background: #f8fafc; padding: 12px; border-radius: 16px; display: flex; flex-direction: column; gap: 10px; border: 1px solid #f1f5f9; }
    
    .hidden-matrix {
        max-height: 0; opacity: 0; transition: all 0.4s ease;
        display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
        margin-top: 0; padding-top: 0; overflow: hidden;
    }
    .project-card:hover .hidden-matrix {
        max-height: 500px; opacity: 1; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1;
    }

    .matrix-item { background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; }
    .matrix-item .label-text { font-size: 9px; color: #64748b; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 5px; margin-bottom: 5px; }
    .matrix-item .main-val { font-size: 14px; font-weight: 900; color: var(--text-main); display: block; }
    .matrix-item .sub-label { font-size: 10px; color: #475569; font-weight: 600; display: flex; justify-content: space-between; margin-top: 4px; padding-top: 4px; border-top: 1px solid rgba(0,0,0,0.05); }
    
    .matrix-dark { background: var(--jct-primary) !important; color: white !important; }
    .matrix-dark .sub-label { color: #ffedd5 !important; border-top-color: rgba(255,255,255,0.1) !important;}

    .bar-group { width: 100%; }
    .bar-label { display: flex; justify-content: space-between; font-size: 10px; font-weight: 900; margin-bottom: 4px; align-items: center; color: var(--jct-primary); }
    .bar-bg { height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
    .bar-fill { height: 100%; transition: width 1.5s cubic-bezier(0.19, 1, 0.22, 1); }
</style>

<div class="dashboard-header">
    <h1><i class="fa-solid fa-boxes-stacked"></i> JCT PROJECTS</h1>
    <div style="font-size: 11px; font-weight: 900; color: #fbbf24;">
        <i class="fa-solid fa-calendar-check"></i> Q1 2026 | JCT <?= ($selectedDiv != 'all') ? " : ".urldecode($selectedDiv) : "" ?>
    </div>
</div>

<button class="floating-filter-btn" onclick="toggleDrawer()">
    <i class="fa-solid fa-magnifying-glass-chart"></i> OPEN FILTERS
</button>

<div class="drawer-overlay" id="overlay" onclick="toggleDrawer()"></div>

<nav class="filter-drawer" id="drawer">
    <div class="drawer-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h2 style="margin:0; font-size:20px; color: var(--jct-primary);"><i class="fa-solid fa-sliders"></i> Filter Menu</h2>
            <button onclick="toggleDrawer()" style="border:none; background:#fee2e2; color:#b91c1c; cursor:pointer; font-size:20px; width:35px; height:35px; border-radius:50%;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="field-group">
            <label><i class="fa-solid fa-magnifying-glass" style="color:var(--jct-primary);"></i> Search Project</label>
            <input type="text" id="searchBox" class="search-input" placeholder="Type name here...">
        </div>

        <div class="field-group">
            <label><i class="fa-solid fa-list-check" style="color:#10b981;"></i> Project Type</label>
            <select id="catFilter" class="select-input">
                <option value="all">All (New, Continuous, Extension)</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= e($c) ?>"><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-group">
            <label><i class="fa-solid fa-traffic-light" style="color:#ef4444;"></i> Status</label>
            <select id="statusFilter" class="select-input">
                <option value="all">All Statuses</option>
                <?php foreach($statuses as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        
        <button onclick="toggleDrawer()" style="width:100%; background:var(--jct-primary); color:white; border:none; padding:18px; border-radius:12px; font-weight:900; cursor:pointer; margin-top:10px; font-size:15px;">APPLY SELECTIONS</button>
    </div>
</nav>

<div class="cards-grid" id="mainGrid"></div>

<script>
const DATA = <?= $projectsJson ?>;
const filters = { 
    query: '', 
    status: 'all',
    cat: 'all'
};

function toggleDrawer() {
    document.getElementById('drawer').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

function getStatusIcon(status) {
    const s = status.toLowerCase();
    if (s.includes('completed')) return '<i class="fa-solid fa-check-double"></i>';
    if (s.includes('track')) return '<i class="fa-solid fa-gauge-high"></i>';
    if (s.includes('delayed')) return '<i class="fa-solid fa-triangle-exclamation"></i>';
    return '<i class="fa-solid fa-circle-nodes"></i>';
}

function render() {
    const filtered = DATA.filter(p => {
        const pName = p.project_name || '';
        const pStat = p.timeline_status || 'Unknown';
        const pCat = p.project_type || 'New';
        
        const matchSearch = pName.toLowerCase().includes(filters.query.toLowerCase());
        const matchStat = filters.status === 'all' || pStat === filters.status;
        const matchCat = filters.cat === 'all' || pCat === filters.cat;
        
        return matchSearch && matchStat && matchCat;
    });

    document.getElementById('mainGrid').innerHTML = filtered.length > 0 ? filtered.map(p => {
        const phys = p.phys_percent || {};
        const pStat = p.timeline_status || 'Unknown';
        const pCat = p.project_type || 'New';
        const pDiv = p.division || 'General';
        const pLoc = p.location || 'N/A';
        const q1Prog = (phys.Q1 || '0').toString().replace('%', '');
        const q1Tgt = (phys.Q1_Target || '0').toString().replace('%', '');
        const finTgt = (p.q1_fin_target || '0').toString().replace('%', '');
        const finAct = (p.actual_exp && p.actual_exp.Q1) ? p.actual_exp.Q1.toString().replace('%', '') : '0';
        
        const targetMet = q1Tgt !== '0' ? ((parseFloat(q1Prog)/parseFloat(q1Tgt))*100).toFixed(0) : '0';
        const finTgtNum = parseFloat(finTgt.toString().replace(/,/g, '')) || 1;
        const finActNum = parseFloat(finAct.toString().replace(/,/g, '')) || 0;
        const finPerc = Math.min(100, (finActNum / finTgtNum) * 100);

        return `
            <div class="project-card">
                <div class="inst-badge"><i class="fa-solid fa-boxes-stacked"></i> JCT</div>
                <div class="st-pill ${pStat.toLowerCase().includes('delayed') ? 'st-delayed' : (pStat.toLowerCase().includes('completed') ? 'st-completed' : 'st-ontrack')}">
                    ${getStatusIcon(pStat)} ${pStat}
                </div>

                <h3 class="card-title">${p.project_name || 'Untitled'}</h3>

                <div class="card-meta-row">
                    <div class="meta-tag"><i class="fa-solid fa-location-dot"></i> <b>Location:</b> ${pLoc}</div>
                    <div class="meta-tag"><i class="fa-solid fa-sitemap"></i> <b>Div:</b> ${pDiv}</div>
                    <div class="meta-tag"><i class="fa-solid fa-tags"></i> <b>Type:</b> ${pCat}</div>
                </div>

                <div class="main-progress-group">
                    <div class="bar-group">
                        <div class="bar-label">
                            <span><i class="fa-solid fa-helmet-safety" style="color:var(--phys-blue);"></i> <b>Physical Progress</b></span>
                            <span>${q1Prog}%</span>
                        </div>
                        <div class="bar-bg"><div class="bar-fill" style="width: ${parseFloat(q1Prog)}%; background:var(--phys-blue);"></div></div>
                    </div>
                    <div class="bar-group">
                        <div class="bar-label">
                            <span><i class="fa-solid fa-money-bill-trend-up" style="color:var(--fin-orange);"></i> <b>Financial Progress</b></span>
                            <span>${finPerc.toFixed(1)}%</span>
                        </div>
                        <div class="bar-bg"><div class="bar-fill" style="width: ${finPerc}%; background:var(--fin-orange);"></div></div>
                    </div>
                </div>

                <div class="hidden-matrix">
                    <div class="matrix-item">
                        <span class="label-text"><i class="fa-solid fa-chart-line"></i> Q1 Physical Status</span>
                        <span class="main-val" style="color:var(--phys-blue);">${q1Prog}% <small style="color:#64748b; font-size:10px;">Actual</small></span>
                        <div class="sub-label"><span>Target:</span> <span>${q1Tgt}%</span></div>
                        <div class="sub-label"><span>Efficiency:</span> <span>${targetMet}%</span></div>
                    </div>
                    <div class="matrix-item">
                        <span class="label-text"><i class="fa-solid fa-coins"></i> Q1 Expenditure</span>
                        <span class="main-val" style="color:var(--fin-orange);">Rs. ${finAct}M</span>
                        <div class="sub-label"><span>Q1 Target:</span> <span>Rs. ${finTgt}M</span></div>
                    </div>
                    <div class="matrix-item">
                        <span class="label-text"><i class="fa-solid fa-vault"></i> Annual Budget</span>
                        <span class="main-val">Rs. ${p.allocation_2026_revised || p.allocation_2026_original}M</span>
                        <div class="sub-label"><span>Original:</span> <span>${p.allocation_2026_original}M</span></div>
                    </div>
                    <div class="matrix-item matrix-dark">
                        <span class="label-text" style="color:#ffedd5"><i class="fa-solid fa-trophy"></i> Achievement Score</span>
                        <span class="main-val" style="color:white;">${(phys.Q1_Overall_Prog_Final || '0').toString().replace('%', '')}%</span>
                        <div class="sub-label"><span>Cumulative Tgt:</span> <span>${(phys.Q1_Cum_Target || '0').toString().replace('%', '')}%</span></div>
                    </div>
                </div>
            </div>
        `;
    }).join('') : '<div style="grid-column:1/-1; text-align:center; padding:50px; opacity:0.5;">No projects found for JCT.</div>';
}

document.getElementById('searchBox').oninput = e => { filters.query = e.target.value; render(); };
document.getElementById('statusFilter').onchange = e => { filters.status = e.target.value; render(); };
document.getElementById('catFilter').onchange = e => { filters.cat = e.target.value; render(); };

render();
</script>