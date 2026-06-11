<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(
    !isset($_SESSION['user_id'])
){
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (basename($path) === 'pages') {
        header("Location: login.php");
    } else {
        header("Location: pages/login.php");
    }
    exit;
}