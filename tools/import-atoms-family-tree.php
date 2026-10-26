<?php

declare(strict_types=1);

/**
 * Import "The Atom's Family Tree" into Chemistry.
 *
 *   php tools/import-atoms-family-tree.php
 *   php tools/import-atoms-family-tree.php --source="/path/to/file.pdf" --cover="/path/to/cover.jpg"
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
    : (getenv('HOME') . '/Downloads/Atoms_Family_Tree_Printable.pdf');
$coverSource = isset($options['cover'])
    ? (string) $options['cover']
    : (dirname(__DIR__) . '/uploads/covers/cover_the_atoms_family_tree.jpg');

$title = "The Atom's Family Tree";
$author = 'Vaishnavi Renduchintala';
$categoryName = 'Chemistry';
$storyTopic = 'Atomic Number, Mass Number, and Isotopes';
$description = 'At a science fair booth called The Atom Detective Agency, Anika and Rohan learn atomic number, mass number, and isotopes — and how atoms of the same element can have different numbers of neutrons.';
$scienceElement = 'Atomic number (Z) is the number of protons and identifies the element; in a neutral atom, protons = electrons. Mass number (A) is protons + neutrons. Isotopes are atoms of the same element with the same atomic number but different numbers of neutrons (and different mass numbers). A nuclide symbol shows A and Z with the element symbol.';

if (!is_file($sourcePdf)) {
    fwrite(STDERR, "Source PDF not found: {$sourcePdf}\n");
    exit(1);
}
if (!is_file($coverSource)) {
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

$logo = site_logo_pdf_disk_path();
$python = find_python_with_pymupdf() ?? 'python3';
$script = dirname(__DIR__) . '/tools/brand_pdf.py';
$cmd = escapeshellarg($python) . ' '
    . escapeshellarg($script) . ' '
    . escapeshellarg($destPdf) . ' '
    . escapeshellarg($logo) . ' '
    . escapeshellarg(site_copyright_text(false)) . ' '
    . escapeshellarg('compact');
exec($cmd, $brandOut, $brandCode);
if ($brandCode !== 0 || !is_file($destPdf)) {
    @unlink($destPdf);
    fwrite(STDERR, "Failed to brand PDF with Science Fables logo.\n");
    exit(1);
}

$coverFilename = 'cover_the_atoms_family_tree.jpg';
$coverDisk = $coversDir . '/' . $coverFilename;
if (realpath($coverSource) !== realpath($coverDisk)) {
    if (!copy($coverSource, $coverDisk)) {
        @unlink($destPdf);
        fwrite(STDERR, "Failed to copy cover image.\n");
        exit(1);
    }
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
