<?php

include_once __DIR__ . '/../db.php';

$project_id = (int) ($_GET['id'] ?? 0);
$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$division = trim((string)($_GET['division'] ?? 'all'));
$sector = trim((string)($_GET['sector'] ?? ''));

$project = null;
$projects_list = [];

if ($project_id > 0) {
    $project_sql = "SELECT project_name, project_code FROM projects WHERE id = $project_id";
    $project_result = mysqli_query($conn, $project_sql);
    if ($project_result) {
        $project = mysqli_fetch_assoc($project_result);
    }
} else {
    $sql = "SELECT p.id as project_id, p.project_name, p.project_code
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

.display-section{
    margin-top:30px;
    max-width: 1200px;
}

.display-card{
    background:#fff;
    padding:20px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.06);
    margin-bottom:30px;
}

.display-title{
    font-size:20px;
    font-weight:700;
    margin-bottom:20px;
    color:#0f172a;
}

.data-table{
    width:100%;
    border-collapse:collapse;
}

.data-table th{
    background:#f8fafc;
    color:#475569;
    padding:12px;
    text-align:left;
    font-size: 14px;
    border-bottom: 1px solid #e2e8f0;
}

.data-table td{
    padding:12px;
    border-bottom:1px solid #e2e8f0;
    font-size: 14px;
}

.data-table tr:hover{
    background:#f1f5f9;
}

