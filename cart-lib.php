<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reviews-lib.php';
require_once __DIR__ . '/pdf-branding-lib.php';

function stories_load_stripe_lib(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    require_once __DIR__ . '/stripe-lib.php';
    $loaded = true;
}

function cart_bootstrap(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return;
    }

    // After session_write_close(), $_SESSION stays in memory — don't reopen (avoids tab lockups).
    if (isset($_SESSION) && is_array($_SESSION)) {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

/** End of the “all books free” launch promotion. */
function all_books_free_promo_until(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-05 23:59:59');
}

function all_books_free_promo_active(): bool
{
    return new DateTimeImmutable('now') <= all_books_free_promo_until();
}

function all_books_free_promo_banner_text(): string
{
    return 'For 3 months, every book on ' . site_brand_name() . ' is free to read.';
}

function render_all_books_free_banner(): void
{
    if (!all_books_free_promo_active()) {
        return;
    }

    echo '<div class="promo-banner" role="status">';
    echo '<div class="promo-banner-glow" aria-hidden="true"></div>';
    echo '<div class="promo-banner-inner">';
    echo '<span class="promo-banner-spark" aria-hidden="true">✦</span>';
    echo '<span class="promo-banner-badge">Limited time</span>';
    echo '<p class="promo-banner-text">' . e(all_books_free_promo_banner_text()) . '</p>';
    echo '<span class="promo-banner-spark" aria-hidden="true">✦</span>';
    echo '</div>';
    echo '</div>';
}

function book_price_cents(array $book): int
{
    if (all_books_free_promo_active()) {
        return 0;
    }

    return max(0, (int) ($book['price_cents'] ?? 0));
}

function is_book_free(array $book): bool
{
    if (all_books_free_promo_active()) {
        return true;
    }

    return book_price_cents($book) === 0;
}

function format_book_price(array $book): string
{
    if (is_book_free($book)) {
        return 'Free';
    }
    return '$' . number_format(book_price_cents($book) / 100, 2);
}

function is_book_purchased(int $bookId): bool
{
    global $pdo;

    $userId = current_user_id();
    if ($userId === null || $bookId <= 0) {
        return false;
    }

    $owned = owned_book_ids_for_user($pdo, $userId);

    return isset($owned[$bookId]);
}

/** @return array<int, true> */
function owned_book_ids_for_user(PDO $pdo, int $userId): array
{
    static $cache = [];
    static $loadedForUser = null;

    if ($userId <= 0) {
        return [];
    }

    if ($loadedForUser === $userId) {
        return $cache;
    }

    stories_load_stripe_lib();
    ensure_purchase_schema($pdo);

    try {
        $stmt = $pdo->prepare('SELECT book_id FROM user_library WHERE user_id = ?');
        $stmt->execute([$userId]);
        $cache = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $bookId) {
            $cache[(int) $bookId] = true;
        }
        $loadedForUser = $userId;
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $cache = [];
        $loadedForUser = $userId;
    }

    return $cache;
}

function can_read_book(array $book, bool $preview = false): bool
{
    if ($preview && is_admin_user()) {
        return true;
    }

    $bookId = (int) ($book['book_id'] ?? 0);
    if ($bookId <= 0) {
        return false;
    }

    if (($book['status'] ?? '') !== 'approved' && !$preview) {
        return false;
    }

    if (is_book_free($book) || is_book_purchased($bookId)) {
        return true;
    }

    $user = current_user();
    if ($user && is_admin_user()) {
        return true;
    }

    return false;
}

function cart_item_count(): int
{
    cart_bootstrap();
    return count($_SESSION['cart']);
}

/** @return list<int> */
function cart_book_ids(): array
{
    cart_bootstrap();
    return array_values(array_map('intval', $_SESSION['cart']));
}

function add_book_to_cart(int $bookId, PDO $pdo): array
{
    cart_bootstrap();

    if ($bookId <= 0) {
        return ['ok' => false, 'error' => 'Invalid story.'];
    }

    if (is_book_purchased($bookId)) {
        return ['ok' => false, 'error' => 'You already own this story.'];
    }

    try {
        $stmt = $pdo->prepare('SELECT book_id, title, price_cents, status FROM books WHERE book_id = ? LIMIT 1');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not add to cart.'];
    }

    if (!$book || ($book['status'] ?? '') !== 'approved') {
        return ['ok' => false, 'error' => 'Story not available.'];
    }

    if (is_book_free($book)) {
        return ['ok' => false, 'error' => 'This story is free — use Read instead.'];
    }

    if (!in_array($bookId, $_SESSION['cart'], true)) {
        stories_open_writable_session();
        $_SESSION['cart'][] = $bookId;
    }

    return ['ok' => true, 'error' => ''];
}

function remove_book_from_cart(int $bookId): void
{
    cart_bootstrap();
    stories_open_writable_session();
    $_SESSION['cart'] = array_values(array_filter(
        $_SESSION['cart'],
        fn($id) => (int) $id !== $bookId
    ));
}

