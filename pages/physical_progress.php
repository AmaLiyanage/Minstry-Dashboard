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

$message = '';
$messageType = '';
$activeTab = 'physical';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];
    $post_project_id = (int) $_POST['project_id'];
    
    if ($action_type === 'quarterly_progress') $activeTab = 'quarterly';
    if ($action_type === 'cumulative_status') $activeTab = 'cumulative';
    if ($action_type === 'funding') $activeTab = 'funding';
    
    $redirectUrl = "index.php?page=physical_progress_display&id=" . $post_project_id;
    if ($orgCode !== '') $redirectUrl .= "&org=" . urlencode($orgCode);
    if ($division !== 'all') $redirectUrl .= "&division=" . urlencode($division);
    
    if ($action_type === 'physical_targets') {
        $quarter = $_POST['quarter'];
        $overall_physical_target = (float) $_POST['overall_physical_target'];
        $progress_31_12_25 = (float) $_POST['progress_31_12_25'];
        $descriptive_target = trim($_POST['descriptive_target']);
        $descriptive_progress = trim($_POST['descriptive_progress']);

        $cumulative_progress = $progress_31_12_25 + $overall_physical_target;
        $actual_physical_progress = $overall_physical_target;
        
        $check_sql = "SELECT id FROM physical_targets WHERE project_id = ? AND quarter = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $post_project_id, $quarter);
        mysqli_stmt_execute($check_stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
            $message = "Quarter already exists for Physical Targets.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO physical_targets (project_id, quarter, overall_physical_target, progress_31_12_25, actual_physical_progress, descriptive_target, descriptive_progress, cumulative_progress) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isdddssd", $post_project_id, $quarter, $overall_physical_target, $progress_31_12_25, $actual_physical_progress, $descriptive_target, $descriptive_progress, $cumulative_progress);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Physical Target saved successfully. Redirecting...";
                $messageType = "success";
                echo "<script>setTimeout(function() { window.location.href = '$redirectUrl'; }, 1500);</script>";
            } else {
                $message = "Error: " . mysqli_error($conn);
                $messageType = "error";
            }
        }
    } elseif ($action_type === 'quarterly_progress') {
        $quarter = $_POST['quarter'];
        $cumulative_quarterly_target = (float) $_POST['cumulative_quarterly_target'];
        $cumulative_quarterly_progress = (float) $_POST['cumulative_quarterly_progress'];
        $descriptive_cumulative_progress = trim($_POST['descriptive_cumulative_progress']);
        $current_quarterly_target = trim($_POST['current_quarterly_target']);
        $current_quarterly_progress = trim($_POST['current_quarterly_progress']);
        
        $progress_percentage = 0;
        if ($cumulative_quarterly_target > 0) {
            $progress_percentage = ($cumulative_quarterly_progress / $cumulative_quarterly_target) * 100;
        }
        
        $check_sql = "SELECT id FROM quarterly_physical_progress WHERE project_id = ? AND quarter = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $post_project_id, $quarter);
        mysqli_stmt_execute($check_stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
            $message = "Quarter already exists for Quarterly Progress.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO quarterly_physical_progress (project_id, quarter, cumulative_quarterly_target, cumulative_quarterly_progress, progress_percentage, descriptive_cumulative_progress, current_quarterly_target, current_quarterly_progress) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isdddsss", $post_project_id, $quarter, $cumulative_quarterly_target, $cumulative_quarterly_progress, $progress_percentage, $descriptive_cumulative_progress, $current_quarterly_target, $current_quarterly_progress);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Quarterly Progress saved successfully. Redirecting...";
                $messageType = "success";
                echo "<script>setTimeout(function() { window.location.href = '$redirectUrl'; }, 1500);</script>";
            } else {
                $message = "Error: " . mysqli_error($conn);
                $messageType = "error";
            }
        }
    } elseif ($action_type === 'cumulative_status') {
        $quarter = $_POST['quarter'];
        $cumulative_overall_target = (float) $_POST['cumulative_overall_target'];
        $cumulative_overall_progress = (float) $_POST['cumulative_overall_progress'];
        $physical_progress_percentage = 0;
        if ($cumulative_overall_target > 0) {
            $physical_progress_percentage = ($cumulative_overall_progress / $cumulative_overall_target) * 100;
        }

        $check_sql = "SELECT id FROM cumulative_physical_status WHERE project_id = ? AND quarter = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $post_project_id, $quarter);
        mysqli_stmt_execute($check_stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
            $message = "Quarter already exists for Cumulative Status.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO cumulative_physical_status (project_id, quarter, cumulative_overall_target, cumulative_overall_progress, physical_progress_percentage) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isddd", $post_project_id, $quarter, $cumulative_overall_target, $cumulative_overall_progress, $physical_progress_percentage);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Cumulative Status saved successfully. Redirecting...";
                $messageType = "success";
                echo "<script>setTimeout(function() { window.location.href = '$redirectUrl'; }, 1500);</script>";
            } else {
                $message = "Error: " . mysqli_error($conn);
                $messageType = "error";
            }
        }
    } elseif ($action_type === 'funding') {
        $funding_source = trim($_POST['funding_source']);
        $funding_amount = (float) $_POST['funding_amount'];
        $allocation_year = (int) $_POST['allocation_year'];
        $allocation_amount = (float) $_POST['allocation_amount'];
        
        $sql = "INSERT INTO funding (project_id, funding_source, funding_amount, allocation_year, allocation_amount) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isdid", $post_project_id, $funding_source, $funding_amount, $allocation_year, $allocation_amount);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Funding details saved successfully. Redirecting...";
            $messageType = "success";
            echo "<script>setTimeout(function() { window.location.href = '$redirectUrl'; }, 1500);</script>";
        } else {
            $message = "Error: " . mysqli_error($conn);
            $messageType = "error";
        }
    }
}

