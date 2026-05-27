<?php

include_once '../db.php';

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    $get = mysqli_query($conn,"
        SELECT project_id
        FROM physical_targets
        WHERE id = $id
    ");

    $row = mysqli_fetch_assoc($get);

    mysqli_query($conn,"
        DELETE FROM physical_targets
        WHERE id = $id
    ");

    header("Location: ../index.php?page=physical_targets&id=".$row['project_id']);
    exit;
}

/*
|--------------------------------------------------------------------------
| ADD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_physical'])) {

    $project_id = (int) $_POST['project_id'];

    $quarter = $_POST['quarter'];

    $overall_target =
        (float) $_POST['overall_physical_target'];

    $progress_31_12_25 =
        (float) $_POST['progress_31_12_25'];

    $descriptive_target =
        $_POST['descriptive_target'];

    $descriptive_progress =
        $_POST['descriptive_progress'];

    /*
    |--------------------------------------------------------------------------
    | SYSTEM CALCULATION
    |--------------------------------------------------------------------------
    */

    $cumulative_progress =
        $progress_31_12_25 + $overall_target;

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO physical_targets (

            project_id,
            quarter,
            overall_physical_target,
            progress_31_12_25,
            descriptive_target,
            descriptive_progress,
            cumulative_progress

        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isddssd",

        $project_id,
        $quarter,
        $overall_target,
        $progress_31_12_25,
        $descriptive_target,
        $descriptive_progress,
        $cumulative_progress
    );

    mysqli_stmt_execute($stmt);

    header("Location: ../index.php?page=physical_targets&id=".$project_id);
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_physical'])) {

    $id = (int) $_POST['id'];

    $project_id = (int) $_POST['project_id'];

    $quarter = $_POST['quarter'];

    $overall_target =
        (float) $_POST['overall_physical_target'];

    $progress_31_12_25 =
        (float) $_POST['progress_31_12_25'];

    $descriptive_target =
        $_POST['descriptive_target'];

    $descriptive_progress =
        $_POST['descriptive_progress'];

    $cumulative_progress =
        $progress_31_12_25 + $overall_target;

    $sql = "
        UPDATE physical_targets SET

            quarter=?,
            overall_physical_target=?,
            progress_31_12_25=?,
            descriptive_target=?,
            descriptive_progress=?,
            cumulative_progress=?

        WHERE id=?
    ";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sddssdi",

        $quarter,
        $overall_target,
        $progress_31_12_25,
        $descriptive_target,
        $descriptive_progress,
        $cumulative_progress,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: ../index.php?page=physical_targets&id=".$project_id);
    exit;
}
?>