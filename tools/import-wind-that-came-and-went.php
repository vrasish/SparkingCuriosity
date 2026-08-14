<?php

declare(strict_types=1);

/**
 * Import "The Wind That Came and Went" into Earth Science (not on homepage).
 *
 *   php tools/import-wind-that-came-and-went.php
 *   php tools/import-wind-that-came-and-went.php --source="/path/to/file.pdf" --cover="/path/to/cover.png"
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/pdf-branding-lib.php';
require_once dirname(__DIR__) . '/cache-lib.php';

$options = getopt('', ['source::', 'cover::']);
$sourcePdf = isset($options['source'])
    ? (string) $options['source']
    : (getenv('HOME') . '/Downloads/The_Wind_That_Came_and_Went_Storybook.pdf');
$coverSource = isset($options['cover'])
    ? (string) $options['cover']
    : '';

$title = 'The Wind That Came and Went';
$author = 'Vaishnavi Renduchintala';
$categoryName = 'Earth Science';
$storyTopic = 'Sea Breeze and Land Breeze';
$description = 'At a sunny beach, two curious kids feel the cool sea breeze in the afternoon and wonder where the wind goes at night—discovering how land and sea heat up differently and swap gentle breezes back and forth.';
$scienceElement = 'During the day, land heats up faster than the ocean. Warm air rises over the land, and cooler air from the sea moves in to replace it—this is the sea breeze. At night, land cools faster than the ocean. Cooler, denser air sinks over the land and warmer air from the sea moves toward shore—this is the land breeze.';

if (!is_file($sourcePdf)) {
    fwrite(STDERR, "Source PDF not found: {$sourcePdf}\n");
    exit(1);
}

if (find_python_with_pymupdf() === null) {
    fwrite(STDERR, "Python with PyMuPDF required.\n");
    exit(1);
}

ensure_book_pdf_schema($pdo);
ensure_book_pricing_schema($pdo);
ensure_story_topic_schema($pdo);

$stmtExisting = $pdo->prepare('SELECT book_id FROM books WHERE title = ? LIMIT 1');
$stmtExisting->execute([$title]);
if ($stmtExisting->fetchColumn()) {
    echo "Already imported: {$title}\n";
    exit(0);
}

$uploadDir = books_upload_dir();
$coversDir = books_covers_dir();
foreach ([$uploadDir, $coversDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

$basename = 'book_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
$destPdf = $uploadDir . '/' . $basename;
if (!copy($sourcePdf, $destPdf)) {
    fwrite(STDERR, "Failed to copy PDF.\n");
    exit(1);
}

if (!brand_pdf_file($destPdf)) {
    @unlink($destPdf);
    fwrite(STDERR, "Failed to brand PDF with Science Fables logo.\n");
    exit(1);
}

$coverFilename = 'cover_the_wind_that_came_and_went.png';
$coverDisk = $coversDir . '/' . $coverFilename;
if ($coverSource !== '' && is_file($coverSource)) {
    if (!copy($coverSource, $coverDisk)) {
        @unlink($destPdf);
        fwrite(STDERR, "Failed to copy cover image.\n");
        exit(1);
    }
} else {
    $python = find_python_with_pymupdf();
    $renderScript = <<<'PY'
import sys
import fitz
pdf_path, out_path = sys.argv[1], sys.argv[2]
doc = fitz.open(pdf_path)
page_index = 1 if doc.page_count > 1 else 0
page = doc[page_index]
pix = page.get_pixmap(dpi=150, alpha=False)
pix.save(out_path)
print("cover_ok")
PY;
    $cmd = escapeshellarg($python) . ' -c ' . escapeshellarg($renderScript) . ' '
        . escapeshellarg($destPdf) . ' ' . escapeshellarg($coverDisk);
    exec($cmd, $out, $code);
    if ($code !== 0 || !is_file($coverDisk)) {
        @unlink($destPdf);
        fwrite(STDERR, "Failed to render cover image.\n");
        exit(1);
    }
}

$relativePdf = 'uploads/books/' . $basename;
$coverUrl = cover_storage_url($coverFilename);

$stmtCat = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
$stmtCat->execute([$categoryName]);
$categoryId = $stmtCat->fetchColumn();
if (!$categoryId) {
    $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$categoryName]);
    $categoryId = (int) $pdo->lastInsertId();
} else {
    $categoryId = (int) $categoryId;
}

try {
    $pdo->beginTransaction();

    $insertBook = $pdo->prepare("
        INSERT INTO books (
            title, author_name, description, cover_image_url,
            age_group, science_element, story_topic, status, book_format, pdf_file_path,
            created_by, price_cents
        ) VALUES (
            :title, :author_name, :description, :cover_image_url,
            '8-15', :science_element, :story_topic, 'approved', 'pdf', :pdf_file_path,
            NULL, 0
        )
    ");
    $insertBook->execute([
        'title' => $title,
        'author_name' => $author,
        'description' => $description,
        'cover_image_url' => $coverUrl,
        'science_element' => $scienceElement,
        'story_topic' => $storyTopic,
        'pdf_file_path' => $relativePdf,
    ]);
    $bookId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)')
        ->execute([$bookId, $categoryId]);

    $pdo->commit();
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($destPdf);
    @unlink($coverDisk);
    fwrite(STDERR, $ex->getMessage() . "\n");
    exit(1);
}

stories_page_cache_clear();

echo "Imported #{$bookId}: {$title}\n";
echo "  Category: {$categoryName}\n";
echo "  PDF: {$relativePdf}\n";
echo "  Cover: {$coverUrl}\n";
echo "  Story topic: {$storyTopic}\n";
echo "  Not added to homepage featured lists.\n";
