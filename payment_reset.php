<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/payments.php';

$user = require_login();
$uid  = (int)$user['id'];
$sql = "DELETE from payment where user_id = ?";
$statement = db()->prepare("DELETE from payment where user_id = ?");
$statement->execute([$uid]);
redirect('dashboard.php');
?>