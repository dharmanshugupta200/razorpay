<?php
session_start();

$destination = isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php';
header('Location: ' . $destination);
exit;
