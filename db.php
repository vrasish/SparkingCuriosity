<?php
$configPath = __DIR__ . '/db.config.php';

if (!is_readable($configPath)) {
    die(
        'Database connection failed: db.config.php is missing. '
        . 'Copy db.config.example.php to db.config.php and set your password.'
    );
}

$dbConfig = require $configPath;
$dbHost = (string) ($dbConfig['host'] ?? '127.0.0.1');
$dbPort = (string) ($dbConfig['port'] ?? '3306');
$dbName = (string) ($dbConfig['dbname'] ?? 'myappdb');
$dbUser = (string) ($dbConfig['user'] ?? 'dbeaver_user');
$dbPassword = $dbConfig['password'] ?? '';

$placeholderPasswords = ['PUT_MY_PASSWORD_HERE', 'YourNewStrongPasswordHere', 'MY_REAL_PASSWORD'];
if ($dbPassword === '' || in_array($dbPassword, $placeholderPasswords, true)) {
    die(
        'Database connection failed: MySQL password not configured. '
        . 'Open db.config.php and set password to the exact same value as in DBeaver (Connection settings → Password).'
    );
}

// Connect via local SSH tunnel (see db.config.php host/port). Do not use 138.197.27.95 here.
$storiesDbDsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4;connect_timeout=5',
    $dbHost,
    $dbPort,
    $dbName
);

/** @var PDO|null */
$pdo = null;

function stories_connect(): PDO
{
    global $pdo, $storiesDbDsn, $dbUser, $dbPassword, $dbHost, $dbPort;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            $storiesDbDsn,
            $dbUser,
            $dbPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, '2002') || str_contains($msg, 'Connection refused')) {
            die(
                'Database connection failed: Nothing is listening on '
                . $dbHost . ':' . $dbPort . ' (Connection refused). '
                . 'The SSH tunnel is not running. '
                . 'One-time fix: open Terminal, cd to your stories folder, and run '
                . '<code>./install-db-tunnel-autostart.sh</code> — the tunnel will start automatically on login. '
                . 'Or run <code>./start-db-tunnel.sh</code> manually and keep that window open.'
            );
        }
        die('Database connection failed: ' . $msg);
    }

    return $pdo;
}

if (!defined('STORIES_SKIP_DB') || !STORIES_SKIP_DB) {
    stories_connect();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Extensionless site URL, e.g. app_url('explore.php') => 'explore', app_url('book.php?id=3') => 'book?id=3' */
function app_url(string $path): string
{
    $path = ltrim($path, '/');
    if ($path === '' || $path === 'index.php' || $path === 'index') {
        return 'index';
    }

    $file = $path;
    $suffix = '';
    if (preg_match('~^([^?#]+)(.*)$~', $path, $matches)) {
        $file = $matches[1];
        $suffix = $matches[2];
    }

    if (str_ends_with(strtolower($file), '.php')) {
        $file = substr($file, 0, -4);
    }

    if ($file === 'index') {
        return 'index' . $suffix;
    }

    return $file . $suffix;
}

/** Absolute URL path for JSON/audio API endpoints (works from any page URL). */
function app_api_url(string $path): string
{
    $base = app_base_path();
    $url = app_url($path);
    if ($base === '') {
        return '/' . ltrim($url, '/');
    }

    return $base . '/' . ltrim($url, '/');
}

function default_cover(?string $title = null): string
{
    $text = $title !== null && $title !== '' ? $title : 'Science Story';
    return 'https://placehold.co/400x560/5b6ee1/ffffff?text=' . rawurlencode($text);
}

function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/stories/index.php');
    $base = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
    return $base;
}

/** Cache-busted URL for static assets (CSS, JS) so browsers pick up changes. */
function asset_url(string $file): string
{
    $relative = ltrim($file, '/');
    $diskPath = __DIR__ . '/' . $relative;
    if (!is_file($diskPath)) {
        return $relative;
    }

    return $relative . '?v=' . filemtime($diskPath);
}

