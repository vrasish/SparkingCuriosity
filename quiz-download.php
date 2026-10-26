<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/quiz-lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';
$lang = strtolower(trim((string) ($_GET['lang'] ?? 'en')));
if ($lang !== 'es') {
    $lang = 'en';
}

if ($bookId <= 0) {
    http_response_code(400);
    exit('Invalid story.');
}

try {
    $stmt = $pdo->prepare('
        SELECT book_id, title, title_es, status, book_format, pdf_file_path, price_cents
        FROM books
        WHERE book_id = ?
        LIMIT 1
    ');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    exit('Could not load story.');
}

if (!$book) {
    http_response_code(404);
    exit('Story not found.');
}

if (($book['status'] ?? '') !== 'approved' && !$preview) {
    http_response_code(403);
    exit('This story is not available.');
}

if (!$preview && !can_read_book($book, $preview)) {
    if (story_requires_signup($bookId)) {
        redirect_guest_to_signup_for_story($bookId);
    }
    http_response_code(403);
    exit('Purchase this story to download the quiz.');
}

$quizLang = $lang;
$questions = quiz_public_questions($bookId, $quizLang);
if ($questions === [] && $quizLang === 'es') {
    $quizLang = 'en';
    $questions = quiz_public_questions($bookId, 'en');
}
if ($questions === []) {
    http_response_code(404);
    exit('Quiz is not available yet.');
}

$displayTitle = '';
if ($quizLang === 'es') {
    $displayTitle = trim((string) ($book['title_es'] ?? ''));
}
if ($displayTitle === '') {
    $displayTitle = (string) ($book['title'] ?? '');
}

try {
    $pdfPath = quiz_build_download_pdf($bookId, $displayTitle, $quizLang);
} catch (Throwable $e) {
    error_log('quiz download failed for book ' . $bookId . ': ' . $e->getMessage());
    http_response_code(500);
    exit('Could not build quiz PDF.');
}

$safeTitle = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $displayTitle);
$safeTitle = trim((string) $safeTitle, '-') ?: 'story';
$filename = $safeTitle . ($quizLang === 'es' ? '-cuestionario.pdf' : '-quiz.pdf');
$fileSize = filesize($pdfPath);

while (ob_get_level() > 0) {
    ob_end_clean();
}

@ini_set('zlib.output_compression', '0');
@set_time_limit(0);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) $fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($pdfPath);
@unlink($pdfPath);
exit;
