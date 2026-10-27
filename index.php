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

if (!empty($_SESSION['payment_succeeded'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment already completed</title>
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
            h1 {
                color: #27833f;
            }
        </style>
    </head>
    <body>
        <main class="container">
            <h1>Payment already completed</h1>
            <p>Your payment was successful. No further payment is needed.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$api = new Api($api_key, $api_secret);

$order = $api->order->create([
    'amount' => 9900,
    'currency' => 'INR',
    'receipt' => 'order_receipt_12asa3'
]);

$_SESSION['razorpay_order_id'] = $order->id;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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
        button {
            padding: 12px 22px;
            color: #fff;
            background: #3395ff;
            border: 0;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:disabled {
            opacity: 0.65;
            cursor: wait;
        }
        #payment-status {
            margin: 0 0 20px;
        }
        [hidden] {
            display: none !important;
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Complete your payment</h1>
        <p>Amount: INR <?php echo number_format($order->amount / 100, 2); ?></p>
        <p id="payment-status" role="status" aria-live="polite" hidden></p>
        <button id="pay-button" type="button">Pay with Razorpay</button>
        <button id="retry-button" type="button" hidden>Retry payment</button>
    </main>
    <script>
        const paymentOptions = <?php echo json_encode([
            'key' => $api_key,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'name' => 'Your Company Name',
            'description' => 'Payment for your order',
            'image' => 'https://cdn.razorpay.com/logos/GhRQcyean79PqE_medium.png',
            'order_id' => $order->id,
            'theme' => ['color' => '#738276']
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        paymentOptions.handler = function (response) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'verify.php';

            ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature'].forEach(function (field) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = field;
                input.value = response[field];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        };

        const payButton = document.getElementById('pay-button');
        const retryButton = document.getElementById('retry-button');
        const paymentStatus = document.getElementById('payment-status');
        let paymentAttemptOpen = false;

        function showFailure(message) {
            paymentAttemptOpen = false;
            paymentStatus.textContent = message;
            paymentStatus.hidden = false;
            payButton.hidden = true;
            retryButton.hidden = false;
        }

        function startPayment() {
            paymentAttemptOpen = true;
            paymentStatus.hidden = true;
            payButton.hidden = true;
            retryButton.hidden = true;

            const checkout = new Razorpay(paymentOptions);
            checkout.on('payment.failed', function () {
                showFailure('Payment failed. You can try again.');
            });
            checkout.on('modal.ondismiss', function () {
                if (paymentAttemptOpen) {
                    showFailure('Payment was not completed. You can try again.');
                }
            });
            checkout.open();
        }

        payButton.addEventListener('click', startPayment);
        retryButton.addEventListener('click', startPayment);
    </script>
</body>
</html>
