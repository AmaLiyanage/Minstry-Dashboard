<?php

include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$project_id = (int) ($_GET['id'] ?? 0);
$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$division = trim((string)($_GET['division'] ?? 'all'));

$project = null;
$projects_list = [];
$projects_grouped = [];

if ($project_id > 0) {
    $project_sql = "
        SELECT project_name, project_code
        FROM projects
        WHERE id = $project_id
    ";
    
    $project_result = mysqli_query($conn, $project_sql);
    $project = mysqli_fetch_assoc($project_result);
} else {
    // Fetch financial records for the selected organization/division
    $sql = "SELECT p.id as project_id, p.project_name, p.project_code, f.id as fin_id, f.quarter,
                   f.cum_fin_target, f.actual_expenditure, f.bills_in_hand, f.cumulative_expenditure, f.financial_progress_percentage,
                   i.code as institution_code, i.institution_name, d.division_name
            FROM financial_progress f
            INNER JOIN projects p ON f.project_id = p.id
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
    $sql .= " ORDER BY p.project_name ASC, f.quarter ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $projects_list[] = $row;
            
            $pid = $row['project_id'];
            if (!isset($projects_grouped[$pid])) {
                $projects_grouped[$pid] = [
                    'project_id' => $row['project_id'],
                    'project_name' => $row['project_name'],
                    'project_code' => $row['project_code'],
                    'institution_code' => $row['institution_code'],
                    'institution_name' => $row['institution_name'],
                    'division_name' => $row['division_name'],
                    'financial_records' => []
                ];
            }
            $projects_grouped[$pid]['financial_records'][] = [
                'fin_id' => $row['fin_id'],
                'quarter' => $row['quarter'],
                'cum_fin_target' => $row['cum_fin_target'],
                'actual_expenditure' => $row['actual_expenditure'],
                'bills_in_hand' => $row['bills_in_hand'],
                'cumulative_expenditure' => $row['cumulative_expenditure'],
                'financial_progress_percentage' => $row['financial_progress_percentage']
            ];
        }
    }
}

