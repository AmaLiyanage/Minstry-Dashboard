<?php

$org = $_GET['org'] ?? '';
$division = $_GET['division'] ?? 'all';

if (empty($org) || empty($division) || $division === 'all') {
    echo "<h1>Invalid Selection</h1><p>Please select a specific organization and division.</p>";
    return;
}

// Helper to determine sector from org code
function get_sector_from_org(string $inst): string {
    $inst = strtoupper(trim($inst));
    if (in_array($inst, ['AASL', 'CAASL'], true)) return 'aviation';
    if ($inst === 'JCT') return 'jct';
    // Default to ports for SLPA, MSS, CSC
    return 'ports';
}

$sector = get_sector_from_org($org);

// Helper to build URLs, simplified from index.php
function build_action_url($page, $org, $division, $sector) {
    $params = [
        'page' => $page,
        'org' => $org,
        'division' => $division,
        'sector' => $sector
    ];
    return 'index.php?' . http_build_query($params);
}

$actions = [
    ['page' => 'project_create', 'label' => 'Add Project Details', 'icon' => 'fa-plus-circle', 'color' => '#2563eb'],
    ['page' => 'add_financial', 'label' => 'Add Financial Details', 'icon' => 'fa-money-bill-wave', 'color' => '#16a34a'],
    ['page' => 'physical_progress', 'label' => 'Add Physical Details', 'icon' => 'fa-bars-progress', 'color' => '#ca8a04'],
    ['page' => 'project_list', 'label' => 'View Project List', 'icon' => 'fa-table-list', 'color' => '#475569'],
    ['page' => 'project_financial', 'label' => 'View Financials', 'icon' => 'fa-chart-pie', 'color' => '#0f766e'],
    ['page' => 'physical_progress_display', 'label' => 'View Physical Progress', 'icon' => 'fa-tasks', 'color' => '#7c3aed'],
];

?>

<style>
    .division-view-page {
        max-width: 1100px;
        margin: 0 auto;
        animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .division-dashboard-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        color: white;
        padding: 30px 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .division-dashboard-header h1 {
        margin: 0 0 5px 0;
        font-size: 28px;
        font-weight: 800;
    }
    .division-dashboard-header p {
        margin: 0;
        font-size: 16px;
        font-weight: 500;
        color: #93c5fd;
    }
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 25px;
    }
    .action-card {
        background: #fff;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        text-decoration: none;
        color: #0f172a;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 180px;
    }
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        border-color: var(--card-color);
    }
    .action-card i {
        font-size: 36px;
        color: var(--card-color);
        margin-bottom: 20px;
        transition: transform 0.3s ease;
    }
    .action-card:hover i {
        transform: scale(1.1);
    }
    .action-card span {
        font-weight: 700;
        font-size: 16px;
        line-height: 1.4;
    }
</style>

<div class="division-view-page">
    <div class="division-dashboard-header">
        <h1><?= htmlspecialchars($org) ?></h1>
        <p>Division Dashboard: <?= htmlspecialchars($division) ?></p>
    </div>

    <div class="action-grid">
        <?php foreach ($actions as $action): ?>
            <a href="<?= build_action_url($action['page'], $org, $division, $sector) ?>" class="action-card" style="--card-color: <?= $action['color'] ?>;">
                <i class="fa-solid <?= $action['icon'] ?>"></i>
                <span><?= $action['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>