if ($project_id > 0) {
    $project_sql = "
        SELECT project_name, project_code
        FROM projects
        WHERE id = $project_id
    ";
    
    $project_result = mysqli_query($conn, $project_sql);
    $project = mysqli_fetch_assoc($project_result);
} else {
    $sql = "SELECT p.id, p.project_name, p.project_code,
                   (SELECT GROUP_CONCAT(quarter) FROM physical_targets WHERE project_id = p.id) as pt_quarters,
                   (SELECT GROUP_CONCAT(quarter) FROM quarterly_physical_progress WHERE project_id = p.id) as qp_quarters,
                   (SELECT GROUP_CONCAT(quarter) FROM cumulative_physical_status WHERE project_id = p.id) as cs_quarters
            FROM projects p
            LEFT JOIN institutions i ON p.institution_id = i.id
            LEFT JOIN divisions d ON p.division_id = d.id";
            
    $conditions = [];
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
    $sql .= " ORDER BY p.project_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $projects_list[] = $row;
        }
    }
}

?>

<style>
    /* Executive Bright Blue Gradient & Uncrowded Canvas Tokens */
    .physical-progress-page {
        --blue-primary: #1e40af;
        --blue-bright-gradient: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
        --light-mesh: radial-gradient(at 0% 0%, #e0f2fe 0px, transparent 55%),
                      radial-gradient(at 100% 100%, #e0e7ff 0px, transparent 55%),
                      #f8fafc;
        
        /* Structural Functional Color Coding Rules for Inline Icons */
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
    .physical-progress-shell {
        background: var(--light-mesh);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-window);
        box-shadow: var(--shadow-window);
        overflow: hidden;
        padding: 56px;
    }

    /* Fluid Styled High-Vibrancy Gradient Header */
    .physical-progress-header {
        margin-bottom: 36px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding-bottom: 32px;
        border-bottom: 2px solid var(--border-soft);
    }

    .physical-progress-title {
        margin: 0;
        color: var(--text-dark);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .physical-progress-subtitle {
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

    /* Professional Navigation Tabs System Controls */
    .tab-navigation-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 44px;
        padding: 8px;
        background: rgba(241, 245, 249, 0.7);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-card);
    }

    .tab-trigger {
        padding: 12px 24px;
        background: transparent;
        color: var(--text-slate);
        border: none;
        cursor: pointer;
        border-radius: var(--radius-control);
        font-weight: 700;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .tab-trigger:hover {
        background: rgba(255, 255, 255, 0.6);
        color: var(--text-dark);
    }

    .tab-trigger.active-tab {
        background: #ffffff;
        color: #2563eb;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
    }

    /* Content Area Visibilities */
    .tab-panel {
        display: none;
    }

    .tab-panel.active-panel {
        display: block;
    }

    /* Frosted Panels */
    .form-section {
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.7);
        border-radius: var(--radius-card);
        padding: 40px;
        box-shadow: var(--shadow-input);
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

    /* Broad Spacing Matrix Layout */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 36px 40px;
    }

    .form-field {
        display: flex;
        flex-direction: column;
        gap: 12px;
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

    /* Color Assignment Layout Variables Map */
    .icon-structure { color: var(--icon-structure) !important; }
    .icon-code { color: var(--icon-code) !important; }
    .icon-logistic { color: var(--icon-logistic) !important; }
    .icon-finance { color: var(--icon-finance) !important; }
    .icon-alert { color: var(--icon-alert) !important; }

    .form-field input,
    .form-field select,
    .form-field textarea {
        width: 100%;
        min-height: 50px;
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
        min-height: 120px;
        padding: 16px;
        resize: vertical;
        line-height: 1.6;
    }

    .form-field input:focus,
    .form-field select:focus,
    .form-field textarea:focus {
        border-color: #4364f7;
        background: #ffffff;
        box-shadow: var(--shadow-focus);
    }

    /* Context Operations Info Helpers */
    .info-box-helper {
        background: #f1f5f9;
        border: 1px solid var(--border-soft);
        color: var(--text-slate);
        padding: 20px 24px;
        border-radius: var(--radius-control);
        font-size: 14.5px;
        line-height: 1.6;
        font-weight: 600;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .info-box-helper strong {
        color: var(--text-dark);
    }

    /* Notification Banners */
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

    .notice.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .notice.error { background: #fff5f5; border: 1px solid #fee2e2; color: #991b1b; }

    /* Actions buttons footer styling */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: 28px;
        border-top: 2px solid var(--border-soft);
        margin-top: 16px;
        gap: 16px;
    }

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

    .physical-progress-form br {
        display: none;
    }

    /* Screen Breakdowns Grid Configurations */
    @media (max-width: 960px) {
        .physical-progress-shell { padding: 40px 32px; }
    }

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr !important; gap: 28px; }
        .tab-navigation-bar { flex-direction: column; gap: 4px; padding: 4px; }
        .tab-trigger { width: 100%; justify-content: flex-start; }
    }

    @media (max-width: 640px) {
        .physical-progress-shell { padding: 32px 20px; }
        .physical-progress-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        .btn-primary { width: 100%; justify-content: center; }
    }
</style>

<div class="physical-progress-page">
    <div class="physical-progress-shell">
        
        <div class="physical-progress-header">
            <div>
                <h1 class="physical-progress-title">Physical Progress Entries</h1>
                <p class="physical-progress-subtitle">Record targets, quarterly performance and funding definitions array structures.</p>
            </div>
            <?php if ($orgCode !== ''): ?>
                <span class="context-chip">
                    <i class="fa fa-building"></i>
                    <?= $escape($orgCode) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($message !== ''): ?>
            <div class="notice <?= $escape($messageType) ?>"><?= $escape($message) ?></div>
        <?php endif; ?>

        <div class="info-box-helper">
            <i class="fa-solid fa-circle-info" style="color: var(--blue-primary); font-size: 16px;"></i>
            <div>
                <?php if ($project): ?>
                    <strong>Project Scope Context:</strong> <?= $escape($project['project_code'] ? $project['project_code'] . ' - ' : '') ?><?= $escape($project['project_name']) ?>
                <?php else: ?>
                    <strong>Context Index:</strong> Please select a project from drop down.
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-navigation-bar">
            <button class="tab-trigger <?= $activeTab === 'physical' ? 'active-tab' : '' ?>" onclick="switchTab(event, 'physical_panel')">
                <i class="fa-solid fa-bullseye icon-structure"></i> Physical Targets
            </button>
            <button class="tab-trigger <?= $activeTab === 'quarterly' ? 'active-tab' : '' ?>" onclick="switchTab(event, 'quarterly_panel')">
                <i class="fa-solid fa-bars-progress icon-logistic"></i> Quarterly Progress
            </button>
            <button class="tab-trigger <?= $activeTab === 'cumulative' ? 'active-tab' : '' ?>" onclick="switchTab(event, 'cumulative_panel')">
                <i class="fa-solid fa-chart-line icon-code"></i> Cumulative Status
            </button>
            <button class="tab-trigger <?= $activeTab === 'funding' ? 'active-tab' : '' ?>" onclick="switchTab(event, 'funding_panel')">
                <i class="fa-solid fa-sack-dollar icon-finance"></i> Funding Parameters
            </button>
        </div>

        <div id="physical_panel" class="tab-panel <?= $activeTab === 'physical' ? 'active-panel' : '' ?>">
            <form class="physical-progress-form" action="" method="POST">
                <input type="hidden" name="action_type" value="physical_targets">
                <?php if ($project): ?><input type="hidden" name="project_id" value="<?= $project_id ?>"><?php endif; ?>

                <div class="form-section">
                    <h2 class="section-title">Physical Targets Allocations</h2>
                    <div class="form-grid">

                        <?php if (!$project): ?>
                            <div class="form-field full">
                                <label for="pt_project_id"><i class="fa-solid fa-folder-tree icon-code"></i> Select Project Profile</label>
                                <select name="project_id" id="pt_project_id" required>
                                    <option value="">-- Choose a Project Scope Map --</option>
                                    <?php foreach ($projects_list as $p): ?>
                                        <?php 
                                            $added = explode(',', $p['pt_quarters'] ?? '');
                                            $all_added = count(array_filter($added)) >= 4;
                                        ?>
                                        <option value="<?= $p['id'] ?>" data-quarters="<?= $escape($p['pt_quarters']) ?>" <?= $all_added ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                            <?= $escape($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= $escape($p['project_name']) ?><?= $all_added ? ' (All Quarters Added)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-field">
                            <label for="pt_quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Quarter</label>
                            <select name="quarter" id="pt_quarter" required>
                                <option value="">Select quadrant...</option>
                                <option value="Q1">Q1</option><option value="Q2">Q2</option><option value="Q3">Q3</option><option value="Q4">Q4</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="overall_physical_target"><i class="fa-solid fa-chart-pie icon-structure"></i> Overall Physical Target (%)</label>
                            <input type="number" step="0.01" name="overall_physical_target" id="overall_physical_target" required placeholder="0.00">
                        </div>

                        <div class="form-field full">
                            <label for="progress_31_12_25"><i class="fa-solid fa-clock-rotate-left icon-finance"></i> Progress as at 31/12/2025 (%)</label>
                            <input type="number" step="0.01" name="progress_31_12_25" id="progress_31_12_25" value="0" required>
                        </div>

                        <div class="form-field full">
                            <label for="descriptive_target"><i class="fa-solid fa-file-signature icon-code"></i> Descriptive Target Specifications</label>
                            <textarea name="descriptive_target" id="descriptive_target" required placeholder="Outline target specifications descriptions details..."></textarea>
                        </div>

                        <div class="form-field full">
                            <label for="descriptive_progress"><i class="fa-solid fa-align-left icon-logistic"></i> Descriptive Progress Notes</label>
                            <textarea name="descriptive_progress" id="descriptive_progress" required placeholder="Document actual descriptive statements updates data..."></textarea>
                        </div>

                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa fa-save"></i> Save Physical Target
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div id="quarterly_panel" class="tab-panel <?= $activeTab === 'quarterly' ? 'active-panel' : '' ?>">
            <form class="physical-progress-form" action="" method="POST">
                <input type="hidden" name="action_type" value="quarterly_progress">
                <?php if ($project): ?><input type="hidden" name="project_id" value="<?= $project_id ?>"><?php endif; ?>

                <div class="form-section">
                    <h2 class="section-title">Quarterly Physical Progress Reports</h2>
                    <div class="form-grid">

                        <?php if (!$project): ?>
                            <div class="form-field full">
                                <label for="qp_project_id"><i class="fa-solid fa-folder-tree icon-code"></i> Select Project Profile</label>
                                <select name="project_id" id="qp_project_id" required>
                                    <option value="">-- Choose a Project Scope Map --</option>
                                    <?php foreach ($projects_list as $p): ?>
                                        <?php 
                                            $added = explode(',', $p['qp_quarters'] ?? '');
                                            $all_added = count(array_filter($added)) >= 4;
                                        ?>
                                        <option value="<?= $p['id'] ?>" data-quarters="<?= $escape($p['qp_quarters']) ?>" <?= $all_added ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                            <?= $escape($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= $escape($p['project_name']) ?><?= $all_added ? ' (All Quarters Added)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-field">
                            <label for="qp_quarter"><i class="fa-regular fa-calendar icon-logistic"></i> Target Period Quarter</label>
                            <select name="quarter" id="qp_quarter" required>
                                <option value="">Select operational quarter window...</option>
                                <option value="Q1">Q1</option><option value="Q2">Q2</option><option value="Q3">Q3</option><option value="Q4">Q4</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="cumulative_quarterly_target"><i class="fa-solid fa-crosshairs icon-structure"></i> Cumulative Quarterly Target (%)</label>
                            <input type="number" step="0.01" name="cumulative_quarterly_target" id="cumulative_quarterly_target" required placeholder="0.00">
                        </div>

                        <div class="form-field full">
                            <label for="cumulative_quarterly_progress"><i class="fa-solid fa-chart-line icon-finance"></i> Cumulative Quarterly Progress (%)</label>
                            <input type="number" step="0.01" name="cumulative_quarterly_progress" id="cumulative_quarterly_progress" required placeholder="0.00">
                        </div>

                        <div class="form-field full">
                            <label for="descriptive_cumulative_progress"><i class="fa-solid fa-message icon-code"></i> Descriptive Cumulative Progress Documentation</label>
                            <textarea name="descriptive_cumulative_progress" id="descriptive_cumulative_progress" required></textarea>
                        </div>

                        <div class="form-field full">
                            <label for="current_quarterly_target"><i class="fa-solid fa-hourglass-start icon-logistic"></i> Current Quarterly Target Statements</label>
                            <textarea name="current_quarterly_target" id="current_quarterly_target" required></textarea>
                        </div>

                        <div class="form-field full">
                            <label for="current_quarterly_progress"><i class="fa-solid fa-square-poll-vertical icon-alert"></i> Current Quarterly Progress Metrics</label>
                            <textarea name="current_quarterly_progress" id="current_quarterly_progress" required></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa fa-save"></i> Save Quarterly Progress
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div id="cumulative_panel" class="tab-panel <?= $activeTab === 'cumulative' ? 'active-panel' : '' ?>">
            <form class="physical-progress-form" action="" method="POST">
                <input type="hidden" name="action_type" value="cumulative_status">
                <?php if ($project): ?><input type="hidden" name="project_id" value="<?= $project_id ?>"><?php endif; ?>

                <div class="form-section">
                    <h2 class="section-title">Cumulative Physical Status Metrics</h2>
                    <div class="form-grid">

                        <?php if (!$project): ?>
                            <div class="form-field full">
                                <label for="cs_project_id"><i class="fa-solid fa-folder-tree icon-code"></i> Select Project Profile</label>
                                <select name="project_id" id="cs_project_id" required>
                                    <option value="">-- Choose a Project Scope Map --</option>
                                    <?php foreach ($projects_list as $p): ?>
                                        <?php 
                                            $added = explode(',', $p['cs_quarters'] ?? '');
                                            $all_added = count(array_filter($added)) >= 4;
                                        ?>
                                        <option value="<?= $p['id'] ?>" data-quarters="<?= $escape($p['cs_quarters']) ?>" <?= $all_added ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                            <?= $escape($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= $escape($p['project_name']) ?><?= $all_added ? ' (All Quarters Added)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-field full">
                            <label for="cs_quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Period Quarter</label>
                            <select name="quarter" id="cs_quarter" required>
                                <option value="">Select target timeline window quadrant...</option>
                                <option value="Q1">Q1</option><option value="Q2">Q2</option><option value="Q3">Q3</option><option value="Q4">Q4</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="cumulative_overall_target"><i class="fa-solid fa-circle-nodes icon-structure"></i> Cumulative Overall Target (%)</label>
                            <input type="number" step="0.01" name="cumulative_overall_target" id="cumulative_overall_target" required placeholder="0.00">
                        </div>

                        <div class="form-field">
                            <label for="cumulative_overall_progress"><i class="fa-solid fa-arrow-trend-up icon-finance"></i> Cumulative Overall Progress (%)</label>
                            <input type="number" step="0.01" name="cumulative_overall_progress" id="cumulative_overall_progress" required placeholder="0.00">
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa fa-save"></i> Save Cumulative Status
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div id="funding_panel" class="tab-panel <?= $activeTab === 'funding' ? 'active-panel' : '' ?>">
            <form class="physical-progress-form" action="" method="POST">
                <input type="hidden" name="action_type" value="funding">
                <?php if ($project): ?><input type="hidden" name="project_id" value="<?= $project_id ?>"><?php endif; ?>

                <div class="form-section">
                    <h2 class="section-title">Funding Information Parameters</h2>
                    <div class="form-grid">

                        <?php if (!$project): ?>
                            <div class="form-field full">
                                <label for="fn_project_id"><i class="fa-solid fa-folder-tree icon-code"></i> Select Project Profile</label>
                                <select name="project_id" id="fn_project_id" required>
                                    <option value="">-- Choose a Project Scope Map --</option>
                                    <?php foreach ($projects_list as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= $escape($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= $escape($p['project_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-field">
                            <label for="funding_source"><i class="fa-solid fa-building-columns icon-structure"></i> Funding Source Origin</label>
                            <input type="text" name="funding_source" id="funding_source" required placeholder="Enter financing institution code source...">
                        </div>

                        <div class="form-field">
                            <label for="funding_amount"><i class="fa-solid fa-money-check-dollar icon-finance"></i> Total Funding Amount</label>
                            <input type="number" step="0.01" name="funding_amount" id="funding_amount" required placeholder="0.00">
                        </div>

                        <div class="form-field">
                            <label for="allocation_year"><i class="fa-regular fa-calendar-days icon-logistic"></i> Budget Allocation Year</label>
                            <input type="number" name="allocation_year" id="allocation_year" value="2026" required>
                        </div>

                        <div class="form-field">
                            <label for="allocation_amount"><i class="fa-solid fa-coins icon-finance"></i> Allocation Financial Amount</label>
                            <input type="number" step="0.01" name="allocation_amount" id="allocation_amount" required placeholder="0.00">
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fa fa-save"></i> Save Funding Details
                        </button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
function switchTab(event, targetPanelId) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(function(panel) {
        panel.classList.remove('active-panel');
    });

    // Deactivate all tab control triggers
    document.querySelectorAll('.tab-trigger').forEach(function(btn) {
        btn.classList.remove('active-tab');
    });

    // Make target active
    document.getElementById(targetPanelId).classList.add('active-panel');
    event.currentTarget.classList.add('active-tab');
}

function setupQuarterDropdown(projectSelectId, quarterSelectId) {
    const projectSelect = document.getElementById(projectSelectId);
    const quarterSelect = document.getElementById(quarterSelectId);
    
    if (projectSelect && quarterSelect) {
        for (let i = 0; i < quarterSelect.options.length; i++) {
            if (quarterSelect.options[i].value !== '') {
                quarterSelect.options[i].setAttribute('data-original-text', quarterSelect.options[i].text);
            }
        }

        projectSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const addedQuarters = (selectedOption.getAttribute('data-quarters') || '').split(',');
            
            for (let i = 0; i < quarterSelect.options.length; i++) {
                const opt = quarterSelect.options[i];
                if (opt.value !== '') {
                    opt.disabled = addedQuarters.includes(opt.value);
                    opt.text = opt.disabled ? opt.getAttribute('data-original-text') + ' (Already Added)' : opt.getAttribute('data-original-text');
                    opt.style.color = opt.disabled ? '#94a3b8' : '';
                }
            }
            quarterSelect.value = '';
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setupQuarterDropdown('pt_project_id', 'pt_quarter');
    setupQuarterDropdown('qp_project_id', 'qp_quarter');
    setupQuarterDropdown('cs_project_id', 'cs_quarter');
});
</script>