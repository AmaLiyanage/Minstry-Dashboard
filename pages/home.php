<?php
include_once __DIR__ . '/auth.php';
include_once __DIR__ . '/../db.php';

$filterSector = $_GET['sector'] ?? 'all';

// Restrict the "Global Dashboard" view to admins only, but allow specific sector views
if ($filterSector === 'all' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    echo "<div style='padding: 60px; text-align: center; font-family: \"Inter\", sans-serif;'>
        <i class='fa-solid fa-shield-halved' style='font-size: 48px; color: #ef4444; margin-bottom: 20px;'></i>
        <h2 style='color: #0f172a; margin-bottom: 10px;'>Access Denied</h2>
        <p style='color: #64748b;'>You must be an administrator to view the Global Dashboard.</p>
    </div>";
    exit;
}

// --- 1. DATA EXTRACTION FROM DB ---
$sql = "SELECT p.*, i.code AS institution_code, i.institution_name, d.division_name AS division 
        FROM projects p 
        LEFT JOIN institutions i ON p.institution_id = i.id
        LEFT JOIN divisions d ON p.division_id = d.id";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $u_div = mysqli_real_escape_string($conn, $_SESSION['division_name'] ?? '');
    $sql .= " WHERE d.division_name = '$u_div'";
}

$result = mysqli_query($conn, $sql);
$allProjects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $allProjects[] = $row;
    }
}

$caaslProjects = [];
$aaslProjects = [];
$slpaProjects = [];
$mssProjects = [];
$jctProjects = [];

foreach ($allProjects as $p) {
    $inst = strtoupper(trim($p['institution_code'] ?: $p['institution_name'] ?? ''));
    if (strpos($inst, 'CAASL') !== false) $caaslProjects[] = $p;
    elseif (strpos($inst, 'AASL') !== false) $aaslProjects[] = $p;
    elseif (strpos($inst, 'SLPA') !== false) $slpaProjects[] = $p;
    elseif (strpos($inst, 'MSS') !== false || strpos($inst, 'MERCHANT') !== false) $mssProjects[] = $p;
    elseif (strpos($inst, 'JCT') !== false) $jctProjects[] = $p;
}

$totalSlpa  = count($slpaProjects);
$totalMss   = count($mssProjects);
$totalAasl  = count($aaslProjects);
$totalCaasl = count($caaslProjects);
$totalJct   = count($jctProjects);

function normalize_division_key(?string $value): string {
    return strtoupper(trim((string)$value));
}

// --- 2. PORT GROUPING (Using Aviation-Style Manual Counters) ---
$slpaDivCounts = [
    'CD' => 0, 'CIVIL' => 0, 'DEV' => 0, 'EE' => 0, 'IS' => 0, 
    'LOG' => 0, 'MECH' => 0, 'NAV' => 0, 'PD' => 0, 'SEC' => 0
];

foreach ($slpaProjects as $p) {
    $divRaw = normalize_division_key($p['division'] ?? '');
    
    if ($divRaw === 'C & D' || $divRaw === 'C&D' || strpos($divRaw, 'C & D') !== false) $slpaDivCounts['CD']++;
    elseif (strpos($divRaw, 'CIVIL') !== false) $slpaDivCounts['CIVIL']++;
    elseif (strpos($divRaw, 'DEVELOPMENT') !== false && strpos($divRaw, 'PLANNING') === false) $slpaDivCounts['DEV']++;
    elseif (strpos($divRaw, 'ELECTRICAL') !== false || $divRaw === 'EE') $slpaDivCounts['EE']++;
    elseif ($divRaw === 'IS' || strpos($divRaw, 'INFORMATION') !== false) $slpaDivCounts['IS']++;
    elseif (strpos($divRaw, 'LOGISTIC') !== false) $slpaDivCounts['LOG']++;
    elseif (strpos($divRaw, 'MECHANICAL') !== false || strpos($divRaw, 'MECH') !== false) $slpaDivCounts['MECH']++;
    elseif (strpos($divRaw, 'NAVIGATION') !== false) $slpaDivCounts['NAV']++;
    elseif (strpos($divRaw, 'PLANNING') !== false || $divRaw === 'P & D' || $divRaw === 'P&D') $slpaDivCounts['PD']++;
    elseif (strpos($divRaw, 'SECURITY') !== false || strpos($divRaw, 'SEC') !== false) $slpaDivCounts['SEC']++;
}

