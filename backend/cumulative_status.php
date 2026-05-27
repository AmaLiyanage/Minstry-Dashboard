<?php

include_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $project_id = (int) $_POST['project_id'];

    $quarter = $_POST['quarter'];

    $target =
        (float) $_POST['cumulative_overall_target'];

    $progress =
        (float) $_POST['cumulative_overall_progress'];

    $percentage = 0;

    if ($target > 0) {

        $percentage =
            ($progress / $target) * 100;
    }

    $sql = "
        INSERT INTO cumulative_physical_status (

            project_id,
            quarter,
            cumulative_overall_target,
            cumulative_overall_progress,
            physical_progress_percentage

        ) VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isddd",

        $project_id,
        $quarter,
        $target,
        $progress,
        $percentage
    );

    mysqli_stmt_execute($stmt);

    header("Location: ../index.php?page=cumulative_status&id=".$project_id);
}
?>