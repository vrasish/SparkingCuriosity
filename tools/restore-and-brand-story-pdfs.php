<?php

declare(strict_types=1);

/**
 * CLI: Restore story PDFs from ~/Downloads (when matched), then apply one logo pass.
 *
 * Usage:
 *   php tools/restore-and-brand-story-pdfs.php
 *   php tools/restore-and-brand-story-pdfs.php --book=62
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/pdf-branding-lib.php';

$options = getopt('', ['book::']);
$onlyBook = isset($options['book']) ? (int) $options['book'] : 0;

if (find_python_with_pymupdf() === null) {
    fwrite(STDERR, "Python with PyMuPDF required.\n");
    exit(1);
}

$downloadsDir = '/Users/vaishnavi/Downloads';
if (!is_dir($downloadsDir)) {
    fwrite(STDERR, "Downloads folder not found: {$downloadsDir}\n");
    exit(1);
}

$stmt = $pdo->query(
    "SELECT book_id, title, pdf_file_path
     FROM books
     WHERE book_format = 'pdf' AND pdf_file_path IS NOT NULL AND pdf_file_path != ''
     ORDER BY book_id"
);

$restored = 0;
$branded = 0;
$skipped = 0;

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $book) {
    $bookId = (int) $book['book_id'];
    if ($onlyBook > 0 && $bookId !== $onlyBook) {
        continue;
    }

    $title = (string) $book['title'];
    $relative = trim((string) ($book['pdf_file_path'] ?? ''));
    if (!is_safe_pdf_path($relative)) {
        fwrite(STDERR, "Skip book #{$bookId}: unsafe path\n");
        $skipped++;
        continue;
    }

    $diskPath = dirname(__DIR__) . '/' . $relative;
    if (!is_file($diskPath)) {
        fwrite(STDERR, "Skip book #{$bookId}: missing file ({$relative})\n");
        $skipped++;
        continue;
    }

    $source = find_source_pdf_in_downloads($title, $downloadsDir);
    if ($source === null) {
        fwrite(STDERR, "No Downloads match for book #{$bookId}: {$title}\n");
        $skipped++;
        continue;
    }

    echo "Book #{$bookId}: {$title}\n";
    echo "  Restore <- {$source}\n";

    if (!copy($source, $diskPath)) {
        fwrite(STDERR, "  Failed to copy source PDF\n");
        $skipped++;
        continue;
    }
    $restored++;

    if (!brand_pdf_file($diskPath)) {
        fwrite(STDERR, "  Failed to brand PDF\n");
        $skipped++;
        continue;
    }
    echo "  Branded once\n";
    $branded++;
}

echo "Done. Restored {$restored}, branded {$branded}, skipped {$skipped}.\n";

/**
 * Find the best matching source PDF in Downloads for a story title.
 */
function find_source_pdf_in_downloads(string $title, string $downloadsDir): ?string
{
    $titleWords = title_match_words($title);
    if ($titleWords === []) {
        return null;
    }

    $candidates = glob($downloadsDir . '/*.pdf') ?: [];
    $bestPath = null;
    $bestScore = 0.0;

    foreach ($candidates as $path) {
        $name = strtolower((string) pathinfo($path, PATHINFO_FILENAME));
        $name = str_replace(['_', '-'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        $matched = 0;
        foreach ($titleWords as $word) {
            if (str_contains($name, $word)) {
                $matched++;
            }
        }

        if ($matched === 0) {
            continue;
        }

        $score = $matched / count($titleWords);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestPath = $path;
        }
    }

    return $bestScore >= 0.75 ? $bestPath : null;
}

/** @return list<string> */
function title_match_words(string $title): array
{
    $title = strtolower($title);
    $title = preg_replace('/[^a-z0-9\s]/', ' ', $title) ?? $title;
    $parts = preg_split('/\s+/', trim($title), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [];
    }

    $stop = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'of', 'with'];
    $words = [];
    foreach ($parts as $word) {
        if (strlen($word) < 3 || in_array($word, $stop, true)) {
            continue;
        }
        $words[] = $word;
    }

    return array_values(array_unique($words));
}
