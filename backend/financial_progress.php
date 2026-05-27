<?php

include_once '../db.php';

/*
|--------------------------------------------------------------------------
| CREATE
|--------------------------------------------------------------------------
*/

if (isset($_POST['create_financial'])) {

    $project_id = (int) $_POST['project_id'];
    $quarter = $_POST['quarter'];

    $cum_fin_target = (float) $_POST['cum_fin_target'];
    $actual_expenditure = (float) $_POST['actual_expenditure'];
    $bills_in_hand = (float) $_POST['bills_in_hand'];

    /*
    |--------------------------------------------------------------------------
    | SYSTEM CALCULATIONS
    |--------------------------------------------------------------------------
    */

    $cumulative_expenditure =
        $actual_expenditure + $bills_in_hand;

    $financial_progress_percentage = 0;

    if ($cum_fin_target > 0) {

        $financial_progress_percentage =
            ($cumulative_expenditure / $cum_fin_target) * 100;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE QUARTER
    |--------------------------------------------------------------------------
    */

    $check_sql = "
        SELECT id
        FROM financial_progress
        WHERE project_id = ?
        AND quarter = ?
    ";

    $check_stmt = mysqli_prepare($conn, $check_sql);

    mysqli_stmt_bind_param(
        $check_stmt,
        "is",
        $project_id,
        $quarter
    );

    mysqli_stmt_execute($check_stmt);

    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {

        die("Quarter already exists for this project.");
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO financial_progress (

            project_id,
            quarter,
            cum_fin_target,
            actual_expenditure,
            bills_in_hand,
            cumulative_expenditure,
            financial_progress_percentage

        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isddddd",

        $project_id,
        $quarter,
        $cum_fin_target,
        $actual_expenditure,
        $bills_in_hand,
        $cumulative_expenditure,
        $financial_progress_percentage
    );

    if (mysqli_stmt_execute($stmt)) {

        header("Location: ../index.php?page=project_financial&id=" . $project_id);
        exit;

    } else {

        echo mysqli_error($conn);
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_financial'])) {

    $id = (int) $_POST['id'];

    $cum_fin_target = (float) $_POST['cum_fin_target'];
    $actual_expenditure = (float) $_POST['actual_expenditure'];
    $bills_in_hand = (float) $_POST['bills_in_hand'];

    /*
    |--------------------------------------------------------------------------
    | CALCULATIONS
    |--------------------------------------------------------------------------
    */

    $cumulative_expenditure =
        $actual_expenditure + $bills_in_hand;

    $financial_progress_percentage = 0;

    if ($cum_fin_target > 0) {

        $financial_progress_percentage =
            ($cumulative_expenditure / $cum_fin_target) * 100;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE financial_progress
        SET

            cum_fin_target = ?,
            actual_expenditure = ?,
            bills_in_hand = ?,
            cumulative_expenditure = ?,
            financial_progress_percentage = ?

        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "dddddi",

        $cum_fin_target,
        $actual_expenditure,
        $bills_in_hand,
        $cumulative_expenditure,
        $financial_progress_percentage,
        $id
    );

    if (mysqli_stmt_execute($stmt)) {

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;

    } else {

        echo mysqli_error($conn);
    }
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    $sql = "
        DELETE FROM financial_progress
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    if (mysqli_stmt_execute($stmt)) {

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;

    } else {

        echo mysqli_error($conn);
    }
}
?>