<?php

declare(strict_types=1);

/**
 * Import "The Mystery of Everyday Changes" into Chemistry.
 *
 *   php tools/import-mystery-of-everyday-changes.php
 *   php tools/import-mystery-of-everyday-changes.php --source="/path/to/file.pdf" --cover="/path/to/cover.jpg"
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/cart-lib.php';
require_once dirname(__DIR__) . '/pdf-branding-lib.php';
require_once dirname(__DIR__) . '/cache-lib.php';

$options = getopt('', ['source::', 'cover::']);
$sourcePdf = isset($options['source'])
    ? (string) $options['source']
    : (getenv('HOME') . '/Downloads/The_Mystery_of_Everyday_Changes 2.pdf');
$coverSource = isset($options['cover'])
    ? (string) $options['cover']
    : '';

$title = 'The Mystery of Everyday Changes';
$author = 'Vaishnavi Renduchintala';
$categoryName = 'Chemistry';
$storyTopic = 'Physical and Chemical Changes';
$description = 'Maya and Arjun explore everyday kitchen moments with Mom — melting ice, toasting bread, lemonade, candles, and rust — and learn to tell physical changes from chemical ones by asking: was a new substance formed?';
$scienceElement = 'A physical change alters shape, size, or state without forming a new substance (melting ice, cutting a lemon, dissolving sugar). A chemical change produces one or more new substances (toasting bread, burning wax, rusting iron). Some physical changes are reversible; chemical changes usually are not. In a closed system, mass is conserved in both kinds of change.';

if (!is_file($sourcePdf)) {
    fwrite(STDERR, "Source PDF not found: {$sourcePdf}\n");
    exit(1);
}
if ($coverSource === '' || !is_file($coverSource)) {
    fwrite(STDERR, "Cover image not found: {$coverSource}\n");
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

// Portrait kitchen/science pages — compact logo so it does not cover Science Fact bars.
if (function_exists('find_python_with_pymupdf') && find_python_with_pymupdf() !== null) {
    if (!brand_pdf_file($destPdf, true)) {
        @unlink($destPdf);
        fwrite(STDERR, "Failed to brand PDF with Science Fables logo.\n");
        exit(1);
    }
} else {
    echo "Warning: PyMuPDF not found — PDF copied without branding.\n";
}

$coverExt = strtolower(pathinfo($coverSource, PATHINFO_EXTENSION) ?: 'jpg');
if (!in_array($coverExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    $coverExt = 'jpg';
}
$coverFilename = 'cover_the_mystery_of_everyday_changes.' . ($coverExt === 'jpeg' ? 'jpg' : $coverExt);
$coverDisk = $coversDir . '/' . $coverFilename;
if (!copy($coverSource, $coverDisk)) {
    @unlink($destPdf);
    fwrite(STDERR, "Failed to copy cover image.\n");
    exit(1);
}

$relativePdf = 'uploads/books/' . $basename;
$coverUrl = function_exists('cover_storage_url')
    ? cover_storage_url($coverFilename)
    : ('uploads/covers/' . $coverFilename);

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

if (function_exists('stories_page_cache_clear')) {
    stories_page_cache_clear();
}
if (function_exists('stories_cache_bust')) {
    stories_cache_bust();
}

echo "Imported #{$bookId}: {$title}\n";
echo "  Category: {$categoryName}\n";
echo "  PDF: {$relativePdf}\n";
echo "  Cover: {$coverUrl}\n";
echo "  Story topic: {$storyTopic}\n";