$financial_records = [];
if ($project_id > 0) {
    $sql = "SELECT * FROM financial_progress WHERE project_id = $project_id ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $financial_records[] = $row;
        }
    }
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
        gap: 28px;
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
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease;
    }

    .project-record-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.08);
        border-color: rgba(67, 100, 247, 0.25);
    }

    /* Left Side Metadata Panel */
    .card-identity-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
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
        font-size: 19px;
        font-weight: 700;
        color: var(--text-dark);
        line-height: 1.4;
    }

    /* Right Side Financial / Logistical Analytics Mini-Grid */
    .card-analytics-panel {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px 24px;
        background: rgba(248, 250, 252, 0.5);
        padding: 24px;
        border-radius: var(--radius-control);
        border: 1px solid #e2e8f0;
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
        background: #10b981;
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
        grid-column: 1 / -1;
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
    }
    .btn-secondary:hover { background: #f8fafc; color: var(--text-dark); border-color: #94a3b8; }

    /* Empty dataset indicator fallback placeholder style card */
    .empty-catalog-fallback {
        background: rgba(255, 255, 255, 0.6);
        border: 2px dashed var(--border-soft);
        border-radius: var(--radius-card);
        padding: 64px;
        text-align: center;
        font-size: 15px;
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

    .modal-btn-cancel:hover {
        background: #f8fafc;
        border-color: var(--text-light);
    }

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

    /* Screen Responsiveness Layout Scaling Constraints */
    @media (max-width: 1024px) {
        .project-record-card {
            grid-template-columns: 1fr;
            gap: 24px;
        }
    }

    @media (max-width: 640px) {
        .form-canvas-container { padding: 32px 20px; }
        .form-canvas-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        .card-analytics-panel { grid-template-columns: 1fr; }
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
                <h1><i class="fa-solid fa-money-bill-wave"></i> Financial Ledger Workspace</h1>
                <p>Track and manage the financial progress records and allocations for active projects.</p>
            </div>
            <?php if ($orgCode !== '' || $division !== 'all'): ?>
                <div class="context-pill-badge">
                    <i class="fa-solid fa-server"></i>
                    <?= $escape($orgCode ?: 'Global Scope') ?><?= $division !== 'all' ? ' / ' . $escape($division) : '' ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!$project): ?>
            
            
            <div class="project-cards-workspace">
                <?php if (empty($projects_grouped)): ?>
                    <div class="empty-catalog-fallback">
                        <i class="fa-solid fa-folder-open" style="font-size: 32px; color: var(--text-light); margin-bottom: 12px; display: block;"></i>
                        No financial record instances matched systems criteria parameters within this inventory sector level.
                    </div>
                <?php else: ?>
                    <?php foreach ($projects_grouped as $p): ?>
                        <article class="project-record-card" style="display: flex; flex-direction: column; gap: 24px;">
                            <div class="card-identity-panel">
                                <div class="card-scope-badge-row">
                                    <span class="scope-micro-badge"><i class="fa-solid fa-hashtag"></i> Code: <?= $escape($p['project_code'] ?: '-') ?></span>
                                    <?php if ($orgCode !== 'JCT'): ?>
                                        <span class="scope-micro-badge"><i class="fa-solid fa-building"></i> <?= $escape($p['institution_code'] ?? $p['institution_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="card-project-title"><?= $escape($p['project_name']) ?></h2>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
                                <?php foreach ($p['financial_records'] as $record): ?>
                                    <div class="card-analytics-panel" style="margin: 0;">
                                        <div style="grid-column: span 2; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-soft); padding-bottom: 16px; margin-bottom: 8px;">
                                            <span class="scope-micro-badge quarter-badge"><i class="fa-regular fa-calendar"></i> <?= $escape($record['quarter']) ?></span>
                                            <div class="action-buttons">
                                                <a href="index.php?page=edit_financial&id=<?= $record['fin_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-edit" style="height: 28px; padding: 0 12px; font-size: 11px;">
                                                    <i class="fa fa-pen"></i> Edit
                                                </a>
                                                <button type="button" class="btn-action btn-delete trigger-custom-delete" data-delete-url="index.php?page=edit_financial&delete_id=<?= $record['fin_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" data-record-context="<?= $escape($p['project_name']) ?> (<?= $escape($record['quarter']) ?>)" style="height: 28px; padding: 0 12px; font-size: 11px;">
                                                    <i class="fa fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                        <div class="analytic-datapoint">
                                            <label><i class="fa-solid fa-crosshairs form-section-icon-code"></i> Target Base</label>
                                            <span><?= number_format((float)$record['cum_fin_target'], 2) ?></span>
                                        </div>
                                        <div class="analytic-datapoint">
                                            <label><i class="fa-solid fa-money-bill-wave form-section-icon-finance"></i> Actual Expenditure</label>
                                            <span><?= number_format((float)$record['actual_expenditure'], 2) ?></span>
                                        </div>
                                        <div class="analytic-datapoint" style="grid-column: span 2;">
                                            <label><i class="fa-solid fa-chart-line form-section-icon-logistic"></i> Consolidated Progress Ratio</label>
                                            <?php
                                            $finPerc = (float)($record['financial_progress_percentage'] ?? 0);
                                            if ($finPerc == 0 && (float)$record['cum_fin_target'] > 0) {
                                                $finPerc = (((float)$record['actual_expenditure'] + (float)$record['bills_in_hand']) / (float)$record['cum_fin_target']) * 100;
                                            }
                                            ?>
                                            <div class="meter-container">
                                                <div class="meter-bar-rail">
                                                <div class="meter-bar-fill" style="width: <?= min(100, max(0, $finPerc)) ?>%;"></div>
                                                </div>
                                                <span class="meter-label-percentage"><?= number_format($finPerc, 2) ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            
            <div class="card-identity-panel" style="margin-bottom: 32px;">
                <h2 class="card-project-title" style="font-size: 24px;">
                    <?= $escape($project['project_code'] ? $project['project_code'] . ' - ' : '') ?><?= $escape($project['project_name']) ?>
                </h2>
                <div class="action-buttons" style="margin-top: 8px;">
                    <a href="index.php?page=project_financial&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Fleet Index
                    </a>
                    <a href="index.php?page=add_financial&id=<?= $project_id ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-edit">
                        <i class="fa fa-plus"></i> Append Quadrant Ledger
                    </a>
                </div>
            </div>

            <div class="project-cards-workspace">
                <?php if (empty($financial_records)): ?>
                    <div class="empty-catalog-fallback">
                        <i class="fa-solid fa-folder-open" style="font-size: 32px; color: var(--text-light); margin-bottom: 12px; display: block;"></i>
                        No operational quadrant statements configured for this software module node allocation target yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($financial_records as $row): ?>
                        <article class="project-record-card">
                            <div class="card-identity-panel">
                                <div class="card-scope-badge-row">
                                    <span class="scope-micro-badge quarter-badge"><i class="fa-regular fa-calendar-check"></i> Operational Focus: <?= $escape($row['quarter']) ?></span>
                                </div>
                                <h3 style="margin:0; font-size:16px; font-weight:700; color: var(--text-dark);">Fiscal Audit Balance Performance Statement</h3>
                            </div>

                            <div class="card-analytics-panel">
                                <div class="analytic-datapoint">
                                    <label><i class="fa-solid fa-bullseye form-section-icon-code"></i> Target Ceiling</label>
                                    <span><?= number_format((float)$row['cum_fin_target'], 2) ?></span>
                                </div>
                                <div class="analytic-datapoint">
                                    <label><i class="fa-solid fa-money-bill-wave form-section-icon-finance"></i> Direct Expenditure</label>
                                    <span><?= number_format((float)$row['actual_expenditure'], 2) ?></span>
                                </div>
                                <div class="analytic-datapoint">
                                    <label><i class="fa-solid fa-receipt form-section-icon-alert"></i> Outstanding Bills</label>
                                    <span><?= number_format((float)$row['bills_in_hand'], 2) ?></span>
                                </div>
                                <div class="analytic-datapoint">
                                    <label><i class="fa-solid fa-calculator form-section-icon-structure"></i> Total Cumulative</label>
                                    <span><?= number_format((float)$row['cumulative_expenditure'], 2) ?></span>
                                </div>
                                <div class="analytic-datapoint" style="grid-column: span 2;">
                                    <label><i class="fa-solid fa-chart-line form-section-icon-finance"></i> Allocation Run-Rate Performance Yield</label>
                                    <?php
                                    $finPerc = (float)($row['financial_progress_percentage'] ?? 0);
                                    if ($finPerc == 0 && (float)$row['cum_fin_target'] > 0) {
                                        $finPerc = (((float)$row['actual_expenditure'] + (float)$row['bills_in_hand']) / (float)$row['cum_fin_target']) * 100;
                                    }
                                    ?>
                                    <div class="meter-container">
                                        <div class="meter-bar-rail">
                                        <div class="meter-bar-fill" style="width: <?= min(100, max(0, $finPerc)) ?>%;"></div>
                                        </div>
                                        <span class="meter-label-percentage"><?= number_format($finPerc, 2) ?>%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-action-bar">
                                <div></div>
                                <div class="action-buttons">
                                    <a href="index.php?page=edit_financial&id=<?= $row['id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-edit">
                                        <i class="fa fa-pen"></i> Update
                                    </a>
                                    <button type="button" class="btn-action btn-delete trigger-custom-delete" data-delete-url="index.php?page=edit_financial&delete_id=<?= $row['id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" data-record-context="Quadrant Segment <?= $escape($row['quarter']) ?>">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<div class="custom-modal-backdrop" id="deleteConfirmationModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Confirm Record Deletion</h3>
        <p>Are you absolutely sure you want to permanently discard this financial tracking ledger log node entry from the active dataset inventory?
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