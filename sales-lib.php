<?php

declare(strict_types=1);

require_once __DIR__ . '/stripe-lib.php';
require_once __DIR__ . '/reviews-lib.php';

/** Share of net sales paid to creators (remainder is platform revenue). */
function creator_revenue_share(): float
{
    return 0.70;
}

function format_money_cents(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

function sales_bootstrap(PDO $pdo): void
{
    ensure_purchase_schema($pdo);
    ensure_book_reviews_schema($pdo);
}

/** Paid order items that still count toward sales (refunds excluded). */
function sales_countable_order_items_sql(string $orderItemAlias = 'oi'): string
{
    return $orderItemAlias . '.refunded_at IS NULL';
}

/**
 * @return array{
 *   units_sold: int,
 *   unique_buyers: int,
 *   gross_revenue_cents: int,
 *   net_revenue_cents: int,
 *   creator_earnings_cents: int,
 *   platform_revenue_cents: int
 * }
 */
function get_creator_sales_summary(PDO $pdo, int $creatorUserId): array
{
    sales_bootstrap($pdo);

    $empty = [
        'units_sold' => 0,
        'unique_buyers' => 0,
        'gross_revenue_cents' => 0,
        'net_revenue_cents' => 0,
        'creator_earnings_cents' => 0,
        'platform_revenue_cents' => 0,
    ];

    if ($creatorUserId <= 0) {
        return $empty;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT oi.order_item_id) AS units_sold,
                COUNT(DISTINCT o.user_id) AS unique_buyers,
                COALESCE(SUM(oi.price_cents), 0) AS gross_revenue_cents
            FROM order_items oi
            INNER JOIN orders o ON o.order_id = oi.order_id AND o.status = 'paid'
            INNER JOIN books b ON b.book_id = oi.book_id
            WHERE b.created_by = ?
              AND " . sales_countable_order_items_sql('oi') . "
        ");
        $stmt->execute([$creatorUserId]);
        $row = $stmt->fetch();
        if (!$row) {
            return $empty;
        }

        $gross = (int) ($row['gross_revenue_cents'] ?? 0);
        $share = creator_revenue_share();

        return [
            'units_sold' => (int) ($row['units_sold'] ?? 0),
            'unique_buyers' => (int) ($row['unique_buyers'] ?? 0),
            'gross_revenue_cents' => $gross,
            'net_revenue_cents' => $gross,
            'creator_earnings_cents' => (int) round($gross * $share),
            'platform_revenue_cents' => (int) round($gross * (1 - $share)),
        ];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return $empty;
    }
}

