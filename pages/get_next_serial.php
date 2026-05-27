<?php
include_once __DIR__ . '/../db.php';

$category_id = (int)($_GET['category_id'] ?? 0);
$institution_id = (int)($_GET['institution_id'] ?? 0);

$next_serial = 1;
$next_inst_serial = 1;

if ($category_id > 0) {
    // Get the highest serial number currently assigned to this category
    $sql = "SELECT MAX(CAST(serial_no AS UNSIGNED)) as max_serial FROM projects WHERE category_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $category_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $next_serial = (int)$row['max_serial'] + 1;
        }
        mysqli_stmt_close($stmt);
    }
}

if ($institution_id > 0) {
    // Get the highest serial number currently assigned to this institution's category string
    $sql2 = "SELECT MAX(CAST(SUBSTRING_INDEX(institution_category_no, '-', -1) AS UNSIGNED)) as max_inst_serial FROM projects WHERE institution_id = ?";
    $stmt2 = mysqli_prepare($conn, $sql2);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 'i', $institution_id);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        if ($row2 = mysqli_fetch_assoc($result2)) {
            $next_inst_serial = (int)$row2['max_inst_serial'] + 1;
        }
        mysqli_stmt_close($stmt2);
    }
}

echo json_encode([
    'next_serial' => sprintf('%04d', $next_serial),
    'next_inst_serial' => sprintf('%04d', $next_inst_serial)
]);
?>