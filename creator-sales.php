<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sales-lib.php';

require_creator_login();

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$dbError = null;

try {
    $summary = get_creator_sales_summary($pdo, $userId);
    $byBook = get_creator_sales_by_book($pdo, $userId);
    $purchases = get_creator_recent_purchases($pdo, $userId);
} catch (Throwable $ex) {
    $dbError = 'Sales data could not be loaded.';
    error_log($ex->getMessage());
    $summary = get_creator_sales_summary($pdo, 0);
    $byBook = [];
    $purchases = [];
}

$shareLabel = creator_share_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creator Sales | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact sales-page">
    <?php render_page_header('Creator Sales', 'Purchases, ratings, and earnings for your stories.'); ?>

    <div class="page-section">
    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php else: ?>
        <div class="sales-stats-grid">
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= (int) $summary['unique_buyers'] ?></div>
                <div class="sales-stat-label">Unique buyers</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= (int) $summary['units_sold'] ?></div>
                <div class="sales-stat-label">Books sold</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= e(format_money_cents((int) $summary['net_revenue_cents'])) ?></div>
                <div class="sales-stat-label">Gross revenue</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= e(format_money_cents((int) $summary['creator_earnings_cents'])) ?></div>
                <div class="sales-stat-label">Your earnings (<?= e($shareLabel) ?>)</div>
            </div>
        </div>

        <section class="sales-section">
            <h2 class="sales-section-title">Sales by book</h2>
            <?php if (empty($byBook)): ?>
                <div class="empty-state empty-state-compact">
                    <p>No sales yet. Once readers buy your stories, they will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-panel">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Sold</th>
                                <th>Buyers</th>
                                <th>Rating</th>
                                <th>Revenue</th>
                                <th>Your earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byBook as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(app_url('book.php?id=' . (int) $row['book_id'] . '&preview=1')) ?>"><?= e($row['title']) ?></a>
                                    </td>
                                    <td><?= (int) $row['units_sold'] ?></td>
                                    <td><?= (int) $row['unique_buyers'] ?></td>
                                    <td><?php render_sales_rating(is_array($row['rating'] ?? null) ? $row['rating'] : null); ?></td>
                                    <td><?= e(format_money_cents((int) $row['revenue_cents'])) ?></td>
                                    <td><?= e(format_money_cents((int) $row['creator_earnings_cents'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="sales-section">
            <h2 class="sales-section-title">Who bought your books</h2>
            <?php if (empty($purchases)): ?>
                <div class="empty-state empty-state-compact">
                    <p>No purchases to show yet.</p>
                </div>
            <?php else: ?>
                <div class="table-panel">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Buyer</th>
                                <th>Email</th>
                                <th>Book</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?= e($purchase['buyer_name'] ?? '') ?></td>
                                    <td><?= e($purchase['buyer_email'] ?? '') ?></td>
                                    <td>
                                        <a href="<?= e(app_url('book.php?id=' . (int) $purchase['book_id'] . '&preview=1')) ?>"><?= e($purchase['book_title'] ?? '') ?></a>
                                    </td>
                                    <td><?= e(format_money_cents((int) ($purchase['price_cents'] ?? 0))) ?></td>
                                    <td><?= e($purchase['purchased_at'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <p class="sales-footnote">Earnings estimate uses a <?= e($shareLabel) ?> creator share on completed sales.</p>
    <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
