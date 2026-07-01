<?php
require_once __DIR__ . '/auth.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'login.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target);
exit;