// Manual Display Array for SLPA (Matching Sidebar/Navbar labels)
$slpaDisplayList = [
    ['label' => 'C & D', 'query' => 'C & D', 'count' => $slpaDivCounts['CD']],
    ['label' => 'Civil Engineering', 'query' => 'Civil Engineering', 'count' => $slpaDivCounts['CIVIL']],
    ['label' => 'Development', 'query' => 'Development', 'count' => $slpaDivCounts['DEV']],
    ['label' => 'Electrical & Electronic', 'query' => 'Electrical & Electronic', 'count' => $slpaDivCounts['EE']],
    ['label' => 'IS', 'query' => 'IS', 'count' => $slpaDivCounts['IS']],
    ['label' => 'Logistics', 'query' => 'Logistics', 'count' => $slpaDivCounts['LOG']],
    ['label' => 'Mechanical', 'query' => 'Mechanical', 'count' => $slpaDivCounts['MECH']],
    ['label' => 'Navigation', 'query' => 'Navigation', 'count' => $slpaDivCounts['NAV']],
    ['label' => 'Planning & Development', 'query' => 'Planning & Development', 'count' => $slpaDivCounts['PD']],
    ['label' => 'Security', 'query' => 'Security', 'count' => $slpaDivCounts['SEC']],
];

// MSS Divisions
$mssDivCounts = [];
foreach ($mssProjects as $p) {
    $div = trim($p['division'] ?? 'General');
    if ($div === '') continue;
    $mssDivCounts[$div] = ($mssDivCounts[$div] ?? 0) + 1;
}

// --- 3. AVIATION GROUPING (Strictly Untouched) ---
$aaslDivCounts = [
    'ALID' => 0, 'AM' => 0, 'CE_PD' => 0, 'CE_PROJ' => 0, 'CE_MAINT' => 0,
    'EANE' => 0, 'EE' => 0, 'HR' => 0, 'IT' => 0, 'MECH' => 0, 
    'MEHE' => 0, 'PROJ' => 0, 'SFRS' => 0, 'SLAA' => 0
];

foreach ($aaslProjects as $p) {
    $divRaw = normalize_division_key($p['division'] ?? '');
    if (strpos($divRaw, 'AL&ID') !== false) $aaslDivCounts['ALID']++;
    elseif (strpos($divRaw, 'AM') !== false) $aaslDivCounts['AM']++;
    elseif (strpos($divRaw, 'CE (P&D)') !== false) $aaslDivCounts['CE_PD']++;
    elseif (strpos($divRaw, 'CE (PROJECT)') !== false) $aaslDivCounts['CE_PROJ']++;
    elseif (strpos($divRaw, 'CE(MAINTENANCE)') !== false) $aaslDivCounts['CE_MAINT']++;
    elseif (strpos($divRaw, 'E&ANE') !== false) $aaslDivCounts['EANE']++;
    elseif ($divRaw === 'EE') $aaslDivCounts['EE']++;
    elseif ($divRaw === 'HR') $aaslDivCounts['HR']++;
    elseif ($divRaw === 'IT') $aaslDivCounts['IT']++;
    elseif ($divRaw === 'MECH') $aaslDivCounts['MECH']++;
    elseif ($divRaw === 'MEHE') $aaslDivCounts['MEHE']++;
    elseif ($divRaw === 'PROJECT') $aaslDivCounts['PROJ']++;
    elseif (strpos($divRaw, 'S&FRS') !== false) $aaslDivCounts['SFRS']++;
    elseif ($divRaw === 'SLAA') $aaslDivCounts['SLAA']++;
}

