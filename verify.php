<?php
require_once 'config.php';
require 'razorpay-php/Razorpay.php';

use Razorpay\Api\Api;

$api_key = $razorpay_config['api_key'];
$api_secret = $razorpay_config['api_secret'];

if (empty($api_key) || empty($api_secret) || strpos($api_key, 'rzp_') !== 0) {
    die('Razorpay API key/secret is missing or invalid. Update the values in config.php with your live/test credentials from the Razorpay Dashboard.');
}

$api = new Api($api_key, $api_secret);

$success = true;
$error = null;

$payment_id = $_POST['razorpay_payment_id'];
$razorpay_signature = $_POST['razorpay_signature'];

try {
    $attributes = array(
        'razorpay_order_id' => $_POST['razorpay_order_id'],
        'razorpay_payment_id' => $payment_id,
        'razorpay_signature' => $razorpay_signature
    );
    $api->utility->verifyPaymentSignature($attributes);
} catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
    $success = false;
    $error = 'Razorpay Signature Verification Failed';
}

if ($success) {
    $payment = $api->payment->fetch($payment_id);
    $amount_paid = $payment->amount / 100;

    echo "Payment Successful! Amount: $amount_paid INR";
} else {
    echo "Payment Failed! Error: $error";
}
?>
