<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/reviews-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('explore.php'));
    exit;
}

$bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
require_login($bookId > 0 ? 'book.php?id=' . $bookId . '#reviews' : 'explore.php');
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$reviewText = trim((string) ($_POST['review_text'] ?? ''));
$redirect = trim((string) ($_POST['redirect'] ?? ''));

if ($redirect === '' || !preg_match('#^book(\.php)?\?id=#', $redirect)) {
    $redirect = app_url('book.php?id=' . $bookId . '#reviews');
}

if ($bookId <= 0) {
    header('Location: ' . app_url('explore.php'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT book_id, status, price_cents, book_format, pdf_file_path FROM books WHERE book_id = ? LIMIT 1');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    header('Location: ' . $redirect . '&review_error=1');
    exit;
}

if (!$book || ($book['status'] ?? '') !== 'approved') {
    header('Location: ' . app_url('explore.php'));
    exit;
}

if (!can_read_book($book)) {
    header('Location: ' . app_url('book.php?id=' . $bookId . '&review_error=locked'));
    exit;
}

$userId = (int) (current_user()['user_id'] ?? 0);
$result = save_book_review($pdo, $bookId, $userId, $rating, $reviewText);

if (!$result['ok']) {
    header('Location: ' . app_url('book.php?id=' . $bookId . '&review_error=' . rawurlencode($result['error']) . '#reviews'));
    exit;
}

header('Location: ' . app_url('book.php?id=' . $bookId . '&review_saved=1#reviews'));
exit;
