<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n-lib.php';

$lang = i18n_normalize_lang((string) ($_GET['lang'] ?? 'en'));
set_site_lang($lang);

$redirect = (string) ($_GET['redirect'] ?? '/');
// Only allow relative redirects on this site.
if ($redirect === '' || preg_match('#^(https?:)?//#i', $redirect) || str_contains($redirect, "\n")) {
    $redirect = app_url('index.php');
} elseif ($redirect[0] !== '/') {
    $redirect = app_url(ltrim($redirect, '/'));
}

// Drop sticky language query params so the cookie alone drives EN/ES after switch.
$parts = parse_url($redirect);
$query = [];
$path = '/';
$fragment = '';
if (is_array($parts)) {
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    unset($query['story_lang'], $query['lang'], $query['_sw'], $query['v']);
    $path = (string) ($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }
    if (!empty($parts['fragment'])) {
        $fragment = (string) $parts['fragment'];
    }
}

// Force a fresh document load (same-URL redirects on /search were keeping the old language).
$query['_sw'] = $lang;
$redirect = $path . '?' . http_build_query($query);
if ($fragment !== '') {
    $redirect .= '#' . $fragment;
}

// Never cache language redirects — browsers were reusing a cached 302 and skipping Set-Cookie.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Location: ' . $redirect, true, 303);
exit;
