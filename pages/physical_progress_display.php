<?php

include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$project_id = (int) ($_GET['id'] ?? 0);
$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$division = trim((string)($_GET['division'] ?? 'all'));
$sector = trim((string)($_GET['sector'] ?? ''));

$project = null;
$projects_list = [];
$projects_grouped = [];

if ($project_id > 0) {
    $project_sql = "SELECT project_name, project_code FROM projects WHERE id = $project_id";
    $project_result = mysqli_query($conn, $project_sql);
    if ($project_result) {
        $project = mysqli_fetch_assoc($project_result);
    }
} else {
    // Fetch parent projects and compile multi-record physical track datasets mapped inside filters
    $sql = "SELECT p.id as project_id, p.project_name, p.project_code, i.code as institution_code, d.division_name
            FROM projects p
            LEFT JOIN institutions i ON p.institution_id = i.id
            LEFT JOIN divisions d ON p.division_id = d.id";
            
    $conditions = [];
    if ($orgCode !== '') {
        $orgCodeEscaped = mysqli_real_escape_string($conn, $orgCode);
        if ($orgCode === 'AASL') {
            $conditions[] = "(UPPER(TRIM(i.code)) = 'AASL' OR (UPPER(TRIM(i.institution_name)) LIKE '%AASL%' AND UPPER(TRIM(i.institution_name)) NOT LIKE '%CAASL%'))";
        } else {
            $conditions[] = "(UPPER(TRIM(i.code)) = '" . $orgCodeEscaped . "' OR UPPER(TRIM(i.institution_name)) LIKE '%" . $orgCodeEscaped . "%')";
        }
    }
    if ($division !== 'all' && $division !== '') {
        $divExact = mysqli_real_escape_string($conn, $division);
        $divClean = mysqli_real_escape_string($conn, preg_replace('/[^A-Z0-9]/', '', strtoupper($division)));
        $conditions[] = "(
            UPPER(TRIM(d.division_name)) = UPPER('" . $divExact . "') 
            OR REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(d.division_name)), ' ', ''), '&', ''), '(', ''), ')', '') = '" . $divClean . "'
            OR UPPER(TRIM(d.division_name)) LIKE UPPER('%" . $divExact . "%')
            OR UPPER('" . $divExact . "') LIKE CONCAT('%', UPPER(TRIM(d.division_name)), '%')
            OR SOUNDEX(d.division_name) = SOUNDEX('" . $divExact . "')
        )";
    }
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY p.project_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $projects_list[] = $row;
            
            // Allocate initial groupings pointers arrays cleanly
            $projects_grouped[$row['project_id']] = [
                'project_id'       => $row['project_id'],
                'project_name'     => $row['project_name'],
                'project_code'     => $row['project_code'],
                'institution_code' => $row['institution_code'],
                'division_name'    => $row['division_name'],
                'has_records'      => false,
                'targets'          => [],
                'quarterly'        => [],
                'cumulative'       => [],
                'funding'          => []
            ];
        }
    }

    // Populate data sets metrics into map blocks natively if projects grid is populated
    if (!empty($projects_grouped)) {
        $id_string = implode(',', array_keys($projects_grouped));

        // 1. Fetch Targets
        $q = mysqli_query($conn, "SELECT * FROM physical_targets WHERE project_id IN ($id_string) ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')");
        while($r = mysqli_fetch_assoc($q)) {
            $projects_grouped[$r['project_id']]['targets'][] = $r;
            $projects_grouped[$r['project_id']]['has_records'] = true;
        }
        // 2. Fetch Quarterly Progress
        $q = mysqli_query($conn, "SELECT * FROM quarterly_physical_progress WHERE project_id IN ($id_string) ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')");
        while($r = mysqli_fetch_assoc($q)) {
            $projects_grouped[$r['project_id']]['quarterly'][] = $r;
            $projects_grouped[$r['project_id']]['has_records'] = true;
        }
        // 3. Fetch Cumulative Status
        $q = mysqli_query($conn, "SELECT * FROM cumulative_physical_status WHERE project_id IN ($id_string) ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')");
        while($r = mysqli_fetch_assoc($q)) {
            $projects_grouped[$r['project_id']]['cumulative'][] = $r;
            $projects_grouped[$r['project_id']]['has_records'] = true;
        }
        // 4. Fetch Funding Details
        $q = mysqli_query($conn, "SELECT * FROM funding WHERE project_id IN ($id_string) ORDER BY allocation_year DESC");
        while($r = mysqli_fetch_assoc($q)) {
            $projects_grouped[$r['project_id']]['funding'][] = $r;
            $projects_grouped[$r['project_id']]['has_records'] = true;
        }
    }
}

