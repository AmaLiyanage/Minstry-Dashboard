<?php
include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$id = (int)($_GET['id'] ?? 0);
$orgCode = trim((string)($_GET['org'] ?? ''));
$sector = trim((string)($_GET['sector'] ?? ''));

// Initialize variables here so they are always defined on initial page load (GET)
$message = '';
$messageType = '';

$sql = "SELECT * FROM projects WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($result);

if (!$project) {
    die("Project not found");
}

$toNullableInt = static function ($value): ?int {
    return $value === '' || $value === null ? null : (int)$value;
};

$toNullableFloat = static function ($value): ?float {
    return $value === '' || $value === null ? null : (float)$value;
};

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $institutionId = (int)($_POST['institution_id'] ?? 0);
    $divisionId = $toNullableInt($_POST['division_id'] ?? null);
    $projectName = trim((string)($_POST['project_name'] ?? ''));
    $projectCode = trim((string)($_POST['project_code'] ?? ''));
    $projectType = trim((string)($_POST['project_type'] ?? 'New'));
    $targetActivities = trim((string)($_POST['target_activities_2026'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $totalCostOriginal = $toNullableFloat($_POST['total_est_cost_original'] ?? null);
    $totalCostRevised = $toNullableFloat($_POST['total_est_cost_revised'] ?? null);
    $periodOriginal = trim((string)($_POST['project_period_original'] ?? ''));
    $periodRevised = trim((string)($_POST['project_period_revised'] ?? ''));
    $fundingSource = trim((string)($_POST['funding_source'] ?? ''));
    $allocationOriginal = $toNullableFloat($_POST['allocation_2026_original'] ?? null);
    $allocationRevised = $toNullableFloat($_POST['allocation_2026_revised'] ?? null);
    $timelineStatus = trim((string)($_POST['timeline_status'] ?? 'On Track'));
    $reasons = trim((string)($_POST['reasons_not_achieving_targets'] ?? ''));

    $update = "UPDATE projects SET
        category_id = ?,
        institution_id = ?,
        division_id = ?,
        project_name = ?,
        project_code = ?,
        project_type = ?,
        target_activities_2026 = ?,
        location = ?,
        total_est_cost_original = ?,
        total_est_cost_revised = ?,
        project_period_original = ?,
        project_period_revised = ?,
        funding_source = ?,
        allocation_2026_original = ?,
        allocation_2026_revised = ?,
        timeline_status = ?,
        reasons_not_achieving_targets = ?
        WHERE id = ?";

    $updateStmt = mysqli_prepare($conn, $update);

    mysqli_stmt_bind_param(
        $updateStmt,
        "iiisssssddsssddssi",
        $categoryId,
        $institutionId,
        $divisionId,
        $projectName,
        $projectCode,
        $projectType,
        $targetActivities,
        $location,
        $totalCostOriginal,
        $totalCostRevised,
        $periodOriginal,
        $periodRevised,
        $fundingSource,
        $allocationOriginal,
        $allocationRevised,
        $timelineStatus,
        $reasons,
        $id
    );

    if (mysqli_stmt_execute($updateStmt)) {
        // Fetch the assigned institution code to redirect to its specific list
        $newOrgCode = $orgCode;
        $instSql = "SELECT code, institution_name FROM institutions WHERE id = ?";
        $instStmt = mysqli_prepare($conn, $instSql);
        if ($instStmt) {
            mysqli_stmt_bind_param($instStmt, 'i', $institutionId);
            mysqli_stmt_execute($instStmt);
            $instRes = mysqli_stmt_get_result($instStmt);
            if ($instRow = mysqli_fetch_assoc($instRes)) {
                $fetchedCode = trim((string)$instRow['code']);
                $fetchedName = trim((string)$instRow['institution_name']);
                $newOrgCode = $fetchedCode !== '' ? $fetchedCode : $fetchedName;
            }
            mysqli_stmt_close($instStmt);
        }

        $redirectUrl = "index.php?page=project_list";
        if ($newOrgCode !== '') $redirectUrl .= "&org=" . urlencode($newOrgCode);
        if ($sector !== '') $redirectUrl .= "&sector=" . urlencode($sector);

        echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
        exit;
    }
}

$categories = [];
$catRes = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($catRes) {
    while ($row = mysqli_fetch_assoc($catRes)) {
        $categories[] = $row;
    }
}

$institutions = [];
$instRes = mysqli_query($conn, "SELECT id, code, institution_name FROM institutions ORDER BY institution_name ASC");
if ($instRes) {
    while ($row = mysqli_fetch_assoc($instRes)) {
        $institutions[] = $row;
    }
}

$divisions = [];
if ($project['institution_id']) {
    $divRes = mysqli_query($conn, "SELECT id, division_name FROM divisions WHERE institution_id = " . (int)$project['institution_id'] . " ORDER BY division_name ASC");
    if ($divRes) {
        while ($row = mysqli_fetch_assoc($divRes)) {
            $divisions[] = $row;
        }
    }
}
?>

<style>
    /* Executive Bright Blue Gradient & Uncrowded Canvas Tokens */
    .project-edit-page {
        --blue-primary: #1e40af;
        --blue-bright-gradient: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
        --light-mesh: radial-gradient(at 0% 0%, #e0f2fe 0px, transparent 55%),
                      radial-gradient(at 100% 100%, #e0e7ff 0px, transparent 55%),
                      #f8fafc;
        
        /* Custom Structural Functional Color Coding Rules for Inline Icons */
        --icon-structure: #4f46e5;    /* Vivid Indigo */
        --icon-code: #00b4d8;         /* Electric Cyan */
        --icon-logistic: #7c3aed;     /* Deep Violet */
        --icon-finance: #10b981;      /* Emerald Green */
        --icon-alert: #f43f5e;        /* Rose Red */
        
        --text-dark: #0f172a;
        --text-slate: #475569;
        --text-light: #94a3b8;
        --border-soft: #cbd5e1;
        --input-fill: #ffffff;
        
        --radius-window: 24px;
        --radius-card: 16px;
        --radius-control: 12px;
        
        --shadow-window: 0 20px 25px -5px rgba(15, 23, 42, 0.03), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
        --shadow-input: inset 0 2px 4px 0 rgba(0, 0, 0, 0.01), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
        --shadow-focus: 0 0 0 4px rgba(67, 100, 247, 0.16);

        max-width: 1220px;
        margin: 20px auto;
        padding: 24px;
        font-family: "Inter", system-ui, -apple-system, sans-serif;
        color: var(--text-slate);
        -webkit-font-smoothing: antialiased;
    }

    /* Outer Wrapper with Highly Refined Light Mesh Gradient Backing */
    .project-edit-shell {
        background: var(--light-mesh);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-window);
        box-shadow: var(--shadow-window);
        overflow: hidden;
        padding: 56px;
    }

    /* Fluid Styled High-Vibrancy Gradient Header */
    .project-edit-header {
        margin-bottom: 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding-bottom: 32px;
        border-bottom: 2px solid var(--border-soft);
    }

    .project-edit-title {
        margin: 0;
        color: var(--text-dark);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .project-edit-subtitle {
        margin: 6px 0 0;
        color: var(--text-slate);
        font-size: 14.5px;
    }

    .context-chip {
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

    /* Frosted Semi-Translucent White Panels */
    .form-section {
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-card);
        padding: 40px;
        margin-bottom: 44px; /* Spacious separation rules */
        box-shadow: var(--shadow-input);
    }

    .form-section:last-child {
        margin-bottom: 0;
    }

    /* Structured Section Group Subheadings */
    .section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin: 0 0 32px 0; 
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-soft);
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Broad Spacing Matrix (Fields are NOT too close to each other) */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 36px 40px; /* Highly expanded column and row gap layout fields */
    }

    /* Target Double Sub-Row Matrix Wrapper Rule */
    .form-grid.two-cols-split {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 36px 40px;
    }

    .form-grid.two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 36px 32px;
    }

    .form-field {
        display: flex;
        flex-direction: column;
        gap: 12px; /* Amplified space separating standard labels from textboxes */
    }

    .form-field.full {
        grid-column: 1 / -1 !important;
    }

    .form-field label {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 2px;
    }

    .form-field label i {
        margin-right: 8px;
        font-size: 14px;
        display: inline-block;
        width: 18px;
        text-align: center;
    }

    /* Color Assignment Variables Mapping Groups */
    .icon-structure { color: var(--icon-structure) !important; }
    .icon-code { color: var(--icon-code) !important; }
    .icon-logistic { color: var(--icon-logistic) !important; }
    .icon-finance { color: var(--icon-finance) !important; }
    .icon-alert { color: var(--icon-alert) !important; }

    .form-field input,
    .form-field select,
    .form-field textarea {
        width: 100%;
        min-height: 50px; /* Solid deep modern design inputs */
        padding: 12px 16px; 
        color: var(--text-dark);
        background: var(--input-fill);
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-control);
        font: inherit;
        font-size: 14px;
        outline: none;
        box-shadow: var(--shadow-input);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-field textarea {
        width: 100%;
        min-height: 120px;
        padding: 16px;
        color: var(--text-dark);
        background: var(--input-fill);
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-control);
        font: inherit;
        font-size: 14px;
        outline: none;
        box-shadow: var(--shadow-input);
        resize: vertical;
        line-height: 1.6;
        transition: all 0.2s ease;
    }

    .form-field input:focus,
    .form-field select:focus,
    .form-field textarea:focus {
        border-color: #4364f7;
        background: #ffffff;
        box-shadow: var(--shadow-focus);
    }

    .form-field input:focus + i,
    .form-field select:focus + i {
        transform: scale(1.15);
    }

    /* Block mapped read only select fields visual parameters override */
    .form-field select[style*="pointer-events"] {
        pointer-events: none;
        background-color: #f1f5f9 !important;
        color: var(--text-slate) !important;
        border-color: var(--border-soft) !important;
        box-shadow: none !important;
    }

    /* Progress Selector Highlight Index Customizing */
    select#timeline_status {
        color: #1e3a8a;
        font-weight: 700;
        background-color: #f0f7ff;
        border-color: #bae6fd;
    }

    /* Context Operations Notification Banner Alerts */
    .notice {
        padding: 16px 20px;
        border-radius: var(--radius-control);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .notice.success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #15803d;
    }

    .notice.error {
        background: #fff5f5;
        border: 1px solid #fee2e2;
        color: #991b1b;
    }

    .empty-helper {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #b45309;
        padding: 24px;
        border-radius: var(--radius-control);
        font-size: 14px;
        line-height: 1.6;
        font-weight: 600;
    }

    /* Operations Form Footer Layout Row Controls Bar */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: 28px;
        border-top: 2px solid var(--border-soft);
        margin-top: 16px;
        gap: 16px;
    }

    /* Dark Blue Button Styling Overrides */
    .btn-primary {
        border: none;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        color: #ffffff;
        min-height: 52px;
        padding: 0 40px;
        font-weight: 700;
        font-size: 14px;
        border-radius: var(--radius-control);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #020617 0%, #0f172a 100%);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.45);
        transform: translateY(-1px);
    }

    .btn-secondary {
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-control);
        background: #ffffff;
        color: var(--text-slate);
        min-height: 52px;
        padding: 0 32px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: all 0.15s ease;
    }

    .btn-secondary:hover {
        background: #f8fafc;
        color: var(--text-dark);
        border-color: var(--text-light);
    }

    /* Custom Glassmorphic Confirmation Modal System CSS */
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
        background: #eff6ff;
        color: #2563eb;
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
        color: var(--blue-primary);
        background: #f0f4ff;
        padding: 10px 14px;
        border-radius: var(--radius-control);
        margin-top: 12px;
        word-break: break-word;
    }

    .modal-action-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .modal-action-buttons button, 
    .modal-action-buttons .modal-btn-confirm {
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

    /* FIX: Set clear background and high contrast text visibility for the confirmation modal button */
    .modal-btn-confirm {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
    }

    .modal-btn-confirm:hover {
        background: linear-gradient(135deg, #020617 0%, #0f172a 100%) !important;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.45);
    }

    .project-edit-form br {
        display: none;
    }

    /* Screen Breakdowns Grid Modifiers Configurations */
    @media (max-width: 960px) {
        .project-edit-shell {
            padding: 40px 32px;
        }
    }

    @media (max-width: 640px) {
        .project-edit-shell {
            padding: 32px 20px;
        }

        .project-edit-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .form-grid, .form-grid.two-cols-split, .form-grid.two {
            grid-template-columns: 1fr !important;
            gap: 28px;
        }

        .btn-primary, .btn-secondary {
            width: 100%;
            justify-content: center;
        }

        .modal-action-buttons {
            flex-direction: column;
        }
        
        .modal-action-buttons button,
        .modal-action-buttons .modal-btn-confirm {
            width: 100%;
        }
    }
</style>

<div class="project-edit-page">
    <div class="project-edit-shell">
        <div class="project-edit-header">
            <div>
                <h1 class="project-edit-title">Edit Project</h1>
                <p class="project-edit-subtitle">Update information for <?= $escape($project['project_name']) ?>.</p>
            </div>
            <?php if ($orgCode !== '' || $sector !== ''): ?>
                <span class="context-chip">
                    <i class="fa fa-building"></i>
                    <?= $escape($orgCode ?: 'Institution') ?><?= $sector !== '' ? ' / ' . $escape(strtoupper($sector)) : '' ?>
                </span>
            <?php endif; ?>
        </div>

        <form class="project-edit-form" id="projectEditForm" method="POST">
            <?php if ($message !== ''): ?>
                <div class="banner-notification <?= $escape($messageType) ?>">
                    <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                    <?= $escape($message) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($categories) || empty($institutions)): ?>
                <div class="empty-helper">
                    Context indexing parent properties unallocated. Establish structural definition nodes in central directory arrays first.
                </div>
            <?php else: ?>
                <input type="hidden" name="create_project" value="1">

                <div class="form-section" <?= $orgCode === 'JCT' ? 'style="display: none;"' : '' ?>>
                    <h2 class="section-title">Structure</h2>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="category_id"><i class="fa-solid fa-layer-group icon-structure"></i> Category</label>
                            <select name="category_id" id="category_id" <?= $orgCode !== 'JCT' ? 'required' : '' ?>>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= $project['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                        <?= $escape($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="institution_id"><i class="fa-solid fa-building icon-structure"></i> Institution</label>
                            <select name="institution_id" id="institution_id" <?= $orgCode !== 'JCT' ? 'required' : '' ?>>
                                <option value="">Select institution</option>
                                <?php foreach ($institutions as $institution): ?>
                                    <option value="<?= $institution['id'] ?>" <?= $project['institution_id'] == $institution['id'] ? 'selected' : '' ?>>
                                        <?= $escape($institution['code'] ? $institution['code'] . ' - ' : '') ?><?= $escape($institution['institution_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="division_id"><i class="fa-solid fa-sitemap icon-structure"></i> Division</label>
                            <select name="division_id" id="division_id" <?= $orgCode !== 'JCT' ? 'required' : '' ?>>
                                <option value="">Select division</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?= $division['id'] ?>" <?= $project['division_id'] == $division['id'] ? 'selected' : '' ?>>
                                        <?= $escape($division['division_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Project Details</h2>
                    <div class="form-grid two-cols-split">
                        <div class="form-field">
                            <label for="project_code"><i class="fa-solid fa-barcode icon-code"></i> Project Code</label>
                            <input type="text" name="project_code" id="project_code" value="<?= $escape($project['project_code']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="project_type"><i class="fa-solid fa-folder-tree icon-code"></i> Project Type</label>
                            <select name="project_type" id="project_type" required>
                                <option value="New" <?= ($project['project_type'] ?? 'New') === 'New' ? 'selected' : '' ?>>New</option>
                                <option value="Continuous" <?= ($project['project_type'] ?? '') === 'Continuous' ? 'selected' : '' ?>>Continuous</option>
                            </select>
                        </div>

                        <div class="form-field full">
                            <label for="project_name"><i class="fa-solid fa-file-signature icon-code"></i> Project Name</label>
                            <input type="text" name="project_name" id="project_name" value="<?= $escape($project['project_name']) ?>" required>
                        </div>

                        <div class="form-field full">
                            <label for="target_activities_2026"><i class="fa-solid fa-bullseye icon-logistic"></i> Target Activities 2026</label>
                            <textarea name="target_activities_2026" id="target_activities_2026"><?= $escape($project['target_activities_2026']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Financials and Status</h2>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="location"><i class="fa-solid fa-location-dot icon-logistic"></i> Location</label>
                            <input type="text" name="location" id="location" value="<?= $escape($project['location']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="funding_source"><i class="fa-solid fa-sack-dollar icon-finance"></i> Funding Source</label>
                            <input type="text" name="funding_source" id="funding_source" value="<?= $escape($project['funding_source']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="timeline_status"><i class="fa-solid fa-bars-progress icon-logistic"></i> Timeline Status</label>
                            <select name="timeline_status" id="timeline_status">
                                <?php foreach (['On Track', 'Delayed', 'At Risk', 'Completed', 'Postponed', 'Terminated'] as $status): ?>
                                    <option value="<?= $escape($status) ?>" <?= $project['timeline_status'] === $status ? 'selected' : '' ?>>
                                        <?= $escape($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="total_est_cost_original"><i class="fa-solid fa-money-bill-wave icon-finance"></i> Total Est. Cost Original</label>
                            <input type="number" step="0.01" name="total_est_cost_original" id="total_est_cost_original" value="<?= $escape($project['total_est_cost_original']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="total_est_cost_revised"><i class="fa-solid fa-money-bill-trend-up icon-finance"></i> Total Est. Cost Revised</label>
                            <input type="number" step="0.01" name="total_est_cost_revised" id="total_est_cost_revised" value="<?= $escape($project['total_est_cost_revised']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="allocation_2026_original"><i class="fa-solid fa-coins icon-finance"></i> Allocation 2026 Original</label>
                            <input type="number" step="0.01" name="allocation_2026_original" id="allocation_2026_original" value="<?= $escape($project['allocation_2026_original']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="allocation_2026_revised"><i class="fa-solid fa-hand-holding-dollar icon-finance"></i> Allocation 2026 Revised</label>
                            <input type="number" step="0.01" name="allocation_2026_revised" id="allocation_2026_revised" value="<?= $escape($project['allocation_2026_revised']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="project_period_original"><i class="fa-regular fa-calendar icon-logistic"></i> Project Period Original</label>
                            <input type="text" name="project_period_original" id="project_period_original" value="<?= $escape($project['project_period_original']) ?>">
                        </div>

                        <div class="form-field">
                            <label for="project_period_revised"><i class="fa-regular fa-calendar-check icon-logistic"></i> Project Period Revised</label>
                            <input type="text" name="project_period_revised" id="project_period_revised" value="<?= $escape($project['project_period_revised']) ?>">
                        </div>

                        <div class="form-field full">
                            <label for="reasons_not_achieving_targets"><i class="fa-solid fa-circle-exclamation icon-alert"></i> Reasons Not Achieving Targets</label>
                            <textarea name="reasons_not_achieving_targets" id="reasons_not_achieving_targets"><?= $escape($project['reasons_not_achieving_targets']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <?php
                    $cancelUrl = "index.php?page=project_list";
                    if ($orgCode !== '') $cancelUrl .= "&org=" . urlencode($orgCode);
                    if ($sector !== '') $cancelUrl .= "&sector=" . urlencode($sector);
                    ?>
                    <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn-secondary">Cancel</a>
                    <button class="btn-primary" type="submit">
                        <i class="fa fa-save"></i> Update Project
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="custom-modal-backdrop" id="confirmationModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid fa-circle-question"></i>
        </div>
        <h3>Confirm Record Changes</h3>
        <p>Are you sure you want to commit these update modifications to the live dataset entry for:
            <span class="modal-target-project-name" id="modalProjectNameLabel">Project Title</span>
        </p>
        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="modalCancelButton">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalConfirmButton">Confirm Update</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const institutionSelect = document.getElementById('institution_id');
    const divisionSelect = document.getElementById('division_id');
    const projectEditForm = document.getElementById('projectEditForm');
    
    // Modal Selectors
    const modalBackdrop = document.getElementById('confirmationModalBackdrop');
    const modalProjectNameLabel = document.getElementById('modalProjectNameLabel');
    const modalCancelButton = document.getElementById('modalCancelButton');
    const modalConfirmButton = document.getElementById('modalConfirmButton');

    if (institutionSelect && divisionSelect) {
        function loadDivisions() {
            const institutionId = institutionSelect.value;
            divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

            if (!institutionId) {
                divisionSelect.innerHTML = '<option value="">Select institution first</option>';
                return;
            }

            fetch('pages/get_divisions.php?institution_id=' + encodeURIComponent(institutionId))
                .then(function (response) {
                    return response.json();
                })
                .then(function (divisions) {
                    let options = '<option value="">Select division</option>';
                    divisions.forEach(function (division) {
                        options += '<option value="' + division.id + '">' + division.division_name + '</option>';
                    });
                    divisionSelect.innerHTML = options;
                })
                .catch(function () {
                    divisionSelect.innerHTML = '<option value="">Could not load divisions</option>';
                });
        }

        institutionSelect.addEventListener('change', function () {
            loadDivisions();
        });
    }

    // Modal Confirmation Interception Hook System
    if (projectEditForm && modalBackdrop && modalProjectNameLabel) {
        projectEditForm.addEventListener('submit', function (event) {
            event.preventDefault(); // Stop standard direct post tracking
            
            // Extract the real-time project title string input dynamically
            const currentProjectTitle = document.getElementById('project_name') ? document.getElementById('project_name').value : 'Project';
            modalProjectNameLabel.textContent = currentProjectTitle;
            
            // Toggle the visibility CSS trigger rules class safely
            modalBackdrop.classList.add('modal-visible');
        });

        // Close backdrop event
        modalCancelButton.addEventListener('click', function () {
            modalBackdrop.classList.remove('modal-visible');
        });

        // Submit verified dispatch transaction form sequence
        modalConfirmButton.addEventListener('click', function () {
            modalBackdrop.classList.remove('modal-visible');
            projectEditForm.submit(); // Force the complete native post
        });
    }
});
</script>