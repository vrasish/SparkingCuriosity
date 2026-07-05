<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/quiz-lib.php';

header('Content-Type: application/json; charset=utf-8');

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
], JSON_UNESCAPED_UNICODE);
