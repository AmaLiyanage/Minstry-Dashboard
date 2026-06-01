<?php
include_once __DIR__ . '/db.php';

// 1. Capture current URL state
$page         = $_GET['page'] ?? 'welcome';
$current_org  = $_GET['org'] ?? '';
$f_div        = $_GET['f_div'] ?? 'All';
$division     = $_GET['division'] ?? 'all';

// Capture report-specific filters for active state matching
$selectedInst = $_GET['inst'] ?? 'All';
$selectedDiv  = $_GET['div'] ?? 'All';

/** 
 * HELPER: sector_of
 */
if (!function_exists('sector_of')) {
    function sector_of(string $inst): string {
        $inst = strtoupper(trim($inst));
        if (in_array($inst, ['AASL', 'CAASL'], true)) return 'Aviation';
        if ($inst === 'JCT') return 'JCT'; 
        return 'Port';
    }
}

// Normalize active parameter for cross-page signature matching
$active_div_param = ($page === 'ports' || $page === 'aviation' || $page === 'mss' || $page === 'jct') ? ($division === 'all' ? 'All' : $division) : $f_div;
$current_view_sig = $page . '|' . $current_org . '|' . $active_div_param;

function get_nav_active_class($target_page, $target_org = '', $target_f_div = 'All') {
    global $current_view_sig;
    $link_sig = $target_page . '|' . $target_org . '|' . $target_f_div;
    return ($current_view_sig === $link_sig) ? 'is-active' : '';
}

function get_org_divisions($org_code) {
    global $conn;
    $divisions = [];
    $org_code_escaped = mysqli_real_escape_string($conn, $org_code);
    
    $sql = "SELECT DISTINCT d.division_name 
            FROM divisions d
            JOIN institutions i ON d.institution_id = i.id
            WHERE UPPER(TRIM(i.code)) = '" . strtoupper($org_code_escaped) . "' 
               OR UPPER(TRIM(i.institution_name)) LIKE '%" . strtoupper($org_code_escaped) . "%'
            ORDER BY d.division_name ASC";
            
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['division_name'])) {
                $divisions[] = trim($row['division_name']);
            }
        }
    }
    return $divisions;
}