function render_stylesheet(): void
{
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" media="print" onload="this.media=\'all\'">' . "\n";
    echo '<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap"></noscript>' . "\n";
    echo '<link rel="stylesheet" href="' . e(asset_url('styles.css')) . '">' . "\n";
}

/**
 * Resolve cover image for <img src>. Fixes paths under /stories/ and falls back if file is missing.
 */
function cover_image_src(?string $url, ?string $title = null): string
{
    if ($url === null || trim($url) === '') {
        return default_cover($title);
    }

    $url = trim($url);
    $base = app_base_path();
    $pathOnly = $url;

    if (preg_match('#^https?://#i', $url)) {
        $parsed = parse_url($url);
        $pathOnly = $parsed['path'] ?? $url;
    }

    // Normalize to a browser path under the app (e.g. /stories/uploads/covers/file.png)
    if (preg_match('#/uploads/covers/([a-zA-Z0-9._-]+\.png)$#i', $pathOnly, $m)) {
        $browserPath = $base . '/uploads/covers/' . $m[1];
        $diskPath = books_covers_dir() . '/' . $m[1];
    } elseif (preg_match('#^uploads/covers/([a-zA-Z0-9._-]+\.png)$#i', $pathOnly, $m)) {
        $browserPath = $base . '/uploads/covers/' . $m[1];
        $diskPath = books_covers_dir() . '/' . $m[1];
    } elseif (str_starts_with($pathOnly, '/images/') || str_starts_with($pathOnly, '/uploads/')) {
        $browserPath = $base . $pathOnly;
        $diskPath = __DIR__ . '/' . ltrim(str_replace($base, '', $browserPath), '/');
    } elseif (str_starts_with($pathOnly, '/')) {
        $browserPath = str_starts_with($pathOnly, $base . '/') ? $pathOnly : $base . $pathOnly;
        $diskPath = __DIR__ . '/' . ltrim(str_replace($base, '', $browserPath), '/');
    } else {
        $browserPath = $base . '/' . ltrim($pathOnly, '/');
        $diskPath = __DIR__ . '/' . ltrim($pathOnly, '/');
    }

    if (isset($diskPath) && is_file($diskPath)) {
        return $browserPath;
    }

    return default_cover($title);
}

function books_covers_dir(): string
{
    return __DIR__ . '/uploads/covers';
}

function cover_slug_from_title(string $title): string
{
    $title = strtolower(trim($title));
    $title = str_replace(["'", '"', '’', '—', '–'], '', $title);
    $title = preg_replace('/[^a-z0-9]+/', '_', $title) ?? $title;

    return trim($title, '_');
}

function cover_storage_url(string $filename): string
{
    return 'uploads/covers/' . ltrim($filename, '/');
}

function cover_filename_from_url(?string $url): ?string
{
    if ($url === null || trim($url) === '') {
        return null;
    }

    if (preg_match('#/uploads/covers/([a-zA-Z0-9._-]+\.(?:png|jpe?g|webp|gif))$#i', trim($url), $m)) {
        return $m[1];
    }

    return null;
}

function find_cover_file_for_title(string $title): ?string
{
    $dir = books_covers_dir();
    $slug = cover_slug_from_title($title);
    $candidates = [
        'cover_the_' . $slug . '.png',
        'cover_' . $slug . '.png',
    ];

    foreach ($candidates as $file) {
        if (is_file($dir . '/' . $file)) {
            return $file;
        }
    }

    return null;
}

