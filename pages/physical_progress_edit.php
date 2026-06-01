<?php
include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$id = (int) ($_GET['id'] ?? 0);
$delete_id = (int) ($_GET['delete_id'] ?? 0);
$type = $_GET['type'] ?? '';

$table = '';
$title = '';

if ($type === 'physical_target') {
    $table = 'physical_targets';
    $title = 'Physical Target';
} elseif ($type === 'quarterly_progress') {
    $table = 'quarterly_physical_progress';
    $title = 'Quarterly Progress';
} elseif ($type === 'cumulative_status') {
    $table = 'cumulative_physical_status';
    $title = 'Cumulative Status';
} elseif ($type === 'funding') {
    $table = 'funding';
    $title = 'Funding';
}

if ($table === '') {
    echo "<div style='padding: 24px; font-family: sans-serif; color: #991b1b; background: #fff5f5;'>Invalid Record Type</div>";
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE RECORD LOGIC
|--------------------------------------------------------------------------
*/
if ($delete_id > 0) {
    $get_sql = "SELECT project_id FROM $table WHERE id = $delete_id";
    $get_result = mysqli_query($conn, $get_sql);
    $data = mysqli_fetch_assoc($get_result);
    
    if ($data) {
        $project_id = $data['project_id'];
        mysqli_query($conn, "DELETE FROM $table WHERE id = $delete_id");
        echo "<script>window.location.href = 'index.php?page=physical_progress_display&id=$project_id';</script>";
        exit;
    }
}

if ($id <= 0) {
    echo "<div style='padding: 24px; font-family: sans-serif; color: #991b1b; background: #fff5f5;'>Invalid Record ID</div>";
    exit;
}

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| UPDATE RECORD LOGIC
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $project_id = (int) $_POST['project_id'];

    if ($type === 'physical_target') {
        $quarter = $_POST['quarter'];
        $overall_physical_target = (float) $_POST['overall_physical_target'];
        $progress_31_12_25 = (float) $_POST['progress_31_12_25'];
        $descriptive_target = trim($_POST['descriptive_target']);
        $descriptive_progress = trim($_POST['descriptive_progress']);

        $cumulative_progress = $progress_31_12_25 + $overall_physical_target;
        $actual_physical_progress = $overall_physical_target;

        $sql = "UPDATE physical_targets SET quarter=?, overall_physical_target=?, progress_31_12_25=?, actual_physical_progress=?, descriptive_target=?, descriptive_progress=?, cumulative_progress=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdddssdi", $quarter, $overall_physical_target, $progress_31_12_25, $actual_physical_progress, $descriptive_target, $descriptive_progress, $cumulative_progress, $id);
        $exec = mysqli_stmt_execute($stmt);

    } elseif ($type === 'quarterly_progress') {
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

        $sql = "UPDATE quarterly_physical_progress SET quarter=?, cumulative_quarterly_target=?, cumulative_quarterly_progress=?, progress_percentage=?, descriptive_cumulative_progress=?, current_quarterly_target=?, current_quarterly_progress=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdddsssi", $quarter, $cumulative_quarterly_target, $cumulative_quarterly_progress, $progress_percentage, $descriptive_cumulative_progress, $current_quarterly_target, $current_quarterly_progress, $id);
        $exec = mysqli_stmt_execute($stmt);

    } elseif ($type === 'cumulative_status') {
        $quarter = $_POST['quarter'];
        $cumulative_overall_target = (float) $_POST['cumulative_overall_target'];
        $cumulative_overall_progress = (float) $_POST['cumulative_overall_progress'];
        
        $physical_progress_percentage = 0;
        if ($cumulative_overall_target > 0) {
            $physical_progress_percentage = ($cumulative_overall_progress / $cumulative_overall_target) * 100;
        }

        $sql = "UPDATE cumulative_physical_status SET quarter=?, cumulative_overall_target=?, cumulative_overall_progress=?, physical_progress_percentage=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdddi", $quarter, $cumulative_overall_target, $cumulative_overall_progress, $physical_progress_percentage, $id);
        $exec = mysqli_stmt_execute($stmt);

    } elseif ($type === 'funding') {
        $funding_source = trim($_POST['funding_source']);
        $funding_amount = (float) $_POST['funding_amount'];
        $allocation_year = (int) $_POST['allocation_year'];
        $allocation_amount = (float) $_POST['allocation_amount'];

        $sql = "UPDATE funding SET funding_source=?, funding_amount=?, allocation_year=?, allocation_amount=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdidi", $funding_source, $funding_amount, $allocation_year, $allocation_amount, $id);
        $exec = mysqli_stmt_execute($stmt);
    }

    if (isset($exec) && $exec) {
        $message = "Record updated successfully. Redirecting...";
        $messageType = "success";
        echo "<script>setTimeout(function() { window.location.href = 'index.php?page=physical_progress_display&id=$project_id'; }, 1500);</script>";
    } else {
        $message = "Error: " . mysqli_error($conn);
        $messageType = "error";
    }
}

