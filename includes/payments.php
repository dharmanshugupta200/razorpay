<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/razorpay-php/Razorpay.php';

function razorpay(): \Razorpay\Api\Api
{
    static $api;
    global $razorpay_config;

    if (!$api) {
        $key = $razorpay_config['api_key'] ?? '';
        $secret = $razorpay_config['api_secret'] ?? '';
        if ($key === '' || $secret === '') {
            throw new RuntimeException('Razorpay API credentials are not configured.');
        }
        $api = new \Razorpay\Api\Api($key, $secret);
    }

    return $api;
}

function fetch_gateway_payment(string $paymentId): ?array
{
    try {
        return razorpay()->payment->fetch($paymentId)->toArray();
    } catch (Throwable $e) {
        error_log('fetch_gateway_payment: ' . $e->getMessage());
        return null;
    }
}

function payment_by_order(string $orderId): ?array
{
    $statement = db()->prepare('SELECT * FROM payment WHERE razorpay_order_id = ? LIMIT 1');
    $statement->execute([$orderId]);
    $row = $statement->fetch();

    return $row ?: null;
}

function payment_transition(int $paymentId, string $status, string $event, array $data = [], array $columns = []): array
{
    $allowed = ['success', 'failed', 'cancelled', 'expired'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported payment status.');
    }

    $connection = db();
    $connection->beginTransaction();
    try {
        $lookup = $connection->prepare('SELECT * FROM payment WHERE id = ? FOR UPDATE');
        $lookup->execute([$paymentId]);
        $row = $lookup->fetch();
        if (!$row) {
            throw new RuntimeException('Payment attempt not found.');
        }

        $userLock = $connection->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $userLock->execute([(int)$row['user_id']]);
        if (!$userLock->fetch()) {
            throw new RuntimeException('Payment owner not found.');
        }

        if (in_array($row['status'], ['success', 'duplicate'], true)) {
            $connection->commit();
            return ['status' => $row['status']];
        }

        if ($status === 'success') {
            $paid = $connection->prepare("SELECT id FROM payment WHERE user_id = ? AND status = 'success' AND id <> ? LIMIT 1");
            $paid->execute([(int)$row['user_id'], $paymentId]);
            if ($paid->fetch()) {
                $status = 'duplicate';
            }
        }

        $history = json_decode((string)$row['response'], true);
        if (!is_array($history)) {
            $history = ['previous_response' => (string)$row['response']];
        }
        if (!isset($history['events']) || !is_array($history['events'])) {
            $history['events'] = [];
        }
        $history['events'][] = [
            'event' => $event,
            'time' => gmdate('c'),
            'data' => $data,
        ];
        $response = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($response === false) {
            throw new RuntimeException('Could not encode payment response data.');
        }

        $updates = [
            'status' => $status,
            'response' => $response,
        ];
        foreach (['razorpay_payment_id', 'error_code', 'error_description'] as $column) {
            if (array_key_exists($column, $columns)) {
                $updates[$column] = $columns[$column];
            }
        }
        $set = implode(', ', array_map(static function ($column) {
            return $column . ' = :' . $column;
        }, array_keys($updates)));
        $updates['id'] = $paymentId;
        $statement = $connection->prepare("UPDATE payment SET $set WHERE id = :id");
        $statement->execute($updates);
        $connection->commit();

        return ['status' => $status];
    } catch (Throwable $e) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $e;
    }
}

function expire_stale_orders(int $userId): void
{
    global $app_config;
    $minutes = max(1, (int)$app_config['order_expiry_minutes']);
    $statement = db()->prepare(
        "SELECT id FROM payment WHERE user_id = ? AND status = 'created' AND timecreated < DATE_SUB(NOW(), INTERVAL $minutes MINUTE)"
    );
    $statement->execute([$userId]);

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $paymentId) {
        payment_transition((int)$paymentId, 'expired', 'order_expired');
    }
}

function paid_row(int $userId): ?array
{
    $statement = db()->prepare("SELECT * FROM payment WHERE user_id = ? AND status IN ('success', 'duplicate') ORDER BY id ASC LIMIT 1");
    $statement->execute([$userId]);
    $row = $statement->fetch();

    return $row ?: null;
}

function lockout_info(int $userId): array
{
    global $app_config;
    $minutes = max(1, (int)$app_config['lockout_minutes']);
    $limit = max(1, (int)$app_config['max_failed_attempts']);
    $statement = db()->prepare(
        "SELECT COUNT(*) AS failures, MIN(timecreated) AS oldest FROM payment WHERE user_id = ? AND status = 'failed' AND timecreated >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)"
    );
    $statement->execute([$userId]);
    $row = $statement->fetch();
    $failures = (int)$row['failures'];
    $remaining = 0;

    if ($failures >= $limit && $row['oldest']) {
        $remaining = max(0, $minutes * 60 - (time() - strtotime($row['oldest'])));
    }

    return [
        'locked' => $remaining > 0,
        'failures' => $failures,
        'remaining' => $remaining,
    ];
}

function user_payments(int $userId): array
{
    $statement = db()->prepare('SELECT * FROM payment WHERE user_id = ? ORDER BY id DESC');
    $statement->execute([$userId]);

    return $statement->fetchAll();
}

function total_failed(int $userId): int
{
    $statement = db()->prepare("SELECT COUNT(*) FROM payment WHERE user_id = ? AND status = 'failed'");
    $statement->execute([$userId]);

    return (int)$statement->fetchColumn();
}

function start_payment_attempt(array $user): array
{
    global $app_config;

    if (paid_row((int)$user['id'])) {
        throw new RuntimeException('already_paid');
    }
    if (lockout_info((int)$user['id'])['locked']) {
        throw new RuntimeException('locked');
    }

    $amount = (int)$app_config['amount_paise'];
    if ($amount < 1) {
        throw new RuntimeException('Payment amount is not configured.');
    }
    $order = razorpay()->order->create([
        'amount' => $amount,
        'currency' => $app_config['currency'],
        'receipt' => 'u' . (int)$user['id'] . '_' . bin2hex(random_bytes(8)),
    ])->toArray();

    if (empty($order['id'])) {
        throw new RuntimeException('Razorpay did not return an order ID.');
    }

    $response = json_encode(['order' => $order], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($response === false) {
        throw new RuntimeException('Could not encode the Razorpay order response.');
    }

    $statement = db()->prepare(
        "INSERT INTO payment (user_id, razorpay_order_id, amount, currency, response, status) VALUES (?, ?, ?, ?, ?, 'created')"
    );
    $statement->execute([
        (int)$user['id'],
        $order['id'],
        $amount,
        $app_config['currency'],
        $response,
    ]);

    return [
        'order_id' => $order['id'],
        'amount' => $amount,
        'currency' => $app_config['currency'],
    ];
}

function status_label(string $status): string
{
    $labels = [
        'created' => 'In progress',
        'success' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'duplicate' => 'Duplicate',
    ];

    return $labels[$status] ?? ucfirst($status);
}
