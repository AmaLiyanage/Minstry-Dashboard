<?php

include_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $project_id = (int) $_POST['project_id'];

    $quarter = $_POST['quarter'];

    $target =
        (float) $_POST['cumulative_quarterly_target'];

    $progress =
        (float) $_POST['cumulative_quarterly_progress'];

    $descriptive_cumulative_progress =
        $_POST['descriptive_cumulative_progress'];

    $current_target =
        $_POST['current_quarterly_target'];

    $current_progress =
        $_POST['current_quarterly_progress'];

    $sql = "
        INSERT INTO quarterly_physical_progress (

            project_id,
            quarter,
            cumulative_quarterly_target,
            cumulative_quarterly_progress,
            descriptive_cumulative_progress,
            current_quarterly_target,
            current_quarterly_progress

        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isddsss",

        $project_id,
        $quarter,
        $target,
        $progress,
        $descriptive_cumulative_progress,
        $current_target,
        $current_progress
    );

    mysqli_stmt_execute($stmt);

    header("Location: ../index.php?page=quarterly_progress&id=".$project_id);
}
?>