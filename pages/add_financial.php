<?php

include_once __DIR__ . '/../db.php';

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
                   EXISTS(SELECT 1 FROM financial_progress WHERE project_id = p.id) as is_added
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

.financial-form-container{

    background:#fff;
    padding:25px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    max-width:900px;
}

.financial-form-container h2{

    margin-bottom:20px;
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
}

.form-group input,
.form-group select{

    padding:10px;
    border:1px solid #ccc;
    border-radius:6px;
}

.full-width{

    grid-column:1 / -1;
}

.btn-submit{

    background:#16a34a;
    color:white;
    border:none;
    padding:12px 20px;
    border-radius:6px;
    cursor:pointer;
    font-weight:bold;
}

.btn-submit:hover{

    background:#15803d;
}

.info-box{

    background:#f1f5f9;
    padding:12px;
    border-radius:6px;
    margin-bottom:20px;
}

</style>

<div class="financial-form-container">

    <h2>
        <i class="fa fa-plus-circle" style="color:#16a34a;"></i> Add Financial Progress
    </h2>

    <?php if ($message !== ''): ?>
        <div class="notice <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <?php if ($project): ?>
            <strong>Project:</strong>
            <?= htmlspecialchars($project['project_name']) ?>
        <?php else: ?>
            <strong>Context:</strong>
            Please select a project from the list below to record its financial progress.
        <?php endif; ?>
    </div>

    <form
        action=""
        method="POST"
    >
        <input type="hidden" name="create_financial" value="1">

        <?php if ($project): ?>
            <input
                type="hidden"
                name="project_id"
                value="<?= $project_id ?>"
            >
        <?php endif; ?>

        <div class="form-grid">

            <?php if (!$project): ?>
                <!-- Project Selection Dropdown -->
                <div class="form-group full-width">
                    <label>Select Project</label>
                    <select name="project_id" required>
                        <option value="">-- Choose a Project --</option>
                        <?php foreach ($projects_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= !empty($p['is_added']) ? 'disabled style="color: #94a3b8; background: #f1f5f9;"' : '' ?>>
                                <?= htmlspecialchars($p['project_code'] ? $p['project_code'] . ' - ' : '') ?><?= htmlspecialchars($p['project_name']) ?><?= !empty($p['is_added']) ? ' (Already Added)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Quarter -->

            <div class="form-group">

                <label>Quarter</label>

                <select name="quarter" required>

                    <option value="">Select Quarter</option>

                    <option value="Q1">Q1</option>
                    <option value="Q2">Q2</option>
                    <option value="Q3">Q3</option>
                    <option value="Q4">Q4</option>

                </select>

            </div>

            <!-- Financial Target -->

            <div class="form-group">

                <label>Cumulative Financial Target</label>

                <input
                    type="number"
                    step="0.01"
                    name="cum_fin_target"
                    required
                >

            </div>

            <!-- Actual Expenditure -->

            <div class="form-group">

                <label>Actual Expenditure</label>

                <input
                    type="number"
                    step="0.01"
                    name="actual_expenditure"
                    required
                >

            </div>

            <!-- Bills in Hand -->

            <div class="form-group">

                <label>Bills in Hand</label>

                <input
                    type="number"
                    step="0.01"
                    name="bills_in_hand"
                    value="0"
                    required
                >

            </div>

            <!-- Submit -->

            <div class="form-group full-width">

                <button
                    type="submit"
                    class="btn-submit"
                >
                    Save Financial Progress
                </button>

            </div>

        </div>

    </form>

</div>