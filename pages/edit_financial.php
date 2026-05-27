<?php

include_once __DIR__ . '/../db.php';

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
    echo "<div style='padding: 20px;'>Invalid Record ID</div>";
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
    echo "<div style='padding: 20px;'>Record not found.</div>";
    exit;
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
    background:#2563eb;
    color:white;
    border:none;
    padding:12px 20px;
    border-radius:6px;
    cursor:pointer;
    font-weight:bold;
}
.btn-submit:hover{
    background:#1d4ed8;
}
.info-box{
    background:#f1f5f9;
    padding:12px;
    border-radius:6px;
    margin-bottom:20px;
    border-left: 4px solid #3b82f6;
}
.notice {
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
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
</style>

<div class="financial-form-container">
    <h2>
        <i class="fa fa-pen" style="color:#2563eb;"></i> Edit Financial Progress
    </h2>

    <?php if ($message !== ''): ?>
        <div class="notice <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Project:</strong>
        <?= htmlspecialchars($record['project_code'] ? $record['project_code'] . ' - ' : '') ?><?= htmlspecialchars($record['project_name']) ?>
    </div>

    <form action="" method="POST">
        <input type="hidden" name="update_financial" value="1">
        
        <div class="form-grid">
            <div class="form-group">
                <label>Quarter</label>
                <select name="quarter" required>
                    <option value="Q1" <?= $record['quarter'] === 'Q1' ? 'selected' : '' ?>>Q1</option>
                    <option value="Q2" <?= $record['quarter'] === 'Q2' ? 'selected' : '' ?>>Q2</option>
                    <option value="Q3" <?= $record['quarter'] === 'Q3' ? 'selected' : '' ?>>Q3</option>
                    <option value="Q4" <?= $record['quarter'] === 'Q4' ? 'selected' : '' ?>>Q4</option>
                </select>
            </div>

            <div class="form-group">
                <label>Cumulative Financial Target</label>
                <input type="number" step="0.01" name="cum_fin_target" value="<?= htmlspecialchars($record['cum_fin_target']) ?>" required>
            </div>

            <div class="form-group">
                <label>Actual Expenditure</label>
                <input type="number" step="0.01" name="actual_expenditure" value="<?= htmlspecialchars($record['actual_expenditure']) ?>" required>
            </div>

            <div class="form-group">
                <label>Bills in Hand</label>
                <input type="number" step="0.01" name="bills_in_hand" value="<?= htmlspecialchars($record['bills_in_hand']) ?>" required>
            </div>

            <div class="form-group full-width" style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="btn-submit">
                    Update Financial Progress
                </button>
                <?php 
                    $cancelUrl = "index.php?page=project_financial&id=" . $record['project_id'];
                    if ($orgCode !== '') $cancelUrl .= "&org=" . urlencode($orgCode);
                    if ($division !== 'all') $cancelUrl .= "&division=" . urlencode($division);
                ?>
                <a href="<?= htmlspecialchars($cancelUrl) ?>" style="text-decoration: none; color: #475569; font-weight: 600; padding: 12px 20px; border: 1px solid #cbd5e1; border-radius: 6px; display: inline-block;">Cancel</a>
            </div>
        </div>
    </form>
</div>