<?php

ob_start();

include("../db.php");

function redirect_to_institutions_panel() {
    if (ob_get_length()) {
        ob_clean();
    }

    header("Location: ../index.php?page=organization_structure#institutions-panel");
    exit;
}


/* =========================
   CREATE INSTITUTION
========================= */

if (isset($_POST['create_institution'])) {

    $category_id = $_POST['category_id'];

    $institution_name = trim($_POST['institution_name']);

    if (!empty($institution_name) && !empty($category_id)) {

        $sql = "INSERT INTO institutions
                (category_id, institution_name)
                VALUES (?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "is",
            $category_id,
            $institution_name
        );

        if (mysqli_stmt_execute($stmt)) {

            redirect_to_institutions_panel();

        } else {

            echo "Error: " . mysqli_error($conn);

        }

    } else {

        echo "All fields are required";

    }
}



/* =========================
   DISPLAY INSTITUTIONS
========================= */

if (isset($_GET['fetch_institutions'])) {

    $sql = "SELECT institutions.id,
                   institutions.institution_name,
                   institutions.category_id,
                   categories.category_name
            FROM institutions
            INNER JOIN categories
            ON institutions.category_id = categories.id
            ORDER BY institutions.id DESC";

    $result = mysqli_query($conn, $sql);

    $institutions = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $institutions[] = $row;

    }

    echo json_encode($institutions);

}



/* =========================
   UPDATE INSTITUTION
========================= */

if (isset($_POST['update_institution'])) {

    $id = $_POST['id'];

    $category_id = $_POST['category_id'];

    $institution_name = trim($_POST['institution_name']);

    if (!empty($institution_name) && !empty($category_id)) {

        $sql = "UPDATE institutions
                SET category_id = ?,
                    institution_name = ?
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "isi",
            $category_id,
            $institution_name,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            redirect_to_institutions_panel();

        } else {

            echo "Error: " . mysqli_error($conn);

        }

    } else {

        echo "All fields are required";

    }
}



/* =========================
   DELETE INSTITUTION
========================= */

if (isset($_POST['delete_institution'])) {

    $id = $_POST['id'];

    $sql = "DELETE FROM institutions
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    if (mysqli_stmt_execute($stmt)) {

        redirect_to_institutions_panel();

    } else {

        echo "Error: " . mysqli_error($conn);

    }

}

?>
