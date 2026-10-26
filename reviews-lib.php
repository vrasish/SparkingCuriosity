<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_book_reviews_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS book_reviews (
            review_id INT AUTO_INCREMENT PRIMARY KEY,
            book_id INT NOT NULL,
            user_id INT NULL,
            visitor_token VARCHAR(64) NULL,
            rating TINYINT NOT NULL,
            review_text TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_book_reviews_user_book (book_id, user_id),
            UNIQUE KEY uq_book_reviews_visitor_book (book_id, visitor_token),
            INDEX idx_book_reviews_book (book_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Older installs may lack visitor_token / nullable user_id.
    $colStmt = $pdo->query("
        SELECT COLUMN_NAME, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'book_reviews'
          AND COLUMN_NAME IN ('user_id', 'visitor_token')
    ");
    $columns = [];
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string) $row['COLUMN_NAME']] = (string) $row['IS_NULLABLE'];
    }

    if (!isset($columns['visitor_token'])) {
        $pdo->exec('ALTER TABLE book_reviews ADD COLUMN visitor_token VARCHAR(64) NULL AFTER user_id');
    }
    if (isset($columns['user_id']) && strtoupper($columns['user_id']) === 'NO') {
        $pdo->exec('ALTER TABLE book_reviews MODIFY user_id INT NULL');
    }

    $idxStmt = $pdo->query("
        SELECT INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'book_reviews'
          AND INDEX_NAME = 'uq_book_reviews_visitor_book'
        LIMIT 1
    ");
    if (!$idxStmt->fetchColumn()) {
        $pdo->exec('ALTER TABLE book_reviews ADD UNIQUE KEY uq_book_reviews_visitor_book (book_id, visitor_token)');
    }

    $checked = true;
}

/** Cookie-backed token so guests can rate once per story without logging in. */
function review_visitor_token(): string
{
    $existing = trim((string) ($_COOKIE['scifables_rater'] ?? ''));
    if ($existing !== '' && preg_match('/^[a-f0-9]{32,64}$/', $existing)) {
        return $existing;
    }

    try {
        $token = bin2hex(random_bytes(16));
    } catch (Throwable $ex) {
        $token = hash('sha256', uniqid('rater', true));
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    setcookie('scifables_rater', $token, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['scifables_rater'] = $token;

    return $token;
}

/** @return array{average: float, count: int}|null */
function normalize_rating_summary(?array $row): ?array
{
    if (!$row || (int) ($row['review_count'] ?? 0) <= 0) {
        return null;
    }

    return [
        'average' => round((float) ($row['avg_rating'] ?? 0), 1),
        'count' => (int) $row['review_count'],
    ];
}

/** @param list<int> $bookIds @return array<int, array{average: float, count: int}> */
function get_book_rating_summaries(PDO $pdo, array $bookIds): array
{
    $bookIds = array_values(array_unique(array_filter(array_map('intval', $bookIds), fn(int $id): bool => $id > 0)));
    if ($bookIds === []) {
        return [];
    }

    ensure_book_reviews_schema($pdo);

    $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
    $stmt = $pdo->prepare("
        SELECT book_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM book_reviews
        WHERE book_id IN ($placeholders)
        GROUP BY book_id
    ");
    $stmt->execute($bookIds);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $summary = normalize_rating_summary($row);
        if ($summary !== null) {
            $out[(int) $row['book_id']] = $summary;
        }
    }

    return $out;
}

/** @return array{average: float, count: int}|null */
function get_book_rating_summary(PDO $pdo, int $bookId): ?array
{
    if ($bookId <= 0) {
        return null;
    }

    $all = get_book_rating_summaries($pdo, [$bookId]);

    return $all[$bookId] ?? null;
}

/** @return list<array{review_id: int, rating: int, review_text: string, full_name: string, created_at: string}> */
function get_book_reviews(PDO $pdo, int $bookId, int $limit = 50): array
{
    if ($bookId <= 0) {
        return [];
    }

    ensure_book_reviews_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT r.review_id, r.rating, r.review_text, r.created_at, u.full_name
        FROM book_reviews r
        LEFT JOIN users u ON u.user_id = r.user_id
        WHERE r.book_id = ?
        ORDER BY r.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $bookId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    $reviews = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = trim((string) ($row['full_name'] ?? ''));
        $reviews[] = [
            'review_id' => (int) $row['review_id'],
            'rating' => (int) $row['rating'],
            'review_text' => trim((string) ($row['review_text'] ?? '')),
            'full_name' => $name !== '' ? $name : 'Reader',
            'created_at' => (string) $row['created_at'],
        ];
    }

    return $reviews;
}

/** @return array{review_id: int, rating: int, review_text: string}|null */
function get_user_book_review(PDO $pdo, int $bookId, int $userId): ?array
{
    if ($bookId <= 0 || $userId <= 0) {
        return null;
    }

    ensure_book_reviews_schema($pdo);

    $stmt = $pdo->prepare('
        SELECT review_id, rating, review_text
        FROM book_reviews
        WHERE book_id = ? AND user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$bookId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'review_id' => (int) $row['review_id'],
        'rating' => (int) $row['rating'],
        'review_text' => trim((string) ($row['review_text'] ?? '')),
    ];
}

/** @return array{review_id: int, rating: int, review_text: string}|null */
function get_visitor_book_review(PDO $pdo, int $bookId, string $visitorToken): ?array
{
    $visitorToken = trim($visitorToken);
    if ($bookId <= 0 || $visitorToken === '') {
        return null;
    }

    ensure_book_reviews_schema($pdo);

    $stmt = $pdo->prepare('
        SELECT review_id, rating, review_text
        FROM book_reviews
        WHERE book_id = ? AND visitor_token = ?
        LIMIT 1
    ');
    $stmt->execute([$bookId, $visitorToken]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'review_id' => (int) $row['review_id'],
        'rating' => (int) $row['rating'],
        'review_text' => trim((string) ($row['review_text'] ?? '')),
    ];
}

/** @return array{ok: bool, error: string} */
function save_book_review(
    PDO $pdo,
    int $bookId,
    ?int $userId,
    int $rating,
    string $reviewText,
    string $visitorToken = ''
): array {
    if ($bookId <= 0) {
        return ['ok' => false, 'error' => 'Invalid story.'];
    }

    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'error' => 'Please choose a rating from 1 to 5 stars.'];
    }

    $reviewText = trim($reviewText);
    if (mb_strlen($reviewText) > 2000) {
        return ['ok' => false, 'error' => 'Written review must be 2000 characters or less.'];
    }

    $userId = $userId !== null && $userId > 0 ? $userId : null;
    $visitorToken = trim($visitorToken);
    if ($userId === null && $visitorToken === '') {
        return ['ok' => false, 'error' => 'Could not save your rating. Please try again.'];
    }

    ensure_book_reviews_schema($pdo);

    if ($userId !== null) {
        $stmt = $pdo->prepare('
            INSERT INTO book_reviews (book_id, user_id, visitor_token, rating, review_text)
            VALUES (?, ?, NULL, ?, ?)
            ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                review_text = VALUES(review_text),
                updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            $bookId,
            $userId,
            $rating,
            $reviewText !== '' ? $reviewText : null,
        ]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO book_reviews (book_id, user_id, visitor_token, rating, review_text)
            VALUES (?, NULL, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                review_text = VALUES(review_text),
                updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            $bookId,
            $visitorToken,
            $rating,
            $reviewText !== '' ? $reviewText : null,
        ]);
    }

    require_once __DIR__ . '/cache-lib.php';
    stories_page_cache_clear();

    return ['ok' => true, 'error' => ''];
}