/*
|--------------------------------------------------------------------------
| FETCH TARGET RECORD
|--------------------------------------------------------------------------
*/
$sql = "SELECT r.*, p.project_name, p.project_code 
        FROM $table r 
        LEFT JOIN projects p ON r.project_id = p.id 
        WHERE r.id = $id";
$result = mysqli_query($conn, $sql);
$record = mysqli_fetch_assoc($result);

if (!$record) {
    echo "<div style='padding: 24px; font-family: sans-serif; color: #991b1b; background: #fff5f5;'>Record not found.</div>";
    exit;
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

    /* Outer Wrapper with Refined Light Mesh Gradient Backing */
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
        margin-bottom: 48px;
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

    /* Frosted Semi-Translucent White Panels */
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

    /* Color Assignment Mappings */
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

    /* Context Info Box Layout Token Styling */
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

    /* Strategic Control Action Rows Layout */
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

    .btn-secondary {
        text-decoration: none;
        background: #ffffff;
        color: var(--text-slate);
        border: 1px solid var(--border-soft);
        min-height: 52px;
        padding: 0 32px;
        font-weight: 600;
        font-size: 14px;
        border-radius: var(--radius-control);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .btn-secondary:hover {
        background: #f8fafc;
        color: var(--text-dark);
        border-color: #94a3b8;
    }

    .btn-danger-outline {
        text-decoration: none;
        background: #ffffff;
        color: #e11d48;
        border: 1px solid #fecdd3;
        min-height: 52px;
        padding: 0 24px;
        font-weight: 600;
        font-size: 14px;
        border-radius: var(--radius-control);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-danger-outline:hover {
        background: #fff5f5;
        color: #9f1239;
        border-color: #fda4af;
    }

    /* Custom Confirmation Modal System Backdrop */
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
        background: #f0f4ff;
        color: #2563eb;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 20px;
    }

    .custom-modal-backdrop.modal-danger .modal-status-icon {
        background: #fff1f2;
        color: #e11d48;
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

    .custom-modal-backdrop.modal-danger .modal-target-project-name {
        color: #9f1239;
        background: #fff1f2;
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
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
    }

    .modal-btn-confirm:hover {
        background: linear-gradient(135deg, #020617 0%, #0f172a 100%) !important;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.45);
    }

    .custom-modal-backdrop.modal-danger .modal-btn-confirm {
        background: linear-gradient(135deg, #e11d48 0%, #9f1239 100%) !important;
        box-shadow: 0 4px 14px rgba(225, 29, 72, 0.3);
    }

    .custom-modal-backdrop.modal-danger .modal-btn-confirm:hover {
        background: linear-gradient(135deg, #be123c 0%, #4c0519 100%) !important;
        box-shadow: 0 6px 16px rgba(190, 18, 60, 0.45);
    }

    .physical-progress-form br {
        display: none;
    }

    /* Screen Breakdowns Grid Modifiers */
    @media (max-width: 960px) {
        .physical-progress-shell { padding: 40px 32px; }
    }

    @media (max-width: 640px) {
        .physical-progress-shell { padding: 32px 20px; }
        .physical-progress-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        .form-grid { grid-template-columns: 1fr !important; gap: 28px; }
        .form-actions { flex-direction: column-reverse; }
        .btn-primary, .btn-secondary, .btn-danger-outline { width: 100%; justify-content: center; }
        .modal-action-buttons { flex-direction: column; }
    }
</style>

<div class="physical-progress-page">
    <div class="physical-progress-shell">
        
        <div class="physical-progress-header">
            <div>
                <h1 class="physical-progress-title">Edit Progress Metrics</h1>
                <p class="physical-progress-subtitle">Modify and commit tracking updates for your <?= $escape($title) ?> logs.</p>
            </div>
        </div>

        <form class="physical-progress-form" id="progressEditForm" method="POST" action="">
            <?php if ($message !== ''): ?>
                <div class="notice <?= $escape($messageType) ?>"><?= $escape($message) ?></div>
            <?php endif; ?>

            <div class="info-box-helper">
                <i class="fa-solid fa-circle-info" style="color: var(--blue-primary); font-size: 16px;"></i>
                <div>
                    <strong>Project Context:</strong> <?= $escape($record['project_code'] ? $record['project_code'] . ' - ' : '') ?><?= $escape($record['project_name']) ?>
                </div>
            </div>

            <input type="hidden" name="update_record" value="1">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="type" value="<?= $escape($type) ?>">
            <input type="hidden" name="project_id" value="<?= $record['project_id'] ?>">
            
            <div class="form-section">
                <h2 class="section-title"><?= $escape($title) ?> Parameters</h2>
                <div class="form-grid">
                    
                    <?php if ($type === 'physical_target'): ?>
                        <div class="form-field">
                            <label for="quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Quarter</label>
                            <select name="quarter" id="quarter" required>
                                <option value="Q1" <?= $record['quarter'] === 'Q1' ? 'selected' : '' ?>>Q1</option>
                                <option value="Q2" <?= $record['quarter'] === 'Q2' ? 'selected' : '' ?>>Q2</option>
                                <option value="Q3" <?= $record['quarter'] === 'Q3' ? 'selected' : '' ?>>Q3</option>
                                <option value="Q4" <?= $record['quarter'] === 'Q4' ? 'selected' : '' ?>>Q4</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="overall_physical_target"><i class="fa-solid fa-chart-pie icon-structure"></i> Overall Physical Target (%)</label>
                            <input type="number" step="0.01" name="overall_physical_target" id="overall_physical_target" value="<?= $escape($record['overall_physical_target']) ?>" required>
                        </div>
                        <div class="form-field full">
                            <label for="progress_31_12_25"><i class="fa-solid fa-clock-rotate-left icon-finance"></i> Progress as at 31/12/2025 (%)</label>
                            <input type="number" step="0.01" name="progress_31_12_25" id="progress_31_12_25" value="<?= $escape($record['progress_31_12_25']) ?>" required>
                        </div>
                        <div class="form-field full">
                            <label for="descriptive_target"><i class="fa-solid fa-file-signature icon-code"></i> Descriptive Target Specifications</label>
                            <textarea name="descriptive_target" id="descriptive_target" required><?= $escape($record['descriptive_target']) ?></textarea>
                        </div>
                        <div class="form-field full">
                            <label for="descriptive_progress"><i class="fa-solid fa-align-left icon-logistic"></i> Descriptive Progress Notes</label>
                            <textarea name="descriptive_progress" id="descriptive_progress" required><?= $escape($record['descriptive_progress']) ?></textarea>
                        </div>

                    <?php elseif ($type === 'quarterly_progress'): ?>
                        <div class="form-field full">
                            <label for="quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Period Quarter</label>
                            <select name="quarter" id="quarter" required>
                                <option value="Q1" <?= $record['quarter'] === 'Q1' ? 'selected' : '' ?>>Q1</option>
                                <option value="Q2" <?= $record['quarter'] === 'Q2' ? 'selected' : '' ?>>Q2</option>
                                <option value="Q3" <?= $record['quarter'] === 'Q3' ? 'selected' : '' ?>>Q3</option>
                                <option value="Q4" <?= $record['quarter'] === 'Q4' ? 'selected' : '' ?>>Q4</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="cumulative_quarterly_target"><i class="fa-solid fa-crosshairs icon-structure"></i> Cumulative Quarterly Target (%)</label>
                            <input type="number" step="0.01" name="cumulative_quarterly_target" id="cumulative_quarterly_target" value="<?= $escape($record['cumulative_quarterly_target']) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="cumulative_quarterly_progress"><i class="fa-solid fa-chart-line icon-finance"></i> Cumulative Quarterly Progress (%)</label>
                            <input type="number" step="0.01" name="cumulative_quarterly_progress" id="cumulative_quarterly_progress" value="<?= $escape($record['cumulative_quarterly_progress']) ?>" required>
                        </div>
                        <div class="form-field full">
                            <label for="descriptive_cumulative_progress"><i class="fa-solid fa-message icon-code"></i> Descriptive Cumulative Progress Documentation</label>
                            <textarea name="descriptive_cumulative_progress" id="descriptive_cumulative_progress" required><?= $escape($record['descriptive_cumulative_progress']) ?></textarea>
                        </div>
                        <div class="form-field full">
                            <label for="current_quarterly_target"><i class="fa-solid fa-hourglass-start icon-logistic"></i> Current Quarterly Target Statements</label>
                            <textarea name="current_quarterly_target" id="current_quarterly_target" required><?= $escape($record['current_quarterly_target']) ?></textarea>
                        </div>
                        <div class="form-field full">
                            <label for="current_quarterly_progress"><i class="fa-solid fa-square-poll-vertical icon-alert"></i> Current Quarterly Progress Metrics</label>
                            <textarea name="current_quarterly_progress" id="current_quarterly_progress" required><?= $escape($record['current_quarterly_progress']) ?></textarea>
                        </div>

                    <?php elseif ($type === 'cumulative_status'): ?>
                        <div class="form-field full">
                            <label for="quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Period Quarter</label>
                            <select name="quarter" id="quarter" required>
                                <option value="Q1" <?= $record['quarter'] === 'Q1' ? 'selected' : '' ?>>Q1</option>
                                <option value="Q2" <?= $record['quarter'] === 'Q2' ? 'selected' : '' ?>>Q2</option>
                                <option value="Q3" <?= $record['quarter'] === 'Q3' ? 'selected' : '' ?>>Q3</option>
                                <option value="Q4" <?= $record['quarter'] === 'Q4' ? 'selected' : '' ?>>Q4</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="cumulative_overall_target"><i class="fa-solid fa-circle-nodes icon-structure"></i> Cumulative Overall Target (%)</label>
                            <input type="number" step="0.01" name="cumulative_overall_target" id="cumulative_overall_target" value="<?= $escape($record['cumulative_overall_target']) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="cumulative_overall_progress"><i class="fa-solid fa-arrow-trend-up icon-finance"></i> Cumulative Overall Progress (%)</label>
                            <input type="number" step="0.01" name="cumulative_overall_progress" id="cumulative_overall_progress" value="<?= $escape($record['cumulative_overall_progress']) ?>" required>
                        </div>

                    <?php elseif ($type === 'funding'): ?>
                        <div class="form-field">
                            <label for="funding_source"><i class="fa-solid fa-building-columns icon-structure"></i> Funding Source Origin</label>
                            <input type="text" name="funding_source" id="funding_source" value="<?= $escape($record['funding_source']) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="funding_amount"><i class="fa-solid fa-money-check-dollar icon-finance"></i> Total Funding Amount</label>
                            <input type="number" step="0.01" name="funding_amount" id="funding_amount" value="<?= $escape($record['funding_amount']) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="allocation_year"><i class="fa-regular fa-calendar-days icon-logistic"></i> Budget Allocation Year</label>
                            <input type="number" name="allocation_year" id="allocation_year" value="<?= $escape($record['allocation_year']) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="allocation_amount"><i class="fa-solid fa-coins icon-finance"></i> Allocation Financial Amount</label>
                            <input type="number" step="0.01" name="allocation_amount" id="allocation_amount" value="<?= $escape($record['allocation_amount']) ?>" required>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="form-actions">
                <?php 
                    $cancelUrl = "index.php?page=physical_progress_display&id=" . $record['project_id'];
                    $deleteUrl = "index.php?page=physical_progress_edit&delete_id=" . $record['id'] . "&type=" . urlencode($type);
                ?>
                <button type="button" class="btn-danger-outline" id="deleteTriggerBtn" data-delete-url="<?= $escape($deleteUrl) ?>">
                    <i class="fa-solid fa-trash-can"></i> Delete Record
                </button>
                
                <div style="flex-grow: 1; display: flex; gap: 16px; justify-content: flex-end;">
                    <a href="<?= $escape($cancelUrl) ?>" class="btn-secondary">Cancel</a>
                    
                    <button class="btn-primary" type="submit">
                        <i class="fa-solid fa-pen-to-square"></i> Update <?= $escape($title) ?>
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<div class="custom-modal-backdrop" id="confirmationModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid"></i>
        </div>
        <h3 id="modalHeadingTitle">Confirm Action</h3>
        <p id="modalBodyText">Are you sure you want to proceed?
            <span class="modal-target-project-name" id="modalProjectNameLabel">Target Context</span>
        </p>
        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="modalCancelButton">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalConfirmButton">Confirm Action</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const progressEditForm = document.getElementById('progressEditForm');
    const deleteTriggerBtn = document.getElementById('deleteTriggerBtn');
    
    const modalBackdrop = document.getElementById('confirmationModalBackdrop');
    const modalHeadingTitle = document.getElementById('modalHeadingTitle');
    const modalBodyText = document.getElementById('modalBodyText');
    const modalProjectNameLabel = document.getElementById('modalProjectNameLabel');
    const modalCancelButton = document.getElementById('modalCancelButton');
    const modalConfirmButton = document.getElementById('modalConfirmButton');
    const modalIcon = modalBackdrop.querySelector('.modal-status-icon i');

    let postCallbackMode = null; 
    let plannedDeleteRedirectUrl = '';

    const defaultProjectTitle = "<?= $escape($record['project_code'] ? $record['project_code'] . ' - ' : '') . $escape($record['project_name']) ?>";
    const recordTypeLabel = "<?= $escape($title) ?>";
    const targetQuarterField = document.getElementById('quarter');

    // Context Interception Hook for UPDATE Context
    if (progressEditForm && modalBackdrop) {
        progressEditForm.addEventListener('submit', function (event) {
            event.preventDefault(); 
            
            postCallbackMode = 'update';
            modalBackdrop.classList.remove('modal-danger');

            modalHeadingTitle.textContent = "Confirm Changes";
            modalBodyText.childNodes[0].textContent = "Are you sure you want to commit these update modifications to the live dataset entry for: ";
            
            let extraContext = targetQuarterField ? " (" + targetQuarterField.value + ")" : "";
            modalProjectNameLabel.textContent = defaultProjectTitle + " - " + recordTypeLabel + extraContext;
            
            modalIcon.className = "fa-solid fa-circle-question";
            modalConfirmButton.textContent = "Confirm Update";

            modalBackdrop.classList.add('modal-visible');
        });
    }

    // Context Interception Hook for DELETE Context
    if (deleteTriggerBtn && modalBackdrop) {
        deleteTriggerBtn.addEventListener('click', function () {
            postCallbackMode = 'delete';
            plannedDeleteRedirectUrl = this.getAttribute('data-delete-url');
            
            modalBackdrop.classList.add('modal-danger');

            modalHeadingTitle.textContent = "Confirm Deletion";
            modalBodyText.childNodes[0].textContent = "Warning: Are you absolutely certain you want to permanently delete the progressive tracker entry for: ";
            
            let extraContext = targetQuarterField ? " (" + targetQuarterField.value + ")" : "";
            modalProjectNameLabel.textContent = defaultProjectTitle + " - " + recordTypeLabel + extraContext;
            
            modalIcon.className = "fa-solid fa-triangle-exclamation";
            modalConfirmButton.textContent = "Permanently Delete";

            modalBackdrop.classList.add('modal-visible');
        });
    }

    // Modal cancellation closures
    modalCancelButton.addEventListener('click', function () {
        modalBackdrop.classList.remove('modal-visible');
    });

    // Strategy evaluation executor dispatches
    modalConfirmButton.addEventListener('click', function () {
        modalBackdrop.classList.remove('modal-visible');
        if (postCallbackMode === 'update') {
            progressEditForm.submit();
        } else if (postCallbackMode === 'delete' && plannedDeleteRedirectUrl !== '') {
            window.location.href = plannedDeleteRedirectUrl;
        }
    });
});
</script>