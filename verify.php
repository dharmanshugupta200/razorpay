<?php
// Razorpay checkout posts here after a successful payment. We never trust it until the signature checks out.
require_once __DIR__ . '/includes/payments.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) {
    flash('Invalid request.', 'error');
    redirect('dashboard.php');
}

$paymentId = (string)($_POST['razorpay_payment_id'] ?? '');
$orderId   = (string)($_POST['razorpay_order_id'] ?? '');
$signature = (string)($_POST['razorpay_signature'] ?? '');

$row = $orderId !== '' ? payment_by_order($orderId) : null;
if (!$row || (int)$row['user_id'] !== (int)$user['id']) {
    flash('We could not match that payment to your account.', 'error');
    redirect('dashboard.php');
}

// 1) Signature check: proves Razorpay (not an attacker) produced this result.
try {
    razorpay()->utility->verifyPaymentSignature([
        'razorpay_order_id'   => $orderId,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature'  => $signature,
    ]);
} catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
    payment_transition((int)$row['id'], 'failed', 'signature_mismatch', ['posted' => $_POST], [
        'razorpay_payment_id' => $paymentId,
        'error_code'          => 'SIGNATURE_MISMATCH',
        'error_description'   => 'Payment signature could not be verified',
    ]);
    flash('Payment could not be verified, so it was not accepted. You can try again.', 'error');
    redirect('dashboard.php');
}

// 2) Ask Razorpay for the real payment record and compare it with what we expected.
$gateway = fetch_gateway_payment($paymentId);
if ($gateway) {
    // Test-mode accounts sometimes leave payments "authorized"; capture so the money is actually taken.
    if (($gateway['status'] ?? '') === 'authorized') {
        try {
            razorpay()->payment->fetch($paymentId)->capture([
                'amount'   => (int)$row['amount'],
                'currency' => $row['currency'],
            ]);
            $gateway = fetch_gateway_payment($paymentId) ?? $gateway;
        } catch (Throwable $e) {
            $gateway['capture_error'] = $e->getMessage();
        }
    }

    $mismatch = ($gateway['order_id'] ?? '') !== $orderId
             || (int)($gateway['amount'] ?? 0) !== (int)$row['amount']
             || !in_array($gateway['status'] ?? '', ['captured', 'authorized'], true);

    if ($mismatch) {
        payment_transition((int)$row['id'], 'failed', 'gateway_mismatch', ['gateway' => $gateway], [
            'razorpay_payment_id' => $paymentId,
            'error_code'          => 'GATEWAY_MISMATCH',
            'error_description'   => 'Amount, order or status did not match what we expected',
        ]);
        flash('The payment details did not match our records, so it was not accepted.', 'error');
        redirect('dashboard.php');
    }
}

// 3) All good: record success (or "duplicate" if the user somehow already paid).
$result = payment_transition((int)$row['id'], 'success', 'payment_verified', [
    'posted'  => ['razorpay_payment_id' => $paymentId, 'razorpay_order_id' => $orderId, 'razorpay_signature' => $signature],
    'gateway' => $gateway,
], ['razorpay_payment_id' => $paymentId]);

if ($result['status'] === 'duplicate') {
    flash('You had already paid, so this second payment was flagged for a refund.', 'warn');
} else {
    flash('Payment successful. Thank you!', 'ok');
}
redirect('dashboard.php');
