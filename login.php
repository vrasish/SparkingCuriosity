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
        $roleHome = login_redirect_for_role($role);
        $posted = (string) ($_POST['redirect'] ?? '');
        // Treat home as "no destination" so teachers with a class go to My Classroom.
        $isHome = $posted === ''
            || preg_match('#^(index\.php|/|/?index\.php)$#i', $posted)
            || $posted === app_url('index.php');
        $redirect = $isHome ? $roleHome : safe_redirect_path($posted, $roleHome);
        header('Location: ' . $redirect);
        exit;
    }
    $error = $result['error'];
    $redirect = safe_redirect_path($_POST['redirect'] ?? null, 'index.php');
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title(t('auth.login_title'))) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('auth-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact auth-main">
    <div class="auth-card">
        <h1 class="auth-title"><?= e(t('auth.login_title')) ?></h1>
        <p class="auth-lead"><?= e(t('auth.login_lead')) ?></p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e(t($error)) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('login.php')) ?>" class="auth-form">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <div class="form-group">
                <label for="email"><?= e(t('auth.email')) ?></label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email"
                    value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password"><?= e(t('auth.password')) ?></label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(t('auth.login_button')) ?></button>
        </form>
        <p class="auth-switch"><?= e(t('auth.need_account')) ?> <a href="<?= e(app_url('register.php?redirect=' . rawurlencode($redirect))) ?>"><?= e(t('auth.create_account_link')) ?></a></p>
    </div>
</main>
<?php render_site_footer(); ?>
<script>
(function () {
    var form = document.querySelector('.auth-form');
    if (form && window.posthog) {
        form.addEventListener('submit', function () {
            posthog.capture('user_logged_in');
        });
    }
    <?php if (isset($_GET['logout'])): ?>
    if (window.posthog) {
        posthog.reset();
    }
    <?php endif; ?>
}());
</script>
</body>
</html>
