<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
$target = 'search.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . app_url($target), true, 301);
exit;
