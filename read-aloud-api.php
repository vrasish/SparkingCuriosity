<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/read-aloud-lib.php';

header('Content-Type: application/json; charset=utf-8');

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bookId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

$data = read_aloud_story_data($bookId);
if ($data === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'book_id' => $bookId]);
    exit;
}

if (read_aloud_neural_available($bookId)) {
    $tts = read_aloud_tts_config();
    $data['neural_audio'] = true;
    $data['tts_api'] = app_api_url('read-aloud-tts.php');
    $data['tts_provider'] = read_aloud_book_prefers_uploaded_audio($bookId) ? 'uploaded' : $tts['provider'];
    $data['voice'] = $tts['voice'];
    $data['model'] = $tts['model'];
    $data['tts_speed'] = $tts['speed'];
}

$data['audio_pages'] = read_aloud_playable_pages($bookId);

if (isset($data['pages']) && is_array($data['pages'])) {
    foreach ($data['pages'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pageText = isset($entry['text']) ? trim((string) $entry['text']) : '';
        if ($pageText === '') {
            continue;
        }
        $data['pages'][$index]['tts_text'] = read_aloud_prepare_text_for_tts($pageText);
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