/** @return array{updated: int, missing: list<string>} */
function sync_book_cover_urls(PDO $pdo, bool $approvedOnly = true): array
{
    $sql = 'SELECT book_id, title, cover_image_url FROM books';
    if ($approvedOnly) {
        $sql .= " WHERE status = 'approved'";
    }
    $sql .= ' ORDER BY book_id';

    $stmt = $pdo->query($sql);
    $update = $pdo->prepare('UPDATE books SET cover_image_url = ? WHERE book_id = ?');
    $updated = 0;
    $missing = [];

    foreach ($stmt->fetchAll() as $row) {
        $bookId = (int) ($row['book_id'] ?? 0);
        $title = trim((string) ($row['title'] ?? ''));
        $current = trim((string) ($row['cover_image_url'] ?? ''));

        $filename = cover_filename_from_url($current);
        if ($filename !== null && is_file(books_covers_dir() . '/' . $filename)) {
            $storageUrl = cover_storage_url($filename);
        } else {
            $filename = find_cover_file_for_title($title);
            if ($filename === null) {
                $missing[] = '#' . $bookId . ' — ' . $title;
                continue;
            }
            $storageUrl = cover_storage_url($filename);
        }

        if ($current !== $storageUrl) {
            $update->execute([$storageUrl, $bookId]);
            $updated += 1;
        }
    }

    return ['updated' => $updated, 'missing' => $missing];
}

/** Science box columns filling left and right edges (center stays clear). */
function render_page_header(string $title, ?string $lead = null): void
{
    echo '<header class="page-header">';
    echo '<h1 class="page-title">' . e($title) . '</h1>';
    if ($lead !== null && $lead !== '') {
        echo '<p class="page-lead">' . e($lead) . '</p>';
    }
    echo '</header>';
}

function render_fun_background(bool $minimal = false): void
{
    // Body gradient only — no decorative overlay layers.
}

function topic_class(string $name): string
{
    $map = [
        'Space' => 'topic-space',
        'Body' => 'topic-body',
        'Plants' => 'topic-plants',
        'Plants & Animals' => 'topic-plants',
        'Animals' => 'topic-animals',
        'Weather' => 'topic-weather',
        'Microbes' => 'topic-germs',
        'Germs' => 'topic-germs',
        'Earth Science' => 'topic-earth-science',
        'Weather and Atmosphere' => 'topic-earth-science',
        'Ocean' => 'topic-earth-science',
        'Engineering' => 'topic-engineering',
        'Physical Science' => 'topic-physical-science',
    ];
    return $map[$name] ?? 'topic-default';
}

function primary_category(?string $categories): string
{
    if ($categories === null || $categories === '') {
        return '';
    }
    $parts = array_map('trim', explode(',', $categories));
    return $parts[0] ?? '';
}

function estimate_reading_minutes(string $text): int
{
    $words = str_word_count(strip_tags($text));
    return max(5, (int) ceil(max(1, $words) / 200));
}

/** At most N words per line; wraps the full text with line breaks. */
function format_text_word_break_html(string $text, int $wordsPerLine): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($words === false || $words === []) {
        return e($text);
    }

    $wordsPerLine = max(1, $wordsPerLine);
    if (count($words) <= $wordsPerLine) {
        return e($text);
    }

    $lines = [];
    foreach (array_chunk($words, $wordsPerLine) as $chunk) {
        $lines[] = e(implode(' ', $chunk));
    }

    return implode('<br>', $lines);
}

function format_story_title_html(string $title, int $wordsPerLine = 3): string
{
    return format_text_word_break_html($title, $wordsPerLine);
}

function format_story_description_html(string $description, int $wordsPerLine = 6): string
{
    return format_text_word_break_html($description, $wordsPerLine);
}

function render_topic_tags(?string $categories): void
{
    if (empty($categories)) {
        return;
    }
    echo '<div class="topic-tags">';
    foreach (array_map('trim', explode(',', $categories)) as $cat) {
        if ($cat === '') {
            continue;
        }
        echo '<span class="topic-tag category-btn ' . topic_class($cat) . '">' . e($cat) . '</span>';
    }
    echo '</div>';
}

