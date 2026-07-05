<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ' . login_redirect_for_role((string) (current_user()['role'] ?? 'reader')));
    exit;
}

$error = '';
$redirect = safe_redirect_path($_GET['redirect'] ?? null, 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = safe_redirect_path($_POST['redirect'] ?? null, 'index.php');
    $role = !empty($_POST['creator_account']) ? 'creator' : 'reader';
    $result = register_user(
        $pdo,
        $_POST['full_name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['password'] ?? '',
        $role
    );

    if ($result['ok']) {
        $login = attempt_login($pdo, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($login['ok']) {
            header('Location: ' . $redirect);
            exit;
        }
        header('Location: ' . app_url('login.php?redirect=' . rawurlencode($redirect)));
        exit;
    }

    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Create Account')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('auth-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact auth-main">
    <div class="auth-card">
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-lead">Enter your name, email, and password to get started.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('register.php')) ?>" class="auth-form">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <div class="form-group">
                <label for="full_name">Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required autocomplete="name"
                    value="<?= e($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email"
                    value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6"
                    autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="creator_account" value="1"<?= !empty($_POST['creator_account']) ? ' checked' : '' ?>>
                    I want to publish stories (creator account)
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="<?= e(app_url('login.php?redirect=' . rawurlencode($redirect))) ?>">Log in with email and password</a></p>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