$aaslDisplay = [
    ['label' => 'AL&ID', 'query' => 'AL&ID', 'count' => $aaslDivCounts['ALID']],
    ['label' => 'AM', 'query' => 'AM', 'count' => $aaslDivCounts['AM']],
    ['label' => 'CE (P&D)', 'query' => 'CE (P&D)', 'count' => $aaslDivCounts['CE_PD']],
    ['label' => 'CE (Project)', 'query' => 'CE (Project)', 'count' => $aaslDivCounts['CE_PROJ']],
    ['label' => 'CE(Maintenance)', 'query' => 'CE(Maintenance)', 'count' => $aaslDivCounts['CE_MAINT']],
    ['label' => 'E&ANE', 'query' => 'E&ANE', 'count' => $aaslDivCounts['EANE']],
    ['label' => 'EE', 'query' => 'EE', 'count' => $aaslDivCounts['EE']],
    ['label' => 'HR', 'query' => 'HR', 'count' => $aaslDivCounts['HR']],
    ['label' => 'IT', 'query' => 'IT', 'count' => $aaslDivCounts['IT']],
    ['label' => 'MECH', 'query' => 'MECH', 'count' => $aaslDivCounts['MECH']],
    ['label' => 'MEHE', 'query' => 'MEHE', 'count' => $aaslDivCounts['MEHE']],
    ['label' => 'Project', 'query' => 'Project', 'count' => $aaslDivCounts['PROJ']],
    ['label' => 'S&FRS', 'query' => 'S&FRS', 'count' => $aaslDivCounts['SFRS']],
    ['label' => 'SLAA', 'query' => 'SLAA', 'count' => $aaslDivCounts['SLAA']],
];

$caaslDivCounts = [];
foreach ($caaslProjects as $p) {
    $div = trim($p['division'] ?? 'Other');
    if ($div === '') continue;
    $caaslDivCounts[$div] = ($caaslDivCounts[$div] ?? 0) + 1;
}
?>

