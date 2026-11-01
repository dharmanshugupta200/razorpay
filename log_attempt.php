<?php
// AJAX: the browser tells us checkout failed or was closed. We store it (and double-check with Razorpay).
require_once __DIR__ . '/includes/payments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}
$user = current_user();
if (!$user) {
    json_out(['error' => 'Please log in again.'], 401);
}
if (!csrf_valid($_POST['csrf'] ?? null)) {
    json_out(['error' => 'Session expired.'], 403);
}

$orderId = (string)($_POST['order_id'] ?? '');
$outcome = (string)($_POST['outcome'] ?? '');
$row = $orderId !== '' ? payment_by_order($orderId) : null;

// A user can only write to their own payment rows.
if (!$row || (int)$row['user_id'] !== (int)$user['id'] || !in_array($outcome, ['failed', 'cancelled'], true)) {
    json_out(['error' => 'Unknown payment.'], 404);
}

$clientError = json_decode((string)($_POST['error'] ?? ''), true);
$clientError = is_array($clientError) ? $clientError : [];
$paymentId   = $clientError['metadata']['payment_id'] ?? null;

$data = ['source' => 'browser', 'client' => $clientError];
$cols = [];

if ($outcome === 'failed') {
    // Do not trust only the browser: if Razorpay says the payment actually went through, record that instead.
    $gateway = is_string($paymentId) && $paymentId !== '' ? fetch_gateway_payment($paymentId) : null;
    if ($gateway) {
        $data['gateway'] = $gateway;
        if (in_array($gateway['status'] ?? '', ['captured', 'authorized'], true)
            && ($gateway['order_id'] ?? '') === $orderId) {
            // Rare, but real: the page said "failed" while the bank said "paid".
            $r = payment_transition((int)$row['id'], 'success', 'gateway_says_paid', $data, ['razorpay_payment_id' => $paymentId]);
            json_out(['status' => $r['status']]);
        }
    }
    $cols = [
        'razorpay_payment_id' => $paymentId,
        'error_code'          => $gateway['error_code']        ?? ($clientError['code']        ?? 'PAYMENT_FAILED'),
        'error_description'   => $gateway['error_description'] ?? ($clientError['description'] ?? 'Payment failed'),
    ];
}

$r = payment_transition((int)$row['id'], $outcome, 'checkout_' . $outcome, $data, $cols);
json_out(['status' => $r['status']]);
