<?php
include_once __DIR__ . '/../db.php';

$institution_id = (int)($_GET['institution_id'] ?? 0);

$sql = "SELECT MIN(id) AS id, TRIM(division_name) AS division_name 
        FROM divisions 
        WHERE institution_id = $institution_id 
        GROUP BY TRIM(division_name) 
        ORDER BY division_name ASC";
$result = mysqli_query($conn, $sql);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

echo json_encode($data);
?>