.action-btn{
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    font-size:13px;
    font-weight:600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.edit-btn{
    background:#facc15;
    color:#000;
}

.delete-btn{
    background:#ef4444;
    color:#fff;
}

.empty-row{
    text-align:center;
    color:#64748b;
    padding:30px;
}

</style>

<div class="display-section">

    <?php if ($project_id == 0): ?>

        <div class="display-card">
            <h2 class="display-title">
                <i class="fa-solid fa-bars-progress" style="color: #2563eb;"></i> Project Physical Progress
            </h2>
            
            <?php if ($orgCode !== '' || $division !== 'all'): ?>
                <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; color: #334155; font-size: 14px; font-weight: 600; border-left: 4px solid #2563eb;">
                    <i class="fa-solid fa-server"></i> <?= htmlspecialchars($orgCode ?: 'Global Scope') ?><?= $division !== 'all' ? ' / ' . htmlspecialchars($division) : '' ?>
                </div>
            <?php endif; ?>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Actions</th>
                    </tr>
                    <?php if (empty($projects_list)): ?>
                        <tr>
                            <td colspan="3" class="empty-row">No projects found for the selected scope.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects_list as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['project_code'] ?: '-') ?></strong></td>
                                <td><?= htmlspecialchars($p['project_name']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="index.php?page=physical_progress_display&id=<?= $p['project_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>&sector=<?= urlencode($sector) ?>" class="action-btn" style="background:#2563eb; color:white;">
                                            <i class="fa fa-eye"></i> View Records
                                        </a>
                                        <a href="index.php?page=physical_progress&id=<?= $p['project_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="action-btn" style="background:#16a34a; color:white;">
                                            <i class="fa fa-plus"></i> Add Record
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    <?php else: ?>

        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php?page=physical_progress_display&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>&sector=<?= urlencode($sector) ?>" class="action-btn" style="background:#e2e8f0; color:#475569;">
                <i class="fa fa-arrow-left"></i> Back to Projects
            </a>
            <a href="index.php?page=physical_progress&id=<?= $project_id ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="action-btn" style="background:#16a34a; color:white;">
                <i class="fa fa-plus"></i> Add Progress Record
            </a>
        </div>

        <?php if ($project): ?>
            <div class="display-card" style="background:#f8fafc; border-left: 4px solid #2563eb; padding: 15px 20px; margin-bottom: 20px;">
                <h2 class="display-title" style="margin-bottom:0; font-size: 18px;">
                    <?= htmlspecialchars($project['project_code'] ? $project['project_code'] . ' - ' : '') ?><?= htmlspecialchars($project['project_name']) ?>
                </h2>
            </div>
        <?php endif; ?>

    <!-- =========================================================
         PHYSICAL TARGETS DISPLAY
    ========================================================== -->

    <div class="display-card">

        <h2 class="display-title">
            Physical Targets Records
        </h2>

            <div style="overflow-x: auto;">
        <table class="data-table">

            <tr>
                <th>Quarter</th>
                <th>Overall Target</th>
                <th>Progress 31/12/25</th>
                <th>Actual Progress</th>
                <th>Cumulative Progress</th>
                <th>Actions</th>
            </tr>

            <?php

            $sql = "
                SELECT *
                FROM physical_targets
                WHERE project_id = $project_id
                ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')
            ";

            $result = mysqli_query($conn, $sql);

                    if($result && mysqli_num_rows($result) > 0):

                while($row = mysqli_fetch_assoc($result)):

            ?>

                <tr>

                            <td><strong><?= $row['quarter'] ?></strong></td>

                    <td>
                        <?= number_format($row['overall_physical_target'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['progress_31_12_25'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['actual_physical_progress'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['cumulative_progress'],2) ?>%
                    </td>

                    <td>

                        <a href="index.php?page=physical_progress_edit&type=physical_target&id=<?= $row['id'] ?>" class="action-btn edit-btn">
                            <i class="fa fa-pen"></i> Edit
                        </a>

                        <a href="index.php?page=physical_progress_edit&type=physical_target&delete_id=<?= $row['id'] ?>" 
                           class="action-btn delete-btn" 
                           onclick="return confirm('Delete this record?')">
                            <i class="fa fa-trash"></i> Delete
                        </a>

                    </td>

                </tr>

            <?php

                endwhile;

            else:

            ?>

                <tr>
                    <td colspan="6" class="empty-row">
                                No physical target records found.
                    </td>
                </tr>

            <?php endif; ?>

        </table>
            </div>

    </div>

    <!-- =========================================================
         QUARTERLY PROGRESS DISPLAY
    ========================================================== -->

    <div class="display-card">

        <h2 class="display-title">
            Quarterly Physical Progress
        </h2>

            <div style="overflow-x: auto;">
        <table class="data-table">

            <tr>
                <th>Quarter</th>
                <th>Target</th>
                <th>Progress</th>
                <th>Progress %</th>
                <th>Actions</th>
            </tr>

            <?php

            $sql = "
                SELECT *
                FROM quarterly_physical_progress
                WHERE project_id = $project_id
                ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')
            ";

            $result = mysqli_query($conn, $sql);

                    if($result && mysqli_num_rows($result) > 0):

                while($row = mysqli_fetch_assoc($result)):

            ?>

                <tr>

                            <td><strong><?= $row['quarter'] ?></strong></td>

                    <td>
                        <?= number_format($row['cumulative_quarterly_target'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['cumulative_quarterly_progress'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['progress_percentage'] ?? 0, 2) ?>%
                    </td>

                    <td>

                        <a href="index.php?page=physical_progress_edit&type=quarterly_progress&id=<?= $row['id'] ?>" class="action-btn edit-btn">
                            <i class="fa fa-pen"></i> Edit
                        </a>

                        <a href="index.php?page=physical_progress_edit&type=quarterly_progress&delete_id=<?= $row['id'] ?>" 
                           class="action-btn delete-btn" 
                           onclick="return confirm('Delete this record?')">
                            <i class="fa fa-trash"></i> Delete
                        </a>

                    </td>

                </tr>

            <?php

                endwhile;

            else:

            ?>

                <tr>
                    <td colspan="5" class="empty-row">
                                No quarterly progress records found.
                    </td>
                </tr>

            <?php endif; ?>

        </table>
            </div>

    </div>

    <!-- =========================================================
         CUMULATIVE STATUS DISPLAY
    ========================================================== -->

    <div class="display-card">

        <h2 class="display-title">
            Cumulative Physical Status
        </h2>

            <div style="overflow-x: auto;">
        <table class="data-table">

            <tr>
                <th>Quarter</th>
                <th>Overall Target</th>
                <th>Overall Progress</th>
                <th>Progress %</th>
                <th>Actions</th>
            </tr>

            <?php

            $sql = "
                SELECT *
                FROM cumulative_physical_status
                WHERE project_id = $project_id
                ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')
            ";

            $result = mysqli_query($conn, $sql);

                    if($result && mysqli_num_rows($result) > 0):

                while($row = mysqli_fetch_assoc($result)):

            ?>

                <tr>

                            <td><strong><?= $row['quarter'] ?></strong></td>

                    <td>
                        <?= number_format($row['cumulative_overall_target'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['cumulative_overall_progress'],2) ?>%
                    </td>

                    <td>
                        <?= number_format($row['physical_progress_percentage'],2) ?>%
                    </td>

                    <td>

                        <a href="index.php?page=physical_progress_edit&type=cumulative_status&id=<?= $row['id'] ?>" class="action-btn edit-btn">
                            <i class="fa fa-pen"></i> Edit
                        </a>

                        <a href="index.php?page=physical_progress_edit&type=cumulative_status&delete_id=<?= $row['id'] ?>" 
                           class="action-btn delete-btn" 
                           onclick="return confirm('Delete this record?')">
                            <i class="fa fa-trash"></i> Delete
                        </a>

                    </td>

                </tr>

            <?php

                endwhile;

            else:

            ?>

                <tr>
                    <td colspan="5" class="empty-row">
                                No cumulative status records found.
                    </td>
                </tr>

            <?php endif; ?>

        </table>
            </div>

    </div>

    <!-- =========================================================
         FUNDING DISPLAY
    ========================================================== -->

    <div class="display-card">

        <h2 class="display-title">
            Funding Details
        </h2>

            <div style="overflow-x: auto;">
        <table class="data-table">

            <tr>
                <th>Funding Source</th>
                <th>Funding Amount</th>
                <th>Allocation Year</th>
                <th>Allocation Amount</th>
                <th>Actions</th>
            </tr>

            <?php

            $sql = "
                SELECT *
                FROM funding
                WHERE project_id = $project_id
                ORDER BY allocation_year DESC
            ";

            $result = mysqli_query($conn, $sql);

                    if($result && mysqli_num_rows($result) > 0):

                while($row = mysqli_fetch_assoc($result)):

            ?>

                <tr>

                    <td><?= htmlspecialchars($row['funding_source']) ?></td>

                    <td>
                        Rs. <?= number_format($row['funding_amount'],2) ?>
                    </td>

                    <td><?= $row['allocation_year'] ?></td>

                    <td>
                        Rs. <?= number_format($row['allocation_amount'],2) ?>
                    </td>

                    <td>

                        <a href="index.php?page=physical_progress_edit&type=funding&id=<?= $row['id'] ?>" class="action-btn edit-btn">
                            <i class="fa fa-pen"></i> Edit
                        </a>

                        <a href="index.php?page=physical_progress_edit&type=funding&delete_id=<?= $row['id'] ?>" 
                           class="action-btn delete-btn" 
                           onclick="return confirm('Delete this funding record?')">
                            <i class="fa fa-trash"></i> Delete
                        </a>

                    </td>

                </tr>

            <?php

                endwhile;

            else:

            ?>

                <tr>
                    <td colspan="5" class="empty-row">
                                No funding records found.
                    </td>
                </tr>

            <?php endif; ?>

        </table>
            </div>

    </div>

    <?php endif; ?>

</div>