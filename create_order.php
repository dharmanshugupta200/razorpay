<?php
// AJAX: called when the user clicks "Pay". Creates the order on the server.
require_once __DIR__ . '/includes/payments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}
$user = current_user();
if (!$user) {
    json_out(['error' => 'Please log in again.', 'redirect' => 'login.php'], 401);
}
if (!csrf_valid($_POST['csrf'] ?? null)) {
    json_out(['error' => 'Session expired. Reload the page.'], 403);
}

try {
    global $razorpay_config, $app_config;
    $order = start_payment_attempt($user);
    json_out([
        'key'         => $razorpay_config['api_key'],
        'order_id'    => $order['order_id'],
        'amount'      => $order['amount'],
        'currency'    => $order['currency'],
        'name'        => 'Payment Practice',
        'description' => $app_config['product_name'],
        'prefill'     => ['name' => $user['name']],
    ]);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'already_paid') {
        json_out(['error' => 'You have already made the payment.', 'reload' => true], 409);
    }
    if ($e->getMessage() === 'locked') {
        json_out(['error' => 'Too many failed attempts. Please wait before trying again.', 'reload' => true], 429);
    }
    error_log('create_order: ' . $e->getMessage());
    json_out(['error' => 'Could not start the payment. Check the Razorpay keys in config.php.'], 500);
} catch (Throwable $e) {
    error_log('create_order: ' . $e->getMessage());
    json_out(['error' => 'Could not start the payment. Please try again.'], 500);
}
