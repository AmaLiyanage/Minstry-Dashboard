<?php
include_once __DIR__ . '/../db.php';

$institution_id = $_GET['institution_id'];

$sql = "SELECT * FROM divisions WHERE institution_id = '$institution_id'";
$result = mysqli_query($conn, $sql);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode($data);
?>