/** @return list<array{label: string, slug: string, icon: string, class: string}> */
function home_topic_tiles(): array
{
    return [
        ['label' => 'Space', 'slug' => 'Space', 'icon' => '🪐', 'class' => 'topic-space'],
        ['label' => 'Plants', 'slug' => 'Plants', 'icon' => '🌱', 'class' => 'topic-plants'],
        ['label' => 'Animals', 'slug' => 'Animals', 'icon' => '🐾', 'class' => 'topic-animals'],
        ['label' => 'Earth Science', 'slug' => 'Earth Science', 'icon' => '🌍', 'class' => 'topic-earth-science'],
        ['label' => 'Human Body', 'slug' => 'Body', 'icon' => '🫀', 'class' => 'topic-body'],
        ['label' => 'Microbes', 'slug' => 'Microbes', 'icon' => '🦠', 'class' => 'topic-germs'],
        ['label' => 'Physical Science', 'slug' => 'Physical Science', 'icon' => '⚛️', 'class' => 'topic-physical-science'],
    ];
}

/** Curated stories shown in the home page "Latest Stories" section. */
function home_featured_story_ids(): array
{
    return [75, 54, 70, 5, 43, 41];
}

/** Curated editor picks shown between Latest Stories rows on the homepage. */
function home_top_pick_story_ids(): array
{
    return [39, 78, 71];
}

/** @return list<array<string, mixed>> */
function home_fetch_books_by_ids(PDO $pdo, array $bookIds): array
{
    $bookIds = array_values(array_filter(array_map('intval', $bookIds)));
    if ($bookIds === []) {
        return [];
    }

    $idList = implode(',', $bookIds);
    $stmt = $pdo->prepare("
        SELECT
            b.book_id,
            b.title,
            b.author_name,
            b.description,
            b.cover_image_url,
            b.age_group,
            b.price_cents,
            b.book_format,
            GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
        FROM books b
        LEFT JOIN book_categories bc ON b.book_id = bc.book_id
        LEFT JOIN categories c ON bc.category_id = c.category_id
        WHERE b.status = 'approved'
          AND b.book_id IN ({$idList})
        GROUP BY b.book_id
        ORDER BY FIELD(b.book_id, {$idList})
    ");
    $stmt->execute();

    return $stmt->fetchAll();
}


function status_class(string $status): string
{
    $allowed = ['approved', 'under_review', 'rejected', 'draft', 'needs_edits'];
    return in_array($status, $allowed, true) ? 'status-' . $status : 'status-draft';
}

function ensure_book_pdf_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $readyFlag = __DIR__ . '/data/.pdf-schema-ready';
    if (is_file($readyFlag)) {
        $checked = true;
        return;
    }

    $stmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'books'
          AND COLUMN_NAME IN ('pdf_file_path', 'book_format')
    ");
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('pdf_file_path', $existing, true)) {
        $pdo->exec('ALTER TABLE books ADD COLUMN pdf_file_path TEXT NULL');
    }
    if (!in_array('book_format', $existing, true)) {
        $pdo->exec("ALTER TABLE books ADD COLUMN book_format VARCHAR(20) NOT NULL DEFAULT 'pages'");
    }

    if (!is_dir(dirname($readyFlag))) {
        mkdir(dirname($readyFlag), 0775, true);
    }
    file_put_contents($readyFlag, date('c') . "\n");

    $checked = true;
}

function books_upload_dir(): string
{
    return __DIR__ . '/uploads/books';
}

function is_safe_pdf_path(?string $path): bool
{
    if ($path === null || $path === '') {
        return false;
    }
    if (str_contains($path, '..') || str_starts_with($path, '/')) {
        return false;
    }
    return (bool) preg_match('#^uploads/books/[a-zA-Z0-9._-]+\.pdf$#', $path);
}

/** @return list<string> */
function admin_story_statuses(): array
{
    return ['draft', 'under_review', 'approved', 'rejected', 'needs_edits'];
}

/** @return list<string> */
function admin_science_topics(): array
{
    return ['Space', 'Body', 'Plants', 'Animals', 'Weather', 'Microbes', 'Earth Science', 'Engineering', 'Physical Science'];
}

