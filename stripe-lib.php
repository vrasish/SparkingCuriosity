<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function stripe_config_paths(): array
{
    return [
        dirname(__DIR__, 2) . '/private/sparking-stripe.config.php',
        __DIR__ . '/stripe.config.php',
    ];
}

function stripe_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'secret_key' => '',
        'webhook_secret' => '',
        'demo_mode' => false,
    ];

    foreach (stripe_config_paths() as $path) {
        if (is_readable($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = array_merge($defaults, $loaded);
                return $config;
            }
        }
    }

    $config = $defaults;
    return $config;
}

function stripe_is_configured(): bool
{
    $key = (string) (stripe_config()['secret_key'] ?? '');
    $placeholders = ['sk_test_PUT_YOUR_SECRET_KEY_HERE', 'PUT_STRIPE_SECRET_KEY_HERE', ''];

    return $key !== '' && !in_array($key, $placeholders, true);
}

function stripe_uses_demo_checkout(): bool
{
    $config = stripe_config();
    if (!empty($config['demo_mode'])) {
        return true;
    }

    return !stripe_is_configured();
}

function app_absolute_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = app_base_path();
    $path = ltrim($path, '/');

    return $scheme . '://' . $host . ($base !== '' ? $base . '/' : '/') . $path;
}

function ensure_purchase_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    ensure_book_pricing_schema($pdo);

    $readyFlag = __DIR__ . '/data/.purchase-schema-ready';
    if (is_file($readyFlag)) {
        $checked = true;
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            total_cents INT NOT NULL DEFAULT 0,
            stripe_checkout_session_id VARCHAR(255) NULL,
            stripe_payment_intent_id VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL,
            INDEX idx_orders_user (user_id),
            INDEX idx_orders_session (stripe_checkout_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            order_item_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            book_id INT NOT NULL,
            price_cents INT NOT NULL,
            stripe_refund_id VARCHAR(255) NULL,
            refunded_at TIMESTAMP NULL,
            INDEX idx_order_items_order (order_id),
            INDEX idx_order_items_book (book_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_library (
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            order_item_id INT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, book_id),
            INDEX idx_user_library_order_item (order_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!is_dir(dirname($readyFlag))) {
        mkdir(dirname($readyFlag), 0775, true);
    }
    file_put_contents($readyFlag, date('c') . "\n");

    $checked = true;
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        if (!function_exists('current_user')) {
            return null;
        }

        $user = current_user();
        if (!$user) {
            return null;
        }

        $id = (int) ($user['user_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}

function is_book_purchased_for_user(int $bookId, int $userId, PDO $pdo): bool
{
    if ($bookId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT 1 FROM user_library WHERE user_id = ? AND book_id = ? LIMIT 1');
        $stmt->execute([$userId, $bookId]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return false;
    }
}

/** @return list<array<string, mixed>> */
function owned_books_for_user(int $userId, PDO $pdo): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT b.book_id, b.title, b.author_name, b.cover_image_url, b.price_cents, b.book_format
            FROM user_library ul
            INNER JOIN books b ON b.book_id = ul.book_id
            WHERE ul.user_id = ?
            ORDER BY ul.granted_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }
}

function create_pending_order(int $userId, PDO $pdo, array $cartItems): array
{
    if ($cartItems === []) {
        return ['ok' => false, 'error' => 'Your cart is empty.', 'order_id' => 0];
    }

    $totalCents = 0;
    foreach ($cartItems as $item) {
        $totalCents += book_price_cents($item);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, status, total_cents)
            VALUES (?, 'pending', ?)
        ");
        $stmt->execute([$userId, $totalCents]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('
            INSERT INTO order_items (order_id, book_id, price_cents)
            VALUES (?, ?, ?)
        ');

        foreach ($cartItems as $item) {
            $bookId = (int) $item['book_id'];
            if (is_book_purchased_for_user($bookId, $userId, $pdo)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'You already own "' . ($item['title'] ?? 'a story') . '". Remove it from your cart.', 'order_id' => 0];
            }
            $itemStmt->execute([$orderId, $bookId, book_price_cents($item)]);
        }

        $pdo->commit();

        return ['ok' => true, 'error' => '', 'order_id' => $orderId];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not start checkout.', 'order_id' => 0];
    }
}

function fulfill_order(int $orderId, PDO $pdo, ?string $sessionId = null, ?string $paymentIntentId = null): bool
{
    if ($orderId <= 0) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT order_id, user_id, status FROM orders WHERE order_id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            return false;
        }

        if (($order['status'] ?? '') === 'paid') {
            $pdo->commit();
            return true;
        }

        $update = $pdo->prepare("
            UPDATE orders
            SET status = 'paid',
                paid_at = COALESCE(paid_at, NOW()),
                stripe_checkout_session_id = COALESCE(?, stripe_checkout_session_id),
                stripe_payment_intent_id = COALESCE(?, stripe_payment_intent_id)
            WHERE order_id = ?
        ");
        $update->execute([$sessionId, $paymentIntentId, $orderId]);

        $userId = (int) $order['user_id'];
        $items = $pdo->prepare('SELECT order_item_id, book_id FROM order_items WHERE order_id = ? AND refunded_at IS NULL');
        $items->execute([$orderId]);
        $rows = $items->fetchAll();

        $grant = $pdo->prepare('
            INSERT IGNORE INTO user_library (user_id, book_id, order_item_id)
            VALUES (?, ?, ?)
        ');

        foreach ($rows as $row) {
            $grant->execute([$userId, (int) $row['book_id'], (int) $row['order_item_id']]);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return false;
    }
}

function complete_demo_checkout(int $userId, PDO $pdo): array
{
    cart_bootstrap();
    $items = cart_items($pdo);

    if ($items === []) {
        return ['ok' => false, 'error' => 'Your cart is empty.'];
    }

    $created = create_pending_order($userId, $pdo, $items);
    if (!$created['ok']) {
        return ['ok' => false, 'error' => $created['error']];
    }

    if (!fulfill_order((int) $created['order_id'], $pdo)) {
        return ['ok' => false, 'error' => 'Could not complete purchase.'];
    }

    $_SESSION['cart'] = [];

    return ['ok' => true, 'error' => ''];
}

/** @return array{ok: bool, error: string, url: string} */
function create_stripe_checkout_session(int $orderId, int $userId, PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.total_cents, oi.order_item_id, oi.book_id, oi.price_cents, b.title
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.order_id
        INNER JOIN books b ON b.book_id = oi.book_id
        WHERE o.order_id = ? AND o.user_id = ? AND o.status = 'pending'
    ");
    $stmt->execute([$orderId, $userId]);
    $rows = $stmt->fetchAll();

    if ($rows === []) {
        return ['ok' => false, 'error' => 'Checkout session could not be created.', 'url' => ''];
    }

    $lineItems = [];
    foreach ($rows as $row) {
        $lineItems[] = [
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => (int) $row['price_cents'],
                'product_data' => [
                    'name' => (string) $row['title'],
                    'metadata' => [
                        'book_id' => (string) $row['book_id'],
                    ],
                ],
            ],
            'quantity' => 1,
        ];
    }

    $successUrl = app_absolute_url('cart-success.php?session_id={CHECKOUT_SESSION_ID}');
    $cancelUrl = app_absolute_url('cart-page.php?cancelled=1');

    $payload = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'client_reference_id' => (string) $orderId,
        'metadata' => [
            'order_id' => (string) $orderId,
            'user_id' => (string) $userId,
        ],
        'line_items' => $lineItems,
    ];

    $user = current_user();
    if ($user && !empty($user['email'])) {
        $payload['customer_email'] = (string) $user['email'];
    }

    $response = stripe_api_request('POST', '/v1/checkout/sessions', $payload);
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'], 'url' => ''];
    }

    $session = $response['data'];
    $sessionId = (string) ($session['id'] ?? '');
    $url = (string) ($session['url'] ?? '');

    if ($sessionId === '' || $url === '') {
        return ['ok' => false, 'error' => 'Stripe returned an invalid checkout session.', 'url' => ''];
    }

    $pdo->prepare('UPDATE orders SET stripe_checkout_session_id = ? WHERE order_id = ?')
        ->execute([$sessionId, $orderId]);

    return ['ok' => true, 'error' => '', 'url' => $url];
}

function stripe_api_request(string $method, string $path, array $params = []): array
{
    $config = stripe_config();
    $secretKey = (string) ($config['secret_key'] ?? '');

    if ($secretKey === '' || str_contains($secretKey, 'PUT_')) {
        return ['ok' => false, 'error' => 'Stripe is not configured.', 'data' => []];
    }

    $url = 'https://api.stripe.com' . $path;
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/x-www-form-urlencoded',
    ];

    $body = stripe_encode_params($params);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'Stripe request failed: ' . $curlError, 'data' => []];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid Stripe response.', 'data' => []];
    }

    if ($httpCode >= 400) {
        $message = (string) ($data['error']['message'] ?? 'Stripe error.');
        return ['ok' => false, 'error' => $message, 'data' => $data];
    }

    return ['ok' => true, 'error' => '', 'data' => $data];
}

