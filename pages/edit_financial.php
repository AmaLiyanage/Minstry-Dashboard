<?php

include_once __DIR__ . '/../db.php';

$escape = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$id = (int) ($_GET['id'] ?? 0);
$delete_id = (int) ($_GET['delete_id'] ?? 0);
$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$division = trim((string)($_GET['division'] ?? 'all'));

/*
|--------------------------------------------------------------------------
| DELETE RECORD
|--------------------------------------------------------------------------
*/
if ($delete_id > 0) {
    $get_sql = "SELECT project_id FROM financial_progress WHERE id = $delete_id";
    $get_result = mysqli_query($conn, $get_sql);
    $data = mysqli_fetch_assoc($get_result);
    if ($data) {
        $project_id = $data['project_id'];
        mysqli_query($conn, "DELETE FROM financial_progress WHERE id = $delete_id");
        $redir = "index.php?page=project_financial&id=" . $project_id;
        if ($orgCode !== '') $redir .= "&org=" . urlencode($orgCode);
        if ($division !== 'all') $redir .= "&division=" . urlencode($division);
        echo "<script>window.location.href = '" . $redir . "';</script>";
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
| UPDATE RECORD
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_financial'])) {
    $quarter = $_POST['quarter'];
    $cum_fin_target = (float) $_POST['cum_fin_target'];
    $actual_expenditure = (float) $_POST['actual_expenditure'];
    $bills_in_hand = (float) $_POST['bills_in_hand'];

    $cumulative_expenditure = $actual_expenditure + $bills_in_hand;
    $financial_progress_percentage = 0;

    if ($cum_fin_target > 0) {
        $financial_progress_percentage = ($cumulative_expenditure / $cum_fin_target) * 100;
    }

    $get_proj_sql = "SELECT project_id FROM financial_progress WHERE id = $id";
    $get_proj_res = mysqli_query($conn, $get_proj_sql);
    $proj_data = mysqli_fetch_assoc($get_proj_res);
    $proj_id = $proj_data['project_id'];

    $check_sql = "SELECT id FROM financial_progress WHERE project_id = ? AND quarter = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "isi", $proj_id, $quarter, $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        $message = "Quarter already exists for this project.";
        $messageType = "error";
    } else {
        $sql = "UPDATE financial_progress SET 
            quarter = ?, 
            cum_fin_target = ?, 
            actual_expenditure = ?, 
            bills_in_hand = ?, 
            cumulative_expenditure = ?, 
            financial_progress_percentage = ? 
        WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdddddi", $quarter, $cum_fin_target, $actual_expenditure, $bills_in_hand, $cumulative_expenditure, $financial_progress_percentage, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Financial progress updated successfully. Redirecting...";
            $messageType = "success";
            $redir = "index.php?page=project_financial&id=" . $proj_id;
            if ($orgCode !== '') $redir .= "&org=" . urlencode($orgCode);
            if ($division !== 'all') $redir .= "&division=" . urlencode($division);
            echo "<script>setTimeout(function() { window.location.href = '" . $redir . "'; }, 1500);</script>";
        } else {
            $message = "Error: " . mysqli_error($conn);
            $messageType = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH RECORD
|--------------------------------------------------------------------------
*/
$sql = "SELECT f.*, p.project_name, p.project_code 
        FROM financial_progress f 
        LEFT JOIN projects p ON f.project_id = p.id 
        WHERE f.id = $id";
$result = mysqli_query($conn, $sql);
$record = mysqli_fetch_assoc($result);

if (!$record) {
    echo "<div style='padding: 24px; font-family: sans-serif; color: #991b1b; background: #fff5f5;'>Record not found.</div>";
    exit;
}
?>

<style>
    /* Executive Bright Blue Gradient & Uncrowded Canvas Tokens */
    .financial-progress-page {
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
    .financial-progress-shell {
        background: var(--light-mesh);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-window);
        box-shadow: var(--shadow-window);
        overflow: hidden;
        padding: 56px;
    }

    /* Fluid Styled High-Vibrancy Gradient Header */
    .financial-progress-header {
        margin-bottom: 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding-bottom: 32px;
        border-bottom: 2px solid var(--border-soft);
    }

    .financial-progress-title {
        margin: 0;
        color: var(--text-dark);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .financial-progress-subtitle {
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
        margin-bottom: 44px;
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
    .form-field select {
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

    .form-field input:focus,
    .form-field select:focus {
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

    /* Operations Form Footer Layout Controls Bar */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: 28px;
        border-top: 2px solid var(--border-soft);
        margin-top: 16px;
        gap: 16px;
    }

    /* Styled Operational Action Controls Buttons */
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

    .financial-progress-form br {
        display: none;
    }

    /* Screen Breakdowns Grid Modifiers */
    @media (max-width: 960px) {
        .financial-progress-shell {
            padding: 40px 32px;
        }
    }

    @media (max-width: 640px) {
        .financial-progress-shell {
            padding: 32px 20px;
        }

        .financial-progress-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .form-grid {
            grid-template-columns: 1fr !important;
            gap: 28px;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .btn-primary, .btn-secondary, .btn-danger-outline {
            width: 100%;
            justify-content: center;
        }

        .modal-action-buttons {
            flex-direction: column;
        }
    }
</style>

<div class="financial-progress-page">
    <div class="financial-progress-shell">
        <div class="financial-progress-header">
            <div>
                <h1 class="financial-progress-title">Edit Financial Progress</h1>
                <p class="financial-progress-subtitle">Modify and fine-tune operational performance tracking logs.</p>
            </div>
            <?php if ($orgCode !== ''): ?>
                <span class="context-chip">
                    <i class="fa fa-building"></i>
                    <?= $escape($orgCode) ?>
                </span>
            <?php endif; ?>
        </div>

        <form class="financial-progress-form" id="financialEditForm" method="POST" action="">
            <?php if ($message !== ''): ?>
                <div class="notice <?= $escape($messageType) ?>"><?= $escape($message) ?></div>
            <?php endif; ?>

            <div class="info-box-helper">
                <i class="fa-solid fa-circle-info" style="color: var(--blue-primary); font-size: 16px;"></i>
                <div>
                    <strong>Project:</strong> <?= $escape($record['project_code'] ? $record['project_code'] . ' - ' : '') ?><?= $escape($record['project_name']) ?>
                </div>
            </div>

            <input type="hidden" name="update_financial" value="1">

            <div class="form-section">
                <h2 class="section-title">Timeline Parameters</h2>
                <div class="form-grid">
                    
                    <div class="form-field full">
                        <label for="quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Period Quarter</label>
                        <select name="quarter" id="quarter" required>
                            <option value="Q1" <?= $record['quarter'] === 'Q1' ? 'selected' : '' ?>>Q1 - First Quarter Breakdown</option>
                            <option value="Q2" <?= $record['quarter'] === 'Q2' ? 'selected' : '' ?>>Q2 - Second Quarter Breakdown</option>
                            <option value="Q3" <?= $record['quarter'] === 'Q3' ? 'selected' : '' ?>>Q3 - Third Quarter Breakdown</option>
                            <option value="Q4" <?= $record['quarter'] === 'Q4' ? 'selected' : '' ?>>Q4 - Fourth Quarter Breakdown</option>
                        </select>
                    </div>

                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">Financial Valuation Records</h2>
                <div class="form-grid">

                    <div class="form-field">
                        <label for="cum_fin_target"><i class="fa-solid fa-bullseye icon-structure"></i> Cumulative Financial Target</label>
                        <input type="number" step="0.01" name="cum_fin_target" id="cum_fin_target" value="<?= $escape($record['cum_fin_target']) ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="actual_expenditure"><i class="fa-solid fa-money-bill-wave icon-finance"></i> Actual Expenditure</label>
                        <input type="number" step="0.01" name="actual_expenditure" id="actual_expenditure" value="<?= $escape($record['actual_expenditure']) ?>" required>
                    </div>

                    <div class="form-field full">
                        <label for="bills_in_hand"><i class="fa-solid fa-receipt icon-alert"></i> Outstanding Bills in Hand</label>
                        <input type="number" step="0.01" name="bills_in_hand" id="bills_in_hand" value="<?= $escape($record['bills_in_hand']) ?>" required>
                    </div>

                </div>
            </div>

            <div class="form-actions">
                <?php 
                    $cancelUrl = "index.php?page=project_financial&id=" . $record['project_id'];
                    $deleteUrl = "index.php?page=project_financial_edit&delete_id=" . $record['id'];
                    if ($orgCode !== '') {
                        $cancelUrl .= "&org=" . urlencode($orgCode);
                        $deleteUrl .= "&org=" . urlencode($orgCode);
                    }
                    if ($division !== 'all') {
                        $cancelUrl .= "&division=" . urlencode($division);
                        $deleteUrl .= "&division=" . urlencode($division);
                    }
                ?>
                <button type="button" class="btn-danger-outline" id="deleteTriggerBtn" data-delete-url="<?= $escape($deleteUrl) ?>">
                    <i class="fa-solid fa-trash-can"></i> Delete Record
                </button>
                
                <div style="flex-grow: 1; display: flex; gap: 16px; justify-content: flex-end;">
                    <a href="<?= $escape($cancelUrl) ?>" class="btn-secondary">Cancel</a>
                    
                    <button class="btn-primary" type="submit">
                        <i class="fa-solid fa-pen-to-square"></i> Update Financial Progress
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<div class="custom-modal-backdrop" id="confirmationModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid id-icon-placeholder"></i>
        </div>
        <h3 id="modalHeadingTitle">Confirm Action</h3>
        <p id="modalBodyText">Are you sure you want to proceed?
            <span class="modal-target-project-name" id="modalProjectNameLabel">Project Title</span>
        </p>
        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="modalCancelButton">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalConfirmButton">Confirm Action</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const financialEditForm = document.getElementById('financialEditForm');
    const deleteTriggerBtn = document.getElementById('deleteTriggerBtn');
    
    const modalBackdrop = document.getElementById('confirmationModalBackdrop');
    const modalHeadingTitle = document.getElementById('modalHeadingTitle');
    const modalBodyText = document.getElementById('modalBodyText');
    const modalProjectNameLabel = document.getElementById('modalProjectNameLabel');
    const modalCancelButton = document.getElementById('modalCancelButton');
    const modalConfirmButton = document.getElementById('modalConfirmButton');
    const modalIcon = modalBackdrop.querySelector('.modal-status-icon i');

    let postCallbackMode = null; // System tracks whether operation target context is 'update' or 'delete'
    let plannedDeleteRedirectUrl = '';

    const defaultProjectTitle = "<?= $escape($record['project_code'] ? $record['project_code'] . ' - ' : '') . $escape($record['project_name']) ?>";
    const selectedQuarterField = document.getElementById('quarter');

    // Context Interception Hook System for UPDATE Operation
    if (financialEditForm && modalBackdrop) {
        financialEditForm.addEventListener('submit', function (event) {
            event.preventDefault(); 
            
            postCallbackMode = 'update';
            modalBackdrop.classList.remove('modal-danger');

            // Set content details matching update requirements
            modalHeadingTitle.textContent = "Confirm Record Changes";
            modalBodyText.childNodes[0].textContent = "Are you sure you want to commit these update modifications to the live dataset entry for: ";
            modalProjectNameLabel.textContent = defaultProjectTitle + " (" + (selectedQuarterField.value || 'N/A') + ")";
            
            modalIcon.className = "fa-solid fa-circle-question";
            modalConfirmButton.textContent = "Confirm Update";

            modalBackdrop.classList.add('modal-visible');
        });
    }

    // Context Interception Hook System for DELETE Operation
    if (deleteTriggerBtn && modalBackdrop) {
        deleteTriggerBtn.addEventListener('click', function () {
            postCallbackMode = 'delete';
            plannedDeleteRedirectUrl = this.getAttribute('data-delete-url');
            
            modalBackdrop.classList.add('modal-danger');

            // Set content details matching catastrophic delete context profile
            modalHeadingTitle.textContent = "Confirm Record Deletion";
            modalBodyText.childNodes[0].textContent = "Warning: Are you absolutely sure you want to permanently delete the tracking log sequence for: ";
            modalProjectNameLabel.textContent = defaultProjectTitle + " (" + (selectedQuarterField.value || 'N/A') + ")";
            
            modalIcon.className = "fa-solid fa-triangle-exclamation";
            modalConfirmButton.textContent = "Permanently Delete";

            modalBackdrop.classList.add('modal-visible');
        });
    }

    // Modal cancellation handling routines
    modalCancelButton.addEventListener('click', function () {
        modalBackdrop.classList.remove('modal-visible');
    });

    // Handle strategic target resolution sequence dynamically on execution trigger click
    modalConfirmButton.addEventListener('click', function () {
        modalBackdrop.classList.remove('modal-visible');
        if (postCallbackMode === 'update') {
            financialEditForm.submit();
        } else if (postCallbackMode === 'delete' && plannedDeleteRedirectUrl !== '') {
            window.location.href = plannedDeleteRedirectUrl;
        }
    });
});
</script>