// Single Selected Project Target Context Records Extract Handlers
$pt_records = []; $qp_records = []; $cs_records = []; $fn_records = [];
if ($project_id > 0) {
    $q = mysqli_query($conn, "SELECT * FROM physical_targets WHERE project_id = $project_id ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')");
    while($r = mysqli_fetch_assoc($q)) $pt_records[] = $r;

    $q = mysqli_query($conn, "SELECT * FROM quarterly_physical_progress WHERE project_id = $project_id ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')");
    while($r = mysqli_fetch_assoc($q)) $qp_records[] = $r;

    $q = mysqli_query($conn, "SELECT * FROM cumulative_physical_status WHERE project_id = $project_id ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')");
    while($r = mysqli_fetch_assoc($q)) $cs_records[] = $r;

    $q = mysqli_query($conn, "SELECT * FROM funding WHERE project_id = $project_id ORDER BY allocation_year DESC");
    while($r = mysqli_fetch_assoc($q)) $fn_records[] = $r;
}
?>

<style>
    /* Executive Bright Blue Gradient & Standalone Grid Workspace Tokens */
    .project-list-page {
        --blue-primary: #1e40af;
        --blue-bright-gradient: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
        --light-mesh: radial-gradient(at 0% 0%, #e0f2fe 0px, transparent 55%),
                      radial-gradient(at 100% 100%, #e0e7ff 0px, transparent 55%),
                      #f8fafc;
        
        /* Functional Icon Palette Groups */
        --icon-structure: #4f46e5;    /* Vivid Indigo */
        --icon-code: #00b4d8;         /* Electric Cyan */
        --icon-logistic: #7c3aed;     /* Deep Violet */
        --icon-finance: #10b981;      /* Emerald Green */
        --icon-alert: #f43f5e;        /* Rose Red */
        
        --text-dark: #0f172a;
        --text-slate: #475569;
        --text-light: #94a3b8;
        --border-soft: #cbd5e1;
        --panel-fill: rgba(255, 255, 255, 0.88);
        
        --radius-window: 24px;
        --radius-card: 16px;
        --radius-control: 12px;
        
        --shadow-window: 0 20px 25px -5px rgba(15, 23, 42, 0.03), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
        --shadow-input: inset 0 2px 4px 0 rgba(0, 0, 0, 0.01), 0 1px 2px 0 rgba(0, 0, 0, 0.03);

        max-width: 1220px;
        margin: 20px auto;
        padding: 24px;
        font-family: "Inter", system-ui, -apple-system, sans-serif;
        color: var(--text-slate);
        -webkit-font-smoothing: antialiased;
    }

    /* Outer Wrapper with Highly Refined Light Mesh Gradient Backing */
    .form-canvas-container {
        background: var(--light-mesh);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-window);
        box-shadow: var(--shadow-window);
        padding: 56px;
    }

    /* Fluid Styled High-Vibrancy Gradient Header */
    .form-canvas-header {
        margin-bottom: 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding-bottom: 32px;
        border-bottom: 2px solid var(--border-soft);
    }

    .form-canvas-header h1 {
        margin: 0;
        color: var(--text-dark);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .form-canvas-header h1 i {
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .form-canvas-header p {
        margin: 6px 0 0;
        color: var(--text-slate);
        font-size: 14.5px;
    }

    .context-pill-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--blue-bright-gradient);
        color: #ffffff;
        border-radius: 999px;
        padding: 8px 18px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        box-shadow: 0 4px 14px rgba(67, 100, 247, 0.3);
        border: none;
    }

    /* Cards Workspace Grid Container */
    .project-cards-workspace {
        display: flex;
        flex-direction: column;
        gap: 32px;
    }

    /* Frosted Semi-Translucent Dashboard Entity Cards */
    .project-record-card {
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-card);
        padding: 32px;
        box-shadow: var(--shadow-input);
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    /* Left Side Metadata Panel */
    .card-identity-panel {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .card-scope-badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .scope-micro-badge {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 4px 10px;
        background: #f1f5f9;
        color: var(--text-slate);
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #e2e8f0;
    }

    .scope-micro-badge.quarter-badge {
        background: var(--blue-bright-gradient);
        color: #ffffff;
        border: none;
    }

    .scope-micro-badge i { color: var(--icon-structure); }
    .scope-micro-badge.quarter-badge i { color: #ffffff; }

    .card-project-title {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: var(--text-dark);
        line-height: 1.4;
    }

    .nested-grid-heading {
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-light);
        margin: 8px 0 -4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Multi Column Layout Panels */
    .card-analytics-panel {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px 24px;
        background: rgba(248, 250, 252, 0.5);
        padding: 24px;
        border-radius: var(--radius-control);
        border: 1px solid #e2e8f0;
    }

    .card-analytics-panel.metrics-quad-row {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .card-analytics-panel.single-column {
        grid-template-columns: 1fr;
    }

    .analytic-datapoint {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .analytic-datapoint label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .analytic-datapoint label i { font-size: 11px; width: 14px; text-align: center;}
    .form-section-icon-code { color: var(--icon-code); }
    .form-section-icon-logistic { color: var(--icon-logistic); }
    .form-section-icon-finance { color: var(--icon-finance); }
    .form-section-icon-alert { color: var(--icon-alert); }

    .analytic-datapoint span {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
    }

    /* Custom Metered Progress Layout */
    .meter-container {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 4px;
        width: 100%;
    }

    .meter-bar-rail {
        flex: 1;
        height: 8px;
        background: #e2e8f0;
        border-radius: 99px;
        overflow: hidden;
        min-width: 100px;
    }

    .meter-bar-fill {
        height: 100%;
        background: #2563eb;
        border-radius: 99px;
    }

    .meter-label-percentage {
        font-weight: 800;
        font-size: 13.5px;
        color: var(--text-dark);
        min-width: 50px;
        text-align: right;
    }

    /* Card Interaction Bar */
    .card-action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 20px;
        border-top: 1px solid var(--border-soft);
        margin-top: 4px;
    }

    .info-box-helper {
        background: #f1f5f9;
        border: 1px solid var(--border-soft);
        color: var(--text-slate);
        padding: 16px 20px;
        border-radius: var(--radius-control);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 16px;
        height: 36px;
        border-radius: var(--radius-control);
        border: 1px solid transparent;
        cursor: pointer;
        color: #ffffff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .btn-edit { background: #2563eb; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15); }
    .btn-edit:hover { background: #1d4ed8; transform: translateY(-1px); }
    .btn-delete { background: #ef4444; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15); }
    .btn-delete:hover { background: #dc2626; transform: translateY(-1px); }
    .btn-secondary { 
        background: #ffffff; 
        color: var(--text-slate); 
        border-color: var(--border-soft); 
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        font-weight: 700;
        height: 36px;
    }
    .btn-secondary:hover { background: #f8fafc; color: var(--text-dark); border-color: #94a3b8; }

    /* Empty Indicators Fallbacks Layouts */
    .empty-catalog-fallback {
        background: rgba(255, 255, 255, 0.6);
        border: 2px dashed var(--border-soft);
        border-radius: var(--radius-card);
        padding: 44px;
        text-align: center;
        font-size: 14px;
        font-weight: 500;
    }

    /* Custom Glassmorphic Confirmation Modal Backdrop */
    .custom-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }

    .custom-modal-backdrop.modal-visible {
        opacity: 1;
        pointer-events: auto;
    }

    .custom-confirmation-modal {
        background: #ffffff;
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-card);
        width: 90%;
        max-width: 480px;
        padding: 32px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.92);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-align: center;
    }

    .custom-modal-backdrop.modal-visible .custom-confirmation-modal {
        transform: scale(1);
    }

    .modal-status-icon {
        width: 56px;
        height: 56px;
        background: #fff1f2;
        color: #e11d48;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 20px;
    }

    .custom-confirmation-modal h3 {
        margin: 0 0 10px 0;
        color: var(--text-dark);
        font-size: 18px;
        font-weight: 700;
    }

    .custom-confirmation-modal p {
        margin: 0 0 24px 0;
        color: var(--text-slate);
        font-size: 14px;
        line-height: 1.5;
    }

    .modal-target-project-name {
        display: block;
        font-weight: 700;
        color: #9f1239;
        background: #fff1f2;
        padding: 10px 14px;
        border-radius: 8px;
        margin-top: 12px;
        word-break: break-word;
    }

    .modal-action-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .modal-action-buttons button {
        min-height: 44px;
        padding: 0 24px;
        font-weight: 700;
        font-size: 13.5px;
        border-radius: var(--radius-control);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .modal-btn-cancel {
        background: #ffffff;
        border: 1px solid var(--border-soft);
        color: var(--text-slate);
    }

    .modal-btn-cancel:hover { background: #f8fafc; border-color: var(--text-light); }

    .modal-btn-confirm {
        background: linear-gradient(135deg, #e11d48 0%, #9f1239 100%) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 4px 14px rgba(225, 29, 72, 0.3);
    }

    .modal-btn-confirm:hover {
        background: linear-gradient(135deg, #be123c 0%, #4c0519 100%) !important;
        box-shadow: 0 6px 16px rgba(190, 18, 60, 0.45);
    }

    /* Screen Responsiveness Layout Constraints Overrides */
    @media (max-width: 1140px) {
        .card-analytics-panel.metrics-quad-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 1024px) {
        .project-record-card { grid-template-columns: 1fr; gap: 24px; }
    }

    @media (max-width: 640px) {
        .form-canvas-container { padding: 32px 20px; }
        .form-canvas-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        .card-analytics-panel, .card-analytics-panel.metrics-quad-row { grid-template-columns: 1fr; }
        .card-action-bar { flex-direction: column; align-items: stretch; gap: 16px; }
        .action-buttons { flex-direction: column; width: 100%; }
        .action-buttons .btn-action { width: 100%; }
        .modal-action-buttons { flex-direction: column; }
    }
</style>

<div class="project-list-page">
    <div class="form-canvas-container">
        
        <header class="form-canvas-header">
            <div>
                <h1><i class="fa-solid fa-bars-progress"></i> Physical Progress Console</h1>
                <p>Track targets, core physical metrics performance levels and funding records.</p>
            </div>
            <?php if ($orgCode !== '' || $division !== 'all'): ?>
                <div class="context-pill-badge">
                    <i class="fa-solid fa-server"></i>
                    <?= $escape($orgCode ?: 'Global Scope') ?><?= $division !== 'all' ? ' / ' . $escape($division) : '' ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- =========================================================
             VIEW MODE A: GLOBAL FLEET SECTOR PROJECTS CATALOG
        ========================================================== -->
        <?php if ($project_id == 0): ?>
            
            
            <div class="project-cards-workspace">
                <?php if (empty($projects_grouped)): ?>
                    <div class="empty-catalog-fallback" style="padding:64px;">
                        <i class="fa-solid fa-folder-open" style="font-size: 32px; color: var(--text-light); margin-bottom: 12px; display: block;"></i>
                        No active project profiles matched system filters indices for the requested workspace structure.
                    </div>
                <?php else: ?>
                    <?php foreach ($projects_grouped as $p): ?>
                        <article class="project-record-card" style="gap:20px;">
                            <div class="card-identity-panel">
                                <div class="card-scope-badge-row">
                                    <span class="scope-micro-badge"><i class="fa-solid fa-hashtag"></i> Code: <?= $escape($p['project_code'] ?: '-') ?></span>
                                    <?php if (!empty($p['institution_code'])): ?>
                                        <span class="scope-micro-badge"><i class="fa-solid fa-building"></i> <?= $escape($p['institution_code']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="card-project-title" style="font-size:19px; font-weight:700;"><?= $escape($p['project_name']) ?></h2>
                            </div>

                            <div class="card-action-bar" style="padding-top:16px; margin:0;">
                                <div style="font-size:12.5px; font-weight:600; color:var(--text-light)">
                                    <i class="fa-solid fa-circle-nodes"></i> Operational Logs Status: <?= $p['has_records'] ? '<span style="color:#10b981">Tracked</span>' : '<span style="color:var(--text-light)">Unallocated</span>' ?>
                                </div>
                                <div class="action-buttons">
                                    <a href="index.php?page=physical_progress_display&id=<?= $p['project_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>&sector=<?= urlencode($sector) ?>" class="btn-action" style="background:#2563eb; color:white;">
                                        <i class="fa fa-eye"></i> View Records
                                    </a>
                                    <a href="index.php?page=physical_progress&id=<?= $p['project_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action" style="background:#16a34a; color:white;">
                                        <i class="fa fa-plus"></i> Add Record
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <!-- =========================================================
             VIEW MODE B: COMPONENT LEVEL BREAKDOWNS (SINGLE PROJECT)
        ========================================================== -->
        <?php else: ?>
            
            <div class="card-identity-panel" style="margin-bottom: 32px;">
                <div class="action-buttons" style="justify-content: space-between; width: 100%;">
                    <a href="index.php?page=physical_progress_display&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>&sector=<?= urlencode($sector) ?>" class="btn-action btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Fleet Index
                    </a>
                    <a href="index.php?page=physical_progress&id=<?= $project_id ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action" style="background:#16a34a; color:white; height:44px; padding:0 24px;">
                        <i class="fa fa-plus"></i> Append Progress Entry
                    </a>
                </div>
                
                <?php if ($project): ?>
                    <h2 class="card-project-title" style="font-size:24px; margin-top:16px; border-left:4px solid #2563eb; padding-left:16px;">
                        <?= $escape($project['project_code'] ? $project['project_code'] . ' - ' : '') ?><?= $escape($project['project_name']) ?>
                    </h2>
                <?php endif; ?>
            </div>

            <div class="project-cards-workspace">

                <!-- SECTION 1: PHYSICAL TARGETS -->
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div class="nested-grid-heading"><i class="fa-solid fa-bullseye" style="color:var(--icon-structure)"></i> Physical Targets Reference Vectors</div>
                    <?php if (empty($pt_records)): ?>
                        <div class="empty-catalog-fallback">No physical target baseline constraints assigned for this index selection.</div>
                    <?php else: ?>
                        <?php foreach ($pt_records as $row): ?>
                            <article class="project-record-card" style="padding:24px;">
                                <div class="card-analytics-panel metrics-quad-row" style="padding:0; background:transparent; border:none;">
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-regular fa-calendar-check form-section-icon-code"></i> Focus Quarter</label>
                                        <strong><?= $escape($row['quarter']) ?></strong>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-chart-pie form-section-icon-logistic"></i> Overall Target</label>
                                        <span><?= number_format((float)$row['overall_physical_target'], 2) ?>%</span>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-history form-section-icon-finance"></i> Baseline (31/12/25)</label>
                                        <span><?= number_format((float)$row['progress_31_12_25'], 2) ?>%</span>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-calculator form-section-icon-structure"></i> Cumulative Target</label>
                                        <span><?= number_format((float)$row['cumulative_progress'], 2) ?>%</span>
                                    </div>
                                </div>
                                <div class="card-action-bar" style="padding-top:16px;">
                                    <div style="font-size:12px; color:var(--text-light)">
                                        Actual Progress Valuation Vector: <strong><?= number_format((float)$row['actual_physical_progress'], 2) ?>%</strong>
                                    </div>
                                    <div class="action-buttons">
                                        <a href="index.php?page=physical_progress_edit&type=physical_target&id=<?= $row['id'] ?>" class="btn-action btn-edit" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-pen"></i> Edit</a>
                                        <button type="button" class="btn-action btn-delete trigger-custom-delete" data-delete-url="index.php?page=physical_progress_edit&type=physical_target&delete_id=<?= $row['id'] ?>" data-record-context="Physical Target Block <?= $escape($row['quarter']) ?>" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-trash"></i> Delete</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- SECTION 2: QUARTERLY PHYSICAL PROGRESS -->
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div class="nested-grid-heading"><i class="fa-solid fa-bars-progress" style="color:var(--icon-logistic)"></i> Realised Quarterly Progress Logs</div>
                    <?php if (empty($qp_records)): ?>
                        <div class="empty-catalog-fallback">No intermediate quarterly monitoring statements verified for this node slot.</div>
                    <?php else: ?>
                        <?php foreach ($qp_records as $row): ?>
                            <article class="project-record-card" style="padding:24px;">
                                <div class="card-analytics-panel" style="padding:0; background:transparent; border:none;">
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-regular fa-calendar-check form-section-icon-code"></i> Interval Node</label>
                                        <strong><?= $escape($row['quarter']) ?></strong>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-crosshairs form-section-icon-structure"></i> Target Bound</label>
                                        <span><?= number_format((float)$row['cumulative_quarterly_target'], 2) ?>%</span>
                                        <label style="margin-top:4px;"><i class="fa-solid fa-arrow-trend-up form-section-icon-finance"></i> Attained</label>
                                        <span><?= number_format((float)$row['cumulative_quarterly_progress'], 2) ?>%</span>
                                    </div>
                                    <div class="analytic-datapoint span-all" style="margin-top:4px;">
                                        <label><i class="fa-solid fa-chart-line form-section-icon-finance"></i> Run-Rate Target Realisation Yield</label>
                                        <div class="meter-container">
                                            <div class="meter-bar-rail">
                                                <div class="meter-bar-fill" style="width: <?= min(100, max(0, (float)($row['progress_percentage'] ?? 0))) ?>%;"></div>
                                            </div>
                                            <span class="meter-label-percentage"><?= number_format((float)($row['progress_percentage'] ?? 0), 2) ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-action-bar" style="padding-top:16px;">
                                    <div></div>
                                    <div class="action-buttons">
                                        <a href="index.php?page=physical_progress_edit&type=quarterly_progress&id=<?= $row['id'] ?>" class="btn-action btn-edit" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-pen"></i> Edit</a>
                                        <button type="button" class="btn-action btn-delete trigger-custom-delete" data-delete-url="index.php?page=physical_progress_edit&type=quarterly_progress&delete_id=<?= $row['id'] ?>" data-record-context="Quarter Progress Statement <?= $escape($row['quarter']) ?>" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-trash"></i> Delete</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- SECTION 3: CUMULATIVE STATUS INDEX -->
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div class="nested-grid-heading"><i class="fa-solid fa-chart-pie" style="color:var(--icon-code)"></i> Cumulative Physical Yield Track</div>
                    <?php if (empty($cs_records)): ?>
                        <div class="empty-catalog-fallback">No compiled master integration reports allocated for this assignment context.</div>
                    <?php else: ?>
                        <?php foreach ($cs_records as $row): ?>
                            <article class="project-record-card" style="padding:24px;">
                                <div class="card-analytics-panel" style="padding:0; background:transparent; border:none;">
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-hashtag form-section-icon-code"></i> Focus Matrix</label>
                                        <strong><?= $escape($row['quarter']) ?></strong>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-circle-nodes form-section-icon-structure"></i> Macro Ceiling Target</label>
                                        <span><?= number_format((float)$row['cumulative_overall_target'], 2) ?>%</span>
                                        <label style="margin-top:4px;"><i class="fa-solid fa-compass form-section-icon-finance"></i> Macro Realised Progress</label>
                                        <span><?= number_format((float)$row['cumulative_overall_progress'], 2) ?>%</span>
                                    </div>
                                    <div class="analytic-datapoint span-all" style="margin-top:8px;">
                                        <label><i class="fa-solid fa-gauge-high form-section-icon-logistic"></i> Consolidated Evaluation Index Ratio</label>
                                        <div class="meter-container">
                                            <div class="meter-bar-rail">
                                                <div class="meter-bar-fill" style="width: <?= min(100, max(0, (float)($row['physical_progress_percentage'] ?? 0))) ?>%; background:#10b981;"></div>
                                            </div>
                                            <span class="meter-label-percentage"><?= number_format((float)$row['physical_progress_percentage'], 2) ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-action-bar" style="padding-top:16px;">
                                    <div></div>
                                    <div class="action-buttons">
                                        <a href="index.php?page=physical_progress_edit&type=cumulative_status&id=<?= $row['id'] ?>" class="btn-action btn-edit" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-pen"></i> Edit</a>
                                        <button type="button" class="btn-action btn-delete trigger-custom-delete" data-delete-url="index.php?page=physical_progress_edit&type=cumulative_status&delete_id=<?= $row['id'] ?>" data-record-context="Cumulative Status Profile Interval <?= $escape($row['quarter']) ?>" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-trash"></i> Delete</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- SECTION 4: FUNDING PARAMETERS -->
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div class="nested-grid-heading"><i class="fa-solid fa-sack-dollar" style="color:var(--icon-finance)"></i> Capital Financing Ledgers</div>
                    <?php if (empty($fn_records)): ?>
                        <div class="empty-catalog-fallback">No strategic cashflow streams recorded against active frameworks targets.</div>
                    <?php else: ?>
                        <?php foreach ($fn_records as $row): ?>
                            <article class="project-record-card" style="padding:24px;">
                                <div class="card-analytics-panel metrics-quad-row" style="padding:0; background:transparent; border:none;">
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-building-columns form-section-icon-structure"></i> Finance Stream Origin</label>
                                        <strong><?= $escape($row['funding_source']) ?></strong>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-money-check-dollar form-section-icon-finance"></i> Total Grant Scope Value</label>
                                        <span style="color:#059669; font-weight:700;">Rs. <?= number_format((float)$row['funding_amount'], 2) ?></span>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-regular fa-calendar-days form-section-icon-logistic"></i> Fiscal Block Year</label>
                                        <span><?= $escape($row['allocation_year']) ?></span>
                                    </div>
                                    <div class="analytic-datapoint">
                                        <label><i class="fa-solid fa-vault form-section-icon-code"></i> Budget Year Grant Block</label>
                                        <span style="font-weight:700;">Rs. <?= number_format((float)$row['allocation_amount'], 2) ?></span>
                                    </div>
                                </div>
                                <div class="card-action-bar" style="padding-top:16px;">
                                    <div></div>
                                    <div class="action-buttons">
                                        <a href="index.php?page=physical_progress_edit&type=funding&id=<?= $row['id'] ?>" class="btn-action btn-edit" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-pen"></i> Edit</a>
                                        <button type="button" class="btn-action btn-delete trigger-custom-delete" data-delete-url="index.php?page=physical_progress_edit&type=funding&delete_id=<?= $row['id'] ?>" data-record-context="Funding Grant - Block Year <?= $escape($row['allocation_year']) ?>" style="height:28px; padding:0 12px; font-size:11px;"><i class="fa fa-trash"></i> Delete</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Custom Glassmorphic Confirmation Modal System -->
<div class="custom-modal-backdrop" id="deleteConfirmationModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Confirm Record Deletion</h3>
        <p>Are you absolutely sure you want to permanently discard this progressive dataset entry from the tracker timeline index repository?
            <span class="modal-target-project-name" id="modalTargetLabel">Target Record Context</span>
        </p>
        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="modalDismissBtn">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalVerifyExecuteBtn">Confirm Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteActionTriggers = document.querySelectorAll('.trigger-custom-delete');
    const modalBackdrop = document.getElementById('deleteConfirmationModalBackdrop');
    const modalTargetLabel = document.getElementById('modalTargetLabel');
    const modalDismissBtn = document.getElementById('modalDismissBtn');
    const modalVerifyExecuteBtn = document.getElementById('modalVerifyExecuteBtn');
    
    let targetExecutionRedirectUrl = '';

    if (deleteActionTriggers.length > 0 && modalBackdrop) {
        deleteActionTriggers.forEach(function (button) {
            button.addEventListener('click', function () {
                targetExecutionRedirectUrl = this.getAttribute('data-delete-url');
                const uniqueContextString = this.getAttribute('data-record-context');
                
                if (modalTargetLabel) modalTargetLabel.textContent = uniqueContextString;
                modalBackdrop.classList.add('modal-visible');
            });
        });
    }

    if (modalDismissBtn && modalBackdrop) {
        modalDismissBtn.addEventListener('click', function () {
            modalBackdrop.classList.remove('modal-visible');
            targetExecutionRedirectUrl = '';
        });
    }

    if (modalVerifyExecuteBtn && modalBackdrop) {
        modalVerifyExecuteBtn.addEventListener('click', function () {
            if (targetExecutionRedirectUrl !== '') {
                modalBackdrop.classList.remove('modal-visible');
                window.location.href = targetExecutionRedirectUrl;
            }
        });
    }
});
</script>