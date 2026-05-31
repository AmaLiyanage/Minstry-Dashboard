<?php

include_once __DIR__ . '/../db.php';

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
                   EXISTS(SELECT 1 FROM physical_targets WHERE project_id = p.id) as has_physical,
                   EXISTS(SELECT 1 FROM quarterly_physical_progress WHERE project_id = p.id) as has_quarterly,
                   EXISTS(SELECT 1 FROM cumulative_physical_status WHERE project_id = p.id) as has_cumulative,
                   EXISTS(SELECT 1 FROM funding WHERE project_id = p.id) as has_funding
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

.page-wrapper{
    background:#f8fafc;
    padding:20px;
    border-radius:12px;
}

/* =========================
   TAB BUTTONS
========================= */

.tab-buttons{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:25px;
}

.tab-btn{
    padding:12px 20px;
    background:#e2e8f0;
    border:none;
    cursor:pointer;
    border-radius:8px;
    font-weight:600;
    transition:0.2s;
}

.tab-btn:hover{
    background:#cbd5e1;
}

.tab-btn.active{
    background:#2563eb;
    color:white;
}

/* =========================
   TAB CONTENT
========================= */

.tab-content{
    display:none;
}

.tab-content.active{
    display:block;
}

/* =========================
   COMMON CARD
========================= */

.section-card{
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.06);
}

