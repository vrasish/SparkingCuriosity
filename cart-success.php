<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/stripe-lib.php';

if (!cart_enabled()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

ensure_purchase_schema($pdo);
cart_bootstrap();

$sessionId = (string) ($_GET['session_id'] ?? '');
$flashSuccess = '';
$flashError = '';

if ($sessionId === '') {
    $flashError = 'Missing checkout session.';
} elseif (!is_logged_in()) {
    $flashError = 'Please log in to view your purchase.';
} elseif (stripe_uses_demo_checkout()) {
    $flashError = 'Stripe checkout is not enabled.';
} else {
    $result = stripe_retrieve_checkout_session($sessionId);
    if (!$result['ok']) {
        $flashError = $result['error'];
    } else {
        $session = $result['data'];
        $userId = current_user_id();
        $orderUserId = (int) ($session['metadata']['user_id'] ?? 0);

        if ($userId === null || ($orderUserId > 0 && $userId !== $orderUserId)) {
            $flashError = 'This purchase does not belong to your account.';
        } elseif (($session['payment_status'] ?? '') !== 'paid') {
            $flashError = 'Payment is not complete yet. If you were charged, refresh in a moment.';
        } else {
            process_stripe_checkout_completed($session, $pdo);
            $_SESSION['cart'] = [];
            $flashSuccess = 'Payment successful! Your stories are ready to read.';
        }
    }
}

if ($flashSuccess !== '') {
    $_SESSION['cart_flash_success'] = $flashSuccess;
} elseif ($flashError !== '') {
    $_SESSION['cart_flash_error'] = $flashError;
}

header('Location: ' . app_url('cart-page.php'));
exit;
