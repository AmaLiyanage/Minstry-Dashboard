<?php
include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$sector = trim((string)($_GET['sector'] ?? ''));
$division = trim((string)($_GET['division'] ?? 'all'));
$prefillDivision = ($division !== 'all') ? $division : '';
$message = '';
$messageType = '';
$selectedDivisionIdPost = (int)($_POST['division_id'] ?? 0);

$toNullableInt = static function ($value): ?int {
    return $value === '' || $value === null ? null : (int)$value;
};

$toNullableFloat = static function ($value): ?float {
    return $value === '' || $value === null ? null : (float)$value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $projectCode = trim((string)($_POST['project_code'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $institutionId = (int)($_POST['institution_id'] ?? 0);
    $divisionId = $toNullableInt($_POST['division_id'] ?? null);
    $projectName = trim((string)($_POST['project_name'] ?? ''));
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

    if ($projectName === '' || $categoryId <= 0 || $institutionId <= 0 || ($orgCode !== 'JCT' && empty($divisionId))) {
        if ($orgCode === 'JCT') {
            $message = 'Please enter the project name.';
        } else {
            $message = 'Please select a category, institution, and division, then enter the project name.';
        }
        $messageType = 'error';
    } else {
        $sql = "INSERT INTO projects (
            project_code,
            category_id,
            institution_id,
            division_id,
            project_name,
            project_type,
            target_activities_2026,
            location,
            total_est_cost_original,
            total_est_cost_revised,
            project_period_original,
            project_period_revised,
            funding_source,
            allocation_2026_original,
            allocation_2026_revised,
            timeline_status,
            reasons_not_achieving_targets
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'siiissssddsssddss',
                $projectCode,
                $categoryId,
                $institutionId,
                $divisionId,
                $projectName,
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
                $reasons
            );

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Project created successfully.';
                $messageType = 'success';
                $_POST = [];
            } else {
                $message = 'Could not create project: ' . mysqli_error($conn);
                $messageType = 'error';
            }
        } else {
            $message = 'Could not prepare project save request: ' . mysqli_error($conn);
            $messageType = 'error';
        }
    }
}

$institutions = [];
$instSql = "SELECT i.id, i.code, i.institution_name, i.category_id, c.category_name
            FROM institutions i
            LEFT JOIN categories c ON i.category_id = c.id";
if ($orgCode !== '') {
    $orgCodeEscaped = mysqli_real_escape_string($conn, $orgCode);
    $instSql .= " WHERE UPPER(TRIM(i.code)) = '" . $orgCodeEscaped . "' OR UPPER(TRIM(i.institution_name)) LIKE '%" . $orgCodeEscaped . "%'";
}
$instSql .= " ORDER BY i.institution_name ASC";

$institutionResult = mysqli_query($conn, $instSql);
if ($institutionResult) {
    while ($row = mysqli_fetch_assoc($institutionResult)) {
        $institutions[] = $row;
    }
}

$categories = [];
$catSql = "SELECT id, category_name FROM categories";
if ($orgCode !== '') {
    $validCategoryIds = array_filter(array_unique(array_column($institutions, 'category_id')));
    if (!empty($validCategoryIds)) {
        $cleanIds = array_map('intval', $validCategoryIds);
        $catSql .= " WHERE id IN (" . implode(',', $cleanIds) . ")";
    } else {
        $catSql .= " WHERE id = -1";
    }
}
$catSql .= " ORDER BY category_name ASC";

$categoryResult = mysqli_query($conn, $catSql);
if ($categoryResult) {
    while ($row = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $row;
    }
}

$selectedInstitutionId = (int)($_POST['institution_id'] ?? 0);
$selectedCategoryId = (int)($_POST['category_id'] ?? 0);

