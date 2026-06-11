<?php
include_once __DIR__ . '/auth_admin.php';
include_once __DIR__ . "/../db.php";

$script_name = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
$app_url = rtrim(dirname($script_name), "/");

if (basename($app_url) === "pages") {
    $app_url = rtrim(dirname($app_url), "/");
}

if ($app_url === "/" || $app_url === ".") {
    $app_url = "";
}

$category_action = htmlspecialchars($app_url . "/backend/category.php", ENT_QUOTES, "UTF-8");
$institution_action = htmlspecialchars($app_url . "/backend/institution.php", ENT_QUOTES, "UTF-8");
$division_action = htmlspecialchars($app_url . "/backend/division.php", ENT_QUOTES, "UTF-8");

function e($value): string {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Management</title>

    <style>
        .org-structure {
            /* Modernized Color Palette & System Tokens */
            --page-bg: #f8fafc;
            --panel-bg: transparent;
            --card-bg: #ffffff;
            --panel-border: transparent;
            --line: #f1f5f9;
            
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-inverse: #ffffff;
            
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-soft: #eef2ff;
            
            --success: #0d9488;
            --success-hover: #0f766e;
            --success-soft: #f0fdfa;
            
            --danger: #e11d48;
            --danger-hover: #be123c;
            --danger-soft: #fff1f2;
            --danger-border: #fecdd3;
            
            --field-bg: #ffffff;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);

            min-height: 100vh;
            padding: clamp(16px, 4vw, 40px);
            font-family: "Inter", system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            background: var(--page-bg);
            -webkit-font-smoothing: antialiased;
        }

        .org-structure *,
        .org-structure *::before,
        .org-structure *::after {
            box-sizing: border-box;
        }

        /* Base Title */
        .org-structure > h1 {
            max-width: 1280px;
            margin: 0 auto 24px;
            color: var(--text-main);
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 800;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .org-structure > h1::before {
            content: "";
            width: 6px;
            height: 28px;
            border-radius: 999px;
            background: var(--primary);
        }

        /* Modern Segmented Control for Tabs */
        .org-structure .structure-tabs {
            max-width: 1280px;
            margin: 0 auto 32px;
            padding: 6px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            background: #e2e8f0;
            border-radius: var(--radius-md);
        }

        .org-structure .structure-tab {
            min-height: 40px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            background: transparent;
            border: none;
            border-radius: calc(var(--radius-md) - 4px);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .org-structure .structure-tab:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.5);
        }

        .org-structure .structure-tab.is-active {
            color: var(--primary);
            background: #ffffff;
            box-shadow: var(--shadow-sm);
        }

        /* Main Container Panel */
        .org-structure .container {
            max-width: 1280px;
            margin: 0 auto;
            background: var(--panel-bg);
            border: none;
            border-radius: 0;
            box-shadow: none;
        }

        .org-structure .structure-section[hidden] {
            display: none;
        }

        /* Dynamic Section Header Layout */
        .org-structure .section-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 0 0 24px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .org-structure h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .org-structure h2::before {
            display: none;
        }

        /* Smooth Interactive Input Fields */
        .org-structure input,
        .org-structure select {
            width: 100%;
            min-height: 44px;
            padding: 10px 14px;
            color: var(--text-main);
            background: var(--field-bg);
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-sm);
            font: inherit;
            font-size: 14px;
            outline: none;
            box-shadow: var(--shadow-sm);
            transition: all 0.15s ease;
        }

        .org-structure input:focus,
        .org-structure select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }

        .org-structure .section-search {
            width: min(100%, 320px);
            display: flex;
            flex-direction: column;
            gap: 6px;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Dashboard Grid Layout (Converts Table view to Grid Workspaces) */
        .org-structure .structure-section {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        /* Master Form Container Split */
        .org-structure .container > form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            background: #ffffff;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        /* Grid layout for Cards */
        .org-structure .record-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        /* Fully Revamped Grid Entity Cards */
        .org-structure .record-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            padding: 24px;
            background: var(--card-bg);
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .org-structure .record-card:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            box-shadow: var(--shadow-md);
        }

        .org-structure .record-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .org-structure .record-title {
            margin: 0;
            color: var(--text-main);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.4;
        }

        .org-structure .record-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        /* Subtle modern chip styles */
        .org-structure .meta-pill {
            padding: 4px 10px;
            color: var(--primary);
            background: var(--primary-soft);
            border: none;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Action forms & Details cleanups */
        .org-structure .record-actions {
            width: 100%;
            margin-top: auto;
            border-top: 1px solid var(--line);
            padding-top: 16px;
        }

        .org-structure .record-edit {
            border: none;
            background: transparent;
        }

        .org-structure .record-edit summary {
            padding: 0;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            list-style: none;
            cursor: pointer;
        }

        .org-structure .record-edit summary:hover {
            color: var(--primary);
        }

        .org-structure .record-edit summary::after {
            content: "Manage ↗";
            text-transform: none;
            font-size: 12px;
            color: var(--primary);
            background: var(--primary-soft);
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            transition: background 0.15s ease;
        }

        .org-structure .record-edit[open] summary::after {
            content: "Collapse";
            color: var(--text-muted);
            background: #f1f5f9;
        }

        /* Collapsible Form Interior Elements */
        .org-structure .edit-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 20px 0 12px 0;
        }

        .org-structure .delete-form {
            padding: 0;
            margin-top: 4px;
        }

        /* Buttons Transformation */
        .org-structure button {
            min-height: 40px;
            padding: 10px 18px;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .org-structure button:hover {
            transform: none;
        }

        .org-structure button[name^="create"] {
            color: var(--text-inverse);
            background: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .org-structure button[name^="create"]:hover {
            background: var(--primary-hover);
        }

        .org-structure button[name^="update"] {
            color: var(--text-inverse);
            background: var(--success);
            width: 100%;
        }

        .org-structure button[name^="update"]:hover {
            background: var(--success-hover);
        }

        .org-structure button[name^="delete"] {
            color: var(--danger);
            background: var(--danger-soft);
            border: 1px solid var(--danger-border);
            width: 100%;
        }

        .org-structure button[name^="delete"]:hover {
            background: var(--danger);
            color: var(--text-inverse);
            border-color: var(--danger);
        }

        /* Load More Area */
        .org-structure .load-more-wrap {
            display: flex;
            justify-content: center;
            padding: 24px 0 0 0;
            border-top: 1px solid #e2e8f0;
        }

        .org-structure .load-more {
            color: var(--text-main);
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            padding: 12px 24px;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
        }

        .org-structure .load-more:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Sleek Empty State Template */
        .org-structure .empty-state {
            grid-column: 1 / -1;
            margin: 0;
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-lg);
            font-weight: 500;
        }

        /* Desktop Form Adaptation Details */
        @media (min-width: 640px) {
            .org-structure .container > form {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) auto;
                align-items: end;
            }
            .org-structure .container > form br {
                display: none;
            }
        }

        /* Clean up system wrappers */
        .org-structure br {
            display: none;
        }
    </style>
</head>
<body>

<main class="org-structure">

<h1>Master Data Management</h1>

<div class="structure-tabs" role="tablist" aria-label="Master data sections">
    <button
        type="button"
        class="structure-tab is-active"
        data-target="categories-panel"
        role="tab"
        aria-selected="true"
        aria-controls="categories-panel"
    >
        <span class="tab-text">Categories</span>
    </button>

    <button
        type="button"
        class="structure-tab"
        data-target="institutions-panel"
        role="tab"
        aria-selected="false"
        aria-controls="institutions-panel"
    >
        <span class="tab-text">Institutions</span>
    </button>

    <button
        type="button"
        class="structure-tab"
        data-target="divisions-panel"
        role="tab"
        aria-selected="false"
        aria-controls="divisions-panel"
    >
        <span class="tab-text">Divisions</span>
    </button>
</div>


<!-- =========================
     CATEGORY SECTION
========================= -->

<section class="container structure-section" id="categories-panel" role="tabpanel" data-page-size="9">

    <div class="section-head">
        <div>
            <h2>Categories</h2>
        </div>

        <label class="section-search">
            Search
            <input type="search" class="record-search" placeholder="Find category">
        </label>
    </div>

    <form action="<?= $category_action; ?>" method="POST">
        <input
            type="text"
            name="category_name"
            placeholder="Enter category name"
            required
        >

        <button type="submit" name="create_category">
            Add Category
        </button>
    </form>

    <div class="record-grid">

        <?php
        $category_query = mysqli_query(
            $conn,
            "SELECT * FROM categories ORDER BY id DESC"
        );

        while($category = mysqli_fetch_assoc($category_query)) {
        ?>

        <article class="record-card" data-record-card data-search="<?= e($category['id'] . ' ' . $category['category_name']); ?>">
            <div class="record-top">
                <h3 class="record-title"><?= e($category['category_name']); ?></h3>
            </div>

            <div class="record-meta">
                <span class="meta-pill">Category</span>
            </div>

            <div class="record-actions">
                <details class="record-edit">
                    <summary>Manage</summary>

                    <form class="edit-form" action="<?= $category_action; ?>" method="POST">
                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($category['id']); ?>"
                        >

                        <input
                            type="text"
                            name="category_name"
                            value="<?= e($category['category_name']); ?>"
                            required
                        >

                        <button
                            type="submit"
                            name="update_category"
                        >
                            Update
                        </button>
                    </form>

                    <form class="delete-form" action="<?= $category_action; ?>" method="POST">
                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($category['id']); ?>"
                        >

                        <button
                            type="submit"
                            name="delete_category"
                            onclick="return confirm('Delete category?')"
                        >
                            Delete
                        </button>
                    </form>
                </details>
            </div>
        </article>

        <?php } ?>

    </div>

    <p class="empty-state">No categories match your search.</p>

    <div class="load-more-wrap">
        <button type="button" class="load-more" data-load-more>Show more</button>
    </div>

</section>




<!-- =========================
     INSTITUTION SECTION
========================= -->

<section class="container structure-section" id="institutions-panel" role="tabpanel" data-page-size="9" hidden>

    <div class="section-head">
        <div>
            <h2>Institutions</h2>
        </div>

        <label class="section-search">
            Search
            <input type="search" class="record-search" placeholder="Find institution">
        </label>
    </div>

    <form action="<?= $institution_action; ?>" method="POST">
        <select name="category_id" required>
            <option value="">Select Category</option>
            <?php
            $categories = mysqli_query(
                $conn,
                "SELECT * FROM categories"
            );

            while($cat = mysqli_fetch_assoc($categories)) {
            ?>
            <option value="<?= e($cat['id']); ?>">
                <?= e($cat['category_name']); ?>
            </option>
            <?php } ?>
        </select>

        <br>

        <input
            type="text"
            name="institution_name"
            placeholder="Enter institution name"
            required
        >

        <button type="submit" name="create_institution">
            Add Institution
        </button>
    </form>

    <div class="record-grid">

        <?php
        $institution_query = mysqli_query(
            $conn,
            "SELECT institutions.*,
                    categories.category_name
             FROM institutions
             INNER JOIN categories
             ON institutions.category_id = categories.id
             ORDER BY institutions.id DESC"
        );

        while($institution = mysqli_fetch_assoc($institution_query)) {
        ?>

        <article class="record-card" data-record-card data-search="<?= e($institution['id'] . ' ' . $institution['category_name'] . ' ' . $institution['institution_name']); ?>">
            <div class="record-top">
                <h3 class="record-title"><?= e($institution['institution_name']); ?></h3>
            </div>

            <div class="record-meta">
                <span class="meta-pill"><?= e($institution['category_name']); ?></span>
            </div>

            <div class="record-actions">
                <details class="record-edit">
                    <summary>Manage</summary>

                    <form class="edit-form" action="<?= $institution_action; ?>" method="POST">
                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($institution['id']); ?>"
                        >

                        <select name="category_id" required>
                            <?php
                            $cat_query = mysqli_query(
                                $conn,
                                "SELECT * FROM categories"
                            );

                            while($cat = mysqli_fetch_assoc($cat_query)) {
                            ?>
                            <option
                                value="<?= e($cat['id']); ?>"
                                <?= ($cat['id'] == $institution['category_id']) ? 'selected' : ''; ?>
                            >
                                <?= e($cat['category_name']); ?>
                            </option>
                            <?php } ?>
                        </select>

                        <input
                            type="text"
                            name="institution_name"
                            value="<?= e($institution['institution_name']); ?>"
                            required
                        >

                        <button
                            type="submit"
                            name="update_institution"
                        >
                            Update
                        </button>
                    </form>

                    <form class="delete-form" action="<?= $institution_action; ?>" method="POST">
                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($institution['id']); ?>"
                        >

                        <button
                            type="submit"
                            name="delete_institution"
                            onclick="return confirm('Delete institution?')"
                        >
                            Delete
                        </button>
                    </form>
                </details>
            </div>
        </article>

        <?php } ?>

    </div>

    <p class="empty-state">No institutions match your search.</p>

    <div class="load-more-wrap">
        <button type="button" class="load-more" data-load-more>Show more</button>
    </div>

</section>





<!-- =========================
     DIVISION SECTION
========================= -->

<section class="container structure-section" id="divisions-panel" role="tabpanel" data-page-size="9" hidden>

    <div class="section-head">
        <div>
            <h2>Divisions</h2>
        </div>

        <label class="section-search">
            Search
            <input type="search" class="record-search" placeholder="Find division">
        </label>
    </div>

    <form action="<?= $division_action; ?>" method="POST">
        <select name="institution_id" required>
            <option value="">Select Institution</option>
            <?php
            $institutions = mysqli_query(
                $conn,
                "SELECT * FROM institutions"
            );

            while($inst = mysqli_fetch_assoc($institutions)) {
            ?>
            <option value="<?= e($inst['id']); ?>">
                <?= e($inst['institution_name']); ?>
            </option>
            <?php } ?>
        </select>

        <br>

        <input
            type="text"
            name="division_name"
            placeholder="Enter division name"
            required
        >

        <button type="submit" name="create_division">
            Add Division
        </button>
    </form>

    <div class="record-grid">

        <?php
        $division_query = mysqli_query(
            $conn,
            "SELECT divisions.*,
                    institutions.institution_name
             FROM divisions
             INNER JOIN institutions
             ON divisions.institution_id = institutions.id
             ORDER BY divisions.id DESC"
        );

        while($division = mysqli_fetch_assoc($division_query)) {
        ?>

        <article class="record-card" data-record-card data-search="<?= e($division['id'] . ' ' . $division['institution_name'] . ' ' . $division['division_name']); ?>">
            <div class="record-top">
                <h3 class="record-title"><?= e($division['division_name']); ?></h3>
            </div>

            <div class="record-meta">
                <span class="meta-pill"><?= e($division['institution_name']); ?></span>
            </div>

            <div class="record-actions">
                <details class="record-edit">
                    <summary>Manage</summary>

                    <form class="edit-form" action="<?= $division_action; ?>" method="POST">
                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($division['id']); ?>"
                        >

                        <select name="institution_id" required>
                            <?php
                            $inst_query = mysqli_query(
                                $conn,
                                "SELECT * FROM institutions"
                            );

                            while($inst = mysqli_fetch_assoc($inst_query)) {
                            ?>
                            <option
                                value="<?= e($inst['id']); ?>"
                                <?= ($inst['id'] == $division['institution_id']) ? 'selected' : ''; ?>
                            >
                                <?= e($inst['institution_name']); ?>
                            </option>
                            <?php } ?>
                        </select>

                        <input
                            type="text"
                            name="division_name"
                            value="<?= e($division['division_name']); ?>"
                            required
                        >

                        <button
                            type="submit"
                            name="update_division"
                        >
                            Update
                        </button>
                    </form>

                    <form class="delete-form" action="<?= $division_action; ?>" method="POST">
                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($division['id']); ?>"
                        >

                        <button
                            type="submit"
                            name="delete_division"
                            onclick="return confirm('Delete division?')"
                        >
                            Delete
                        </button>
                    </form>
                </details>
            </div>
        </article>

        <?php } ?>

    </div>

    <p class="empty-state">No divisions match your search.</p>

    <div class="load-more-wrap">
        <button type="button" class="load-more" data-load-more>Show more</button>
    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.org-structure');
    if (!root) return;

    const tabs = root.querySelectorAll('.structure-tab');
    const panels = root.querySelectorAll('.structure-section');

    function setupSection(section) {
        const pageSize = parseInt(section.dataset.pageSize || '9', 10);
        let visibleLimit = pageSize;
        const search = section.querySelector('.record-search');
        const cards = Array.from(section.querySelectorAll('[data-record-card]'));
        const loadMore = section.querySelector('[data-load-more]');
        const emptyState = section.querySelector('.empty-state');

        function render(resetLimit) {
            if (resetLimit) {
                visibleLimit = pageSize;
            }

            const query = search ? search.value.trim().toLowerCase() : '';
            let matchedCount = 0;

            cards.forEach(function (card) {
                const matchesSearch = !query || (card.dataset.search || '').toLowerCase().includes(query);
                card.classList.toggle('is-filtered', !matchesSearch);

                if (!matchesSearch) {
                    card.classList.remove('is-over-limit');
                    return;
                }

                matchedCount += 1;
                card.classList.toggle('is-over-limit', matchedCount > visibleLimit);
            });

            if (loadMore) {
                const remaining = Math.max(matchedCount - visibleLimit, 0);
                loadMore.hidden = remaining === 0;
                loadMore.textContent = 'Show more';
            }

            if (emptyState) {
                emptyState.classList.toggle('is-visible', matchedCount === 0);
            }
        }

        if (search) {
            search.addEventListener('input', function () {
                render(true);
            });
        }

        if (loadMore) {
            loadMore.addEventListener('click', function () {
                visibleLimit += pageSize;
                render(false);
            });
        }

        section.renderRecords = render;
        render(false);
    }

    panels.forEach(setupSection);

    function activatePanel(targetId, updateHash) {
        tabs.forEach(function (tab) {
            const active = tab.dataset.target === targetId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            panel.hidden = panel.id !== targetId;

            if (panel.id === targetId && panel.renderRecords) {
                panel.renderRecords(false);
            }
        });

        if (updateHash) {
            history.replaceState(null, '', '#' + targetId);
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activatePanel(tab.dataset.target, true);
        });
    });

    const hashTarget = window.location.hash.replace('#', '');
    if (hashTarget && root.querySelector('#' + hashTarget)) {
        activatePanel(hashTarget, false);
    }
});
</script>

</main>

</body>
</html>

```