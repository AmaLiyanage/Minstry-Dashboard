<?php
include_once __DIR__ . '/../db.php';

// Quick aggregations for a lively first impression
$portsCount = 0;
$aviationCount = 0;

$sql = "SELECT p.id, i.code, i.institution_name FROM projects p LEFT JOIN institutions i ON p.institution_id = i.id";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $code = strtoupper(trim($row['code'] ?: $row['institution_name'] ?? ''));
        if (strpos($code, 'AASL') !== false || strpos($code, 'CAASL') !== false) {
            $aviationCount++;
        } else {
            $portsCount++;
        }
    }
}
?>

<style>
    .welcome-page {
        padding: 20px 24px;
        max-width: 1100px;
        margin: 0 auto;
        font-family: "Inter", system-ui, sans-serif;
        animation: fadeSlideUp 0.6s ease-out forwards;
    }

    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .welcome-hero {
        text-align: center;
        margin-bottom: 40px;
        padding: 50px 24px;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        border-radius: 24px;
        color: white;
        box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.2);
        position: relative;
        overflow: hidden;
    }

    .welcome-hero::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at 50% -20%, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }

    .welcome-hero h1 {
        font-size: 36px;
        font-weight: 800;
        margin: 0 0 16px 0;
        letter-spacing: -0.02em;
    }

    .welcome-hero p {
        font-size: 16px;
        color: #94a3b8;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .sector-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 32px;
    }

    .sector-card {
        background: white;
        border-radius: 24px;
        padding: 40px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .sector-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.1);
    }

    .sector-icon {
        font-size: 44px;
        margin-bottom: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 80px;
        height: 80px;
        border-radius: 20px;
    }

    .sector-card.ports .sector-icon { color: #0284c7; background: #e0f2fe; }
    .sector-card.aviation .sector-icon { color: #4f46e5; background: #e0e7ff; }

    .sector-title {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .sector-count {
        font-size: 13px;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
        padding: 6px 12px;
        border-radius: 99px;
    }

    .sector-desc {
        font-size: 14.5px;
        color: #475569;
        line-height: 1.6;
        margin: 0 0 32px 0;
        flex-grow: 1;
    }

    .inst-links {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .btn-main {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 14.5px;
        text-decoration: none;
        color: white;
        width: 100%;
        justify-content: center;
        margin-bottom: 8px;
        transition: opacity 0.2s, transform 0.2s;
    }

    .btn-main:hover { opacity: 0.9; transform: translateY(-1px); }
    .sector-card.ports .btn-main { background: #0284c7; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3); }
    .sector-card.aviation .btn-main { background: #4f46e5; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }

    .btn-sub {
        flex: 1;
        text-align: center;
        padding: 12px 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #334155;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        transition: background 0.2s, color 0.2s, border-color 0.2s;
    }

    .btn-sub:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #cbd5e1;
    }

    @media (max-width: 768px) {
        .sector-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="welcome-page">
    <div class="welcome-hero">
        <h1>Ministry Projects Dashboard</h1>
        <p>A centralized platform for tracking the operational, physical, and financial progression of major infrastructure developments.</p>
    </div>

    <div class="sector-grid">
        <!-- Maritime & Ports -->
        <div class="sector-card ports">
            <div class="sector-icon"><i class="fa-solid fa-ship"></i></div>
            <h2 class="sector-title">Maritime & Ports <span class="sector-count"><?= $portsCount ?> Projects</span></h2>
            <p class="sector-desc">Track infrastructure and framework development projects across all port facilities, merchant shipping, and maritime authorities.</p>
            <div class="inst-links">
                <a href="index.php?page=home&sector=ports" class="btn-main"><i class="fa-solid fa-anchor"></i> View All Maritime Operations</a>
            </div>
        </div>

        <!-- Aviation -->
        <div class="sector-card aviation">
            <div class="sector-icon"><i class="fa-solid fa-plane-up"></i></div>
            <h2 class="sector-title">Aviation Sector <span class="sector-count"><?= $aviationCount ?> Projects</span></h2>
            <p class="sector-desc">Monitor civil aviation regulatory frameworks, airport expansions, and terminal maintenance infrastructure deployments.</p>
            <div class="inst-links">
                <a href="index.php?page=home&sector=aviation" class="btn-main"><i class="fa-solid fa-plane-departure"></i> View All Aviation Operations</a>
            </div>
        </div>
    </div>
</div>