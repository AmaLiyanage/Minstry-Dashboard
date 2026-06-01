<?php
include_once __DIR__ . '/../db.php';

// --- 1. DATA EXTRACTION FROM DB ---
$sql = "SELECT p.*, i.code AS institution_code, i.institution_name, d.division_name AS division 
        FROM projects p 
        LEFT JOIN institutions i ON p.institution_id = i.id
        LEFT JOIN divisions d ON p.division_id = d.id";
$result = mysqli_query($conn, $sql);
$allProjects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $allProjects[] = $row;
    }
}

$aaslProjects = [];
$caaslProjects = [];
$slpaProjects = [];
$mssProjects = [];

foreach ($allProjects as $p) {
    $inst = strtoupper(trim($p['institution_code'] ?: $p['institution_name'] ?? ''));
    if (strpos($inst, 'CAASL') !== false) $caaslProjects[] = $p;
    elseif (strpos($inst, 'AASL') !== false) $aaslProjects[] = $p;
    elseif (strpos($inst, 'SLPA') !== false) $slpaProjects[] = $p;
    elseif (strpos($inst, 'MSS') !== false || strpos($inst, 'MERCHANT') !== false) $mssProjects[] = $p;
}

$totalSlpa  = count($slpaProjects);
$totalMss   = count($mssProjects);
$totalAasl  = count($aaslProjects);
$totalCaasl = count($caaslProjects);

function normalize_division_key(?string $value): string {
    return strtoupper(trim((string)$value));
}

// --- 2. PORT GROUPING (Using Aviation-Style Manual Counters) ---
$slpaStats = [
    'CD' => 0, 'CIVIL' => 0, 'DEV' => 0, 'EE' => 0, 'IS' => 0, 
    'LOG' => 0, 'MECH' => 0, 'NAV' => 0, 'PD' => 0, 'SEC' => 0
];

foreach ($slpaProjects as $p) {
    $d = normalize_division_key($p['division'] ?? '');
    
    if ($d === 'C & D' || $d === 'C&D' || strpos($d, 'C & D') !== false) $slpaStats['CD']++;
    elseif (strpos($d, 'CIVIL') !== false) $slpaStats['CIVIL']++;
    elseif (strpos($d, 'DEVELOPMENT') !== false && strpos($d, 'PLANNING') === false) $slpaStats['DEV']++;
    elseif (strpos($d, 'ELECTRICAL') !== false || $d === 'EE') $slpaStats['EE']++;
    elseif ($d === 'IS' || strpos($d, 'INFORMATION') !== false) $slpaStats['IS']++;
    elseif (strpos($d, 'LOGISTIC') !== false) $slpaStats['LOG']++;
    elseif (strpos($d, 'MECHANICAL') !== false || strpos($d, 'MECH') !== false) $slpaStats['MECH']++;
    elseif (strpos($d, 'NAVIGATION') !== false) $slpaStats['NAV']++;
    elseif (strpos($d, 'PLANNING') !== false || $d === 'P & D' || $d === 'P&D') $slpaStats['PD']++;
    elseif (strpos($d, 'SECURITY') !== false || strpos($d, 'SEC') !== false) $slpaStats['SEC']++;
}

// Manual Display Array for SLPA (Matching Sidebar/Navbar labels)
$slpaDisplayList = [
    ['label' => 'C & D', 'query' => 'C & D', 'count' => $slpaStats['CD']],
    ['label' => 'Civil Engineering', 'query' => 'Civil Engineering', 'count' => $slpaStats['CIVIL']],
    ['label' => 'Development', 'query' => 'Development', 'count' => $slpaStats['DEV']],
    ['label' => 'Electrical & Electronic', 'query' => 'Electrical & Electronic', 'count' => $slpaStats['EE']],
    ['label' => 'IS', 'query' => 'IS', 'count' => $slpaStats['IS']],
    ['label' => 'Logistics', 'query' => 'Logistics', 'count' => $slpaStats['LOG']],
    ['label' => 'Mechanical', 'query' => 'Mechanical', 'count' => $slpaStats['MECH']],
    ['label' => 'Navigation', 'query' => 'Navigation', 'count' => $slpaStats['NAV']],
    ['label' => 'Planning & Development', 'query' => 'Planning & Development', 'count' => $slpaStats['PD']],
    ['label' => 'Security', 'query' => 'Security', 'count' => $slpaStats['SEC']],
];

// MSS Divisions
$mssDivCounts = [];
foreach ($mssProjects as $p) {
    $divName = trim($p['division'] ?? 'General');
    if ($divName === '') continue;
    $mssDivCounts[$divName] = ($mssDivCounts[$divName] ?? 0) + 1;
}

// --- 3. AVIATION GROUPING (Strictly Untouched) ---
$aaslStats = [
    'ALID' => 0, 'AM' => 0, 'CE_PD' => 0, 'CE_PROJ' => 0, 'CE_MAINT' => 0,
    'EANE' => 0, 'EE' => 0, 'HR' => 0, 'IT' => 0, 'MECH' => 0, 
    'MEHE' => 0, 'PROJ' => 0, 'SFRS' => 0, 'SLAA' => 0
];

