<?php

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "ministry_dashboard";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Optional: set charset (recommended)
mysqli_set_charset($conn, "utf8");

// Connection success message (remove in production)
// echo "Connected successfully";

?>