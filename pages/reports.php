<?php
include_once __DIR__ . '/../db.php';

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function cv($v): string { return trim((string)($v ?? '')); }

function showv($v): string { 
    $v = cv($v); 
    return ($v === '' || $v === '0' || $v === '0%' || $v === '-') ? '-' : $v; 
}

/** 
 * HELPER: sector_of
 * Wrapped in function_exists to prevent "Cannot redeclare" errors 
 * when the main index.php also defines this function.
 */
if (!function_exists('sector_of')) {
    function sector_of(string $inst): string {
        $inst = strtoupper(trim($inst));
        return in_array($inst, ['AASL', 'CAASL'], true) ? 'Aviation' : 'Ports';
    }
}

$allProjects = [];
$sql = "SELECT p.*, i.code AS institution_code, i.institution_name, d.division_name AS division,
       f.cum_fin_target AS q1_fin_target,
       f.actual_expenditure AS q1_fin_actual,
       f.bills_in_hand AS q1_bills_in_hand,
       qp.cumulative_quarterly_target AS q1_phys_target,
       qp.cumulative_quarterly_progress AS q1_phys_actual,
       qp.progress_percentage AS q1_quarterly_cum,
       cp.cumulative_overall_target AS q1_cum_target,
       cp.cumulative_overall_progress AS q1_cum_prog,
       cp.physical_progress_percentage AS q1_overall_prog_final
FROM projects p
LEFT JOIN institutions i ON p.institution_id = i.id
LEFT JOIN divisions d ON p.division_id = d.id
LEFT JOIN financial_progress f ON p.id = f.project_id AND f.quarter = 'Q1'
LEFT JOIN quarterly_physical_progress qp ON p.id = qp.project_id AND qp.quarter = 'Q1'
LEFT JOIN cumulative_physical_status cp ON p.id = cp.project_id AND cp.quarter = 'Q1'";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $codeRaw = trim($row['institution_code'] ?: $row['institution_name'] ?? '');
        $code = strtoupper($codeRaw);
        if (strpos($code, 'CAASL') !== false) $code = 'CAASL';
        elseif (strpos($code, 'AASL') !== false) $code = 'AASL';
        elseif (strpos($code, 'SLPA') !== false) $code = 'SLPA';
        elseif (strpos($code, 'MSS') !== false || strpos($code, 'MERCHANT') !== false) $code = 'MSS';
        elseif (strpos($code, 'JCT') !== false) $code = 'JCT';
        elseif (strpos($code, 'CSC') !== false) $code = 'CSC';
        else $code = $codeRaw !== '' ? $codeRaw : 'UNKNOWN';
        
        $row['institution'] = $code;
        $row['actual_exp'] = ['Q1' => $row['q1_fin_actual']];
        $row['phys_percent'] = [
            'Q1' => $row['q1_phys_actual'],
            'Q1_Target' => $row['q1_phys_target'],
            'Q1_Overall_Prog_Final' => $row['q1_overall_prog_final'],
            'Q1_Cum_Target' => $row['q1_cum_target'],
            'Q1_Cum_Prog' => $row['q1_cum_prog'],
            'Q1_Quarterly_Cum' => $row['q1_quarterly_cum']
        ];
        $allProjects[] = $row;
    }
}

// 1. Capture filters from the Sidebar URL
$selectedSector = $_GET['sector'] ?? 'All';
$selectedInst = $_GET['inst'] ?? 'All'; 
$selectedDiv = $_GET['div'] ?? 'All';   

// 2. Build Dynamic Filter Lists for the UI dropdowns
$instDropdown = [];
$divDropdown = [];
foreach ($allProjects as $p) {
    $instCode = strtoupper(cv($p['institution']));
    $sec = sector_of($instCode);
    
    if ($selectedSector === 'All' || $selectedSector === $sec) {
        $instDropdown[] = $instCode;
        if ($selectedInst === 'All' || $selectedInst === $instCode) {
            if (!empty($p['division'])) $divDropdown[] = cv($p['division']);
        }
    }
}
$instDropdown = array_unique($instDropdown); sort($instDropdown);
$divDropdown = array_unique($divDropdown); sort($divDropdown);

