<?php

include_once __DIR__ . '/../db.php';

$project_id = (int) ($_GET['id'] ?? 0);
$orgCode = strtoupper(trim((string)($_GET['org'] ?? '')));
$division = trim((string)($_GET['division'] ?? 'all'));

$project = null;
$projects_list = [];

if ($project_id > 0) {
    $project_sql = "
        SELECT project_name, project_code
        FROM projects
        WHERE id = $project_id
    ";
    
    $project_result = mysqli_query($conn, $project_sql);
    $project = mysqli_fetch_assoc($project_result);
} else {
    // Fetch financial records for the selected organization/division
    $sql = "SELECT p.id as project_id, p.project_name, p.project_code, f.id as fin_id, f.quarter
            FROM financial_progress f
            INNER JOIN projects p ON f.project_id = p.id
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
    $sql .= " ORDER BY p.project_name ASC, f.quarter ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $projects_list[] = $row;
        }
    }
}

$financial_records = [];
if ($project_id > 0) {
    $sql = "SELECT * FROM financial_progress WHERE project_id = $project_id ORDER BY FIELD(quarter,'Q1','Q2','Q3','Q4')";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $financial_records[] = $row;
        }
    }
}

?>

<style>
    /* Executive Bright Blue Gradient & Standalone Grid Workspace Tokens */
    .project-list-page {
        --blue-primary: #1e40af;
        --blue-bright-gradient: linear-gradient(135deg, #0052d4 0%, #4364f7 50%, #6fb1fc 100%);
        --light-mesh: radial-gradient(at 0% 0%, #e0f2fe 0px, transparent 55%),
                      radial-gradient(at 100% 100%, #e0e7ff 0px, transparent 55%),
                      #f8fafc;

        --text-dark: #0f172a;
        --text-slate: #475569;
        --text-light: #94a3b8;
        --border-soft: #cbd5e1;
        --panel-fill: rgba(255, 255, 255, 0.88);
        
        --radius-window: 24px;
        --radius-card: 16px;
        --radius-control: 12px;
        
        --shadow-window: 0 20px 25px -5px rgba(15, 23, 42, 0.03), 0 8px 10px -6px rgba(15, 23, 42, 0.02);

        max-width: 1220px;
        margin: 20px auto;
        padding: 24px;
        font-family: "Inter", system-ui, -apple-system, sans-serif;
        color: var(--text-slate);
        -webkit-font-smoothing: antialiased;
    }

    .form-canvas-container {
        background: var(--light-mesh);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-window);
        box-shadow: var(--shadow-window);
        padding: 56px;
    }

    .form-canvas-header {
        margin-bottom: 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding-bottom: 32px;
        border-bottom: 2px solid var(--border-soft);
    }

    .form-canvas-header h1 {
        margin: 0;
        color: var(--text-dark);
        font-size: 26px;
        font-weight: 800;
        display: flex;
        gap: 12px;
    }

    .form-canvas-header h1 i {
        background: var(--blue-bright-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .form-canvas-header p {
        color: var(--text-slate);
        margin: 6px 0 0;
        font-size: 14.5px;
    }

    .context-pill-badge {
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
    }

    .info-box {
        background: #f1f5f9;
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid #3b82f6;
        color: #334155;
        font-size: 14.5px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-dark);
    }

    .form-group select {
        padding: 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        background: #fff;
        outline: none;
        max-width: 600px;
    }

    .card-identity-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .card-project-title {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: var(--text-dark);
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        background: #fff;
        border-radius: 12px;
        border: 1px solid var(--border-soft);
    }

    .modern-data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .modern-data-table th {
        background: #f8fafc;
        padding: 16px 20px;
        font-size: 12px;
        color: var(--text-slate);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .modern-data-table td {
        padding: 16px 20px;
        border-top: 1px solid var(--border-soft);
        font-size: 14px;
        color: var(--text-dark);
        vertical-align: middle;
    }

    .modern-data-table tbody tr:hover {
        background: #fcfcfd;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 16px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        color: #ffffff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.15s ease;
        height: 36px;
    }

    .btn-edit { background: #2563eb; }
    .btn-edit:hover { background: #1d4ed8; }
    .btn-delete { background: #ef4444; }
    .btn-delete:hover { background: #dc2626; }
    .btn-secondary { background: #e2e8f0; color: #475569; }
    .btn-secondary:hover { background: #cbd5e1; color: #334155; }

    @media (max-width: 640px) {
        .form-canvas-container { padding: 32px 20px; }
        .form-canvas-header { flex-direction: column; align-items: flex-start; gap: 16px; }
    }
</style>

<div class="project-list-page">
    <div class="form-canvas-container">
        <header class="form-canvas-header">
            <div>
                <h1><i class="fa-solid fa-money-bill-wave"></i> Project Financials</h1>
                <p>Track and manage the financial progress records for active projects.</p>
            </div>
            <?php if ($orgCode !== '' || $division !== 'all'): ?>
                <div class="context-pill-badge">
                    <i class="fa-solid fa-server"></i>
                    <?= htmlspecialchars($orgCode ?: 'Global Scope') ?><?= $division !== 'all' ? ' / ' . htmlspecialchars($division) : '' ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!$project): ?>
            <div class="info-box" style="margin-bottom: 24px;">
                <strong>Context:</strong> Active financial progress records for the selected scope.
            </div>
            
            <div class="table-responsive">
                <table class="modern-data-table">
                    <thead>
                        <tr>
                            <th>Project Code</th>
                            <th>Project Name</th>
                            <th>Quarter</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projects_list)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 32px;">No financial records found for the selected scope.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($projects_list as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['project_code'] ?: '-') ?></strong></td>
                                    <td><?= htmlspecialchars($p['project_name']) ?></td>
                                    <td><span class="context-pill-badge" style="padding: 4px 10px; font-size: 10px;"><?= htmlspecialchars($p['quarter']) ?></span></td>
                                    <td>
                                        <div class="action-buttons" style="justify-content: flex-start;">
                                            <a href="index.php?page=edit_financial&id=<?= $p['fin_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-edit" style="height: 28px; padding: 0 10px; font-size: 11px;"><i class="fa fa-pen" style="margin-right: 4px;"></i> Update</a>
                                            <a href="index.php?page=edit_financial&delete_id=<?= $p['fin_id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" onclick="return confirm('Are you sure you want to delete this record?')" class="btn-action btn-delete" style="height: 28px; padding: 0 10px; font-size: 11px;"><i class="fa fa-trash" style="margin-right: 4px;"></i> Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            
            <div class="card-identity-panel" style="margin-bottom: 32px;">
                <h2 class="card-project-title"><?= htmlspecialchars($project['project_code'] ? $project['project_code'] . ' - ' : '') ?><?= htmlspecialchars($project['project_name']) ?></h2>
                <div class="action-buttons" style="margin-top: 16px;">
                    <a href="index.php?page=project_financial&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-secondary">
                        <i class="fa fa-arrow-left" style="margin-right: 6px;"></i> Back to Projects
                    </a>
                    <a href="index.php?page=add_financial&id=<?= $project_id ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-edit">
                        <i class="fa fa-plus"></i> Add Progress Record
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-data-table">
                    <thead>
                        <tr>
                            <th>Quarter</th>
                            <th>Target</th>
                            <th>Actual</th>
                            <th>Bills</th>
                            <th>Cumulative</th>
                            <th>Progress %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($financial_records)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 32px;">No financial progress records added for this project yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($financial_records as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['quarter']) ?></strong></td>
                                    <td><?= number_format($row['cum_fin_target'], 2) ?></td>
                                    <td><?= number_format($row['actual_expenditure'], 2) ?></td>
                                    <td><?= number_format($row['bills_in_hand'], 2) ?></td>
                                    <td><?= number_format($row['cumulative_expenditure'], 2) ?></td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap: 8px;">
                                            <div style="flex:1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow:hidden;">
                                                <div style="width: <?= min(100, $row['financial_progress_percentage']) ?>%; height: 100%; background: #10b981;"></div>
                                            </div>
                                            <span style="font-weight: 700; font-size: 12px; min-width: 45px;"><?= number_format($row['financial_progress_percentage'], 2) ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons" style="justify-content: flex-start;">
                                            <a href="index.php?page=edit_financial&id=<?= $row['id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" class="btn-action btn-edit" style="height: 28px; padding: 0 10px; font-size: 11px;"><i class="fa fa-pen" style="margin-right: 4px;"></i> Update</a>
                                            <a href="index.php?page=edit_financial&delete_id=<?= $row['id'] ?>&org=<?= urlencode($orgCode) ?>&division=<?= urlencode($division) ?>" onclick="return confirm('Are you sure you want to delete this record?')" class="btn-action btn-delete" style="height: 28px; padding: 0 10px; font-size: 11px;"><i class="fa fa-trash" style="margin-right: 4px;"></i> Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>
</div>