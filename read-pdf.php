<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cart-lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';

if ($bookId <= 0) {
    http_response_code(400);
    exit('Invalid story.');
}

try {
    $stmt = $pdo->prepare('
        SELECT book_id, title, status, book_format, pdf_file_path, price_cents
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
    http_response_code(403);
    exit('Purchase this story to read it.');
}

$pdfPath = $book['pdf_file_path'] ?? '';
if (($book['book_format'] ?? '') !== 'pdf' || !is_safe_pdf_path($pdfPath)) {
    http_response_code(404);
    exit('PDF not available.');
}

$diskPath = __DIR__ . '/' . $pdfPath;
if (!is_file($diskPath)) {
    http_response_code(404);
    exit('PDF file not found.');
}

$filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $book['title']) . '.pdf';
$download = isset($_GET['download']) && $_GET['download'] === '1';
$fileSize = filesize($diskPath);

while (ob_get_level() > 0) {
    ob_end_clean();
}

@ini_set('zlib.output_compression', '0');
@set_time_limit(0);

header('Content-Type: application/pdf');
header(
    'Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"'
);
header('Content-Length: ' . (string) $fileSize);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
    $start = $matches[1] !== '' ? (int) $matches[1] : 0;
    $end = $matches[2] !== '' ? (int) $matches[2] : ($fileSize - 1);

    if ($start > $end || $start >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    $end = min($end, $fileSize - 1);
    $length = $end - $start + 1;

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . (string) $length);

    $handle = fopen($diskPath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        exit('Could not open PDF.');
    }

    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($handle);
    exit;
}

$handle = fopen($diskPath, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Could not open PDF.');
}

fpassthru($handle);
fclose($handle);