function stripe_encode_params(array $params, string $prefix = ''): string
{
    $parts = [];

    foreach ($params as $key => $value) {
        $encodedKey = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

        if (is_array($value)) {
            if (array_is_list($value)) {
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $parts[] = stripe_encode_params($item, $encodedKey . '[' . $index . ']');
                    } else {
                        $parts[] = rawurlencode($encodedKey . '[' . $index . ']') . '=' . rawurlencode((string) $item);
                    }
                }
            } else {
                $parts[] = stripe_encode_params($value, $encodedKey);
            }
        } else {
            $parts[] = rawurlencode($encodedKey) . '=' . rawurlencode((string) $value);
        }
    }

    return implode('&', array_filter($parts, fn($part) => $part !== ''));
}

function stripe_retrieve_checkout_session(string $sessionId): array
{
    return stripe_api_request('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId) . '?expand[]=payment_intent');
}

function stripe_verify_webhook(string $payload, string $sigHeader): ?array
{
    $secret = (string) (stripe_config()['webhook_secret'] ?? '');
    if ($secret === '' || str_contains($secret, 'PUT_')) {
        return null;
    }

    $parts = explode(',', $sigHeader);
    $timestamp = null;
    $signatures = [];

    foreach ($parts as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $timestamp = $kv[1];
        } elseif ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }

    if ($timestamp === null || $signatures === []) {
        return null;
    }

    if (abs(time() - (int) $timestamp) > 300) {
        return null;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            $event = json_decode($payload, true);
            return is_array($event) ? $event : null;
        }
    }

    return null;
}

