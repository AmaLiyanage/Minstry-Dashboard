<?php
include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

// Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project_id'])) {
    $deleteId = (int)$_POST['delete_project_id'];
    if ($deleteId > 0) {
        $delSql = "DELETE FROM projects WHERE id = ?";
        $delStmt = mysqli_prepare($conn, $delSql);
        if ($delStmt) {
            mysqli_stmt_bind_param($delStmt, 'i', $deleteId);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);
        }
    }
}

$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$sector = trim((string)($_GET['sector'] ?? ''));
$division = trim((string)($_GET['division'] ?? 'all'));

// Fetch projects and join related table names
$sql = "SELECT p.*, i.institution_name, i.code as institution_code, c.category_name, d.division_name
        FROM projects p
        LEFT JOIN institutions i ON p.institution_id = i.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN divisions d ON p.division_id = d.id";

$conditions = [];
// Apply the same institution filter if the org code exists in the URL
if ($orgCode !== '') {
    $orgCodeEscaped = mysqli_real_escape_string($conn, $orgCode);
    $conditions[] = "(UPPER(TRIM(i.code)) = '" . $orgCodeEscaped . "' OR UPPER(TRIM(i.institution_name)) LIKE '%" . $orgCodeEscaped . "%')";
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

$sql .= " ORDER BY p.id ASC";

$projects = [];
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
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

    .scope-micro-badge i { color: var(--icon-structure); }

    .card-project-title {
        margin: 0;
        font-size: 19px;
        font-weight: 700;
        color: var(--text-dark);
        line-height: 1.4;
    }

    .card-project-desc {
        font-size: 13.5px;
        color: var(--text-slate);
        line-height: 1.55;
        margin: 0;
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

    .card-timestamp-wrapper {
        font-size: 12px;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Executive Control Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .status-on-track { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .status-delayed { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .status-at-risk { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .status-completed { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .status-postponed { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .status-terminated { background: #fff1f2; color: #9f1239; border: 1px solid #ffe4e6; }

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
        border: none;
        cursor: pointer;
        color: #ffffff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .btn-view { background: #0284c7; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.15); }
    .btn-view:hover { background: #0369a1; transform: translateY(-1px); }
    .btn-edit { background: #2563eb; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.15); }
    .btn-edit:hover { background: #1d4ed8; transform: translateY(-1px); }
    .btn-delete { background: #ef4444; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15); }
    .btn-delete:hover { background: #dc2626; transform: translateY(-1px); }

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

    /* Modal Modifiers Base Layout */
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
        width: 95%;
        max-width: 680px; 
        padding: 32px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.92);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-align: left;
    }

    .custom-modal-backdrop.modal-visible .custom-confirmation-modal {
        transform: scale(1);
    }

    .modal-status-icon {
        width: 56px;
        height: 56px;
        background: #fef2f2;
        color: #ef4444;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 20px;
    }

    .modal-status-icon.info-theme {
        background: #eff6ff;
        color: #3b82f6;
        margin: 0 0 16px 0;
    }

    .custom-confirmation-modal h3 {
        margin: 0 0 16px 0;
        color: var(--text-dark);
        font-size: 20px;
        font-weight: 700;
        border-bottom: 1px solid var(--border-soft);
        padding-bottom: 12px;
    }

    .modal-target-project-name {
        display: block;
        font-weight: 700;
        color: #b91c1c;
        background: #fff5f5;
        padding: 10px 14px;
        border-radius: 8px;
        margin-top: 12px;
        word-break: break-word;
    }

    .modal-action-buttons {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        border-top: 1px solid var(--border-soft);
        padding-top: 16px;
        margin-top: 24px;
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
        background: linear-gradient(135deg, #7f1d1d 0%, #ef4444 100%) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
    }

    .modal-btn-confirm:hover {
        background: linear-gradient(135deg, #9f1239 0%, #dc2626 100%) !important;
        box-shadow: 0 6px 16px rgba(239, 68, 68, 0.45);
    }

    /* Modal Specifications Data Grid Mapping */
    .modal-spec-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 24px;
    }

    .modal-spec-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .modal-spec-cell.span-all {
        grid-column: span 2;
    }

    .modal-spec-cell label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-light);
    }

    .modal-spec-cell span {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--text-dark);
    }

    /* Screen Responsiveness Layout Scaling Constraints */
    @media (max-width: 1024px) {
        .project-record-card {
            grid-template-columns: 1fr;
            gap: 24px;
        }
    }

    @media (max-width: 640px) {
        .form-canvas-container {
            padding: 32px 20px;
        }

        .project-list-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .card-analytics-panel, .modal-spec-grid {
            grid-template-columns: 1fr;
        }

        .modal-spec-cell.span-all {
            grid-column: span 1;
        }

        .card-action-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .action-buttons {
            flex-direction: column;
            width: 100%;
        }

        .action-buttons .btn-action {
            width: 100%;
        }
    }
</style>

<div class="project-list-page">
    <div class="form-canvas-container">
        
        <header class="form-canvas-header">
            <div>
                <h1><i class="fa-solid fa-list-check"></i> Project Inventory Workspace</h1>
                <p>Overview of all active registered items records matching systemic tracking rules.</p>
            </div>
            <?php if ($orgCode !== '' || $sector !== ''): ?>
                <div class="context-pill-badge">
                    <i class="fa-solid fa-server"></i>
                    <?= $escape($orgCode ?: 'Global Scope') ?><?= $sector !== '' ? ' / ' . $escape($sector) : '' ?><?= $division !== 'all' ? ' / ' . $escape($division) : '' ?>
                </div>
            <?php endif; ?>
        </header>

        <div class="project-cards-workspace">
            <?php if (empty($projects)): ?>
                <div class="empty-catalog-fallback">
                    <i class="fa-solid fa-folder-open" style="font-size: 32px; color: var(--text-light); margin-bottom: 12px; display: block;"></i>
                    No data metric entries matched systems filters indexing templates for this repository scope selection.
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <article class="project-record-card">
                        
                        <div class="card-identity-panel">
                            <div class="card-scope-badge-row">
                                <span class="scope-micro-badge"><i class="fa-solid fa-hashtag"></i> Code: <?= $escape($project['project_code']) ?></span>
                                <span class="scope-micro-badge"><i class="fa-solid fa-code-fork"></i> <?= $escape($project['project_type'] ?? 'New') ?></span>
                                <?php if ($orgCode !== 'JCT'): ?>
                                    <span class="scope-micro-badge"><i class="fa-solid fa-tag"></i> <?= $escape($project['category_name']) ?></span>
                                    <span class="scope-micro-badge"><i class="fa-solid fa-building"></i> <?= $escape($project['institution_code'] ?? $project['institution_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <h2 class="card-project-title"><?= $escape($project['project_name']) ?></h2>
                            
                            <?php if(!empty($project['target_activities_2026'])): ?>
                                <p class="card-project-desc truncate-text" style="max-width: 100%;">
                                    <strong>Roadmap 2026:</strong> <?= $escape($project['target_activities_2026']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="card-analytics-panel">
                            <div class="analytic-datapoint">
                                <label><i class="fa-solid fa-map-pin form-section-icon-code"></i> Location Site</label>
                                <span><?= !empty($project['location']) ? $escape($project['location']) : 'Not mapped' ?></span>
                            </div>
                            <div class="analytic-datapoint">
                                <label><i class="fa-solid fa-vault form-section-icon-finance"></i> Funding Provider</label>
                                <span><?= !empty($project['funding_source']) ? $escape($project['funding_source']) : 'Treasury Core' ?></span>
                            </div>
                            <div class="analytic-datapoint">
                                <label><i class="fa-solid fa-money-bill-wave form-section-icon-finance"></i> Estimated Cost (Orig / Rev)</label>
                                <span><?= $project['total_est_cost_original'] ? number_format((float)$project['total_est_cost_original'], 2) : '-' ?> / <?= $project['total_est_cost_revised'] ? number_format((float)$project['total_est_cost_revised'], 2) : '-' ?></span>
                            </div>
                            <div class="analytic-datapoint">
                                <label><i class="fa-solid fa-hourglass-half form-section-icon-logistic"></i> Duration Period (Orig / Rev)</label>
                                <span><?= !empty($project['project_period_original']) ? $escape($project['project_period_original']) : '-' ?> / <?= !empty($project['project_period_revised']) ? $escape($project['project_period_revised']) : '-' ?></span>
                            </div>
                            <div class="analytic-datapoint" style="grid-column: span 2;">
                                <label><i class="fa-solid fa-coins form-section-icon-finance"></i> Fiscal 2026 Allocation Balance</label>
                                <span style="color: var(--blue-primary); font-size:15px; font-weight:700;">
                                    <?= $project['allocation_2026_original'] ? number_format((float)$project['allocation_2026_original'], 2) : '-' ?> Orig &rarr; <?= $project['allocation_2026_revised'] ? number_format((float)$project['allocation_2026_revised'], 2) : '-' ?> Rev
                                </span>
                            </div>
                        </div>

                        <div class="card-action-bar">
                            <div class="card-timestamp-wrapper">
                                <?php
                                $status = $project['timeline_status'] ?? 'On Track';
                                $statusClass = 'status-on-track';
                                if ($status === 'Delayed') $statusClass = 'status-delayed';
                                elseif ($status === 'At Risk') $statusClass = 'status-at-risk';
                                elseif ($status === 'Completed') $statusClass = 'status-completed';
                                elseif ($status === 'Postponed') $statusClass = 'status-postponed';
                                elseif ($status === 'Terminated') $statusClass = 'status-terminated';
                                ?>
                                <span class="status-badge <?= $statusClass ?>"><?= $escape($status) ?></span>
                                <span style="margin-left: 12px;"><i class="fa-regular fa-clock"></i> Registered: <?= date('Y-m-d H:i', strtotime($project['created_at'])) ?></span>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="button" class="btn-action btn-view trigger-custom-view" data-json="<?= $escape(json_encode($project)) ?>">
                                    <i class="fa-solid fa-eye"></i> View All
                                </button>
                                <a href="index.php?page=project_edit&id=<?= $project['id'] ?>&org=<?= urlencode($orgCode) ?>&sector=<?= urlencode($sector) ?>" class="btn-action btn-edit" title="Edit Data Record Fields">
                                    <i class="fa fa-pen"></i> Edit
                                </a>
                                <button type="button" class="btn-action btn-delete trigger-custom-delete" data-id="<?= $project['id'] ?>" data-name="<?= $escape($project['project_name']) ?>" title="Purge Record From Catalog Index">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>

                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<form id="nativeDeleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="delete_project_id" id="nativeDeleteInput" value="">
</form>

<div class="custom-modal-backdrop" id="viewModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon info-theme">
            <i class="fa-solid fa-folder-tree"></i>
        </div>
        <h3 id="viewModalTitle">Project File Registry Spec</h3>
        
        <div class="modal-spec-grid">
            <div class="modal-spec-cell">
                <label>Project Tracking Code</label>
                <span id="specProjCode">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Classification Operational Type</label>
                <span id="specProjType">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Structural Category Mapping</label>
                <span id="specCategoryName">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Parent Institution Unit</label>
                <span id="specInstitutionName">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Accountable Managed Division</label>
                <span id="specDivisionName">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Geographic Deployment Coordinates</label>
                <span id="specLocation">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Baseline Valuation Cost (Original)</label>
                <span id="specCostOriginal">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Baseline Valuation Cost (Revised)</label>
                <span id="specCostRevised">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Lifecycle Scheduled Period (Original)</label>
                <span id="specPeriodOriginal">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Lifecycle Scheduled Period (Revised)</label>
                <span id="specPeriodRevised">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Funding Streams Channel Source</label>
                <span id="specFundingSource">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Current Progress Timeline Status</label>
                <span id="specTimelineStatus">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Fiscal 2026 Allocation (Original Baseline)</label>
                <span id="specAllocOriginal">-</span>
            </div>
            <div class="modal-spec-cell">
                <label>Fiscal 2026 Allocation (Revised Baseline)</label>
                <span id="specAllocRevised">-</span>
            </div>
            <div class="modal-spec-cell span-all">
                <label>Target Scope Actions Roadmap Milestones (2026)</label>
                <span id="specTargetActivities" style="white-space: pre-wrap; line-height: 1.5; font-weight: 500;">-</span>
            </div>
            <div class="modal-spec-cell span-all">
                <label>Explanatory Constraints Bottleneck Assessment Log</label>
                <span id="specReasons" style="white-space: pre-wrap; line-height: 1.5; font-weight: 500; color: var(--icon-alert);">-</span>
            </div>
        </div>

        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="viewModalCloseButton">Close Workspace View</button>
        </div>
    </div>
</div>

<div class="custom-modal-backdrop" id="deleteModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3>Confirm Record Deletion</h3>
        <p>Are you sure you want to permanently remove this project tracking record from the active inventory catalog?
            <span class="modal-target-project-name" id="modalProjectNameLabel">Project Title</span>
        </p>
        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="modalCancelButton">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalConfirmButton">Confirm Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // View Modal Selectors
    const viewButtons = document.querySelectorAll('.trigger-custom-view');
    const viewBackdrop = document.getElementById('viewModalBackdrop');
    const viewModalTitle = document.getElementById('viewModalTitle');
    const viewModalCloseButton = document.getElementById('viewModalCloseButton');

    // Delete Modal Selectors
    const deleteButtons = document.querySelectorAll('.trigger-custom-delete');
    const deleteBackdrop = document.getElementById('deleteModalBackdrop');
    const modalProjectNameLabel = document.getElementById('modalProjectNameLabel');
    const modalCancelButton = document.getElementById('modalCancelButton');
    const modalConfirmButton = document.getElementById('modalConfirmButton');
    
    const nativeDeleteForm = document.getElementById('nativeDeleteForm');
    const nativeDeleteInput = document.getElementById('nativeDeleteInput');
    
    let activeTargetId = null;

    // Helper: Formatter
    function formatCurrency(val) {
        return val ? parseFloat(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-';
    }

    // Modal view display logic parsing routine
    if (viewButtons.length > 0 && viewBackdrop) {
        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const data = JSON.parse(this.getAttribute('data-json'));
                
                if (viewModalTitle) viewModalTitle.textContent = data.project_name || 'Project Details';
                
                document.getElementById('specProjCode').textContent = data.project_code || '-';
                document.getElementById('specProjType').textContent = data.project_type || 'New';
                document.getElementById('specCategoryName').textContent = data.category_name || '-';
                document.getElementById('specInstitutionName').textContent = data.institution_name || '-';
                document.getElementById('specDivisionName').textContent = data.division_name || '-';
                document.getElementById('specLocation').textContent = data.location || 'Not mapped';
                document.getElementById('specFundingSource').textContent = data.funding_source || 'Treasury Core';
                document.getElementById('specTimelineStatus').textContent = data.timeline_status || 'On Track';
                
                document.getElementById('specCostOriginal').textContent = formatCurrency(data.total_est_cost_original);
                document.getElementById('specCostRevised').textContent = formatCurrency(data.total_est_cost_revised);
                document.getElementById('specAllocOriginal').textContent = formatCurrency(data.allocation_2026_original);
                document.getElementById('specAllocRevised').textContent = formatCurrency(data.allocation_2026_revised);
                
                document.getElementById('specPeriodOriginal').textContent = data.project_period_original || '-';
                document.getElementById('specPeriodRevised').textContent = data.project_period_revised || '-';
                document.getElementById('specTargetActivities').textContent = data.target_activities_2026 || 'No milestones added.';
                document.getElementById('specReasons').textContent = data.reasons_not_achieving_targets || 'No constraints reported.';
                
                viewBackdrop.classList.add('modal-visible');
            });
        });

        viewModalCloseButton.addEventListener('click', function () {
            viewBackdrop.classList.remove('modal-visible');
        });
    }

    // Modal Delete Interception Hook System
    if (deleteButtons.length > 0 && deleteBackdrop && nativeDeleteForm && nativeDeleteInput) {
        deleteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeTargetId = this.getAttribute('data-id');
                const projectName = this.getAttribute('data-name');
                
                modalProjectNameLabel.textContent = projectName;
                deleteBackdrop.classList.add('modal-visible');
            });
        });

        modalCancelButton.addEventListener('click', function () {
            deleteBackdrop.classList.remove('modal-visible');
            activeTargetId = null;
        });

        modalConfirmButton.addEventListener('click', function () {
            if (activeTargetId) {
                nativeDeleteInput.value = activeTargetId;
                deleteBackdrop.classList.remove('modal-visible');
                nativeDeleteForm.submit();
            }
        });
    }
});
</script>