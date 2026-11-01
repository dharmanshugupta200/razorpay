<?php
// Copy this file to config.php and add your local credentials and database settings.
$razorpay_config = [
    'api_key'        => 'your_razorpay_test_key_id',
    'api_secret'     => 'your_razorpay_test_key_secret',
    'webhook_secret' => '',
];

$db_config = [
    'host'    => '127.0.0.1',
    'name'    => 'demo_pagination',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];

$app_config = [
    'product_name'        => 'Premium Plan',
    'amount_paise'        => 9900,
    'currency'            => 'INR',
    'max_failed_attempts' => 3,
    'lockout_minutes'     => 15,
    'order_expiry_minutes'=> 30,
];
