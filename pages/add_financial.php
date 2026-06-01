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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_financial'])) {
    $post_project_id = (int) $_POST['project_id'];
    $quarter = $_POST['quarter'];

    $cum_fin_target = (float) $_POST['cum_fin_target'];
    $actual_expenditure = (float) $_POST['actual_expenditure'];
    $bills_in_hand = (float) $_POST['bills_in_hand'];

    $cumulative_expenditure = $actual_expenditure + $bills_in_hand;
    $financial_progress_percentage = 0;

    if ($cum_fin_target > 0) {
        $financial_progress_percentage = ($cumulative_expenditure / $cum_fin_target) * 100;
    }

    $check_sql = "SELECT id FROM financial_progress WHERE project_id = ? AND quarter = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "is", $post_project_id, $quarter);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        $message = "Quarter already exists for this project.";
        $messageType = "error";
    } else {
        $sql = "INSERT INTO financial_progress (
            project_id, quarter, cum_fin_target, actual_expenditure, bills_in_hand, cumulative_expenditure, financial_progress_percentage
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isddddd", $post_project_id, $quarter, $cum_fin_target, $actual_expenditure, $bills_in_hand, $cumulative_expenditure, $financial_progress_percentage);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Financial progress added successfully. Redirecting...";
            $messageType = "success";
            echo "<script>setTimeout(function() { window.location.href = 'index.php?page=project_financial&id=" . $post_project_id . "'; }, 1500);</script>";
        } else {
            $message = "Error: " . mysqli_error($conn);
            $messageType = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET PROJECT DETAILS
|--------------------------------------------------------------------------
*/

if ($project_id > 0) {
    $project_sql = "
        SELECT project_name
        FROM projects
        WHERE id = $project_id
    ";
    
    $project_result = mysqli_query($conn, $project_sql);
    $project = mysqli_fetch_assoc($project_result);
} else {
    // Fetch projects for the selected organization to populate dropdown
    $sql = "SELECT p.id, p.project_name, p.project_code,
                   (SELECT GROUP_CONCAT(quarter) FROM financial_progress WHERE project_id = p.id) as added_quarters
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

    /* Context Info Box styling matches empty-helper / info layout tokens */
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

    /* Operations Form Footer Layout Controls */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: 28px;
        border-top: 2px solid var(--border-soft);
        margin-top: 16px;
        gap: 16px;
    }

    /* Action Buttons styling */
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

        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="financial-progress-page">
    <div class="financial-progress-shell">
        <div class="financial-progress-header">
            <div>
                <h1 class="financial-progress-title">Add Financial Progress</h1>
                <p class="financial-progress-subtitle">Record and trace fiscal performance matrices metrics.</p>
            </div>
            <?php if ($orgCode !== ''): ?>
                <span class="context-chip">
                    <i class="fa fa-building"></i>
                    <?= $escape($orgCode) ?>
                </span>
            <?php endif; ?>
        </div>

        <form class="financial-progress-form" method="POST" action="">
            <?php if ($message !== ''): ?>
                <div class="notice <?= $escape($messageType) ?>"><?= $escape($message) ?></div>
            <?php endif; ?>

            <div class="info-box-helper">
                <i class="fa-solid fa-circle-info" style="color: var(--blue-primary); font-size: 16px;"></i>
                <div>
                    <?php if ($project): ?>
                        <strong>Project:</strong> <?= $escape($project['project_name']) ?>
                    <?php else: ?>
                        <strong>Context:</strong> Please select a project from drop down.
                    <?php endif; ?>
                </div>
            </div>

            <input type="hidden" name="create_financial" value="1">

            <?php if ($project): ?>
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <?php endif; ?>

            <div class="form-section">
                <h2 class="section-title">Scope Targeting Parameters</h2>
                <div class="form-grid">
                    
                    <?php if (!$project): ?>
                        <div class="form-field full">
                            <label for="project_id"><i class="fa-solid fa-folder-tree icon-code"></i> Select Project</label>
                            <select name="project_id" id="project_id" required>
                                <option value="">-- Choose a Project Configuration --</option>
                                <?php foreach ($projects_list as $p): ?>
                                    <?php 
                                        $added = explode(',', $p['added_quarters'] ?? '');
                                        $all_added = count(array_filter($added)) >= 4;
                                    ?>
                                    <option value="<?= $p['id'] ?>" data-quarters="<?= $escape($p['added_quarters']) ?>" <?= $all_added ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                        <?= $escape($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= $escape($p['project_name']) ?><?= $all_added ? ' (All Quarters Tracked)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-field full">
                        <label for="quarter"><i class="fa-regular fa-calendar-check icon-logistic"></i> Target Period Quarter</label>
                        <select name="quarter" id="quarter" required>
                            <option value="">Select target operational quadrant window...</option>
                            <option value="Q1">Q1 - First Quarter Breakdown</option>
                            <option value="Q2">Q2 - Second Quarter Breakdown</option>
                            <option value="Q3">Q3 - Third Quarter Breakdown</option>
                            <option value="Q4">Q4 - Fourth Quarter Breakdown</option>
                        </select>
                    </div>

                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">Financial Ledgers Valuation</h2>
                <div class="form-grid">

                    <div class="form-field">
                        <label for="cum_fin_target"><i class="fa-solid fa-bullseye icon-structure"></i> Cumulative Financial Target</label>
                        <input type="number" step="0.01" name="cum_fin_target" id="cum_fin_target" required placeholder="0.00">
                    </div>

                    <div class="form-field">
                        <label for="actual_expenditure"><i class="fa-solid fa-money-bill-wave icon-finance"></i> Actual Expenditure</label>
                        <input type="number" step="0.01" name="actual_expenditure" id="actual_expenditure" required placeholder="0.00">
                    </div>

                    <div class="form-field full">
                        <label for="bills_in_hand"><i class="fa-solid fa-receipt icon-alert"></i> Outstanding Bills in Hand</label>
                        <input type="number" step="0.01" name="bills_in_hand" id="bills_in_hand" value="0" required placeholder="0.00">
                    </div>

                </div>
            </div>

            <div class="form-actions">
                <button class="btn-primary" type="submit">
                    <i class="fa fa-save"></i> Save Financial Progress
                </button>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('project_id');
    const quarterSelect = document.getElementById('quarter');
    
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
});
</script>