function render_stars_html(int $rating, bool $filled = true): string
{
    $rating = max(0, min(5, $rating));
    $html = '<span class="stars-display" aria-hidden="true">';
    for ($i = 1; $i <= 5; $i++) {
        $class = 'star' . ($i <= $rating ? ' star-filled' : ' star-empty');
        if (!$filled && $i <= $rating) {
            $class = 'star star-filled';
        }
        $html .= '<span class="' . $class . '">★</span>';
    }
    $html .= '</span>';

    return $html;
}

function render_stars_from_average(float $average): string
{
    $rounded = (int) round($average);
    $rounded = max(1, min(5, $rounded));

    return render_stars_html($rounded);
}

/** @param array{average: float, count: int}|null $summary */
function render_story_card_rating(?array $summary): void
{
    if ($summary === null || $summary['count'] <= 0) {
        $noReviews = function_exists('t') ? t('book.no_reviews') : 'No reviews';
        $noReviewsYet = function_exists('t') ? t('book.no_reviews_aria') : 'No reviews yet';
        echo '<span class="story-card-rating story-card-rating-empty" aria-label="' . e($noReviewsYet) . '">';
        echo render_stars_html(0);
        echo '<span class="rating-label">' . e($noReviews) . '</span>';
        echo '</span>';
        return;
    }

    $avg = $summary['average'];
    $count = $summary['count'];
    $countLabel = function_exists('t')
        ? ($count === 1 ? t('book.review_count_one') : t('book.review_count_many', ['count' => (string) $count]))
        : ($count . ' review' . ($count === 1 ? '' : 's'));
    $outOf5 = function_exists('t') ? t('book.out_of_5') : 'out of 5';
    $aria = number_format($avg, 1) . ' ' . $outOf5 . ', ' . $countLabel;
    echo '<span class="story-card-rating" aria-label="' . e($aria) . '">';
    echo render_stars_from_average($avg);
    echo '<span class="rating-label">' . e(number_format($avg, 1)) . ' (' . $count . ')</span>';
    echo '</span>';
}

