<?php
include_once __DIR__ . '/../db.php';

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
    echo "<div style='padding: 20px;'>Invalid Record Type</div>";
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
    echo "<div style='padding: 20px;'>Invalid Record ID</div>";
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
    echo "<div style='padding: 20px;'>Record not found.</div>";
    exit;
}
?>

<style>
.edit-form-container { background:#fff; padding:25px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.08); max-width:900px; }
.edit-form-container h2 { margin-bottom:20px; color: #0f172a; }
.form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; }
.form-group { display:flex; flex-direction:column; }
.form-group label { margin-bottom:8px; font-weight:600; color: #334155; }
.form-group input, .form-group select, .form-group textarea { padding:10px; border:1px solid #ccc; border-radius:6px; font-family: inherit; }
.form-group textarea { resize:vertical; min-height:90px; }
.full-width { grid-column:1 / -1; }
.btn-submit { background:#2563eb; color:white; border:none; padding:12px 20px; border-radius:6px; cursor:pointer; font-weight:bold; }
.btn-submit:hover { background:#1d4ed8; }
.info-box { background:#f1f5f9; padding:12px; border-radius:6px; margin-bottom:20px; border-left: 4px solid #3b82f6; }
.notice { padding: 12px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
.notice.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.notice.error { background: #fff5f5; border: 1px solid #fee2e2; color: #991b1b; }
</style>

<div class="edit-form-container">
    <h2><i class="fa fa-pen" style="color:#2563eb;"></i> Edit <?= htmlspecialchars($title) ?></h2>

    <?php if ($message !== ''): ?>
        <div class="notice <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Project:</strong>
        <?= htmlspecialchars($record['project_code'] ? $record['project_code'] . ' - ' : '') ?><?= htmlspecialchars($record['project_name']) ?>
    </div>

    <form action="" method="POST">
        <input type="hidden" name="update_record" value="1">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="project_id" value="<?= $record['project_id'] ?>">
        
        <div class="form-grid">
            
            <?php if ($type === 'physical_target'): ?>
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
                    <label>Overall Physical Target (%)</label>
                    <input type="number" step="0.01" name="overall_physical_target" value="<?= htmlspecialchars($record['overall_physical_target']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Progress 31/12/25 (%)</label>
                    <input type="number" step="0.01" name="progress_31_12_25" value="<?= htmlspecialchars($record['progress_31_12_25']) ?>" required>
                </div>
                <div class="form-group full-width">
                    <label>Descriptive Target</label>
                    <textarea name="descriptive_target" required><?= htmlspecialchars($record['descriptive_target']) ?></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Descriptive Progress</label>
                    <textarea name="descriptive_progress" required><?= htmlspecialchars($record['descriptive_progress']) ?></textarea>
                </div>

            <?php elseif ($type === 'quarterly_progress'): ?>
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
                    <label>Cumulative Quarterly Target (%)</label>
                    <input type="number" step="0.01" name="cumulative_quarterly_target" value="<?= htmlspecialchars($record['cumulative_quarterly_target']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Cumulative Quarterly Progress (%)</label>
                    <input type="number" step="0.01" name="cumulative_quarterly_progress" value="<?= htmlspecialchars($record['cumulative_quarterly_progress']) ?>" required>
                </div>
                <div class="form-group full-width">
                    <label>Descriptive Cumulative Progress</label>
                    <textarea name="descriptive_cumulative_progress" required><?= htmlspecialchars($record['descriptive_cumulative_progress']) ?></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Current Quarterly Target</label>
                    <textarea name="current_quarterly_target" required><?= htmlspecialchars($record['current_quarterly_target']) ?></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Current Quarterly Progress</label>
                    <textarea name="current_quarterly_progress" required><?= htmlspecialchars($record['current_quarterly_progress']) ?></textarea>
                </div>

            <?php elseif ($type === 'cumulative_status'): ?>
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
                    <label>Cumulative Overall Target (%)</label>
                    <input type="number" step="0.01" name="cumulative_overall_target" value="<?= htmlspecialchars($record['cumulative_overall_target']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Cumulative Overall Progress (%)</label>
                    <input type="number" step="0.01" name="cumulative_overall_progress" value="<?= htmlspecialchars($record['cumulative_overall_progress']) ?>" required>
                </div>

            <?php elseif ($type === 'funding'): ?>
                <div class="form-group">
                    <label>Funding Source</label>
                    <input type="text" name="funding_source" value="<?= htmlspecialchars($record['funding_source']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Funding Amount</label>
                    <input type="number" step="0.01" name="funding_amount" value="<?= htmlspecialchars($record['funding_amount']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Allocation Year</label>
                    <input type="number" name="allocation_year" value="<?= htmlspecialchars($record['allocation_year']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Allocation Amount</label>
                    <input type="number" step="0.01" name="allocation_amount" value="<?= htmlspecialchars($record['allocation_amount']) ?>" required>
                </div>
            <?php endif; ?>

            <div class="form-group full-width" style="display: flex; gap: 12px; align-items: center; margin-top: 10px;">
                <button type="submit" class="btn-submit">
                    Update Record
                </button>
                <a href="index.php?page=physical_progress_display&id=<?= $record['project_id'] ?>" style="text-decoration: none; color: #475569; font-weight: 600; padding: 12px 20px; border: 1px solid #cbd5e1; border-radius: 6px; display: inline-block;">Cancel</a>
            </div>
        </div>
    </form>
</div>