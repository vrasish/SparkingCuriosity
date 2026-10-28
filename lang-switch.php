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
if (is_array($parts)) {
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        unset($query['story_lang'], $query['lang']);
    }
    $path = (string) ($parts['path'] ?? '/');
    $redirect = $path;
    if ($query !== []) {
        $redirect .= '?' . http_build_query($query);
    }
    if (!empty($parts['fragment'])) {
        $redirect .= '#' . $parts['fragment'];
    }
}

header('Location: ' . $redirect, true, 302);
exit;
