<?php

include_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $project_id = (int) $_POST['project_id'];

    $funding_source =
        $_POST['funding_source'];

    $funding_amount =
        (float) $_POST['funding_amount'];

    $allocation_year =
        $_POST['allocation_year'];

    $allocation_amount =
        (float) $_POST['allocation_amount'];

    $sql = "
        INSERT INTO funding (

            project_id,
            funding_source,
            funding_amount,
            allocation_year,
            allocation_amount

        ) VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isdid",

        $project_id,
        $funding_source,
        $funding_amount,
        $allocation_year,
        $allocation_amount
    );

    mysqli_stmt_execute($stmt);

    header("Location: ../index.php?page=funding&id=".$project_id);
}
?>