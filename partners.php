<?php
require_once __DIR__ . '/auth.php';

$target = app_url('get-involved.php');
$query = [];
if (!empty($_SERVER['QUERY_STRING'])) {
    parse_str((string) $_SERVER['QUERY_STRING'], $query);
}
if (isset($query['section']) && $query['section'] === 'become') {
    $target .= '#partner-with-us';
} else {
    $target .= '#partners';
}

header('Location: ' . $target, true, 301);
exit;
