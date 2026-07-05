<?php

declare(strict_types=1);

/**
 * CLI: Add logo + copyright to all story PDFs.
 *
 * Usage:
 *   php tools/brand-story-pdfs.php
 *   php tools/brand-story-pdfs.php --book=62
 *   php tools/brand-story-pdfs.php --force
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/pdf-branding-lib.php';

$options = getopt('', ['book::', 'force']);
$onlyBook = isset($options['book']) ? (int) $options['book'] : 0;

if (find_python_with_pymupdf() === null) {
    fwrite(STDERR, "Python with PyMuPDF required. Run: python3 -m venv /tmp/pdfvenv && pip install pymupdf\n");
    exit(1);
}

$stmt = $pdo->query(
    "SELECT book_id, title, pdf_file_path
     FROM books
     WHERE book_format = 'pdf' AND pdf_file_path IS NOT NULL AND pdf_file_path != ''
     ORDER BY book_id"
);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $book) {
    $bookId = (int) $book['book_id'];
    if ($onlyBook > 0 && $bookId !== $onlyBook) {
        continue;
    }

    $relative = trim((string) ($book['pdf_file_path'] ?? ''));
    if (!is_safe_pdf_path($relative)) {
        fwrite(STDERR, "Skip book #{$bookId}: unsafe path\n");
        continue;
    }

    $diskPath = __DIR__ . '/../' . $relative;
    if (!is_file($diskPath)) {
        fwrite(STDERR, "Skip book #{$bookId}: file missing ({$relative})\n");
        continue;
    }

    echo "Branding book #{$bookId}: {$book['title']}\n";
    $compact = in_array($bookId, pdf_compact_brand_book_ids(), true);
    if (!brand_pdf_file($diskPath, $compact)) {
        fwrite(STDERR, "Failed to brand book #{$bookId}\n");
        continue;
    }
}

echo "Done.\n";
