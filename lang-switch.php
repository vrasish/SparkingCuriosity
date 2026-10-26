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

header('Location: ' . $redirect, true, 302);
exit;
