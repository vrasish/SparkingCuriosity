<?php

declare(strict_types=1);

/**
 * Generate 5-question quizzes for approved stories.
 *
 * Examples:
 *   php tools/generate-story-quizzes.php
 *   php tools/generate-story-quizzes.php --book=48
 *   php tools/generate-story-quizzes.php --force
 */

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/quiz-lib.php';

$options = getopt('', ['book::', 'force']);
$onlyBook = isset($options['book']) ? (int) $options['book'] : 0;
$force = array_key_exists('force', $options);

$pdo = stories_connect();

if ($onlyBook > 0) {
    $bookIds = [$onlyBook];
} else {
    $bookIds = array_map('intval', $pdo->query("SELECT book_id FROM books WHERE status = 'approved' ORDER BY book_id")->fetchAll(PDO::FETCH_COLUMN));
}

$created = 0;
$skipped = 0;
$failed = 0;

foreach ($bookIds as $bookId) {
    if ($bookId <= 0) {
        continue;
    }

    if (!$force && quiz_has_story($bookId)) {
        echo "Skip book {$bookId} (quiz exists)\n";
        $skipped += 1;
        continue;
    }

    echo "Generating quiz for book {$bookId}...\n";
    $result = quiz_generate_fallback_for_book($pdo, $bookId);
    if (!$result['ok']) {
        echo "  Fallback failed, trying AI...\n";
        $result = quiz_generate_for_book($pdo, $bookId);
    }
    if (!$result['ok']) {
        echo "  Failed: " . ($result['error'] ?? 'unknown error') . "\n";
        $failed += 1;
        continue;
    }

    echo "  Saved: " . quiz_json_path($bookId) . "\n";
    $created += 1;
    usleep(300000);
}

echo "Done. Created {$created}, skipped {$skipped}, failed {$failed}.\n";
