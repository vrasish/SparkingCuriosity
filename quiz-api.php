<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/quiz-lib.php';
require_once __DIR__ . '/quiz-progress-lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $bookId = isset($payload['book_id']) ? (int) $payload['book_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
    $score = isset($payload['score']) ? (int) $payload['score'] : 0;
    $total = isset($payload['total']) ? (int) $payload['total'] : 0;

    if ($bookId <= 0 || !quiz_has_story($bookId)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_id']);
        exit;
    }

    $saved = false;
    $userId = current_user_id();
    if ($userId !== null) {
        $saved = save_quiz_completion($pdo, $userId, $bookId, $score, $total);
    }

    echo json_encode([
        'ok' => true,
        'book_id' => $bookId,
        'saved' => $saved,
        'logged_in' => $userId !== null,
    ]);
    exit;
}

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bookId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

$questions = quiz_public_questions($bookId);
if ($questions === []) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'book_id' => $bookId]);
    exit;
}

$data = quiz_story_data($bookId);

echo json_encode([
    'book_id' => $bookId,
    'title' => (string) ($data['title'] ?? ''),
    'intro' => (string) ($data['intro'] ?? ''),
    'questions' => $questions,
    'quiz_completed' => is_quiz_completed_for_viewer($bookId),
], JSON_UNESCAPED_UNICODE);