foreach ($aaslProjects as $p) {
    $d = normalize_division_key($p['division'] ?? '');
    if (strpos($d, 'AL&ID') !== false) $aaslStats['ALID']++;
    elseif (strpos($d, 'AM') !== false) $aaslStats['AM']++;
    elseif (strpos($d, 'CE (P&D)') !== false) $aaslStats['CE_PD']++;
    elseif (strpos($d, 'CE (PROJECT)') !== false) $aaslStats['CE_PROJ']++;
    elseif (strpos($d, 'CE(MAINTENANCE)') !== false) $aaslStats['CE_MAINT']++;
    elseif (strpos($d, 'E&ANE') !== false) $aaslStats['EANE']++;
    elseif ($d === 'EE') $aaslStats['EE']++;
    elseif ($d === 'HR') $aaslStats['HR']++;
    elseif ($d === 'IT') $aaslStats['IT']++;
    elseif ($d === 'MECH') $aaslStats['MECH']++;
    elseif ($d === 'MEHE') $aaslStats['MEHE']++;
    elseif ($d === 'PROJECT') $aaslStats['PROJ']++;
    elseif (strpos($d, 'S&FRS') !== false) $aaslStats['SFRS']++;
    elseif ($d === 'SLAA') $aaslStats['SLAA']++;
}

$aaslDisplay = [
    ['label' => 'AL&ID', 'query' => 'AL&ID', 'count' => $aaslStats['ALID']],
    ['label' => 'AM', 'query' => 'AM', 'count' => $aaslStats['AM']],
    ['label' => 'CE (P&D)', 'query' => 'CE (P&D)', 'count' => $aaslStats['CE_PD']],
    ['label' => 'CE (Project)', 'query' => 'CE (Project)', 'count' => $aaslStats['CE_PROJ']],
    ['label' => 'CE(Maintenance)', 'query' => 'CE(Maintenance)', 'count' => $aaslStats['CE_MAINT']],
    ['label' => 'E&ANE', 'query' => 'E&ANE', 'count' => $aaslStats['EANE']],
    ['label' => 'EE', 'query' => 'EE', 'count' => $aaslStats['EE']],
    ['label' => 'HR', 'query' => 'HR', 'count' => $aaslStats['HR']],
    ['label' => 'IT', 'query' => 'IT', 'count' => $aaslStats['IT']],
    ['label' => 'MECH', 'query' => 'MECH', 'count' => $aaslStats['MECH']],
    ['label' => 'MEHE', 'query' => 'MEHE', 'count' => $aaslStats['MEHE']],
    ['label' => 'Project', 'query' => 'Project', 'count' => $aaslStats['PROJ']],
    ['label' => 'S&FRS', 'query' => 'S&FRS', 'count' => $aaslStats['SFRS']],
    ['label' => 'SLAA', 'query' => 'SLAA', 'count' => $aaslStats['SLAA']],
];

