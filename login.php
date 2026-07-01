<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ' . login_redirect_for_role((string) (current_user()['role'] ?? '')));
    exit;
}

$error = '';
$redirect = safe_redirect_path($_GET['redirect'] ?? null, 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = attempt_login($pdo, $_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        $role = $result['role'];
        if (!empty($_POST['creator_account']) && $role === 'reader') {
            $user = current_user();
            if ($user !== null) {
                $upgrade = upgrade_reader_to_creator($pdo, (int) $user['user_id']);
                if ($upgrade['ok']) {
                    $role = $upgrade['role'];
                }
            }
        }
        $redirect = safe_redirect_path($_POST['redirect'] ?? null, login_redirect_for_role($role));
        header('Location: ' . $redirect);
        exit;
    }
    $error = $result['error'];
    $redirect = safe_redirect_path($_POST['redirect'] ?? null, 'index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('auth-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <?php render_page_header('Log in', 'Sign in with your email and password.'); ?>

    <div class="page-section">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="form-panel login-panel">
            <form method="post" action="<?= e(app_url('login.php')) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email"
                        value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="creator_account" value="1"<?= !empty($_POST['creator_account']) ? ' checked' : '' ?>>
                        I want to publish stories (creator account)
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Log in</button>
            </form>
            <p class="mt-2">Need an account? <a href="<?= e(app_url('register.php?redirect=' . rawurlencode($redirect))) ?>">Create one with your name, email, and password</a></p>
        </div>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
