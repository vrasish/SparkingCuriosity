<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/reviews-lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$payload = $_POST;
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (str_contains($contentType, 'application/json')) {
    $raw = file_get_contents('php://input');
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$bookId = isset($payload['book_id']) ? (int) $payload['book_id'] : 0;
$rating = isset($payload['rating']) ? (int) $payload['rating'] : 0;
$reviewText = trim((string) ($payload['review_text'] ?? ''));

if ($bookId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid story.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT book_id, status, price_cents, book_format, pdf_file_path FROM books WHERE book_id = ? LIMIT 1');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save your rating. Please try again.']);
    exit;
}

if (!$book || ($book['status'] ?? '') !== 'approved') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Story not found.']);
    exit;
}

if (!can_read_book($book)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Purchase this story before leaving a rating.']);
    exit;
}

$userId = is_logged_in() ? (int) (current_user()['user_id'] ?? 0) : null;
$visitorToken = $userId ? '' : review_visitor_token();
$result = save_book_review($pdo, $bookId, $userId, $rating, $reviewText, $visitorToken);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$summary = get_book_rating_summary($pdo, $bookId);

echo json_encode([
    'ok' => true,
    'rating' => $rating,
    'message' => function_exists('t') ? t('book.rating_thanks') : 'Thank you for rating this story!',
    'summary' => $summary,
]);
