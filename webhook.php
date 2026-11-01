<?php
// Razorpay -> your server. Updates the DB even if the user closed the tab before returning to the site.
// Local testing: expose localhost with ngrok / cloudflared and add https://<tunnel>/<folder>/webhook.php
// in Razorpay Dashboard > Settings > Webhooks (events: payment.captured, payment.failed, order.paid).
require_once __DIR__ . '/includes/payments.php';

global $razorpay_config;
$secret = $razorpay_config['webhook_secret'] ?? '';
$body   = file_get_contents('php://input');
$sig    = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if ($secret === '' || $sig === '') {
    http_response_code(400);
    exit('webhook not configured');
}
try {
    razorpay()->utility->verifyWebhookSignature($body, $sig, $secret);
} catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
    http_response_code(400);
    exit('bad signature');
}

$event   = json_decode($body, true) ?: [];
$type    = $event['event'] ?? '';
$payment = $event['payload']['payment']['entity'] ?? null;
$orderId = $payment['order_id'] ?? ($event['payload']['order']['entity']['id'] ?? '');

$row = $orderId !== '' ? payment_by_order($orderId) : null;
if (!$row) {
    http_response_code(200);        // not ours / unknown: acknowledge so Razorpay stops retrying
    exit('ignored');
}

try {
    // payment_transition() is idempotent for finished payments, so duplicate deliveries are harmless.
    if (in_array($type, ['payment.captured', 'order.paid'], true) && $payment) {
        if ((int)($payment['amount'] ?? 0) === (int)$row['amount']) {
            payment_transition((int)$row['id'], 'success', 'webhook_' . $type, ['payment' => $payment], ['razorpay_payment_id' => $payment['id']]);
        }
    } elseif ($type === 'payment.failed' && $payment) {
        payment_transition((int)$row['id'], 'failed', 'webhook_payment.failed', ['payment' => $payment], [
            'razorpay_payment_id' => $payment['id'],
            'error_code'          => $payment['error_code'] ?? 'PAYMENT_FAILED',
            'error_description'   => $payment['error_description'] ?? 'Payment failed',
        ]);
    }
} catch (Throwable $e) {
    error_log('webhook: ' . $e->getMessage());
    http_response_code(500);        // 5xx => Razorpay will retry later
    exit('error');
}
http_response_code(200);
echo 'ok';