if ($selectedInstitutionId <= 0) {
    if (count($institutions) === 1) {
        $selectedInstitutionId = (int)$institutions[0]['id'];
        $selectedCategoryId = (int)$institutions[0]['category_id'];
    } elseif ($orgCode !== '') {
        foreach ($institutions as $institution) {
            $codeMatch = strtoupper(trim((string)$institution['code'])) === $orgCode;
            $nameMatch = stripos(trim((string)$institution['institution_name']), $orgCode) !== false;
            if ($codeMatch || $nameMatch) {
                $selectedInstitutionId = (int)$institution['id'];
                $selectedCategoryId = (int)$institution['category_id'];
                break;
            }
        }
    }
}

if ($selectedCategoryId <= 0 && count($categories) === 1) {
    $selectedCategoryId = (int)$categories[0]['id'];
}
?>

<style>
    /* Executive Bright Blue Gradient & Uncrowded Canvas Tokens */
    .project-create-page {
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
    .project-create-shell {
        background: var(--light-mesh);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-window);
        box-shadow: var(--shadow-window);
        overflow: hidden;
        padding: 56px;
    }

    /* Fluid Styled High-Vibrancy Gradient Header */
    .project-create-header {
        margin-bottom: 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding-bottom: 32px;
        border-bottom: 2px solid var(--border-soft);
    }

    .project-create-title {
        margin: 0;
        color: var(--text-dark);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .project-create-subtitle {
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
        border: 1px solid rgba(255, 255, 255, 0.7);
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

    .btn-primary:active {
        transform: translateY(0);
    }

    .project-create-form br {
        display: none;
    }

    /* Screen Breakdowns Grid Modifiers Configurations */
    @media (max-width: 960px) {
        .project-create-shell {
            padding: 40px 32px;
        }
    }

    @media (max-width: 640px) {
        .project-create-shell {
            padding: 32px 20px;
        }

        .project-create-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .form-grid, .form-grid.two-cols-split, .form-grid.two {
            grid-template-columns: 1fr !important;
            gap: 28px;
        }

        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="project-create-page">
    <div class="project-create-shell">
        <div class="project-create-header">
            <div>
                <h1 class="project-create-title">Add Project</h1>
                <p class="project-create-subtitle">Create a project under the selected ministry structure.</p>
            </div>
            <?php if ($orgCode !== '' || $sector !== ''): ?>
                <span class="context-chip">
                    <i class="fa fa-building"></i>
                    <?= $escape($orgCode ?: 'Institution') ?><?= $sector !== '' ? ' / ' . $escape(strtoupper($sector)) : '' ?>
                </span>
            <?php endif; ?>
        </div>

        <form class="project-create-form" method="post" action="">
            <?php if ($message !== ''): ?>
                <div class="notice <?= $escape($messageType) ?>"><?= $escape($message) ?></div>
            <?php endif; ?>

            <?php if (empty($categories) || empty($institutions)): ?>
                <div class="empty-helper">
                    <?php if ($orgCode !== ''): ?>
                        No institution or category was found matching the organization code "<strong><?= $escape($orgCode) ?></strong>". Please verify that an institution with this code or name exists in the Organization Structure.
                    <?php else: ?>
                        Add at least one category and institution in Organization Structure before creating projects.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <input type="hidden" name="create_project" value="1">

                <div class="form-section" <?= $orgCode === 'JCT' ? 'style="display: none;"' : '' ?>>
                    <h2 class="section-title">Structure</h2>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="category_id"><i class="fa-solid fa-layer-group icon-structure"></i> Category</label>
                            <select name="category_id" id="category_id" <?= $orgCode !== 'JCT' ? 'required' : '' ?> <?= count($categories) === 1 ? 'style="pointer-events: none; background-color: #f1f5f9; color: #475569;" tabindex="-1"' : '' ?>>
                                <?php if (count($categories) !== 1): ?>
                                    <option value="">Select category</option>
                                <?php endif; ?>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryId = (int)$category['id']; ?>
                                    <option value="<?= $categoryId ?>" <?= $selectedCategoryId === $categoryId ? 'selected' : '' ?>>
                                        <?= $escape($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="institution_id"><i class="fa-solid fa-building icon-structure"></i> Institution</label>
                            <select name="institution_id" id="institution_id" <?= $orgCode !== 'JCT' ? 'required' : '' ?> <?= count($institutions) === 1 ? 'style="pointer-events: none; background-color: #f1f5f9; color: #475569;" tabindex="-1"' : '' ?>>
                                <?php if (count($institutions) !== 1): ?>
                                    <option value="">Select institution</option>
                                <?php endif; ?>
                                <?php foreach ($institutions as $institution): ?>
                                    <?php $institutionId = (int)$institution['id']; ?>
                                    <option
                                        value="<?= $institutionId ?>"
                                        data-category-id="<?= (int)$institution['category_id'] ?>"
                                        <?= $selectedInstitutionId === $institutionId ? 'selected' : '' ?>
                                    >
                                        <?= $escape($institution['code'] ? $institution['code'] . ' - ' : '') ?><?= $escape($institution['institution_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="division_id"><i class="fa-solid fa-sitemap icon-structure"></i> Division</label>
                            <select name="division_id" id="division_id" <?= $orgCode !== 'JCT' ? 'required' : '' ?> <?= $prefillDivision !== '' ? 'style="pointer-events: none; background-color: #f1f5f9; color: #475569;" tabindex="-1"' : '' ?>>
                                <option value="">Select institution first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Project Details</h2>
                    <div class="form-grid two-cols-split">
                        <div class="form-field">
                            <label for="project_code"><i class="fa-solid fa-barcode icon-code"></i> Project Code</label>
                            <input type="text" name="project_code" id="project_code" value="<?= $escape($_POST['project_code'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="project_type"><i class="fa-solid fa-folder-tree icon-code"></i> Project Type</label>
                            <?php $selectedType = $_POST['project_type'] ?? 'New'; ?>
                            <select name="project_type" id="project_type" required>
                                <option value="New" <?= $selectedType === 'New' ? 'selected' : '' ?>>New</option>
                                <option value="Continuous" <?= $selectedType === 'Continuous' ? 'selected' : '' ?>>Continuous</option>
                            </select>
                        </div>

                        <div class="form-field full">
                            <label for="project_name"><i class="fa-solid fa-file-signature icon-code"></i> Project Name</label>
                            <input type="text" name="project_name" id="project_name" value="<?= $escape($_POST['project_name'] ?? '') ?>" required>
                        </div>

                        <div class="form-field full">
                            <label for="target_activities_2026"><i class="fa-solid fa-bullseye icon-logistic"></i> Target Activities 2026</label>
                            <textarea name="target_activities_2026" id="target_activities_2026"><?= $escape($_POST['target_activities_2026'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Financials and Status</h2>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="location"><i class="fa-solid fa-location-dot icon-logistic"></i> Location</label>
                            <input type="text" name="location" id="location" value="<?= $escape($_POST['location'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="funding_source"><i class="fa-solid fa-sack-dollar icon-finance"></i> Funding Source</label>
                            <input type="text" name="funding_source" id="funding_source" value="<?= $escape($_POST['funding_source'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="timeline_status"><i class="fa-solid fa-bars-progress icon-logistic"></i> Timeline Status</label>
                            <?php $selectedStatus = $_POST['timeline_status'] ?? 'On Track'; ?>
                            <select name="timeline_status" id="timeline_status">
                                <?php foreach (['On Track', 'Delayed', 'At Risk', 'Completed', 'Postponed', 'Terminated'] as $status): ?>
                                    <option value="<?= $escape($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>>
                                        <?= $escape($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="total_est_cost_original"><i class="fa-solid fa-money-bill-wave icon-finance"></i> Total Est. Cost Original</label>
                            <input type="number" step="0.01" name="total_est_cost_original" id="total_est_cost_original" value="<?= $escape($_POST['total_est_cost_original'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="total_est_cost_revised"><i class="fa-solid fa-money-bill-trend-up icon-finance"></i> Total Est. Cost Revised</label>
                            <input type="number" step="0.01" name="total_est_cost_revised" id="total_est_cost_revised" value="<?= $escape($_POST['total_est_cost_revised'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="allocation_2026_original"><i class="fa-solid fa-coins icon-finance"></i> Allocation 2026 Original</label>
                            <input type="number" step="0.01" name="allocation_2026_original" id="allocation_2026_original" value="<?= $escape($_POST['allocation_2026_original'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="allocation_2026_revised"><i class="fa-solid fa-hand-holding-dollar icon-finance"></i> Allocation 2026 Revised</label>
                            <input type="number" step="0.01" name="allocation_2026_revised" id="allocation_2026_revised" value="<?= $escape($_POST['allocation_2026_revised'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="project_period_original"><i class="fa-regular fa-calendar icon-logistic"></i> Project Period Original</label>
                            <input type="text" name="project_period_original" id="project_period_original" value="<?= $escape($_POST['project_period_original'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label for="project_period_revised"><i class="fa-regular fa-calendar-check icon-logistic"></i> Project Period Revised</label>
                            <input type="text" name="project_period_revised" id="project_period_revised" value="<?= $escape($_POST['project_period_revised'] ?? '') ?>">
                        </div>

                        <div class="form-field full">
                            <label for="reasons_not_achieving_targets"><i class="fa-solid fa-circle-exclamation icon-alert"></i> Reasons Not Achieving Targets</label>
                            <textarea name="reasons_not_achieving_targets" id="reasons_not_achieving_targets"><?= $escape($_POST['reasons_not_achieving_targets'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn-primary" type="submit">
                        <i class="fa fa-save"></i> Save Project
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const institutionSelect = document.getElementById('institution_id');
    const categorySelect = document.getElementById('category_id');
    const divisionSelect = document.getElementById('division_id');
    
    const prefillDivisionStr = <?= json_encode($prefillDivision) ?>;
    const selectedDivisionIdPost = <?= $selectedDivisionIdPost ?>;

    if (!institutionSelect || !categorySelect || !divisionSelect) return;

    function syncCategory() {
        const selectedOption = institutionSelect.options[institutionSelect.selectedIndex];
        const categoryId = selectedOption ? selectedOption.getAttribute('data-category-id') : '';
        if (categoryId) {
            categorySelect.value = categoryId;
        }
    }

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
                
                const urlDivClean = prefillDivisionStr ? prefillDivisionStr.replace(/[^A-Z0-9]/ig, '').toUpperCase() : '';
                
                divisions.forEach(function (division) {
                    let isSelected = '';
                    if (selectedDivisionIdPost > 0 && selectedDivisionIdPost == division.id) {
                        isSelected = 'selected';
                    } else if (selectedDivisionIdPost === 0 && urlDivClean !== '') {
                        const dbDivClean = division.division_name.replace(/[^A-Z0-9]/ig, '').toUpperCase();
                        if (dbDivClean === urlDivClean || division.division_name.toUpperCase().includes(prefillDivisionStr.toUpperCase()) || prefillDivisionStr.toUpperCase().includes(division.division_name.toUpperCase())) {
                            isSelected = 'selected';
                        }
                    }
                    options += '<option value="' + division.id + '" ' + isSelected + '>' + division.division_name + '</option>';
                });
                divisionSelect.innerHTML = options;
            })
            .catch(function () {
                divisionSelect.innerHTML = '<option value="">Could not load divisions</option>';
            });
    }

    institutionSelect.addEventListener('change', function () {
        syncCategory();
        loadDivisions();
    });

    if (institutionSelect.value) {
        syncCategory();
        loadDivisions();
    }
});
</script>