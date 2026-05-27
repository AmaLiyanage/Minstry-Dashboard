<?php

include_once __DIR__ . "/../db.php";


/* =========================
   CREATE PROJECT
========================= */

if (isset($_POST['create_project'])) {

    $sql = "INSERT INTO projects (
        serial_no,
        institution_category_no,
        project_code,
        category_id,
        institution_id,
        division_id,
        project_name,
        project_detail,
        target_activities_2026,
        location,
        total_est_cost_original,
        total_est_cost_revised,
        project_period_original,
        project_period_revised,
        funding_source,
        allocation_2026_original,
        allocation_2026_revised,
        timeline_status,
        reasons_not_achieving_targets
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "issiiissssddsssddss",
        $_POST['serial_no'],
        $_POST['institution_category_no'],
        $_POST['project_code'],
        $_POST['category_id'],
        $_POST['institution_id'],
        $_POST['division_id'],
        $_POST['project_name'],
        $_POST['project_detail'],
        $_POST['target_activities_2026'],
        $_POST['location'],
        $_POST['total_est_cost_original'],
        $_POST['total_est_cost_revised'],
        $_POST['project_period_original'],
        $_POST['project_period_revised'],
        $_POST['funding_source'],
        $_POST['allocation_2026_original'],
        $_POST['allocation_2026_revised'],
        $_POST['timeline_status'],
        $_POST['reasons_not_achieving_targets']
    );

    if (mysqli_stmt_execute($stmt)) {
        echo "Project created successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}


/* =========================
   DISPLAY PROJECTS
========================= */

if (isset($_GET['fetch_projects'])) {

    $sql = "SELECT p.*,
                   c.category_name,
                   i.institution_name,
                   d.division_name
            FROM projects p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN institutions i ON p.institution_id = i.id
            LEFT JOIN divisions d ON p.division_id = d.id
            ORDER BY p.id DESC";

    $result = mysqli_query($conn, $sql);

    $projects = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
    }

    echo json_encode($projects);
}


/* =========================
   UPDATE PROJECT
========================= */

if (isset($_POST['update_project'])) {

    $sql = "UPDATE projects SET
        serial_no=?,
        institution_category_no=?,
        project_code=?,
        category_id=?,
        institution_id=?,
        division_id=?,
        project_name=?,
        project_detail=?,
        target_activities_2026=?,
        location=?,
        total_est_cost_original=?,
        total_est_cost_revised=?,
        project_period_original=?,
        project_period_revised=?,
        funding_source=?,
        allocation_2026_original=?,
        allocation_2026_revised=?,
        timeline_status=?,
        reasons_not_achieving_targets=?
        WHERE id=?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "issiiissssddsssddssi",
        $_POST['serial_no'],
        $_POST['institution_category_no'],
        $_POST['project_code'],
        $_POST['category_id'],
        $_POST['institution_id'],
        $_POST['division_id'],
        $_POST['project_name'],
        $_POST['project_detail'],
        $_POST['target_activities_2026'],
        $_POST['location'],
        $_POST['total_est_cost_original'],
        $_POST['total_est_cost_revised'],
        $_POST['project_period_original'],
        $_POST['project_period_revised'],
        $_POST['funding_source'],
        $_POST['allocation_2026_original'],
        $_POST['allocation_2026_revised'],
        $_POST['timeline_status'],
        $_POST['reasons_not_achieving_targets'],
        $_POST['id']
    );

    if (mysqli_stmt_execute($stmt)) {
        echo "Project updated successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}


/* =========================
   DELETE PROJECT
========================= */

if (isset($_POST['delete_project'])) {

    $id = $_POST['id'];

    $sql = "DELETE FROM projects WHERE id=?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo "Project deleted successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

?>