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

if (!is_logged_in()) {
    $redirect = urlencode('stripe-checkout.php');
    header('Location: login.php?redirect=' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('cart-page.php'));
    exit;
}

$userId = current_user_id();
if ($userId === null) {
    $_SESSION['cart_flash_error'] = 'Please log in to check out.';
    header('Location: login.php?redirect=' . urlencode('cart-page.php'));
    exit;
}

$items = cart_items($pdo);
if ($items === []) {
    $_SESSION['cart_flash_error'] = 'Your cart is empty.';
    header('Location: ' . app_url('cart-page.php'));
    exit;
}

if (stripe_uses_demo_checkout()) {
    $result = complete_demo_checkout($userId, $pdo);
    if ($result['ok']) {
        $_SESSION['cart_flash_success'] = 'Purchase complete! Your stories are ready to read.';
    } else {
        $_SESSION['cart_flash_error'] = $result['error'];
    }
    header('Location: ' . app_url('cart-page.php'));
    exit;
}

$created = create_pending_order($userId, $pdo, $items);
if (!$created['ok']) {
    $_SESSION['cart_flash_error'] = $created['error'];
    header('Location: ' . app_url('cart-page.php'));
    exit;
}

$checkout = create_stripe_checkout_session((int) $created['order_id'], $userId, $pdo);
if (!$checkout['ok']) {
    $_SESSION['cart_flash_error'] = $checkout['error'];
    header('Location: ' . app_url('cart-page.php'));
    exit;
}

header('Location: ' . $checkout['url']);
exit;