$caaslDivCounts = [];
foreach ($caaslProjects as $p) {
    $divName = trim($p['division'] ?? 'Other');
    if ($divName === '') continue;
    $caaslDivCounts[$divName] = ($caaslDivCounts[$divName] ?? 0) + 1;
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
        height: calc(100vh - 110px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background: linear-gradient(to bottom, var(--sky) 0%, #ffffff 90%);
        overflow: hidden;
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

    .dashboard-content { position: relative; z-index: 10; width: 95%; max-width: 1280px; margin-top: -20px; }
    .card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

    .card-item {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.05);
        height: 460px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        border: 1px solid #edf2f7;
    }

    .card-head { padding: 15px; text-align: center; border-bottom: 1px solid #edf2f7; }
    .card-head i { font-size: 28px; margin-bottom: 5px; display: block; }
    .card-head h2 { margin: 0; font-size: 16px; text-transform: uppercase; color: var(--text); font-weight: 800; }

    .card-body { padding: 10px; flex: 1; overflow-y: auto; scrollbar-width: thin; }

    .nav-link {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 14px; background: #f8fafc; border-radius: 8px;
        text-decoration: none; color: var(--ports); font-weight: 700;
        font-size: 13.5px; border: 1px solid #e2e8f0; margin-bottom: 10px;
    }

    .sub-list { list-style: none; margin: -2px 0 12px 18px; padding: 0 0 0 14px; border-left: 1px dashed #cbd5e0; }
    .sub-item { position: relative; padding-left: 10px; margin-bottom: 6px; }
    .sub-item::before { content: ""; position: absolute; left: -14px; top: 12px; width: 10px; height: 1px; background: #cbd5e0; }
    .sub-link { display: flex; justify-content: space-between; gap: 10px; text-decoration: none; color: #4a5568; font-size: 12px; font-weight: 500; }
    .sub-link:hover { color: #1e3a5f; }
    .sub-name { display: inline-flex; align-items: center; gap: 6px; }
    .sub-count { font-size: 11px; color: #718096; font-weight: 700; white-space: nowrap; }

    .badge { color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
    .badge-ports { background: var(--ports); }
    .badge-aviation { background: var(--aviation); }

    .ports-card { border-top: 5px solid var(--ports); }
    .avia-card { border-top: 5px solid var(--aviation); }
    .min-card { border-top: 5px solid var(--ministry); }

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
        .card-grid { grid-template-columns: 1fr; }
        .card-item { height: auto; min-height: 280px; }
        .dashboard-stage { height: auto; min-height: calc(100vh - 110px); padding: 60px 0 100px; }
        .ship-container { display: none; }
    }
</style>

<div class="dashboard-stage">
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

    <div class="ship-container">
        <div class="funnel f1"></div><div class="funnel f2"></div>
        <div class="ship-cabin"></div><div class="ship-hull"></div>
    </div>

    <div class="dashboard-content">
        <div class="card-grid">
            
            <div class="card-item ports-card">
                <div class="card-head"><i class="fa fa-ship" style="color:var(--ports)"></i><h2>Ports</h2></div>
                <div class="card-body">
                    <a href="index.php?page=summary&org=SLPA&division=all" class="nav-link">
                        <span>SLPA</span><span class="badge badge-ports"><?= $totalSlpa ?></span>
                    </a>
                    
                    <ul class="sub-list">
                        <?php foreach ($slpaDisplayList as $div): if($div['count'] > 0): ?>
                        <li class="sub-item">
                            <a href="index.php?page=summary&org=SLPA&division=<?= urlencode($div['query']) ?>" class="sub-link">
                                <span class="sub-name"><i class="fa fa-angle-right"></i> <?= htmlspecialchars($div['label']) ?></span>
                                <span class="sub-count"><?= (int)$div['count'] ?></span>
                            </a>
                        </li>
                        <?php endif; endforeach; ?>
                    </ul>

                    <a href="index.php?page=summary&org=MSS&division=all" class="nav-link">
                        <span>Merchant Shipping (MSS)</span><span class="badge badge-ports"><?= $totalMss ?></span>
                    </a>
                    <?php if (!empty($mssDivCounts)): ?>
                    <ul class="sub-list">
                        <?php foreach ($mssDivCounts as $name => $count): ?>
                        <li class="sub-item">
                            <a href="index.php?page=summary&org=MSS&division=<?= urlencode($name) ?>" class="sub-link">
                                <span class="sub-name"><i class="fa fa-angle-right"></i> <?= htmlspecialchars($name) ?></span>
                                <span class="sub-count"><?= $count ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <a href="index.php?page=summary&org=CSC&division=all" class="nav-link">Ceylon Shipping Corporation</a>
                </div>
            </div>

            <div class="card-item avia-card">
                <div class="card-head"><i class="fa fa-plane" style="color:var(--aviation)"></i><h2>Aviation</h2></div>
                <div class="card-body">
                    <a href="index.php?page=summary&org=AASL&division=all" class="nav-link" style="color:var(--aviation)">
                        <span>AASL</span><span class="badge badge-aviation"><?= $totalAasl ?></span>
                    </a>
                    <ul class="sub-list">
                        <?php foreach ($aaslDisplay as $div): if($div['count'] > 0): ?>
                        <li class="sub-item">
                            <a href="index.php?page=summary&org=AASL&division=<?= urlencode($div['query']) ?>" class="sub-link" style="color:#2f855a;">
                                <span class="sub-name"><i class="fa fa-angle-right"></i> <?= htmlspecialchars($div['label']) ?></span>
                                <span class="sub-count"><?= (int)$div['count'] ?></span>
                            </a>
                        </li>
                        <?php endif; endforeach; ?>
                    </ul>

                    <a href="index.php?page=summary&org=CAASL&division=all" class="nav-link" style="color:var(--aviation)">
                        <span>CAASL</span><span class="badge badge-aviation"><?= $totalCaasl ?></span>
                    </a>
                    <ul class="sub-list">
                        <?php foreach ($caaslDivCounts as $name => $count): ?>
                        <li class="sub-item">
                            <a href="index.php?page=summary&org=CAASL&division=<?= urlencode($name) ?>" class="sub-link" style="color:#2f855a;">
                                <span class="sub-name"><i class="fa fa-angle-right"></i> <?= htmlspecialchars($name) ?></span>
                                <span class="sub-count"><?= $count ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="card-item min-card">
                <div class="card-head"><i class="fa fa-landmark" style="color:var(--ministry)"></i><h2>Ministry</h2></div>
                <div class="card-body">
                    <a href="index.php?page=summary&org=all" class="nav-link" style="color:var(--ministry)">Global Dashboard</a>
                    <a href="index.php?page=reports" class="nav-link" style="color:var(--ministry)">Action Plan Reports</a>
                </div>
            </div>
        </div>
    </div>

    <div class="sea-zone"><div class="wave"></div></div>
</div>