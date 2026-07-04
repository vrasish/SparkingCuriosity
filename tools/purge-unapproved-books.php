<?php

declare(strict_types=1);

/**
 * Remove rejected/unpublished stories from the database.
 * Keeps only approved books (plus any with purchase history, which cannot be deleted).
 *
 *   php tools/purge-unapproved-books.php
 */

require_once dirname(__DIR__) . '/db.php';

$pdo = stories_connect();

$pending = $pdo->query("
    SELECT book_id, title, status
    FROM books
    WHERE status != 'approved'
    ORDER BY book_id
")->fetchAll();

if ($pending === []) {
    echo "No unapproved books to remove.\n";
    exit(0);
}

echo "Removing " . count($pending) . " unapproved book(s):\n";
foreach ($pending as $row) {
    echo '  #' . (int) $row['book_id'] . ' — ' . ($row['title'] ?? '') . ' (' . ($row['status'] ?? '') . ")\n";
}

$removed = purge_unapproved_books($pdo);

echo "\nRemoved " . count($removed) . " book(s): " . implode(', ', $removed) . "\n";

$remaining = (int) $pdo->query("SELECT COUNT(*) FROM books WHERE status != 'approved'")->fetchColumn();
if ($remaining > 0) {
    echo "Warning: {$remaining} unapproved book(s) could not be removed (likely purchase history).\n";
}

$approved = (int) $pdo->query("SELECT COUNT(*) FROM books WHERE status = 'approved'")->fetchColumn();
echo "Approved books remaining: {$approved}\n";
