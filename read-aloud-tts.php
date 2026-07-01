<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/read-aloud-lib.php';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 0;

if ($bookId <= 0 || $page <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

if (read_aloud_story_data($bookId) === null) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'story_not_found']);
    exit;
}

try {
    $audioPath = read_aloud_generate_audio($bookId, $page);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'tts_failed']);
    exit;
}

header('Content-Type: audio/mpeg');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . (string) filesize($audioPath));
readfile($audioPath);
