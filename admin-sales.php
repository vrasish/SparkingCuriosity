<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sales-lib.php';

require_admin_login();

$dbError = null;

try {
    $summary = get_admin_sales_summary($pdo);
    $topBooks = get_admin_sales_by_book($pdo);
    $purchases = get_admin_recent_purchases($pdo);
} catch (Throwable $ex) {
    $dbError = 'Sales data could not be loaded.';
    error_log($ex->getMessage());
    $summary = get_admin_sales_summary($pdo);
    $topBooks = [];
    $purchases = [];
}

$shareLabel = creator_share_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Library Sales')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main sales-page">
    <?php render_page_header('Library Sales', 'Store-wide revenue, top sellers, and purchase history.'); ?>

    <div class="page-section">
    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php else: ?>
        <div class="sales-stats-grid">
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= e(format_money_cents((int) $summary['net_revenue_cents'])) ?></div>
                <div class="sales-stat-label">Total revenue</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= e(format_money_cents((int) $summary['platform_profit_cents'])) ?></div>
                <div class="sales-stat-label">Platform profit</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= e(format_money_cents((int) $summary['creator_payouts_cents'])) ?></div>
                <div class="sales-stat-label">Creator payouts (<?= e($shareLabel) ?>)</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= (int) $summary['units_sold'] ?></div>
                <div class="sales-stat-label">Books sold</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= (int) $summary['unique_buyers'] ?></div>
                <div class="sales-stat-label">Unique buyers</div>
            </div>
            <div class="sales-stat-card">
                <div class="sales-stat-num"><?= e(format_money_cents((int) $summary['refunded_cents'])) ?></div>
                <div class="sales-stat-label">Refunded</div>
            </div>
        </div>

        <section class="sales-section">
            <h2 class="sales-section-title">Top selling books</h2>
            <?php if (empty($topBooks)): ?>
                <div class="empty-state">
                    <p>No paid sales recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="table-panel">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Creator account</th>
                                <th>Sold</th>
                                <th>Buyers</th>
                                <th>Rating</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topBooks as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(app_url('book.php?id=' . (int) $row['book_id'] . '&preview=1')) ?>"><?= e($row['title']) ?></a>
                                    </td>
                                    <td><?= e($row['author_name']) ?></td>
                                    <td><?= e($row['creator_name'] !== '' ? $row['creator_name'] : '—') ?></td>
                                    <td><?= (int) $row['units_sold'] ?></td>
                                    <td><?= (int) $row['unique_buyers'] ?></td>
                                    <td><?php render_sales_rating(is_array($row['rating'] ?? null) ? $row['rating'] : null); ?></td>
                                    <td><?= e(format_money_cents((int) $row['revenue_cents'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="sales-section">
            <h2 class="sales-section-title">Recent purchases</h2>
            <?php if (empty($purchases)): ?>
                <div class="empty-state">
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
                                <th>Author</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <?php $refunded = !empty($purchase['refunded_at']); ?>
                                <tr>
                                    <td><?= e($purchase['buyer_name'] ?? '') ?></td>
                                    <td><?= e($purchase['buyer_email'] ?? '') ?></td>
                                    <td>
                                        <a href="<?= e(app_url('book.php?id=' . (int) $purchase['book_id'] . '&preview=1')) ?>"><?= e($purchase['book_title'] ?? '') ?></a>
                                    </td>
                                    <td><?= e($purchase['author_name'] ?? '') ?></td>
                                    <td><?= e(format_money_cents((int) ($purchase['price_cents'] ?? 0))) ?></td>
                                    <td><?= e($purchase['purchased_at'] ?? '') ?></td>
                                    <td>
                                        <?php if ($refunded): ?>
                                            <span class="status-tag status-rejected">Refunded</span>
                                        <?php else: ?>
                                            <span class="status-tag status-approved">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <p class="sales-footnote">Platform profit assumes a <?= e((string) (100 - (int) round(creator_revenue_share() * 100))) ?>% platform share after refunds.</p>
    <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
