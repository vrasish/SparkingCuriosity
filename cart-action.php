<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart-lib.php';

if (!cart_enabled()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

ensure_book_pricing_schema($pdo);
cart_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('cart-page.php'));
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$bookId = (int) ($_POST['book_id'] ?? 0);
$redirect = 'search.php';

if ($action === 'add') {
    $result = add_book_to_cart($bookId, $pdo);
    if (!$result['ok']) {
        $_SESSION['cart_flash_error'] = $result['error'];
    } else {
        $_SESSION['cart_flash_success'] = 'Added to cart.';
    }
    $redirect = safe_redirect_path($_POST['redirect'] ?? null, 'search.php');
}

if ($action === 'remove') {
    remove_book_from_cart($bookId);
    $_SESSION['cart_flash_success'] = 'Removed from cart.';
    $redirect = safe_redirect_path($_POST['redirect'] ?? null, 'cart-page.php');
}

if ($action === 'return') {
    $result = return_purchased_book($bookId, $pdo);
    if (!$result['ok']) {
        $_SESSION['cart_flash_error'] = $result['error'];
    } else {
        $refund = format_book_price(['price_cents' => (int) ($result['price_cents'] ?? 200)]);
        $_SESSION['cart_flash_success'] = 'Returned "' . ($result['title'] ?? 'story') . '" — refund of ' . $refund . ' applied.';
    }
    $redirect = safe_redirect_path($_POST['redirect'] ?? null, 'cart-page.php');
}

header('Location: ' . $redirect);
