<?php

ob_start();

include("../db.php");

function redirect_to_divisions_panel() {
    if (ob_get_length()) {
        ob_clean();
    }

    header("Location: ../index.php?page=organization_structure#divisions-panel");
    exit;
}


/* =========================
   CREATE DIVISION
========================= */

if (isset($_POST['create_division'])) {

    $institution_id = $_POST['institution_id'];

    $division_name = trim($_POST['division_name']);

    if (!empty($division_name) && !empty($institution_id)) {

        $sql = "INSERT INTO divisions
                (institution_id, division_name)
                VALUES (?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "is",
            $institution_id,
            $division_name
        );

        if (mysqli_stmt_execute($stmt)) {

            redirect_to_divisions_panel();

        } else {

            echo "Error: " . mysqli_error($conn);

        }

    } else {

        echo "All fields are required";

    }
}



/* =========================
   DISPLAY DIVISIONS
========================= */

if (isset($_GET['fetch_divisions'])) {

    $sql = "SELECT divisions.id,
                   divisions.division_name,
                   divisions.institution_id,
                   institutions.institution_name,
                   categories.category_name
            FROM divisions

            INNER JOIN institutions
            ON divisions.institution_id = institutions.id

            INNER JOIN categories
            ON institutions.category_id = categories.id

            ORDER BY divisions.id DESC";

    $result = mysqli_query($conn, $sql);

    $divisions = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $divisions[] = $row;

    }

    echo json_encode($divisions);

}



/* =========================
   UPDATE DIVISION
========================= */

if (isset($_POST['update_division'])) {

    $id = $_POST['id'];

    $institution_id = $_POST['institution_id'];

    $division_name = trim($_POST['division_name']);

    if (!empty($division_name) && !empty($institution_id)) {

        $sql = "UPDATE divisions
                SET institution_id = ?,
                    division_name = ?
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "isi",
            $institution_id,
            $division_name,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            redirect_to_divisions_panel();

        } else {

            echo "Error: " . mysqli_error($conn);

        }

    } else {

        echo "All fields are required";

    }
}



/* =========================
   DELETE DIVISION
========================= */

if (isset($_POST['delete_division'])) {

    $id = $_POST['id'];

    $sql = "DELETE FROM divisions
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    if (mysqli_stmt_execute($stmt)) {

        redirect_to_divisions_panel();

    } else {

        echo "Error: " . mysqli_error($conn);

    }

}

?>