function build_nav_url($target_page, $sector, $org, $div) {
    $params = [
        'page'     => $target_page,
        'org'      => $org,
        'division' => $div,
        'f_sector' => ucfirst($sector),
        'f_org'    => $org,
        'f_div'    => $div == 'all' ? 'All' : $div,
    ];
    if($target_page === 'reports') {
        return "index.php?page=reports&inst=$org&div=" . urlencode($div);
    }
    if($target_page === 'analytics') {
        return "index.php?page=analytics&org=$org&f_div=" . urlencode($div);
    }
    return 'index.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministry Projects Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --sidebar-bg : #222e3c;
            --highlight  : #4a72d4;
            --header-bg  : #f8fafc;
            --nav-color  : #cbd5e1;
            --nav-hover-bg: rgba(255,255,255,0.10);
        }
        body { font-family: 'Inter', sans-serif; background: #fff; color: #1e293b; }
        .header { background: var(--header-bg); padding: 16px 24px; font-weight: 700; font-size: 18px; color: #0f172a; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 999; margin-left: 280px; }
        .container { display: flex; }
        .content   { margin-left: 280px; width: calc(100% - 280px); padding: 25px; min-height: 100vh; }
        .sidebar { background: var(--sidebar-bg); width: 280px; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto; z-index: 1000; padding-bottom: 30px; }
        .sidebar-label { padding: 18px 20px 6px; font-size: 10px; font-weight: 700; letter-spacing: 1.4px; text-transform: uppercase; color: rgba(255,255,255,0.22); }
        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 8px 16px 6px; }
        a.nav-item, div.nav-item { display: flex; align-items: center; padding: 9px 16px; text-decoration: none; color: #cbd5e1; font-size: 13px; font-weight: 500; border-radius: 6px; margin: 1px 10px; cursor: pointer; transition: background 0.15s, color 0.15s; isolation: isolate; }
        a.nav-item i:not(.arrow), div.nav-item i:not(.arrow) { width: 18px; margin-right: 10px; color: inherit; }
        a.nav-item:hover, div.nav-item:hover { background: var(--nav-hover-bg); color: #ffffff; }
        a.nav-item.is-active, div.nav-item.is-active { background: var(--highlight); color: #ffffff; font-weight: 700; }
        div.nav-item.open { color: #ffffff; }
        div.nav-item { justify-content: space-between; }
        div.nav-item span { display: flex; align-items: center; }
        i.arrow { font-size: 10px; color: rgba(255,255,255,0.35); transition: transform 0.25s; }
        div.nav-item.open i.arrow { transform: rotate(180deg); color: #fff; }
        .sub-menu { display: none; list-style: none; }
        .sub-menu.open { display: block; }
        .level-2 { padding-left: 20px !important; }
        .level-3 { padding-left: 20px !important; }
        .level-4 { padding-left: 36px !important; font-size: 12.5px; }
        .level-5 { padding-left: 52px !important; font-size: 12px; text-transform: capitalize; }
        .level-5 i { font-size: 10px; opacity: 0.7; }
        
        /* Stylized Action Item for adding entities inside submenu listings */
        .add-action-item { color: #22c55e !important; font-weight: 600 !important; }
        .add-action-item:hover { background: rgba(34, 197, 94, 0.15) !important; color: #4ade80 !important; }
    </style>
</head>
<body>

<div class="header">
    <a href="index.php?page=welcome" style="color: inherit; text-decoration: none; display: flex; align-items: center;">
        <i class="fa-solid fa-layer-group" style="color:var(--highlight);margin-right:12px;"></i>
        Ministry Projects Dashboard
    </a>
</div>

<div class="container">
<div class="sidebar">
    <div class="sidebar-label">Main Menu</div>
    <a href="index.php?page=welcome" class="nav-item <?= get_nav_active_class('welcome') ?>">
        <span><i class="fa-solid fa-door-open"></i> Welcome Portal</span>
    </a>
    <a href="index.php?page=home" class="nav-item <?= get_nav_active_class('home') ?>">
        <span><i class="fa-solid fa-earth-americas"></i> Global Dashboard</span>
    </a>
    <a href="index.php?page=organization_structure" class="nav-item <?= get_nav_active_class('organization_structure') ?>">
        <span><i class="fa-solid fa-sitemap"></i> Organization Structure</span>
    </a>

    <div class="nav-item menu-toggle open" onclick="toggleMenu('projects-menu',this)">
        <span><i class="fa fa-folder-open"></i> Projects Control</span>
        <i class="fa fa-chevron-down arrow"></i>
    </div>
    <div id="projects-menu" class="sub-menu open">
        <?php
        $sectors = [
            'ports' => ['label' => 'Port', 'icon' => 'fa-ship', 'orgs' => ['SLPA','MSS','CSC']],
            'aviation' => ['label' => 'Aviation', 'icon' => 'fa-plane', 'orgs' => ['AASL','CAASL']],
            'jct' => ['label' => 'JCT', 'icon' => 'fa-boxes-stacked']
        ];

        foreach ($sectors as $sectorKey => $sectorData):
            $sector_open = ($page === $sectorKey || (isset($sectorData['orgs']) && in_array($current_org, $sectorData['orgs'])) || ($page === 'reports' && sector_of($selectedInst) === $sectorData['label']));
        ?>
            <!-- LEVEL 2: SECTOR -->
            <div class="nav-item level-2 <?= $sector_open ? 'open' : '' ?>" onclick="toggleMenu('<?= $sectorKey ?>-sub',this)">
                <span><i class="fa <?= $sectorData['icon'] ?>"></i> <?= $sectorData['label'] ?></span>
                <i class="fa fa-chevron-down arrow"></i>
            </div>
            <div id="<?= $sectorKey ?>-sub" class="sub-menu <?= $sector_open ? 'open' : '' ?>">
                
                <?php if ($sectorKey === 'jct'): ?>
                    <a href="index.php?page=project_create&org=JCT&sector=jct" class="nav-item level-3 add-action-item <?= get_nav_active_class('project_create', 'JCT') ?>">
                        <span><i class="fa fa-plus-circle"></i> Add Project Details</span>
                    </a>
                    <a href="index.php?page=add_financial&org=JCT&sector=jct" class="nav-item level-3 add-action-item <?= ($page === 'add_financial' && $current_org === 'JCT') ? 'is-active' : '' ?>">
                        <span><i class="fa fa-plus-circle"></i> Add Financial Details</span>
                    </a>
                    <a href="index.php?page=physical_progress&org=JCT&sector=jct" class="nav-item level-3 add-action-item <?= ($page === 'physical_progress' && $current_org === 'JCT') ? 'is-active' : '' ?>">
                        <span><i class="fa fa-plus-circle"></i> Add Physical Details</span>
                    </a>
                    <a href="index.php?page=project_list&org=JCT&sector=jct" class="nav-item level-3 <?= ($page === 'project_list' && $current_org === 'JCT') ? 'is-active' : '' ?>">
                        <span><i class="fa-solid fa-table-list"></i> Project List</span>
                    </a>
                    <a href="index.php?page=project_financial&org=JCT&sector=jct" class="nav-item level-3 <?= ($page === 'project_financial' && $current_org === 'JCT') ? 'is-active' : '' ?>">
                        <span><i class="fa-solid fa-money-bill-wave"></i> Financial Details</span>
                    </a>
                    <a href="index.php?page=physical_progress_display&org=JCT&sector=jct" class="nav-item level-3 <?= ($page === 'physical_progress_display' && $current_org === 'JCT') ? 'is-active' : '' ?>">
                        <span><i class="fa-solid fa-bars-progress"></i> Physical Details</span>
                    </a>
                    <a href="index.php?page=jct&org=JCT&division=all" class="nav-item level-3 <?= get_nav_active_class('jct', 'JCT', 'All') ?>">
                        <span><i class="fa-solid fa-list"></i> Summary</span>
                    </a>
                    

                    <a href="<?= build_nav_url('progress_view', 'jct', 'JCT', 'all') ?>" class="nav-item level-3 <?= get_nav_active_class('progress_view', 'JCT', 'All') ?>">
                        <span><i class="fa-solid fa-chart-simple"></i> Progress Status</span>
                    </a>
                    <a href="index.php?page=analytics&org=JCT&f_div=All" class="nav-item level-3 <?= get_nav_active_class('analytics', 'JCT', 'All') ?>">
                        <span><i class="fa-solid fa-chart-line"></i> Analytics</span>
                    </a>
                    <a href="index.php?page=reports&inst=JCT&div=All" class="nav-item level-3 <?= ($page === 'reports' && $selectedInst === 'JCT') ? 'is-active' : '' ?>">
                        <span><i class="fa-solid fa-file-contract"></i> Executive Report</span>
                    </a>

                <?php else: ?>
                    <?php foreach ($sectorData['orgs'] as $org): 
                        $org_divs = get_org_divisions($org);
                        $org_open = ($current_org === $org || $selectedInst === $org || (in_array($page, ['project_create', 'add_financial', 'physical_progress', 'project_list', 'project_financial', 'physical_progress_display']) && $current_org === $org));
                        $pageSlug = ($org === 'MSS') ? 'mss' : $sectorKey;
                    ?>
                        <!-- LEVEL 3: INSTITUTION -->
                        <div class="nav-item level-3 <?= $org_open ? 'open' : '' ?>" onclick="toggleMenu('org-<?= $org ?>',this)">
                            <span><i class="fa fa-university"></i> <?= $org ?></span>
                            <i class="fa fa-chevron-down arrow"></i>
                        </div>
                        <div id="org-<?= $org ?>" class="sub-menu <?= $org_open ? 'open' : '' ?>">
                            
                            <!-- 1. DYNAMIC ADD PROJECT LINK PER INSTITUTION -->
                            <div class="nav-item level-4" onclick="toggleMenu('add-proj-<?= $org ?>',this)">
                                <span class="add-action-item"><i class="fa fa-plus-circle"></i> Add Project Details</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="add-proj-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('project_create', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('project_create', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('project_create', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('project_create', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 1.2. ADD FINANCIAL -->
                            <div class="nav-item level-4" onclick="toggleMenu('add-fin-<?= $org ?>',this)">
                                <span class="add-action-item"><i class="fa fa-plus-circle"></i> Add Financial Details</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="add-fin-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('add_financial', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('add_financial', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('add_financial', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('add_financial', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 1.3. ADD PHYSICAL PROGRESS -->
                            <div class="nav-item level-4" onclick="toggleMenu('add-phys-<?= $org ?>',this)">
                                <span class="add-action-item"><i class="fa fa-plus-circle"></i> Add Physical Details</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="add-phys-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('physical_progress', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('physical_progress', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('physical_progress', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('physical_progress', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 1.5. PROJECT LIST -->
                            <div class="nav-item level-4" onclick="toggleMenu('proj-list-<?= $org ?>',this)">
                                <span><i class="fa-solid fa-table-list"></i> Project List</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="proj-list-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('project_list', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('project_list', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('project_list', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('project_list', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 1.6. PROJECT FINANCIAL -->
                            <div class="nav-item level-4" onclick="toggleMenu('proj-fin-<?= $org ?>',this)">
                                <span><i class="fa-solid fa-money-bill-wave"></i> Financial Details</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="proj-fin-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('project_financial', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('project_financial', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('project_financial', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('project_financial', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 1.7. PROJECT PHYSICALS -->
                            <div class="nav-item level-4" onclick="toggleMenu('proj-phys-<?= $org ?>',this)">
                                <span><i class="fa-solid fa-bars-progress"></i> Physical Details</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="proj-phys-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('physical_progress_display', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('physical_progress_display', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('physical_progress_display', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('physical_progress_display', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 2. STANDARD VIEW -->
                            <div class="nav-item level-4" onclick="toggleMenu('std-<?= $org ?>',this)">
                                <span><i class="fa-solid fa-list"></i> Summary</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="std-<?= $org ?>" class="sub-menu">
                                <a href="index.php?page=<?= $pageSlug ?>&org=<?= $org ?>&division=all" class="nav-item level-5 <?= get_nav_active_class($pageSlug, $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="index.php?page=<?= $pageSlug ?>&org=<?= $org ?>&division=<?= urlencode($div) ?>" class="nav-item level-5 <?= get_nav_active_class($pageSlug, $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 3. STATUS BOARD -->
                            <div class="nav-item level-4" onclick="toggleMenu('prog-<?= $org ?>',this)">
                                <span><i class="fa-solid fa-chart-simple"></i> Progress Status</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="prog-<?= $org ?>" class="sub-menu">
                                <a href="<?= build_nav_url('progress_view', $sectorKey, $org, 'all') ?>" class="nav-item level-5 <?= get_nav_active_class('progress_view', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="<?= build_nav_url('progress_view', $sectorKey, $org, $div) ?>" class="nav-item level-5 <?= get_nav_active_class('progress_view', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 4. ANALYTICS -->
                            <div class="nav-item level-4" onclick="toggleMenu('ana-<?= $org ?>',this)">
                                <span><i class="fa fa-chart-line"></i> Analytics</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="ana-<?= $org ?>" class="sub-menu">
                                <a href="index.php?page=analytics&org=<?= $org ?>&f_div=All" class="nav-item level-5 <?= get_nav_active_class('analytics', $org, 'All') ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="index.php?page=analytics&org=<?= $org ?>&f_div=<?= urlencode($div) ?>" class="nav-item level-5 <?= get_nav_active_class('analytics', $org, $div) ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- 5. EXECUTIVE REPORT -->
                            <div class="nav-item level-4" onclick="toggleMenu('rep-<?= $org ?>',this)">
                                <span><i class="fa-solid fa-file-contract"></i> Executive Report</span>
                                <i class="fa fa-chevron-down arrow"></i>
                            </div>
                            <div id="rep-<?= $org ?>" class="sub-menu">
                                <a href="index.php?page=reports&inst=<?= $org ?>&div=All" class="nav-item level-5 <?= ($page === 'reports' && $selectedInst === $org && $selectedDiv === 'All') ? 'is-active' : '' ?>">
                                    <i class="fa-solid fa-layer-group"></i> Institutional
                                </a>
                                <?php foreach ($org_divs as $div): ?>
                                    <a href="index.php?page=reports&inst=<?= $org ?>&div=<?= urlencode($div) ?>" class="nav-item level-5 <?= ($page === 'reports' && $selectedInst === $org && urldecode($selectedDiv) === $div) ? 'is-active' : '' ?>">
                                        <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($div) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="content">
    <?php
        $file = "pages/{$page}.php";
        if (file_exists($file)) { 
            include $file; 
        } else { 
            echo "<div style='padding:40px;text-align:center'><h2>Welcome</h2><p>Please select a project from the sidebar.</p></div>"; 
        }
    ?>
</div>
</div>

<script>
function toggleMenu(id, el) {
    const target = document.getElementById(id);
    if (!target) return;
    target.classList.toggle('open');
    el.classList.toggle('open');
}

document.addEventListener('DOMContentLoaded', function () {
    const active = document.querySelector('.nav-item.is-active');
    if (active) {
        let parent = active.parentElement.closest('.sub-menu');
        while (parent) {
            parent.classList.add('open');
            const trigger = document.querySelector(`[onclick*="toggleMenu('${parent.id}'"]`);
            if (trigger) trigger.classList.add('open');
            parent = parent.parentElement.closest('.sub-menu');
        }
    }
});
</script>
</body>
</html>
