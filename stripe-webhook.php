<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stripe-lib.php';

ensure_purchase_schema($pdo);

$payload = file_get_contents('php://input');
$sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if ($payload === false || $payload === '') {
    http_response_code(400);
    exit('Empty payload');
}

$event = stripe_verify_webhook($payload, $sigHeader);
if ($event === null) {
    http_response_code(400);
    exit('Invalid signature');
}

$type = (string) ($event['type'] ?? '');

try {
    if ($type === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? null;
        if (is_array($session) && ($session['payment_status'] ?? '') === 'paid') {
            process_stripe_checkout_completed($session, $pdo);
        }
    }
} catch (Throwable $ex) {
    error_log('Stripe webhook error: ' . $ex->getMessage());
    http_response_code(500);
    exit('Webhook handler failed');
}

http_response_code(200);
echo json_encode(['received' => true]);