function render_book_rating_summary(?array $summary): void
{
    echo '<div class="book-rating-summary">';
    if ($summary === null || $summary['count'] <= 0) {
        echo '<p class="book-rating-line"><span class="book-rating-stars">' . render_stars_html(0) . '</span>';
        echo '<span class="book-rating-text">' . e(function_exists('t') ? t('book.no_reviews_first') : 'No reviews yet — be the first!') . '</span></p>';
    } else {
        $count = (int) $summary['count'];
        $countLabel = function_exists('t')
            ? ($count === 1 ? t('book.review_count_one') : t('book.review_count_many', ['count' => (string) $count]))
            : ($count . ' review' . ($count === 1 ? '' : 's'));
        $outOf5 = function_exists('t') ? t('book.out_of_5') : 'out of 5';
        echo '<p class="book-rating-line"><span class="book-rating-stars">' . render_stars_from_average($summary['average']) . '</span>';
        echo '<span class="book-rating-text"><strong>' . e(number_format($summary['average'], 1)) . '</strong> ' . e($outOf5) . ' · ';
        echo e($countLabel) . '</span></p>';
    }
    echo '</div>';
}

function render_review_form(int $bookId, ?array $userReview, bool $canSubmit, bool $isLoggedIn = true): void
{
    echo '<section class="book-reviews-form panel-section">';
    echo '<h2 class="section-subtitle">' . e(function_exists('t') ? t('book.rate_story') : 'Rate this story') . '</h2>';

    if (!$canSubmit) {
        echo '<p class="page-lead">' . e(function_exists('t') ? t('book.review_unlock') : 'Purchase or unlock this story to leave a review.') . '</p>';
        echo '</section>';
        return;
    }

    $currentRating = $userReview['rating'] ?? 0;
    $currentText = $userReview['review_text'] ?? '';

    echo '<form method="post" action="' . e(app_url('review-submit.php')) . '" class="review-form">';
    echo '<input type="hidden" name="book_id" value="' . $bookId . '">';
    echo '<input type="hidden" name="redirect" value="' . e(app_url('book.php?id=' . $bookId . '#reviews')) . '">';

    echo '<fieldset class="star-rating-fieldset">';
    echo '<legend>' . e(function_exists('t') ? t('book.rating_required') : 'Your rating (required)') . '</legend>';
    echo '<div class="star-rating-input">';
    for ($i = 5; $i >= 1; $i--) {
        $checked = $currentRating === $i ? ' checked' : '';
        $required = $currentRating === 0 ? ' required' : '';
        echo '<label class="star-rating-choice">';
        echo '<input type="radio" name="rating" value="' . $i . '"' . $checked . $required . '>';
        echo '<span class="star-choice-icon" aria-hidden="true">★</span>';
        echo '<span class="visually-hidden">' . $i . ' star' . ($i === 1 ? '' : 's') . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</fieldset>';

    echo '<label for="review_text" class="form-label">' . e(function_exists('t') ? t('book.review_optional') : 'Written review (optional)') . '</label>';
    echo '<textarea id="review_text" name="review_text" class="form-control" rows="4" maxlength="2000" placeholder="' . e(function_exists('t') ? t('book.review_placeholder') : 'What did you think of this story?') . '">' . e($currentText) . '</textarea>';

    $submitLabel = $userReview
        ? (function_exists('t') ? t('book.update_review') : 'Update Review')
        : (function_exists('t') ? t('book.submit_review') : 'Submit Review');
    echo '<button type="submit" class="btn btn-primary">' . e($submitLabel) . '</button>';
    echo '</form>';
    echo '</section>';
}

function render_reviews_list(array $reviews): void
{
    echo '<section class="book-reviews-list panel-section" id="reviews">';
    echo '<h2 class="section-subtitle">' . e(function_exists('t') ? t('book.reader_reviews') : 'Reader reviews') . '</h2>';

    if ($reviews === []) {
        echo '<p class="empty-state-inline">' . e(function_exists('t') ? t('book.no_reviews_yet') : 'No written reviews yet.') . '</p>';
        echo '</section>';
        return;
    }

    echo '<ul class="reviews-list">';
    foreach ($reviews as $review) {
        echo '<li class="review-item">';
        echo '<div class="review-item-head">';
        echo '<span class="review-item-stars">' . render_stars_html((int) $review['rating']) . '</span>';
        echo '<span class="review-item-author">' . e($review['full_name']) . '</span>';
        echo '</div>';
        if ($review['review_text'] !== '') {
            echo '<p class="review-item-text">' . nl2br(e($review['review_text'])) . '</p>';
        } else {
            echo '<p class="review-item-text review-item-text-muted"><em>' . e(function_exists('t') ? t('book.rated_only') : 'Rated without a written review.') . '</em></p>';
        }
        echo '</li>';
    }
    echo '</ul>';
    echo '</section>';
}
