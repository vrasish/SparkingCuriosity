<?php

declare(strict_types=1);

require_once __DIR__ . '/i18n-lib.php';

/** Ensure books table can store Spanish title, description, science, topic, and PDF. */
function ensure_book_i18n_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $needed = [
        'title_es' => 'VARCHAR(255) NULL',
        'description_es' => 'TEXT NULL',
        'science_element_es' => 'TEXT NULL',
        'story_topic_es' => 'VARCHAR(255) NULL',
        'pdf_file_path_es' => 'TEXT NULL',
    ];

    $colStmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'books'
    ");
    $existing = [];
    foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $existing[(string) $name] = true;
    }

    foreach ($needed as $column => $definition) {
        if (!isset($existing[$column])) {
            $pdo->exec('ALTER TABLE books ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    $checked = true;
}

/** @param array<string, mixed> $book */
function book_has_spanish(array $book): bool
{
    $pdf = trim((string) ($book['pdf_file_path_es'] ?? ''));

    return $pdf !== '';
}

/**
 * Whether the visitor asked for Spanish story content (site language or ?story_lang=es).
 *
 * @param array<string, mixed> $book
 */
function book_wants_spanish(array $book): bool
{
    if (isset($_GET['story_lang'])) {
        return i18n_normalize_lang((string) $_GET['story_lang']) === 'es';
    }

    return is_spanish();
}

/**
 * Resolve which story language content to serve (PDF/quiz).
 * Spanish PDF only when a Spanish file exists; otherwise English (unless Spanish UI hides it as coming soon).
 *
 * @param array<string, mixed> $book
 */
function book_content_lang(array $book): string
{
    return book_wants_spanish($book) && book_has_spanish($book) ? 'es' : 'en';
}

/**
 * Localized display fields for a book in the given content language.
 * Spanish UI uses Spanish title/description when present; without a Spanish PDF the reader shows coming soon (no English PDF).
 *
 * @param array<string, mixed> $book
 * @return array{title: string, description: string, science_element: string, story_topic: string, pdf_file_path: string, content_lang: string, has_spanish: bool, spanish_coming_soon: bool, wants_spanish: bool}
 */
function book_localized_fields(array $book, ?string $contentLang = null): array
{
    $hasSpanish = book_has_spanish($book);
    $wantSpanish = book_wants_spanish($book);
    $contentLang = $contentLang ?? book_content_lang($book);
    $useSpanishPdf = $contentLang === 'es' && $hasSpanish;
    $spanishComingSoon = $wantSpanish && !$hasSpanish;

    $title = trim((string) ($book['title'] ?? ''));
    $description = trim((string) ($book['description'] ?? ''));
    $science = trim((string) ($book['science_element'] ?? ''));
    $topic = trim((string) ($book['story_topic'] ?? ''));
    $pdf = trim((string) ($book['pdf_file_path'] ?? ''));

    if ($wantSpanish) {
        $titleEs = trim((string) ($book['title_es'] ?? ''));
        $descEs = trim((string) ($book['description_es'] ?? ''));
        $scienceEs = trim((string) ($book['science_element_es'] ?? ''));
        $topicEs = trim((string) ($book['story_topic_es'] ?? ''));

        if ($titleEs !== '') {
            $title = $titleEs;
        }
        if ($descEs !== '') {
            $description = $descEs;
        }
        if ($scienceEs !== '') {
            $science = $scienceEs;
        }
        if ($topicEs !== '') {
            $topic = $topicEs;
        }

        if ($useSpanishPdf) {
            $pdfEs = trim((string) ($book['pdf_file_path_es'] ?? ''));
            if ($pdfEs !== '') {
                $pdf = $pdfEs;
            }
        } elseif ($spanishComingSoon) {
            // Do not fall back to the English PDF in Spanish UI.
            $pdf = '';
        }
    }

    return [
        'title' => $title,
        'description' => $description,
        'science_element' => $science,
        'story_topic' => $topic,
        'pdf_file_path' => $pdf,
        'content_lang' => $useSpanishPdf ? 'es' : 'en',
        'has_spanish' => $hasSpanish,
        'wants_spanish' => $wantSpanish,
        'spanish_coming_soon' => $spanishComingSoon,
    ];
}

/**
 * Quiz JSON path for a language. Spanish uses {id}.es.json when present.
 */
function quiz_json_path_for_lang(int $bookId, string $lang = 'en'): string
{
    $dir = __DIR__ . '/data/quiz';
    if ($lang === 'es') {
        $esPath = $dir . '/' . $bookId . '.es.json';
        if (is_file($esPath)) {
            return $esPath;
        }
    }

    return $dir . '/' . $bookId . '.json';
}

function book_has_spanish_quiz(int $bookId): bool
{
    return is_file(__DIR__ . '/data/quiz/' . $bookId . '.es.json');
}

function render_book_language_toggle(int $bookId, array $book, bool $wantsSpanish): void
{
    $enUrl = app_url('book.php?id=' . $bookId . '&story_lang=en');
    $esUrl = app_url('book.php?id=' . $bookId . '&story_lang=es');

    echo '<div class="book-lang-toggle" role="group" aria-label="' . e(t('book.story_language')) . '">';
    echo '<span class="book-lang-toggle-label">' . e(t('book.story_language')) . '</span>';
    echo '<a href="' . e($enUrl) . '" class="book-lang-btn' . (!$wantsSpanish ? ' is-active' : '') . '"'
        . (!$wantsSpanish ? ' aria-current="true"' : '') . '>' . e(t('book.lang_english')) . '</a>';
    echo '<a href="' . e($esUrl) . '" class="book-lang-btn' . ($wantsSpanish ? ' is-active' : '') . '"'
        . ($wantsSpanish ? ' aria-current="true"' : '') . '>' . e(t('book.lang_spanish')) . '</a>';
    echo '</div>';
}

function render_spanish_coming_soon_notice(): void
{
    echo '<div class="book-spanish-soon" role="status">';
    echo '<p class="book-spanish-soon-title">' . e(t('book.spanish_coming_soon')) . '</p>';
    echo '<p class="book-spanish-soon-detail">' . e(t('book.spanish_coming_soon_detail')) . '</p>';
    echo '</div>';
}

/** Badge for story cards when Spanish UI is on and this story has no Spanish PDF yet. */
function render_spanish_coming_soon_badge(?array $book = null): void
{
    if (!function_exists('is_spanish') || !is_spanish()) {
        return;
    }

    if ($book !== null && book_has_spanish($book)) {
        return;
    }

    echo '<span class="badge-spanish-soon">' . e(t('book.coming_soon_badge')) . '</span>';
}

/** Whether this book should appear in the current site language listings. */
function book_available_in_site_lang(array $book): bool
{
    // Show all approved stories in Spanish UI; cards use Spanish title/description
    // when available, and coming-soon when the Spanish PDF is not ready yet.
    return true;
}

/**
 * Listing filter for the current site language.
 * Spanish UI keeps all stories visible (Spanish card copy + coming-soon badge).
 *
 * @param list<array<string, mixed>> $books
 * @return list<array<string, mixed>>
 */
function filter_books_for_site_lang(array $books): array
{
    return $books;
}

/**
 * Card title/description for the current site language.
 *
 * @param array<string, mixed> $book
 * @return array{title: string, description: string}
 */
function book_card_display_fields(array $book): array
{
    $title = trim((string) ($book['title'] ?? ''));
    $description = trim((string) ($book['description'] ?? ''));

    if (function_exists('is_spanish') && is_spanish()) {
        $titleEs = trim((string) ($book['title_es'] ?? ''));
        $descEs = trim((string) ($book['description_es'] ?? ''));
        if ($titleEs !== '') {
            $title = $titleEs;
        }
        if ($descEs !== '') {
            $description = $descEs;
        }
    }

    return [
        'title' => $title,
        'description' => $description,
    ];
}
