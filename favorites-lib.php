<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function ensure_user_favorites_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $readyFlag = __DIR__ . '/data/.favorites-schema-ready';
    if (is_file($readyFlag)) {
        $checked = true;
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_favorites (
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, book_id),
            INDEX idx_user_favorites_book (book_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!is_dir(dirname($readyFlag))) {
        mkdir(dirname($readyFlag), 0775, true);
    }
    file_put_contents($readyFlag, date('c') . "\n");

    $checked = true;
}

/** @return array<int, true> */
function favorited_book_ids_for_user(PDO $pdo, int $userId, bool $refresh = false): array
{
    static $cache = [];
    static $loadedForUser = null;

    if ($userId <= 0) {
        return [];
    }

    if ($refresh) {
        $loadedForUser = null;
    }

    if ($loadedForUser === $userId) {
        return $cache;
    }

    ensure_user_favorites_schema($pdo);

    try {
        $stmt = $pdo->prepare('SELECT book_id FROM user_favorites WHERE user_id = ? ORDER BY saved_at DESC');
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

function is_book_favorited(int $bookId): bool
{
    global $pdo;

    $userId = current_user_id();
    if ($userId === null || $bookId <= 0) {
        return false;
    }

    $favorites = favorited_book_ids_for_user($pdo, $userId);

    return isset($favorites[$bookId]);
}

/** @return list<array<string, mixed>> */
function favorite_books_for_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    ensure_user_favorites_schema($pdo);

    try {
        $stmt = $pdo->prepare("
            SELECT
                b.book_id,
                b.title,
                b.author_name,
                b.description,
                b.science_element,
                b.cover_image_url,
                b.age_group,
                b.book_format,
                b.price_cents,
                GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
            FROM user_favorites uf
            INNER JOIN books b ON b.book_id = uf.book_id
            LEFT JOIN book_categories bc ON b.book_id = bc.book_id
            LEFT JOIN categories c ON bc.category_id = c.category_id
            WHERE uf.user_id = ?
              AND b.status = 'approved'
            GROUP BY b.book_id
            ORDER BY uf.saved_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return [];
    }
}

/** @return array{ok: bool, favorited: bool, error: string} */
function toggle_favorite(int $bookId, PDO $pdo): array
{
    $userId = current_user_id();
    if ($userId === null) {
        return ['ok' => false, 'favorited' => false, 'error' => 'Log in to save stories to your library.'];
    }
    if ($bookId <= 0) {
        return ['ok' => false, 'favorited' => false, 'error' => 'Invalid story.'];
    }

    ensure_user_favorites_schema($pdo);

    $stmt = $pdo->prepare("SELECT book_id FROM books WHERE book_id = ? AND status = 'approved' LIMIT 1");
    $stmt->execute([$bookId]);
    if (!$stmt->fetchColumn()) {
        return ['ok' => false, 'favorited' => false, 'error' => 'Story not found.'];
    }

    $favorites = favorited_book_ids_for_user($pdo, $userId);
    $isFavorited = isset($favorites[$bookId]);

    try {
        if ($isFavorited) {
            $pdo->prepare('DELETE FROM user_favorites WHERE user_id = ? AND book_id = ?')->execute([$userId, $bookId]);
            $favorited = false;
        } else {
            $pdo->prepare('INSERT INTO user_favorites (user_id, book_id) VALUES (?, ?)')->execute([$userId, $bookId]);
            $favorited = true;
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'favorited' => $isFavorited, 'error' => 'Could not update your library.'];
    }

    favorited_book_ids_for_user($pdo, $userId, true);

    return ['ok' => true, 'favorited' => $favorited, 'error' => ''];
}

function favorites_page_redirect(?string $redirect = null): string
{
    if ($redirect !== null && $redirect !== '') {
        return safe_redirect_path($redirect, 'my-library.php');
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($requestUri !== '') {
        return safe_redirect_path($requestUri, 'my-library.php');
    }

    return app_url('my-library.php');
}

function render_story_favorite_button(int $bookId, ?string $redirect = null): void
{
    if ($bookId <= 0) {
        return;
    }

    $redirect = favorites_page_redirect($redirect);
    $loggedIn = is_logged_in();
    $favorited = $loggedIn && is_book_favorited($bookId);
    $classes = 'story-favorite-btn' . ($favorited ? ' is-favorited' : '');
    $label = $favorited ? 'Remove from My Library' : 'Save to My Library';

    if (!$loggedIn) {
        $loginUrl = app_url('login.php?redirect=' . rawurlencode($redirect));
        echo '<a href="' . e($loginUrl) . '" class="' . e($classes) . '" aria-label="Log in to save this story" title="Save to My Library">';
        echo '<span class="story-favorite-icon" aria-hidden="true">♡</span>';
        echo '</a>';
        return;
    }

    echo '<form method="post" action="' . e(app_url('favorites-action.php')) . '" class="story-favorite-form">';
    echo '<input type="hidden" name="action" value="toggle">';
    echo '<input type="hidden" name="book_id" value="' . $bookId . '">';
    echo '<input type="hidden" name="redirect" value="' . e($redirect) . '">';
    echo '<button type="submit" class="' . e($classes) . '" aria-label="' . e($label) . '" aria-pressed="' . ($favorited ? 'true' : 'false') . '" title="' . e($label) . '">';
    echo '<span class="story-favorite-icon" aria-hidden="true">' . ($favorited ? '♥' : '♡') . '</span>';
    echo '</button>';
    echo '</form>';
}

function render_story_card_cover(int $bookId, string $coverUrl, string $title, ?string $redirect = null): void
{
    echo '<div class="story-card-media">';
    render_story_favorite_button($bookId, $redirect);
    echo '<img src="' . e($coverUrl) . '" alt="Cover of ' . e($title) . '" class="story-card-img" loading="lazy">';
    echo '</div>';
}
