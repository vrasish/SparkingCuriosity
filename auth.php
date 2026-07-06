<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cart-lib.php';
require_once __DIR__ . '/pdf-branding-lib.php';

/** Start session for reading only — releases lock immediately (prevents tab lockups). */
function stories_start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_start(['read_and_close' => true]);
}

/** Reopen session when this request needs to save cart/login data. */
function stories_open_writable_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_start();
}

stories_start_session();

/** @deprecated Use read_and_close via stories_start_session(); kept for cart-action pages. */
function release_session_lock(): void
{
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function current_user_id(): ?int
{
    $user = current_user();
    if (!$user) {
        return null;
    }

    $id = (int) ($user['user_id'] ?? 0);

    return $id > 0 ? $id : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin_user(): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function is_creator_user(): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === 'creator';
}

function ai_authoring_enabled(): bool
{
    return false;
}

function login_redirect_for_role(string $role): string
{
    if ($role === 'admin') {
        return app_url('admin-review.php');
    }
    if ($role === 'creator') {
        return app_url('creator-dashboard.php');
    }
    return app_url('index.php');
}

function redirect_to(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function login_user(array $row): void
{
    stories_open_writable_session();
    $_SESSION['user'] = [
        'user_id' => (int) $row['user_id'],
        'full_name' => (string) $row['full_name'],
        'email' => (string) $row['email'],
        'role' => (string) $row['role'],
    ];
}

function logout_user(): void
{
    stories_open_writable_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    session_destroy();
}

function safe_redirect_path(?string $path, string $default): string
{
    if ($path === null || $path === '') {
        return app_url($default);
    }
    if (str_contains($path, '://') || str_contains($path, '..')) {
        return app_url($default);
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }
    if (preg_match('#^[a-z0-9_-]+(\.[a-z0-9_-]+)?(\?[a-z0-9_=&%-]*)?(#[\w-]*)?$#i', $path)) {
        return app_url($path);
    }
    return app_url($default);
}

function require_creator_login(): void
{
    if (is_creator_user()) {
        return;
    }

    if (is_admin_user()) {
        redirect_to('admin-review.php');
    }

    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? app_base_path() . '/login');
    redirect_to('login.php?redirect=' . $redirect);
}

function require_admin_login(): void
{
    if (is_admin_user()) {
        return;
    }

    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? app_base_path() . '/admin-review');
    redirect_to('login.php?redirect=' . $redirect);
}

function require_login(string $redirectTarget = 'index.php'): void
{
    if (is_logged_in()) {
        return;
    }

    $redirect = urlencode(app_url($redirectTarget));
    redirect_to('login.php?redirect=' . $redirect);
}

/**
 * @return array{ok: bool, error: string}
 */
function ensure_users_schema(PDO $pdo): array
{
    static $checked = false;
    if ($checked) {
        return ['ok' => true, 'error' => ''];
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'reader',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $checked = true;
        return ['ok' => true, 'error' => ''];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Account system is not available right now.'];
    }
}

/**
 * @return array{ok: bool, error: string}
 */
function register_user(PDO $pdo, string $fullName, string $email, string $password, string $role = 'reader'): array
{
    $schema = ensure_users_schema($pdo);
    if (!$schema['ok']) {
        return $schema;
    }

    $fullName = trim($fullName);
    $email = trim(strtolower($email));
    $role = in_array($role, ['reader', 'creator'], true) ? $role : 'reader';

    if ($fullName === '' || $email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter your name, email, and password.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    try {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'An account with that email already exists. Try logging in instead.'];
        }

        $insert = $pdo->prepare('
            INSERT INTO users (full_name, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ');
        $insert->execute([
            $fullName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
        ]);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not create account. Please try again.'];
    }

    return ['ok' => true, 'error' => ''];
}

/** @deprecated Use register_user() */
function register_reader(PDO $pdo, string $fullName, string $email, string $password): array
{
    return register_user($pdo, $fullName, $email, $password, 'reader');
}

/**
 * @return array{ok: bool, error: string, role: string}
 */
function attempt_login(PDO $pdo, string $email, string $password): array
{
    $schema = ensure_users_schema($pdo);
    if (!$schema['ok']) {
        return ['ok' => false, 'error' => $schema['error'], 'role' => ''];
    }

    $email = trim(strtolower($email));
    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter your email and password.', 'role' => ''];
    }

    try {
        $stmt = $pdo->prepare('
            SELECT user_id, full_name, email, password_hash, role
            FROM users
            WHERE email = ?
            LIMIT 1
        ');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not sign in right now. Please try again.', 'role' => ''];
    }

    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Email or password is incorrect.', 'role' => ''];
    }

    $role = (string) $row['role'];
    if (!in_array($role, ['admin', 'creator', 'reader'], true)) {
        return ['ok' => false, 'error' => 'This account cannot sign in here.', 'role' => ''];
    }

    login_user($row);

    return ['ok' => true, 'error' => '', 'role' => $role];
}

/**
 * Upgrade a reader account to creator (no-op if already creator or admin).
 *
 * @return array{ok: bool, error: string, role: string}
 */
