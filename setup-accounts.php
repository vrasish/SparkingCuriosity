<?php
/**
 * One-time setup: creates demo creator and admin accounts when the users table is empty.
 * Run in browser on localhost or: php setup-accounts.php
 */
require_once __DIR__ . '/auth.php';

$isCli = PHP_SAPI === 'cli';
$isLocalWeb = !$isCli
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

if (!$isCli && !$isLocalWeb) {
    http_response_code(403);
    die('Setup is only allowed from localhost.');
}

$accounts = [
    [
        'full_name' => 'Demo Creator',
        'email' => 'creator@sparkingcuriosity.test',
        'password' => 'creator123',
        'role' => 'creator',
    ],
    [
        'full_name' => 'Site Admin',
        'email' => 'admin@sparkingcuriosity.test',
        'password' => 'admin123',
        'role' => 'admin',
    ],
];

$messages = [];

try {
    ensure_users_schema($pdo);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        $messages[] = 'Users already exist (' . $count . '). No accounts were added.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO users (full_name, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ');
        foreach ($accounts as $account) {
            $stmt->execute([
                $account['full_name'],
                $account['email'],
                password_hash($account['password'], PASSWORD_DEFAULT),
                $account['role'],
            ]);
            $messages[] = 'Created ' . $account['role'] . ': ' . $account['email'] . ' / ' . $account['password'];
        }
    }
} catch (PDOException $ex) {
    $messages[] = 'Error: ' . $ex->getMessage();
}

if ($isCli) {
    foreach ($messages as $line) {
        echo $line . PHP_EOL;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Accounts | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>
<main class="container page-main">
    <?php render_page_header('Account Setup', 'Demo logins for local development.'); ?>
    <div class="page-section">
        <div class="form-panel">
            <ul class="setup-list">
                <?php foreach ($messages as $line): ?>
                    <li><?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
            <p><a href="<?= e(app_url('login.php')) ?>" class="btn btn-primary">Log in</a></p>
        </div>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
