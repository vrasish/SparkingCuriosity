<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/stripe-lib.php';

ensure_purchase_schema($pdo);
cart_bootstrap();

$flashSuccess = (string) ($_SESSION['cart_flash_success'] ?? '');
$flashError = (string) ($_SESSION['cart_flash_error'] ?? '');
unset($_SESSION['cart_flash_success'], $_SESSION['cart_flash_error']);

if (isset($_GET['cancelled'])) {
    $flashError = $flashError !== '' ? $flashError : 'Checkout was cancelled.';
}

$items = cart_items($pdo);
$totalCents = cart_total_cents($pdo);
$loggedIn = is_logged_in();
$usesDemo = stripe_uses_demo_checkout();
$stripeReady = stripe_is_configured() && !$usesDemo;
$ownedBooks = $loggedIn ? owned_books($pdo) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Your Cart')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <?php render_page_header('Your Cart', 'Every book is free to read for the next 3 months.'); ?>

    <div class="page-section">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= e($flashError) ?></div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <p>Your cart is empty.</p>
                <p class="mt-2"><a href="<?= e(app_url('search.php')) ?>" class="btn btn-primary">Browse Stories</a></p>
            </div>
        <?php else: ?>
            <div class="cart-panel">
                <ul class="cart-list">
                    <?php foreach ($items as $item): ?>
                        <?php $cover = cover_image_src($item['cover_image_url'] ?? null, $item['title']); ?>
                        <li class="cart-item">
                            <img src="<?= e($cover) ?>" alt="" class="cart-item-cover">
                            <div class="cart-item-info">
                                <h3><?= e($item['title']) ?></h3>
                                <p class="story-card-meta">By <?= e($item['author_name']) ?></p>
                                <p class="cart-item-price"><?= e(format_book_price($item)) ?></p>
                            </div>
                            <form method="post" action="<?= e(app_url('cart-action.php')) ?>" class="inline-form">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="book_id" value="<?= (int) $item['book_id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e(app_url('cart-page.php')) ?>">
                                <button type="submit" class="btn btn-outline btn-sm">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="cart-summary">
                    <p class="cart-total"><strong>Total:</strong> <?= e(format_cents($totalCents)) ?></p>

                    <?php if (!$loggedIn): ?>
                        <p class="page-lead">Log in or create an account to check out. Purchases stay in your library.</p>
                        <p class="mt-2">
                            <a href="<?= e(app_url('login.php?redirect=' . rawurlencode(app_url('cart-page.php')))) ?>" class="btn btn-primary btn-lg">Log in to Check Out</a>
                            <a href="<?= e(app_url('register.php')) ?>" class="btn btn-outline btn-lg">Create Account</a>
                        </p>
                    <?php elseif ($usesDemo): ?>
                        <form method="post" action="<?= e(app_url('stripe-checkout.php')) ?>">
                            <button type="submit" class="btn btn-primary btn-lg">Buy Now (Demo)</button>
                        </form>
                        <p class="cart-note">Demo mode — add Stripe keys in <code>stripe.config.php</code> for real payments.</p>
                    <?php elseif ($stripeReady): ?>
                        <form method="post" action="<?= e(app_url('stripe-checkout.php')) ?>">
                            <button type="submit" class="btn btn-primary btn-lg">Pay with Stripe</button>
                        </form>
                        <p class="cart-note">Secure checkout powered by Stripe. You will be redirected to pay by card.</p>
                    <?php else: ?>
                        <div class="alert alert-error">Stripe is not configured. Copy <code>stripe.config.example.php</code> to <code>stripe.config.php</code> and add your test keys.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ownedBooks !== []): ?>
            <div class="cart-owned mt-4">
                <h2 class="section-title">Your Library</h2>
                <p class="page-lead">Stories you have purchased. You can return any story for a full refund.</p>
                <ul class="cart-list cart-owned-grid">
                    <?php foreach ($ownedBooks as $owned): ?>
                        <?php $cover = cover_image_src($owned['cover_image_url'] ?? null, $owned['title']); ?>
                        <li class="cart-item cart-owned-item">
                            <img src="<?= e($cover) ?>" alt="" class="cart-item-cover">
                            <div class="cart-item-info">
                                <h3><a href="<?= e(app_url('book.php?id=' . (int) $owned['book_id'])) ?>"><?= e($owned['title']) ?></a></h3>
                                <p class="story-card-meta">By <?= e($owned['author_name']) ?></p>
                                <p class="cart-item-price"><?= e(format_book_price($owned)) ?></p>
                            </div>
                            <div class="cart-owned-actions">
                                <a href="<?= e(app_url('book.php?id=' . (int) $owned['book_id'])) ?>" class="btn btn-primary btn-sm">Read</a>
                                <?php if (!is_book_free($owned)): ?>
                                <form method="post" action="<?= e(app_url('cart-action.php')) ?>" class="inline-form">
                                    <input type="hidden" name="action" value="return">
                                    <input type="hidden" name="book_id" value="<?= (int) $owned['book_id'] ?>">
                                    <input type="hidden" name="redirect" value="<?= e(app_url('cart-page.php')) ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">Return for Refund</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
