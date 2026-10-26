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

$order = $api->order->create([
    'amount' => 9900,
    'currency' => 'INR',
    'receipt' => 'order_receipt_12asa3'
]);

$order_id = $order->id;
$callback_url = 'http://localhost:8000/payment/razorpay-sample-project/success.html';

echo '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';
echo '<button onclick="startPayment()">Pay with Razorpay</button>';

echo '<script>
    function startPayment() {
        var options = {
            key: "' . $api_key . '",
            amount: ' . $order->amount . ',
            currency: "' . $order->currency . '",
            name: "Your Company Name",
            description: "Payment for your order",
            image: "https://cdn.razorpay.com/logos/GhRQcyean79PqE_medium.png",
            order_id: "' . $order_id . '",
            theme: {
                "color": "#738276"
            },
            callback_url: "' . $callback_url . '"
        };
        var rzp = new Razorpay(options);
        rzp.open();
    }
</script>';
?>