/** @return list<array<string, mixed>> */
function get_creator_sales_by_book(PDO $pdo, int $creatorUserId): array
{
    sales_bootstrap($pdo);

    if ($creatorUserId <= 0) {
        return [];
    }

    try {
        $countable = sales_countable_order_items_sql('oi');
        $stmt = $pdo->prepare("
            SELECT
                b.book_id,
                b.title,
                COUNT(DISTINCT oi.order_item_id) AS units_sold,
                COUNT(DISTINCT o.user_id) AS unique_buyers,
                COALESCE(SUM(oi.price_cents), 0) AS revenue_cents
            FROM books b
            INNER JOIN order_items oi ON oi.book_id = b.book_id AND {$countable}
            INNER JOIN orders o ON o.order_id = oi.order_id AND o.status = 'paid'
            WHERE b.created_by = ?
            GROUP BY b.book_id, b.title
            HAVING units_sold > 0
            ORDER BY revenue_cents DESC, b.title ASC
        ");
        $stmt->execute([$creatorUserId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }

    $bookIds = array_map(fn(array $row): int => (int) $row['book_id'], $rows);
    $ratings = get_book_rating_summaries($pdo, $bookIds);
    $share = creator_revenue_share();

    $out = [];
    foreach ($rows as $row) {
        $bookId = (int) $row['book_id'];
        $revenue = (int) ($row['revenue_cents'] ?? 0);
        $out[] = [
            'book_id' => $bookId,
            'title' => (string) $row['title'],
            'units_sold' => (int) ($row['units_sold'] ?? 0),
            'unique_buyers' => (int) ($row['unique_buyers'] ?? 0),
            'revenue_cents' => $revenue,
            'creator_earnings_cents' => (int) round($revenue * $share),
            'rating' => $ratings[$bookId] ?? null,
        ];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function get_creator_recent_purchases(PDO $pdo, int $creatorUserId, int $limit = 50): array
{
    sales_bootstrap($pdo);

    if ($creatorUserId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                oi.order_item_id,
                b.book_id,
                b.title AS book_title,
                u.full_name AS buyer_name,
                u.email AS buyer_email,
                oi.price_cents,
                oi.refunded_at,
                COALESCE(o.paid_at, o.created_at) AS purchased_at
            FROM order_items oi
            INNER JOIN orders o ON o.order_id = oi.order_id AND o.status = 'paid'
            INNER JOIN books b ON b.book_id = oi.book_id
            INNER JOIN users u ON u.user_id = o.user_id
            WHERE b.created_by = ?
              AND " . sales_countable_order_items_sql('oi') . "
            ORDER BY purchased_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $creatorUserId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }
}

/**
 * @return array{
 *   units_sold: int,
 *   unique_buyers: int,
 *   paid_orders: int,
 *   gross_revenue_cents: int,
 *   refunded_cents: int,
 *   net_revenue_cents: int,
 *   creator_payouts_cents: int,
 *   platform_profit_cents: int
 * }
 */
function get_admin_sales_summary(PDO $pdo): array
{
    sales_bootstrap($pdo);

    $empty = [
        'units_sold' => 0,
        'unique_buyers' => 0,
        'paid_orders' => 0,
        'gross_revenue_cents' => 0,
        'refunded_cents' => 0,
        'net_revenue_cents' => 0,
        'creator_payouts_cents' => 0,
        'platform_profit_cents' => 0,
    ];

    try {
        $stmt = $pdo->query("
            SELECT
                COUNT(DISTINCT CASE WHEN oi.refunded_at IS NULL THEN oi.order_item_id END) AS units_sold,
                COUNT(DISTINCT CASE WHEN oi.refunded_at IS NULL THEN o.user_id END) AS unique_buyers,
                COUNT(DISTINCT o.order_id) AS paid_orders,
                COALESCE(SUM(CASE WHEN oi.refunded_at IS NULL THEN oi.price_cents ELSE 0 END), 0) AS gross_revenue_cents,
                COALESCE(SUM(CASE WHEN oi.refunded_at IS NOT NULL THEN oi.price_cents ELSE 0 END), 0) AS refunded_cents
            FROM order_items oi
            INNER JOIN orders o ON o.order_id = oi.order_id AND o.status = 'paid'
        ");
        $row = $stmt->fetch();
        if (!$row) {
            return $empty;
        }

        $gross = (int) ($row['gross_revenue_cents'] ?? 0);
        $refunded = (int) ($row['refunded_cents'] ?? 0);
        $net = max(0, $gross);
        $share = creator_revenue_share();

        return [
            'units_sold' => (int) ($row['units_sold'] ?? 0),
            'unique_buyers' => (int) ($row['unique_buyers'] ?? 0),
            'paid_orders' => (int) ($row['paid_orders'] ?? 0),
            'gross_revenue_cents' => $gross,
            'refunded_cents' => $refunded,
            'net_revenue_cents' => $net,
            'creator_payouts_cents' => (int) round($net * $share),
            'platform_profit_cents' => (int) round($net * (1 - $share)),
        ];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return $empty;
    }
}

/** @return list<array<string, mixed>> */
function get_admin_sales_by_book(PDO $pdo, int $limit = 100): array
{
    sales_bootstrap($pdo);

    try {
        $stmt = $pdo->prepare("
            SELECT
                b.book_id,
                b.title,
                b.author_name,
                u.full_name AS creator_name,
                COUNT(DISTINCT CASE WHEN oi.refunded_at IS NULL THEN oi.order_item_id END) AS units_sold,
                COUNT(DISTINCT CASE WHEN oi.refunded_at IS NULL THEN o.user_id END) AS unique_buyers,
                COALESCE(SUM(CASE WHEN oi.refunded_at IS NULL THEN oi.price_cents ELSE 0 END), 0) AS revenue_cents,
                COALESCE(SUM(CASE WHEN oi.refunded_at IS NOT NULL THEN oi.price_cents ELSE 0 END), 0) AS refunded_cents
            FROM books b
            LEFT JOIN users u ON u.user_id = b.created_by
            LEFT JOIN order_items oi ON oi.book_id = b.book_id
            LEFT JOIN orders o ON o.order_id = oi.order_id AND o.status = 'paid'
            GROUP BY b.book_id, b.title, b.author_name, u.full_name
            HAVING units_sold > 0 OR revenue_cents > 0 OR refunded_cents > 0
            ORDER BY units_sold DESC, revenue_cents DESC, b.title ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }

    $bookIds = array_map(fn(array $row): int => (int) $row['book_id'], $rows);
    $ratings = get_book_rating_summaries($pdo, $bookIds);

    $out = [];
    foreach ($rows as $row) {
        $bookId = (int) $row['book_id'];
        $out[] = [
            'book_id' => $bookId,
            'title' => (string) $row['title'],
            'author_name' => (string) $row['author_name'],
            'creator_name' => (string) ($row['creator_name'] ?? ''),
            'units_sold' => (int) ($row['units_sold'] ?? 0),
            'unique_buyers' => (int) ($row['unique_buyers'] ?? 0),
            'revenue_cents' => (int) ($row['revenue_cents'] ?? 0),
            'refunded_cents' => (int) ($row['refunded_cents'] ?? 0),
            'rating' => $ratings[$bookId] ?? null,
        ];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function get_admin_recent_purchases(PDO $pdo, int $limit = 75): array
{
    sales_bootstrap($pdo);

    try {
        $stmt = $pdo->prepare("
            SELECT
                oi.order_item_id,
                b.book_id,
                b.title AS book_title,
                b.author_name,
                u.full_name AS buyer_name,
                u.email AS buyer_email,
                oi.price_cents,
                oi.refunded_at,
                COALESCE(o.paid_at, o.created_at) AS purchased_at
            FROM order_items oi
            INNER JOIN orders o ON o.order_id = oi.order_id AND o.status = 'paid'
            INNER JOIN books b ON b.book_id = oi.book_id
            INNER JOIN users u ON u.user_id = o.user_id
            ORDER BY purchased_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }
}

function creator_share_label(): string
{
    return (int) round(creator_revenue_share() * 100) . '%';
}

function render_sales_rating(?array $rating): void
{
    if ($rating === null || (int) ($rating['count'] ?? 0) <= 0) {
        echo '<span class="sales-rating-empty">No ratings yet</span>';
        return;
    }

    echo '<span class="sales-rating">';
    echo e(number_format((float) $rating['average'], 1)) . ' ★';
    echo ' <span class="sales-rating-count">(' . (int) $rating['count'] . ')</span>';
    echo '</span>';
}