/** @return list<array<string, mixed>> */
function cart_items(PDO $pdo): array
{
    cart_bootstrap();
    $ids = cart_book_ids();
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT book_id, title, author_name, cover_image_url, price_cents, book_format
            FROM books
            WHERE book_id IN ($placeholders)
        ");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['book_id']] = $row;
    }

    $items = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $items[] = $byId[$id];
        }
    }

    return $items;
}

function cart_total_cents(PDO $pdo): int
{
    $total = 0;
    foreach (cart_items($pdo) as $item) {
        $total += book_price_cents($item);
    }
    return $total;
}

function format_cents(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

function complete_cart_purchase(PDO $pdo): array
{
    $userId = current_user_id();
    if ($userId === null) {
        return ['ok' => false, 'error' => 'Please log in to complete your purchase.'];
    }

    stories_load_stripe_lib();
    ensure_purchase_schema($pdo);

    if (stripe_uses_demo_checkout()) {
        return complete_demo_checkout($userId, $pdo);
    }

    return ['ok' => false, 'error' => 'Use the Pay with Stripe button to check out.'];
}

function return_purchased_book(int $bookId, PDO $pdo): array
{
    $userId = current_user_id();
    if ($userId === null) {
        return ['ok' => false, 'error' => 'Please log in to return a story.'];
    }

    stories_load_stripe_lib();
    ensure_purchase_schema($pdo);

    return return_purchased_book_stripe($bookId, $userId, $pdo);
}

/** @return list<array<string, mixed>> */
function owned_books(PDO $pdo): array
{
    $userId = current_user_id();
    if ($userId === null) {
        return [];
    }

    stories_load_stripe_lib();
    ensure_purchase_schema($pdo);

    return owned_books_for_user($userId, $pdo);
}

function pdf_reader_url(int $bookId, bool $preview = false): string
{
    $url = app_url('read-pdf.php?id=' . $bookId);
    if ($preview) {
        $url .= '&preview=1';
    }
    return $url;
}

function pdf_download_url(int $bookId, bool $preview = false): string
{
    return pdf_reader_url($bookId, $preview) . '&download=1';
}

function is_title_free_story(string $title): bool
{
    $t = strtolower(trim($title));

    if (str_contains($t, 'planetary') && str_contains($t, 'adventure')) {
        return true;
    }
    if (str_contains($t, 'mountain') && str_contains($t, 'echo')) {
        return true;
    }
    if (str_contains($t, 'grandpa green')) {
        return true;
    }
    if (str_contains($t, 'blood') && str_contains($t, 'drop')) {
        return true;
    }

    return false;
}

function ensure_book_pricing_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $seedFlag = __DIR__ . '/data/.pricing-seeded';
    if (is_file($seedFlag)) {
        $checked = true;
        return;
    }

    $stmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'books'
          AND COLUMN_NAME = 'price_cents'
    ");
    $columnExists = (bool) $stmt->fetchColumn();
    if (!$columnExists) {
        $pdo->exec('ALTER TABLE books ADD COLUMN price_cents INT NOT NULL DEFAULT 200');
    }

    if (!is_file($seedFlag)) {
        try {
            $books = $pdo->query("SELECT book_id, title FROM books WHERE status = 'approved'")->fetchAll();
            $update = $pdo->prepare('UPDATE books SET price_cents = ? WHERE book_id = ?');
            foreach ($books as $book) {
                $price = is_title_free_story((string) $book['title']) ? 0 : 200;
                $update->execute([$price, (int) $book['book_id']]);
            }
            if (!is_dir(dirname($seedFlag))) {
                mkdir(dirname($seedFlag), 0775, true);
            }
            file_put_contents($seedFlag, date('c') . "\n");
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
        }
    }

    $checked = true;
}

function render_story_card_actions(array $book, ?array $ratingSummary = null): void
{
    $bookId = (int) ($book['book_id'] ?? 0);
    $free = is_book_free($book);
    $owned = !$free && is_book_purchased($bookId);

    echo '<div class="story-card-footer">';
    echo '<span class="story-card-badges">';

    if ($free) {
        echo '<span class="badge-free">Free</span>';
    } elseif ($owned) {
        echo '<span class="badge-owned">Owned</span>';
    } else {
        echo '<span class="badge-price">' . e(format_book_price($book)) . '</span>';
    }

    if (($book['book_format'] ?? '') === 'pdf') {
        echo '<span class="badge-format-pdf">PDF</span>';
    }

    render_story_card_rating($ratingSummary);

    echo '</span>';
    echo '<span class="story-card-buttons">';
    echo '<a href="' . e(app_url('book.php?id=' . $bookId)) . '" class="btn btn-view-more btn-sm">View More</a>';
    echo '</span>';
    echo '</div>';
}