function delete_book_files(array $book): void
{
    $pdfPath = $book['pdf_file_path'] ?? '';
    if (is_string($pdfPath) && is_safe_pdf_path($pdfPath)) {
        $disk = __DIR__ . '/' . $pdfPath;
        if (is_file($disk)) {
            @unlink($disk);
        }
    }

    $coverUrl = $book['cover_image_url'] ?? '';
    if (is_string($coverUrl) && preg_match('#/uploads/covers/([a-zA-Z0-9._-]+\.png)$#i', $coverUrl, $m)) {
        $disk = books_covers_dir() . '/' . $m[1];
        if (is_file($disk)) {
            @unlink($disk);
        }
    }
}

function delete_book_sidecar_data(int $bookId): void
{
    if ($bookId <= 0) {
        return;
    }

    $paths = [
        __DIR__ . '/data/quiz/' . $bookId . '.json',
        __DIR__ . '/data/read-aloud/' . $bookId . '.json',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $audioDir = __DIR__ . '/data/read-aloud/audio/' . $bookId;
    if (is_dir($audioDir)) {
        foreach (glob($audioDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($audioDir);
    }
}

function book_has_order_history(PDO $pdo, int $bookId): bool
{
    if ($bookId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT 1 FROM order_items WHERE book_id = ? LIMIT 1');
        $stmt->execute([$bookId]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $ex) {
        return false;
    }
}

function delete_book_by_id(PDO $pdo, int $bookId): bool
{
    if ($bookId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('
            SELECT book_id, pdf_file_path, cover_image_url
            FROM books
            WHERE book_id = ?
            LIMIT 1
        ');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();
        if (!$book) {
            return false;
        }

        if (book_has_order_history($pdo, $bookId)) {
            error_log('delete_book_by_id blocked for book ' . $bookId . ': purchase history exists.');
            return false;
        }

        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM reports WHERE book_id = ?')->execute([$bookId]);
        $pdo->prepare('DELETE FROM book_pages WHERE book_id = ?')->execute([$bookId]);
        $pdo->prepare('DELETE FROM book_categories WHERE book_id = ?')->execute([$bookId]);
        $pdo->prepare('DELETE FROM submissions WHERE book_id = ?')->execute([$bookId]);

        if (is_file(__DIR__ . '/reviews-lib.php')) {
            require_once __DIR__ . '/reviews-lib.php';
            ensure_book_reviews_schema($pdo);
            $pdo->prepare('DELETE FROM book_reviews WHERE book_id = ?')->execute([$bookId]);
        }

        if (is_file(__DIR__ . '/favorites-lib.php')) {
            require_once __DIR__ . '/favorites-lib.php';
            ensure_user_favorites_schema($pdo);
            $pdo->prepare('DELETE FROM user_favorites WHERE book_id = ?')->execute([$bookId]);
        }

        if (is_file(__DIR__ . '/stripe-lib.php')) {
            require_once __DIR__ . '/stripe-lib.php';
            ensure_purchase_schema($pdo);
            $pdo->prepare('DELETE FROM user_library WHERE book_id = ?')->execute([$bookId]);
        }

        $pdo->prepare('DELETE FROM books WHERE book_id = ?')->execute([$bookId]);
        $pdo->commit();

        delete_book_files($book);
        delete_book_sidecar_data($bookId);

        return true;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return false;
    }
}

/** @return list<int> */
function purge_unapproved_books(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT book_id, title, status
        FROM books
        WHERE status != 'approved'
        ORDER BY book_id
    ");
    $removed = [];

    foreach ($stmt->fetchAll() as $row) {
        $bookId = (int) ($row['book_id'] ?? 0);
        if ($bookId <= 0) {
            continue;
        }
        if (delete_book_by_id($pdo, $bookId)) {
            $removed[] = $bookId;
        }
    }

    return $removed;
}
