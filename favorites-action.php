<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/favorites-lib.php';

stories_open_writable_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('my-library.php'));
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$bookId = (int) ($_POST['book_id'] ?? 0);
$redirect = safe_redirect_path($_POST['redirect'] ?? null, 'my-library.php');

if ($action === 'toggle') {
    $result = toggle_favorite($bookId, $pdo);
    if (!$result['ok']) {
        $_SESSION['favorites_flash_error'] = $result['error'];
    }
}

header('Location: ' . $redirect);
exit;