function process_stripe_checkout_completed(array $session, PDO $pdo): void
{
    $orderId = (int) ($session['metadata']['order_id'] ?? $session['client_reference_id'] ?? 0);
    $sessionId = (string) ($session['id'] ?? '');
    $paymentIntent = $session['payment_intent'] ?? null;
    $paymentIntentId = is_array($paymentIntent)
        ? (string) ($paymentIntent['id'] ?? '')
        : (string) $paymentIntent;

    if ($orderId <= 0) {
        return;
    }

    fulfill_order($orderId, $pdo, $sessionId !== '' ? $sessionId : null, $paymentIntentId !== '' ? $paymentIntentId : null);
}

function return_purchased_book_stripe(int $bookId, int $userId, PDO $pdo): array
{
    if ($bookId <= 0 || $userId <= 0) {
        return ['ok' => false, 'error' => 'Invalid story.'];
    }

    if (!is_book_purchased_for_user($bookId, $userId, $pdo)) {
        return ['ok' => false, 'error' => 'You have not purchased this story.'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT ul.order_item_id, oi.price_cents, oi.refunded_at, oi.order_id,
                   o.stripe_payment_intent_id, b.title, b.price_cents AS book_price_cents, b.status
            FROM user_library ul
            INNER JOIN books b ON b.book_id = ul.book_id
            LEFT JOIN order_items oi ON oi.order_item_id = ul.order_item_id
            LEFT JOIN orders o ON o.order_id = oi.order_id
            WHERE ul.user_id = ? AND ul.book_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $bookId]);
        $row = $stmt->fetch();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not process return.'];
    }

    if (!$row || ($row['status'] ?? '') !== 'approved') {
        return ['ok' => false, 'error' => 'Story not available.'];
    }

    if (book_price_cents($row) === 0) {
        return ['ok' => false, 'error' => 'Free stories cannot be returned.'];
    }

    if (!empty($row['refunded_at'])) {
        return ['ok' => false, 'error' => 'This story was already refunded.'];
    }

    $priceCents = (int) ($row['price_cents'] ?? $row['book_price_cents'] ?? 200);
    $paymentIntentId = (string) ($row['stripe_payment_intent_id'] ?? '');
    $orderItemId = (int) ($row['order_item_id'] ?? 0);

    if ($paymentIntentId !== '' && stripe_is_configured() && !stripe_uses_demo_checkout()) {
        $refund = stripe_api_request('POST', '/v1/refunds', [
            'payment_intent' => $paymentIntentId,
            'amount' => $priceCents,
            'metadata' => [
                'order_item_id' => (string) $orderItemId,
                'book_id' => (string) $bookId,
                'user_id' => (string) $userId,
            ],
        ]);

        if (!$refund['ok']) {
            return ['ok' => false, 'error' => $refund['error']];
        }

        $refundId = (string) ($refund['data']['id'] ?? '');
        if ($orderItemId > 0) {
            $pdo->prepare('
                UPDATE order_items
                SET stripe_refund_id = ?, refunded_at = NOW()
                WHERE order_item_id = ?
            ')->execute([$refundId !== '' ? $refundId : null, $orderItemId]);
        }
    } elseif ($orderItemId > 0) {
        $pdo->prepare('UPDATE order_items SET refunded_at = NOW() WHERE order_item_id = ?')
            ->execute([$orderItemId]);
    }

    $pdo->prepare('DELETE FROM user_library WHERE user_id = ? AND book_id = ?')
        ->execute([$userId, $bookId]);

    remove_book_from_cart($bookId);

    return [
        'ok' => true,
        'error' => '',
        'title' => (string) $row['title'],
        'price_cents' => $priceCents,
    ];
}