function upgrade_reader_to_creator(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'Invalid account.', 'role' => ''];
    }

    try {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $role = (string) ($stmt->fetchColumn() ?: '');
        if ($role === '' || !in_array($role, ['admin', 'creator', 'reader'], true)) {
            return ['ok' => false, 'error' => 'Account not found.', 'role' => ''];
        }
        if ($role !== 'reader') {
            return ['ok' => true, 'error' => '', 'role' => $role];
        }

        $update = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ? AND role = ?');
        $update->execute(['creator', $userId, 'reader']);
        if ($update->rowCount() > 0) {
            $role = 'creator';
            $user = current_user();
            if ($user !== null && (int) ($user['user_id'] ?? 0) === $userId) {
                stories_open_writable_session();
                $_SESSION['user']['role'] = 'creator';
            }
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not update account. Please try again.', 'role' => ''];
    }

    return ['ok' => true, 'error' => '', 'role' => $role];
}

function body_class(string $extra = ''): string
{
    return trim('public-page ' . $extra);
}

function render_site_logo_icon(): void
{
    $logoUrl = site_logo_url();
    echo '<img src="' . e($logoUrl) . '" alt="' . e(site_brand_name()) . '" class="site-logo-img" width="110" height="48">';
}

function render_site_footer(bool $compact = false): void
{
    $class = 'site-footer' . ($compact ? ' site-footer-compact' : '');
    echo '<footer class="' . $class . '">';
    echo '<p class="site-footer-copyright">' . e(site_copyright_text()) . '</p>';
    echo '</footer>';
    echo '<script src="' . e(asset_url('assets/site.js')) . '" defer></script>';
}

function render_site_header(string $variant = 'public', bool $homeNav = false): void
{
    $user = current_user();
    $isAdminNav = $variant === 'admin';
    $headerClass = 'site-header' . ($isAdminNav ? ' admin-header' : '') . ($homeNav ? ' site-header-home' : '');

    echo '<header class="' . $headerClass . '">';
    echo '<div class="container nav-container">';

    if (!$isAdminNav) {
        echo '<div class="site-brand">';
        echo '<a href="' . e(app_url('index.php')) . '" class="logo">';
        render_site_logo_icon();
        echo '</a>';
        echo '<p class="logo-tagline">Science stories kids actually want to read</p>';
        echo '</div>';
    } else {
        echo '<a href="' . e(app_url('index.php')) . '" class="logo">';
        render_site_logo_icon();
        echo '<span class="admin-badge">Admin</span>';
        echo '</a>';
    }

    echo '<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">';
    echo '<span class="nav-toggle-lines" aria-hidden="true"></span>';
    echo '</button>';

    echo '<nav class="nav-links" id="site-nav" aria-label="Main">';

    if ($isAdminNav) {
        echo '<a href="' . e(app_url('admin-stories.php')) . '">All Stories</a>';
        echo '<a href="' . e(app_url('admin-review.php')) . '">Story Review</a>';
        echo '<a href="' . e(app_url('admin-sales.php')) . '">Library Sales</a>';
        echo '<a href="' . e(app_url('reports-admin.php')) . '">Reports</a>';
        echo '<a href="' . e(app_url('index.php')) . '">Public Site</a>';
    } else {
        echo '<a href="' . e(app_url('index.php')) . '">Home</a>';
        echo '<a href="' . e(app_url('search.php')) . '">Search</a>';
        if ($user) {
            echo '<a href="' . e(app_url('my-library.php')) . '">My Library</a>';
        }
        if ($user && is_creator_user()) {
            if (ai_authoring_enabled()) {
                echo '<a href="' . e(app_url('ai-authoring.php')) . '">AI Authoring Tool</a>';
            }
            echo '<a href="' . e(app_url('upload-book.php')) . '">Upload PDF</a>';
            echo '<a href="' . e(app_url('creator-dashboard.php')) . '">Creator Dashboard</a>';
            echo '<a href="' . e(app_url('creator-sales.php')) . '">Creator Sales</a>';
        }
        if ($user && is_admin_user()) {
            echo '<a href="' . e(app_url('admin-review.php')) . '">Admin</a>';
        }
    }

    echo '<span class="nav-auth">';
    if (!$isAdminNav) {
        cart_bootstrap();
        $cartCount = cart_item_count();
        echo '<a href="' . e(app_url('cart-page.php')) . '" class="nav-cart" aria-label="Shopping cart">';
        echo '<span class="nav-cart-icon" aria-hidden="true">🛒</span>';
        if ($cartCount > 0) {
            echo '<span class="nav-cart-badge">' . (int) $cartCount . '</span>';
        }
        echo '</a>';
    }
    if ($user) {
        echo '<span class="nav-user-name">' . e($user['full_name']) . '</span>';
        echo '<a href="' . e(app_url('logout.php')) . '" class="btn btn-outline btn-sm nav-login-btn">Log out</a>';
    } else {
        echo '<a href="' . e(app_url('register.php')) . '" class="btn btn-outline btn-sm nav-login-btn">Sign up</a>';
        echo '<a href="' . e(app_url('login.php')) . '" class="btn btn-outline btn-sm nav-login-btn">Log in</a>';
    }
    echo '</span>';

    echo '</nav>';
    echo '</div>';
    echo '</header>';
}
