<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (basename($path) === 'pages') {
        header("Location: login.php");
    } else {
        header("Location: pages/login.php");
    }
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<div style='padding: 60px; text-align: center; font-family: \"Inter\", sans-serif;'>
        <i class='fa-solid fa-shield-halved' style='font-size: 48px; color: #ef4444; margin-bottom: 20px;'></i>
        <h2 style='color: #0f172a; margin-bottom: 10px;'>Access Denied</h2>
        <p style='color: #64748b;'>You must be an administrator to view this module.</p>
    </div>";
    exit;
}