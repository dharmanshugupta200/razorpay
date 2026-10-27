<?php
session_start();

require_once 'config.php';
require 'razorpay-php/Razorpay.php';

use Razorpay\Api\Api;

$api_key = $razorpay_config['api_key'];
$api_secret = $razorpay_config['api_secret'];

if (empty($api_key) || empty($api_secret) || strpos($api_key, 'rzp_') !== 0) {
    die('Razorpay API key/secret is missing or invalid. Update the values in config.php with your live/test credentials from the Razorpay Dashboard.');
}

$api = new Api($api_key, $api_secret);

$payment_id = $_POST['razorpay_payment_id'] ?? '';
$order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';
$payment_succeeded = false;
$session_order_id = $_SESSION['razorpay_order_id'] ?? '';

if (
    $order_id !== ''
    && $session_order_id !== ''
    && hash_equals($session_order_id, $order_id)
) {
    if (!empty($_SESSION['payment_succeeded'])) {
        $payment_succeeded = true;
    } elseif ($payment_id !== '' && $razorpay_signature !== '') {
        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $order_id,
                'razorpay_payment_id' => $payment_id,
                'razorpay_signature' => $razorpay_signature
            ]);
            $payment_succeeded = true;
            $_SESSION['payment_succeeded'] = true;
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            $payment_succeeded = false;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $payment_succeeded ? 'Payment successful' : 'Payment failed'; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
        }
        .container {
            max-width: 420px;
            width: calc(100% - 48px);
            padding: 32px;
            text-align: center;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        h1.success {
            color: #27833f;
        }
        h1.failure {
            color: #c62828;
        }
        a {
            display: inline-block;
            padding: 12px 22px;
            color: #fff;
            background: #3395ff;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="container">
        <?php if ($payment_succeeded): ?>
            <h1 class="success">Payment successful</h1>
            <p>Thank you. Your payment was verified successfully.</p>
        <?php else: ?>
            <h1 class="failure">Payment failed</h1>
            <p>Your payment could not be verified. You can try again.</p>
            <a href="index.php">Retry payment</a>
        <?php endif; ?>
    </main>
</body>
</html>
