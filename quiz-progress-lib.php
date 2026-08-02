<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function ensure_user_quiz_completions_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $readyFlag = __DIR__ . '/data/.quiz-completions-schema-ready';
    if (is_file($readyFlag)) {
        $checked = true;
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_quiz_completions (
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            score INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, book_id),
            INDEX idx_user_quiz_completions_book (book_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!is_dir(dirname($readyFlag))) {
        mkdir(dirname($readyFlag), 0775, true);
    }
    file_put_contents($readyFlag, date('c') . "\n");

    $checked = true;
}

/** @return array<int, true> */
function quiz_completed_book_ids_for_user(PDO $pdo, int $userId, bool $refresh = false): array
{
    static $cache = [];
    static $loadedForUser = null;

    if ($userId <= 0) {
        return [];
    }

    if ($refresh) {
        $loadedForUser = null;
    }

    if ($loadedForUser === $userId) {
        return $cache;
    }

    ensure_user_quiz_completions_schema($pdo);

    try {
        $stmt = $pdo->prepare('SELECT book_id FROM user_quiz_completions WHERE user_id = ?');
        $stmt->execute([$userId]);
        $cache = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $bookId) {
            $cache[(int) $bookId] = true;
        }
        $loadedForUser = $userId;
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $cache = [];
        $loadedForUser = $userId;
    }

    return $cache;
}

function is_quiz_completed_for_viewer(int $bookId): bool
{
    global $pdo;

    $userId = current_user_id();
    if ($userId === null || $bookId <= 0) {
        return false;
    }

    $completed = quiz_completed_book_ids_for_user($pdo, $userId);

    return isset($completed[$bookId]);
}

function save_quiz_completion(PDO $pdo, int $userId, int $bookId, int $score, int $total): bool
{
    if ($userId <= 0 || $bookId <= 0) {
        return false;
    }

    ensure_user_quiz_completions_schema($pdo);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_quiz_completions (user_id, book_id, score, total)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                total = VALUES(total),
                completed_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$userId, $bookId, max(0, $score), max(0, $total)]);
        quiz_completed_book_ids_for_user($pdo, $userId, true);

        return true;
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return false;
    }
}