// 3. Strict filtering based on sidebar click or dropdown selection
$filtered = array_values(array_filter($allProjects, function($p) use ($selectedSector, $selectedInst, $selectedDiv) {
    $inst = strtoupper(cv($p['institution'] ?? ''));
    $sec = sector_of($inst);
    $div = cv($p['division'] ?? '');
    
    if ($selectedSector !== 'All' && $selectedSector !== $sec) return false;
    if ($selectedInst !== 'All' && $selectedInst !== $inst) return false;
    if ($selectedDiv !== 'All' && $selectedDiv !== $div) return false;
    
    return true;
}));
?>

<style>
    :root { --primary-blue: #1e3a5f; --line-color: #d8e2ef; --phys-blue: #0ea5e9; }
    .panel-report { background: white; border: 1px solid var(--line-color); border-radius: 16px; padding: 15px; margin-bottom: 12px; }
    .table-wrap-report { width: 100%; overflow-x: auto; border: 1px solid var(--line-color); border-radius: 12px; background: #fff; }
    
    table.report-table { width: 100%; border-collapse: collapse; font-size: 10px; min-width: 1300px; table-layout: fixed; }
    table.report-table thead th { background: var(--primary-blue); color: white; padding: 10px 4px; border: 1px solid rgba(255,255,255,0.1); text-transform: uppercase; font-size: 9.5px; }
    table.report-table tbody td { padding: 4px 6px; border: 1px solid #f0f0f0; vertical-align: top; word-wrap: break-word; line-height: 1.2; }
    
    .mini-row { display: flex; justify-content: space-between; font-size: 9px; margin-bottom: 2px; border-bottom: 1px solid #f1f5f9; }
    .mini-row b { color: #475569; font-weight: 800; }
    .tag-inst { background: #edf2f7; padding: 1px 4px; border-radius: 3px; font-weight: 800; color: var(--primary-blue); font-size: 9px; }
    
    .col-id { width: 90px; }
    .col-name { width: 220px; }
    .col-fin { width: 110px; }
    .col-phys { width: 140px; background: #fcfdfe; }
    .col-status { width: 65px; text-align: center; }

    .btn-xls { background: #107c41; color: white; border: 0; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; }
    .btn-pdf { background: #d32f2f; color: white; border: 0; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; }
    
    select.filter-select { font-size: 12px; padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; color: #334155; min-width: 140px; }
</style>

<div class="panel-report">
    <!-- Scope Indicator -->
    <div style="background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid var(--primary-blue);">
        <small style="text-transform: uppercase; font-weight: 800; color: #64748b; font-size: 10px;">Export Scope</small>
        <div style="font-weight: 700; color: var(--primary-blue);">
            <i class="fa fa-filter"></i> 
            <?= h($selectedInst) ?> &raquo; <?= h($selectedDiv) ?> Projects
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0; font-size: 1.4rem;"><i class="fa fa-file-contract"></i> Executive Report</h3>
        <div style="display:flex; gap:10px;">
            <button class="btn-xls" onclick="exportToExcel()"><i class="fa fa-file-excel"></i> Export Excel</button>
            <button class="btn-pdf" onclick="exportToPDF()"><i class="fa fa-file-pdf"></i> Export PDF</button>
        </div>
    </div>

    <!-- Dropdown filters (synced with sidebar parameters) -->
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:12px;">
        <input type="hidden" name="page" value="reports">
        
        <div style="display:flex; flex-direction:column; gap:4px;">
            <small style="font-weight:800; color:#64748b; font-size:9px;">SECTOR</small>
            <select name="sector" class="filter-select" onchange="this.form.submit()">
                <option value="All">All Sectors</option>
                <option value="Ports" <?= $selectedSector==='Ports'?'selected':'' ?>>Ports</option>
                <option value="Aviation" <?= $selectedSector==='Aviation'?'selected':'' ?>>Aviation</option>
            </select>
        </div>

        <div style="display:flex; flex-direction:column; gap:4px;">
            <small style="font-weight:800; color:#64748b; font-size:9px;">INSTITUTION</small>
            <select name="inst" class="filter-select" onchange="this.form.submit()">
                <option value="All">All Institutions</option>
                <?php foreach($instDropdown as $i): ?><option value="<?= h($i) ?>" <?= $selectedInst===$i?'selected':'' ?>><?= h($i) ?></option><?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; flex-direction:column; gap:4px;">
            <small style="font-weight:800; color:#64748b; font-size:9px;">DIVISION</small>
            <select name="div" class="filter-select" onchange="this.form.submit()">
                <option value="All">All Divisions</option>
                <?php foreach($divDropdown as $d): ?><option value="<?= h($d) ?>" <?= $selectedDiv===$d?'selected':'' ?>><?= h($d) ?></option><?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="table-wrap-report">
    <table id="reportTable" class="report-table">
        <thead>
            <tr>
                <th style="width:25px">#</th>
                <th class="col-id">Identity</th>
                <th class="col-name">Project Name</th>
                <th class="col-fin">Budget (Mn)</th>
                <th class="col-fin">Financial Q1</th>
                <th class="col-phys">Physical Q1 (6-Points)</th>
                <th class="col-fin">Historical</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered)): ?>
                <tr><td colspan="8" align="center" style="padding:40px; font-size:14px; color:#64748b;">No projects found for the selected scope.</td></tr>
            <?php else: ?>
                <?php foreach ($filtered as $idx => $p): 
                    $phys = $p['phys_percent'] ?? [];
                ?>
                <tr>
                    <td align="center" style="font-weight:bold;"><?= $idx + 1 ?></td>
                    <td>
                        <span class="tag-inst"><?= h($p['institution']) ?></span>
                        <div class="mini-row" style="margin-top:4px;"><b>ID:</b> <span><?= h($p['project_cord'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>DIV:</b> <span><?= h($p['division'] ?? '-') ?></span></div>
                    </td>
                    <td><div style="font-weight:800; color:var(--primary-blue); font-size: 11px;"><?= h($p['project_name']) ?></div></td>
                    <td>
                        <div class="mini-row"><b>Orig:</b> <span style="font-weight:700;"><?= showv($p['allocation_2026_original'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Rev:</b> <span style="font-weight:700; color:#1e3a5f;"><?= showv($p['allocation_2026_revised'] ?? '-') ?></span></div>
                    </td>
                    <td>
                        <div class="mini-row"><b>Target:</b> <span><?= showv($p['q1_fin_target'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Actual:</b> <span style="font-weight:900; color:#0f172a;"><?= showv($p['actual_exp']['Q1'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Bills:</b> <span><?= showv($p['q1_bills_in_hand'] ?? '-') ?></span></div>
                    </td>
                    <td class="col-phys">
                        <div class="mini-row"><b>Q1 Target:</b> <span><?= showv($phys['Q1_Target'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Q1 Actual:</b> <span style="color:var(--phys-blue); font-weight:900;"><?= showv($phys['Q1'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Qly Cum:</b> <span><?= showv($phys['Q1_Quarterly_Cum'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Overall Tgt:</b> <span><?= showv($phys['Q1_Cum_Target'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Overall Prg:</b> <span><?= showv($phys['Q1_Cum_Prog'] ?? '-') ?></span></div>
                        <div class="mini-row" style="background:#f1f5f9;"><b>Achievement:</b> <span style="font-weight:900; color:var(--primary-blue);"><?= showv($phys['Q1_Overall_Prog_Final'] ?? '-') ?></span></div>
                    </td>
                    <td>
                        <div class="mini-row"><b>Phys 2025:</b> <span><?= showv($p['cum_phys_2025'] ?? '-') ?></span></div>
                        <div class="mini-row"><b>Exp 2025:</b> <span><?= showv($p['cum_exp_2025'] ?? '-') ?></span></div>
                    </td>
                    <td class="col-status">
                        <div style="font-weight:900; color:<?= (strtolower($p['timeline_status'] ?? '')=='delayed'?'#d32f2f':'#137448') ?>; font-size:9px; text-transform:uppercase;">
                            <?= h($p['timeline_status'] ?? '-') ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
function exportToExcel() {
    const table = document.getElementById('reportTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Q1 Report" });
    XLSX.writeFile(wb, "Ministry_Q1_Full_Report.xlsx");
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a3'); 
    doc.setFontSize(20);
    doc.setTextColor(30, 58, 95);
    doc.text("Ministry Action Plan 2026 - Q1 Progress Report", 15, 15);
    doc.autoTable({
        html: '#reportTable',
        startY: 25,
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [30, 58, 95], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [248, 250, 252] },
        margin: { left: 10, right: 10 }
    });
    doc.save("Ministry_Q1_Progress_Report.pdf");
}
</script>