<style>
    :root {
        --ports: #1e3a5f;
        --aviation: #28a745;
        --ministry: #f6ad55;
        --text: #2d3748;
        --sky: #87ceeb;
        --ship-cabin: #94a3b8;
        --ship-hull: #1a202c;
    }

    .dashboard-stage {
        position: relative;
        width: 100%;
        min-height: calc(100vh - 110px);
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: center;
        background: linear-gradient(to bottom, var(--sky) 0%, #ffffff 90%);
        padding: 40px 0 140px 0;
        box-sizing: border-box;
        overflow-x: hidden;
    }

    .sky-zone {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 180px;
        pointer-events: none;
        z-index: 1;
    }

    .sun-glow {
        position: absolute;
        top: -40px;
        right: -40px;
        width: 250px;
        height: 250px;
        background: radial-gradient(circle, rgba(255,255,255,0.9) 0%, rgba(255,252,200,0.2) 60%, transparent 80%);
        filter: blur(15px);
    }

    .cloud-group { position: absolute; animation: drift linear infinite; opacity: 0.8; }
    .cloud-part { background: white; border-radius: 50%; position: absolute; }
    .cloud-base { background: white; border-radius: 100px; }

    .c1 { top: 65px; left: -200px; animation-duration: 55s; }
    .c1 .p1 { width: 65px; height: 65px; top: -30px; left: 20px; }
    .c1 .p2 { width: 55px; height: 55px; top: -25px; left: 65px; }

    .c2 { top: 40px; left: -150px; animation-duration: 40s; animation-delay: -8s; }
    .c2 .p1 { width: 50px; height: 50px; top: -25px; left: 20px; }
    .c2 .p2 { width: 45px; height: 45px; top: -20px; left: 55px; }

    .plane-wrapper {
        position: absolute;
        top: 25px;
        width: 90px;
        height: 28px;
        animation: plane-sky-fly 22s linear infinite, fly-float 3s ease-in-out infinite;
        z-index: 2;
    }

    .plane-body {
        position: absolute;
        width: 90px; height: 20px;
        background: #cbd5e0;
        border-radius: 25px 60px 15px 25px;
        border-bottom: 5px solid #4a90e2;
    }

    .plane-windows { position: absolute; top: 5px; left: 15px; display: flex; gap: 3px; }
    .window { width: 4px; height: 4px; background: #1e3a5f; border-radius: 1px; }
    .cockpit { position: absolute; top: 3px; right: 6px; width: 16px; height: 10px; background: #1a202c; border-radius: 1px 10px 1px 1px; }
    .wing-top { position: absolute; top: -8px; left: 30px; width: 15px; height: 25px; background: #a0aec0; transform: skewX(-30deg); z-index: -1; }
    .wing-bottom { position: absolute; bottom: -7px; left: 30px; width: 20px; height: 25px; background: #a0aec0; transform: skewX(30deg); }
    .tail { position: absolute; top: -10px; left: 0; width: 12px; height: 22px; background: #cbd5e0; transform: skewX(-20deg); }

    @keyframes drift { from { left: -250px; } to { left: 110%; } }
    @keyframes plane-sky-fly { from { left: -150px; } to { left: 110%; } }
    @keyframes fly-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

    /* =========================================================
       DECREASED GRID PROFILE ARCHITECTURE
    ============================================================ */
    .dashboard-content { 
        position: relative; 
        z-index: 10; 
        width: 96%; 
        max-width: 1140px; /* Reduced outer grid window boundary constraint */
        display: flex; 
        flex-direction: column; 
        gap: 28px; /* Tighter layout rows spacing */
        margin: 0 auto;
    }

    .sector-block {
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-radius: 18px; /* Slightly tighter border arcs */
        padding: 24px; /* Decreased internal canvas clearance space padding */
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.6);
        transition: transform 0.25s ease;
    }

    .sector-block:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }

    .sector-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
    }
    .sector-header i { font-size: 28px; } /* Scaled down slightly */
    .sector-header h2 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.02em; }

    .inst-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Decreased item box width index limits */
        gap: 18px; /* Tighter grid intersections */
    }

    .inst-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 16px 18px; /* Tighter item box clearance tracking layout padding */
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.01);
        transition: all 0.2s ease;
    }
    
    .inst-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
        transform: translateY(-2px);
    }

    .inst-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
    }
    
    .inst-title {
        font-size: 15px; /* Decreased text configuration */
        font-weight: 800;
        color: #0f172a;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.15s ease;
    }
    .inst-title :hover { color: #2563eb; }

    .badge { color: white; padding: 2px 8px; border-radius: 8px; font-size: 10.5px; font-weight: 800; }
    .badge-ports { background: var(--ports); }
    .badge-aviation { background: var(--aviation); }

    .div-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px; /* Reduced gap index space constraints */
    }
    
    .div-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px; /* Reduced chip parameters thickness */
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px; /* Tighter configuration text size values */
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .div-chip:hover {
        background: #ffffff;
        color: #0f172a;
        border-color: #94a3b8;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    
    .div-count {
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 800;
    }
    
    /* Sector-specific division count badge colors */
    .count-ports { background: #e0f2fe; color: #0284c7; }
    .div-chip:hover .count-ports { background: #bae6fd; color: #0369a1; }
    
    .count-aviation { background: #dcfce7; color: #16a34a; }
    .div-chip:hover .count-aviation { background: #bbf7d0; color: #15803d; }
    /* ========================================================= */

    .ship-container {
        position: absolute; bottom: 35px; right: 10%; width: 280px; height: 140px; z-index: 20;
        animation: ship-ride 6s ease-in-out infinite;
        filter: drop-shadow(0 10px 15px rgba(0,0,0,0.2));
    }
    .ship-hull { position: absolute; bottom: 0; width: 280px; height: 55px; background: var(--ship-hull); clip-path: polygon(0 0, 100% 0, 85% 100%, 15% 100%); border-bottom: 8px solid #c53030; }

    .ship-cabin {
        position: absolute;
        bottom: 55px;
        left: 45px;
        width: 170px;
        height: 38px;
        background: var(--ship-cabin);
        border-radius: 4px 22px 0 0;
        background-image: repeating-linear-gradient(to right, transparent, transparent 6px, #000000 6px, #000000 14px);
        background-size: 100% 10px;
        background-repeat: no-repeat;
        background-position: 0 14px;
    }

    .funnel { position: absolute; bottom: 93px; width: 24px; height: 35px; background: #e53e3e; border-top: 8px solid #1e293b; }
    .f1 { left: 70px; } .f2 { left: 110px; }
    @keyframes ship-ride { 0%, 100% { transform: translateY(0) rotate(-1deg); } 50% { transform: translateY(-10px) rotate(1.5deg); } }

    .sea-zone { position: absolute; bottom: 0; left: 0; width: 100%; height: 60px; background: #2b6cb0; z-index: 25; }
    .wave { position: absolute; top: -30px; width: 200%; height: 40px; background-repeat: repeat-x; animation: wave-move 10s linear infinite; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%232b6cb0" d="M0,192L48,181.3C96,171,192,149,288,160C384,171,480,213,576,213.3C672,213,768,171,864,138.7C960,107,1056,85,1152,106.7C1248,128,1344,192,1392,224L1440,256V320H0Z"></path></svg>'); }
    @keyframes wave-move { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    @media (max-width: 1150px) {
        .inst-grid { grid-template-columns: 1fr; }
        .ship-container { display: none; }
    }
</style>

<div class="dashboard-stage">
    <?php if ($filterSector === 'all' || $filterSector === 'aviation'): ?>
    <div class="sky-zone">
        <div class="sun-glow"></div>
        <div class="cloud-group c1"><div class="cloud-part p1"></div><div class="cloud-part p2"></div><div class="cloud-base" style="width: 120px; height: 35px;"></div></div>
        <div class="cloud-group c2"><div class="cloud-part p1"></div><div class="cloud-part p2"></div><div class="cloud-base" style="width: 100px; height: 30px;"></div></div>

        <div class="plane-wrapper">
            <div class="tail"></div><div class="wing-top"></div>
            <div class="plane-body">
                <div class="plane-windows"><div class="window"></div><div class="window"></div><div class="window"></div></div>
                <div class="cockpit"></div>
            </div>
            <div class="wing-bottom"></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($filterSector === 'all' || $filterSector === 'ports'): ?>
    <div class="ship-container">
        <div class="funnel f1"></div><div class="funnel f2"></div>
        <div class="ship-cabin"></div><div class="ship-hull"></div>
    </div>
    <?php endif; ?>

    <div class="dashboard-content">
        <?php if ($filterSector !== 'all'): ?>
            <div style="text-align: center; margin-bottom: 12px;">
                <a href="index.php?page=welcome" style="display: inline-block; padding: 8px 20px; background: #ffffff; color: #1e3a5f; font-weight: 800; border-radius: 50px; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); font-size: 13px; transition: transform 0.2s;"><i class="fa-solid fa-arrow-left"></i> Back to Welcome Portal</a>
            </div>
        <?php endif; ?>
            
            <?php if ($filterSector === 'all' || $filterSector === 'ports'): ?>
            <div class="sector-block" style="border-top: 5px solid var(--ports);">
                <div class="sector-header">
                    <i class="fa fa-ship" style="color:var(--ports)"></i>
                    <h2 style="color:var(--ports)">Maritime & Ports</h2>
                </div>
                
                <div class="inst-grid">
                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=summary&org=SLPA&division=all" class="inst-title"><i class="fa-solid fa-building"></i> SLPA</a>
                            <span class="badge badge-ports"><?= $totalSlpa ?></span>
                        </div>
                        <div class="div-chips">
                            <?php foreach ($slpaDisplayList as $div): if($div['count'] > 0): ?>
                                <a href="index.php?page=division_view&org=SLPA&division=<?= urlencode($div['query']) ?>" class="div-chip">
                                    <?= htmlspecialchars($div['label']) ?> <span class="div-count count-ports"><?= (int)$div['count'] ?></span>
                                </a>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>

                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=mss&org=MSS&division=all" class="inst-title"><i class="fa-solid fa-building"></i> Merchant Shipping (MSS)</a>
                            <span class="badge badge-ports"><?= $totalMss ?></span>
                        </div>
                        <div class="div-chips">
                            <?php foreach ($mssDivCounts as $name => $count): ?>
                                <a href="index.php?page=division_view&org=MSS&division=<?= urlencode($name) ?>" class="div-chip">
                                    <?= htmlspecialchars($name) ?> <span class="div-count count-ports"><?= $count ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=jct&org=JCT&division=all" class="inst-title"><i class="fa-solid fa-boxes-stacked"></i> JCT Terminal</a>
                            <span class="badge badge-ports"><?= $totalJct ?></span>
                        </div>
                    </div>

                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=summary&org=CSC&division=all" class="inst-title"><i class="fa-solid fa-building"></i> Ceylon Shipping Corp (CSC)</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($filterSector === 'all' || $filterSector === 'aviation'): ?>
            <div class="sector-block" style="border-top: 5px solid var(--aviation);">
                <div class="sector-header">
                    <i class="fa fa-plane" style="color:var(--aviation)"></i>
                    <h2 style="color:var(--aviation)">Aviation Sector</h2>
                </div>
                
                <div class="inst-grid">
                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=summary&org=AASL&division=all" class="inst-title" style="color:var(--aviation)"><i class="fa-solid fa-plane-departure"></i> AASL</a>
                            <span class="badge badge-aviation"><?= $totalAasl ?></span>
                        </div>
                        <div class="div-chips">
                            <?php foreach ($aaslDisplay as $div): if($div['count'] > 0): ?>
                                <a href="index.php?page=division_view&org=AASL&division=<?= urlencode($div['query']) ?>" class="div-chip">
                                    <?= htmlspecialchars($div['label']) ?> <span class="div-count count-aviation"><?= (int)$div['count'] ?></span>
                                </a>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>

                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=summary&org=CAASL&division=all" class="inst-title" style="color:var(--aviation)"><i class="fa-solid fa-plane-arrival"></i> CAASL</a>
                            <span class="badge badge-aviation"><?= $totalCaasl ?></span>
                        </div>
                        <div class="div-chips">
                            <?php foreach ($caaslDivCounts as $name => $count): ?>
                                <a href="index.php?page=division_view&org=CAASL&division=<?= urlencode($name) ?>" class="div-chip">
                                    <?= htmlspecialchars($name) ?> <span class="div-count count-aviation"><?= $count ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($filterSector === 'all'): ?>
            <div class="sector-block" style="border-top: 5px solid var(--ministry);">
                <div class="sector-header">
                    <i class="fa fa-landmark" style="color:var(--ministry)"></i>
                    <h2 style="color:var(--ministry)">Ministry Overview</h2>
                </div>
                <div class="inst-grid">
                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=summary&org=all" class="inst-title" style="color:var(--ministry)"><i class="fa-solid fa-earth-americas"></i> Global Dashboard</a>
                        </div>
                    </div>
                    <div class="inst-card">
                        <div class="inst-header">
                            <a href="index.php?page=reports" class="inst-title" style="color:var(--ministry)"><i class="fa-solid fa-file-contract"></i> Action Plan Reports</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
    </div>

    <?php if ($filterSector === 'all' || $filterSector === 'ports'): ?>
    <div class="sea-zone"><div class="wave"></div></div>
    <?php endif; ?>
</div>