.section-title{
    margin-bottom:20px;
    font-size:22px;
    font-weight:700;
    color:#0f172a;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}

.form-group{
    display:flex;
    flex-direction:column;
}

.form-group label{
    margin-bottom:8px;
    font-weight:600;
    color:#334155;
}

.form-group input,
.form-group select,
.form-group textarea{
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-size:14px;
    font-family:inherit;
}

.form-group textarea{
    resize:vertical;
    min-height:90px;
}

.full-width{
    grid-column:1 / -1;
}

.btn-submit{
    background:#16a34a;
    color:white;
    border:none;
    padding:12px 20px;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

.btn-submit:hover{
    background:#15803d;
}

.notice {
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
}
.notice.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.notice.error { background: #fff5f5; border: 1px solid #fee2e2; color: #991b1b; }

.info-box{
    background:#eff6ff;
    padding:14px;
    border-left:4px solid #2563eb;
    border-radius:8px;
    margin-bottom:20px;
}

@media(max-width:768px){

    .form-grid{
        grid-template-columns:1fr;
    }

}

</style>

<div class="page-wrapper">

    <?php if ($message !== ''): ?>
        <div class="notice <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <?php if ($project): ?>
            <strong>Project:</strong>
            <?= htmlspecialchars($project['project_code'] ? $project['project_code'] . ' - ' : '') ?><?= htmlspecialchars($project['project_name']) ?>
        <?php else: ?>
            <strong>Context:</strong>
            Please select a project from the dropdowns below to record progress.
        <?php endif; ?>
    </div>

    <!-- =========================
         TABS
    ========================== -->

    <div class="tab-buttons">


        <button class="tab-btn <?= $activeTab === 'physical' ? 'active' : '' ?>" onclick="showTab(event,'physical')">
            Physical Targets
        </button>

        <button class="tab-btn <?= $activeTab === 'quarterly' ? 'active' : '' ?>" onclick="showTab(event,'quarterly')">
            Quarterly Progress
        </button>

        <button class="tab-btn <?= $activeTab === 'cumulative' ? 'active' : '' ?>" onclick="showTab(event,'cumulative')">
            Cumulative Status
        </button>

        <button class="tab-btn <?= $activeTab === 'funding' ? 'active' : '' ?>" onclick="showTab(event,'funding')">
            Funding
        </button>

    </div>

    <!-- =========================================================
         PHYSICAL TARGETS TAB
    ========================================================== -->

    <div id="physical" class="tab-content <?= $activeTab === 'physical' ? 'active' : '' ?>">

        <div class="section-card">

            <h2 class="section-title">
                Physical Targets
            </h2>

            <form action="" method="POST">

                <div class="form-grid">
                    <input type="hidden" name="action_type" value="physical_targets">

                    <?php if ($project): ?>
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                    <?php else: ?>
                        <div class="form-group full-width">
                            <label>Select Project</label>
                            <select name="project_id" required>
                                <option value="">-- Choose a Project --</option>
                                <?php foreach ($projects_list as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= !empty($p['has_physical']) ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                        <?= htmlspecialchars($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= htmlspecialchars($p['project_name']) ?><?= !empty($p['has_physical']) ? ' (Already Added)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Quarter</label>

                        <select name="quarter" required>
                            <option value="">Select</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Overall Physical Target (%)</label>

                        <input
                            type="number"
                            step="0.01"
                            name="overall_physical_target"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Progress 31/12/25 (%)</label>

                        <input
                            type="number"
                            step="0.01"
                            name="progress_31_12_25"
                            value="0"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <label>Descriptive Target</label>

                        <textarea
                            name="descriptive_target"
                            required
                        ></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Descriptive Progress</label>

                        <textarea
                            name="descriptive_progress"
                            required
                        ></textarea>
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="btn-submit">
                            Save Physical Target
                        </button>
                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- =========================================================
         QUARTERLY PHYSICAL PROGRESS TAB
    ========================================================== -->

    <div id="quarterly" class="tab-content <?= $activeTab === 'quarterly' ? 'active' : '' ?>">

        <div class="section-card">

            <h2 class="section-title">
                Quarterly Physical Progress
            </h2>

            <form action="" method="POST">

                <div class="form-grid">
                    <input type="hidden" name="action_type" value="quarterly_progress">

                    <?php if ($project): ?>
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                    <?php else: ?>
                        <div class="form-group full-width">
                            <label>Select Project</label>
                            <select name="project_id" required>
                                <option value="">-- Choose a Project --</option>
                                <?php foreach ($projects_list as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= !empty($p['has_quarterly']) ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                        <?= htmlspecialchars($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= htmlspecialchars($p['project_name']) ?><?= !empty($p['has_quarterly']) ? ' (Already Added)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Quarter</label>

                        <select name="quarter" required>
                            <option value="">Select</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cumulative Quarterly Target (%)</label>

                        <input
                            type="number"
                            step="0.01"
                            name="cumulative_quarterly_target"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Cumulative Quarterly Progress (%)</label>

                        <input
                            type="number"
                            step="0.01"
                            name="cumulative_quarterly_progress"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <label>Descriptive Cumulative Progress</label>

                        <textarea
                            name="descriptive_cumulative_progress"
                            required
                        ></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Current Quarterly Target</label>

                        <textarea
                            name="current_quarterly_target"
                            required
                        ></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Current Quarterly Progress</label>

                        <textarea
                            name="current_quarterly_progress"
                            required
                        ></textarea>
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="btn-submit">
                            Save Quarterly Progress
                        </button>
                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- =========================================================
         CUMULATIVE STATUS TAB
    ========================================================== -->

    <div id="cumulative" class="tab-content <?= $activeTab === 'cumulative' ? 'active' : '' ?>">

        <div class="section-card">

            <h2 class="section-title">
                Cumulative Physical Status
            </h2>

            <form action="" method="POST">

                <div class="form-grid">
                    <input type="hidden" name="action_type" value="cumulative_status">

                    <?php if ($project): ?>
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                    <?php else: ?>
                        <div class="form-group full-width">
                            <label>Select Project</label>
                            <select name="project_id" required>
                                <option value="">-- Choose a Project --</option>
                                <?php foreach ($projects_list as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= !empty($p['has_cumulative']) ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                        <?= htmlspecialchars($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= htmlspecialchars($p['project_name']) ?><?= !empty($p['has_cumulative']) ? ' (Already Added)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Quarter</label>

                        <select name="quarter" required>
                            <option value="">Select</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cumulative Overall Target (%)</label>

                        <input
                            type="number"
                            step="0.01"
                            name="cumulative_overall_target"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Cumulative Overall Progress (%)</label>

                        <input
                            type="number"
                            step="0.01"
                            name="cumulative_overall_progress"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="btn-submit">
                            Save Cumulative Status
                        </button>
                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- =========================================================
         FUNDING TAB
    ========================================================== -->

    <div id="funding" class="tab-content <?= $activeTab === 'funding' ? 'active' : '' ?>">

        <div class="section-card">

            <h2 class="section-title">
                Funding Information
            </h2>

            <form action="" method="POST">

                <div class="form-grid">
                    <input type="hidden" name="action_type" value="funding">

                    <?php if ($project): ?>
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                    <?php else: ?>
                        <div class="form-group full-width">
                            <label>Select Project</label>
                            <select name="project_id" required>
                                <option value="">-- Choose a Project --</option>
                                <?php foreach ($projects_list as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= !empty($p['has_funding']) ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                        <?= htmlspecialchars($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= htmlspecialchars($p['project_name']) ?><?= !empty($p['has_funding']) ? ' (Already Added)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Funding Source</label>

                        <input
                            type="text"
                            name="funding_source"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Funding Amount</label>

                        <input
                            type="number"
                            step="0.01"
                            name="funding_amount"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Allocation Year</label>

                        <input
                            type="number"
                            name="allocation_year"
                            value="<?= date('Y') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Allocation Amount</label>

                        <input
                            type="number"
                            step="0.01"
                            name="allocation_amount"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="btn-submit">
                            Save Funding
                        </button>
                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

function showTab(event, tabId){

    document
        .querySelectorAll('.tab-content')
        .forEach(function(tab){

            tab.classList.remove('active');

        });

    document
        .querySelectorAll('.tab-btn')
        .forEach(function(btn){

            btn.classList.remove('active');

        });

    document
        .getElementById(tabId)
        .classList.add('active');

    event.currentTarget.classList.add('active');
}

</script>