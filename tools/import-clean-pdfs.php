<?php

declare(strict_types=1);

/**
 * Replace story PDFs from a folder of clean (unbranded) files, then stamp Science Fables logo.
 *
 * Usage:
 *   php tools/import-clean-pdfs.php
 *   php tools/import-clean-pdfs.php --dir=/path/to/clean-pdfs
 *   php tools/import-clean-pdfs.php --no-brand
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/pdf-branding-lib.php';

$options = getopt('', ['dir::', 'book::', 'no-brand']);
$onlyBook = isset($options['book']) ? (int) $options['book'] : 0;
$skipBrand = array_key_exists('no-brand', $options);
$cleanDir = isset($options['dir']) ? (string) $options['dir'] : (getenv('HOME') . '/Downloads/science-fables-clean-pdfs');

if (!is_dir($cleanDir)) {
    fwrite(STDERR, "Clean PDF folder not found: {$cleanDir}\n");
    exit(1);
}

if (!$skipBrand && find_python_with_pymupdf() === null) {
    fwrite(STDERR, "Python with PyMuPDF required. Run: python3 -m venv /tmp/pdfvenv && pip install pymupdf\n");
    exit(1);
}

/** @var array<int, string> */
$manualMap = [
    69 => 'The_Mystery_of_the_Cubes_Images_As_Is.pdf',
];

$pdfFiles = glob(rtrim($cleanDir, '/') . '/*.pdf') ?: [];
if ($pdfFiles === []) {
    fwrite(STDERR, "No PDF files in {$cleanDir}\n");
    exit(1);
}

$stmt = $pdo->query(
    "SELECT book_id, title, pdf_file_path
     FROM books
     WHERE book_format = 'pdf' AND pdf_file_path IS NOT NULL AND pdf_file_path != ''
     ORDER BY book_id"
);

$replaced = 0;
$branded = 0;
$skipped = 0;
$unmatched = [];

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
    $source = null;

    if (isset($manualMap[$bookId])) {
        $candidate = rtrim($cleanDir, '/') . '/' . $manualMap[$bookId];
        if (is_file($candidate)) {
            $source = $candidate;
        }
    }

    if ($source === null) {
        $source = find_clean_pdf_for_title($title, $pdfFiles);
    }

    if ($source === null) {
        fwrite(STDERR, "No clean PDF match for book #{$bookId}: {$title}\n");
        $unmatched[] = $bookId . ': ' . $title;
        $skipped++;
        continue;
    }

    echo "Book #{$bookId}: {$title}\n";
    echo "  Source <- " . basename($source) . "\n";

    if (!copy($source, $diskPath)) {
        fwrite(STDERR, "  Failed to copy PDF\n");
        $skipped++;
        continue;
    }
    $replaced++;

    if ($skipBrand) {
        echo "  Replaced (no logo)\n";
        continue;
    }

    if (!brand_pdf_file($diskPath, in_array($bookId, pdf_compact_brand_book_ids(), true))) {
        fwrite(STDERR, "  Failed to brand PDF\n");
        $skipped++;
        continue;
    }
    $brandNote = in_array($bookId, pdf_compact_brand_book_ids(), true) ? ' (compact logo)' : '';
    echo "  Replaced and branded{$brandNote}\n";
    $branded++;
}

echo "\nDone. Replaced {$replaced}, branded {$branded}, skipped {$skipped}.\n";
if ($unmatched !== []) {
    echo "\nUnmatched stories (add PDFs to {$cleanDir}):\n";
    foreach ($unmatched as $line) {
        echo "  - {$line}\n";
    }
}

function find_clean_pdf_for_title(string $title, array $pdfFiles): ?string
{
    $titleWords = title_match_words($title);
    if ($titleWords === []) {
        return null;
    }

    $bestPath = null;
    $bestScore = -1.0;

    foreach ($pdfFiles as $path) {
        $name = normalize_match_name((string) pathinfo($path, PATHINFO_FILENAME));

        $matched = 0;
        foreach ($titleWords as $word) {
            if (str_contains($name, $word)) {
                $matched++;
            }
        }

        if ($matched === 0) {
            continue;
        }

        $titleScore = $matched / count($titleWords);
        if ($titleScore < 0.75) {
            continue;
        }

        $score = $titleScore * 100 + clean_pdf_preference_bonus($path);

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestPath = $path;
        }
    }

    return $bestPath;
}

function normalize_match_name(string $name): string
{
    $name = strtolower($name);
    $name = str_replace(["'", '’'], '', $name);
    $name = str_replace(['_', '-'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;

    return trim($name);
}

function clean_pdf_preference_bonus(string $path): float
{
    $name = strtolower((string) pathinfo($path, PATHINFO_FILENAME));
    $bonus = 0.0;

    if (str_contains($name, 'no_logo') || str_contains($name, 'no logo')) {
        $bonus += 30;
    }
    if (str_contains($name, 'realistic')) {
        $bonus += 15;
    }
    if (str_contains($name, 'images_as_is') || str_contains($name, 'images as is')) {
        $bonus += 12;
    }
    if (str_contains($name, 'images_only') || str_contains($name, 'images only')) {
        $bonus += 12;
    }
    if (str_contains($name, 'storytelling')) {
        $bonus += 8;
    }
    if (str_contains($name, 'final')) {
        $bonus += 6;
    }

    return $bonus;
}

/** @return list<string> */
function title_match_words(string $title): array
{
    $title = normalize_match_name($title);
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
