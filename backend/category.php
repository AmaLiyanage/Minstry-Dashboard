<?php

ob_start();

include("../db.php");

function redirect_to_categories_panel() {
    if (ob_get_length()) {
        ob_clean();
    }

    header("Location: ../index.php?page=organization_structure#categories-panel");
    exit;
}


/* =========================
   CREATE CATEGORY
========================= */

if (isset($_POST['create_category'])) {

    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {

        $sql = "INSERT INTO categories (category_name)
                VALUES (?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $category_name
        );

        if (mysqli_stmt_execute($stmt)) {

            redirect_to_categories_panel();

        } else {

            echo "Error: " . mysqli_error($conn);

        }

    } else {

        echo "Category name is required";

    }
}



/* =========================
   DISPLAY CATEGORIES
========================= */

if (isset($_GET['fetch_categories'])) {

    $sql = "SELECT * FROM categories
            ORDER BY id DESC";

    $result = mysqli_query($conn, $sql);

    $categories = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $categories[] = $row;

    }

    echo json_encode($categories);

}



/* =========================
   UPDATE CATEGORY
========================= */

if (isset($_POST['update_category'])) {

    $id = $_POST['id'];

    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {

        $sql = "UPDATE categories
                SET category_name = ?
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $category_name,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            redirect_to_categories_panel();

        } else {

            echo "Error: " . mysqli_error($conn);

        }

    } else {

        echo "Category name is required";

    }
}



/* =========================
   DELETE CATEGORY
========================= */

if (isset($_POST['delete_category'])) {

    $id = $_POST['id'];

    $sql = "DELETE FROM categories
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    if (mysqli_stmt_execute($stmt)) {

        redirect_to_categories_panel();

    } else {

        echo "Error: " . mysqli_error($conn);

    }

}

?>
