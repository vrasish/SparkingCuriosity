<?php

declare(strict_types=1);

/**
 * Link cover image files on disk to books.cover_image_url in the database.
 *
 *   php tools/sync-cover-images.php
 */

require_once dirname(__DIR__) . '/db.php';

$pdo = stories_connect();
$result = sync_book_cover_urls($pdo, true);

echo 'Updated ' . $result['updated'] . " book cover URL(s).\n";

if ($result['missing'] !== []) {
    echo "\nNo cover file found for:\n";
    foreach ($result['missing'] as $line) {
        echo '  ' . $line . "\n";
    }
    exit(1);
}

$sample = $pdo->query("
    SELECT book_id, title, cover_image_url
    FROM books
    WHERE status = 'approved'
    ORDER BY book_id
    LIMIT 5
")->fetchAll();

echo "\nSample rows in database:\n";
foreach ($sample as $row) {
    echo '  #' . (int) $row['book_id'] . ' — ' . ($row['cover_image_url'] ?? '') . "\n";
}

$empty = (int) $pdo->query("
    SELECT COUNT(*)
    FROM books
    WHERE status = 'approved'
      AND (cover_image_url IS NULL OR cover_image_url = '')
")->fetchColumn();

echo "\nApproved books without cover_image_url: {$empty}\n";
