<?php

declare(strict_types=1);

/**
 * Import "The Secret of the Charged Kingdom" into Chemistry.
 *
 *   php tools/import-secret-of-the-charged-kingdom.php
 *   php tools/import-secret-of-the-charged-kingdom.php --source="/path/to/file.pdf" --cover="/path/to/cover.jpg"
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
    : (getenv('HOME') . '/Downloads/The_Secret_of_the_Charged_Kingdom_Complete_A4.pdf');
$coverSource = isset($options['cover'])
    ? (string) $options['cover']
    : (getenv('HOME') . '/Downloads/The Secret of the Charged Kingdom.png');

$title = 'The Secret of the Charged Kingdom';
$author = 'Vaishnavi Renduchintala';
$categoryName = 'Chemistry';
$storyTopic = 'Ions, Cations, and Anions';
$description = 'Riya and Kabir enter Ionora, the Kingdom of Ions, with their robot guide Sparky. Through glowing castles, electron-exchange bridges, and crystal lessons, they discover how atoms become charged: lose an electron to form a cation, or gain an electron to form an anion.';
$scienceElement = 'An ion is a charged atom or group of atoms. A cation is a positive ion formed when an atom loses one or more electrons (example: Na → Na+). An anion is a negative ion formed when an atom gains one or more electrons (example: Cl → Cl−). Losing or gaining electrons changes the charge, but the number of protons stays the same, so the element identity does not change.';

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

if (!brand_pdf_file($destPdf, true)) {
    @unlink($destPdf);
    fwrite(STDERR, "Failed to brand PDF with Science Fables logo.\n");
    exit(1);
}

$coverFilename = 'cover_the_secret_of_the_charged_kingdom.jpg';
$coverDisk = $coversDir . '/' . $coverFilename;
if ($coverSource !== '' && is_file($coverSource)) {
    if (!copy($coverSource, $coverDisk)) {
        @unlink($destPdf);
        fwrite(STDERR, "Failed to copy cover image.\n");
        exit(1);
    }
} else {
    $python = find_python_with_pymupdf();
    // Prefer the kingdom illustration region on page 1 for a strong catalog cover.
    $renderScript = <<<'PY'
import sys
import fitz
pdf_path, out_path = sys.argv[1], sys.argv[2]
doc = fitz.open(pdf_path)
page = doc[0]
rect = page.rect
# Top-right story illustration area (approx) — falls back to full page if crop fails.
crop = fitz.Rect(rect.width * 0.42, rect.height * 0.08, rect.width * 0.97, rect.height * 0.58)
pix = page.get_pixmap(dpi=160, alpha=False, clip=crop)
if pix.width < 200 or pix.height < 200:
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
