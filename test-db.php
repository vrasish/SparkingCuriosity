<?php
header('Content-Type: text/html; charset=utf-8');

echo '<h1>Database connection test</h1>';

if (!extension_loaded('pdo')) {
    die('<p><strong>Error:</strong> PDO extension is not enabled in PHP.</p>');
}

if (!extension_loaded('pdo_mysql')) {
    die('<p><strong>Error:</strong> pdo_mysql extension is not enabled in PHP. Enable it in XAMPP php.ini.</p>');
}

echo '<p>PDO extension: enabled</p>';
echo '<p>pdo_mysql extension: enabled</p>';

$configPath = __DIR__ . '/db.config.php';
if (!is_readable($configPath)) {
    die(
        '<p><strong>Database connection failed:</strong> db.config.php is missing. '
        . 'Copy db.config.example.php to db.config.php and set your password.</p>'
    );
}

$dbConfig = require $configPath;
$dbHost = (string) ($dbConfig['host'] ?? '127.0.0.1');
$dbPort = (string) ($dbConfig['port'] ?? '3306');
$dbName = (string) ($dbConfig['dbname'] ?? 'myappdb');
$dbUser = (string) ($dbConfig['user'] ?? 'dbeaver_user');
$dbPassword = $dbConfig['password'] ?? '';

$placeholders = ['PUT_MY_PASSWORD_HERE', 'YourNewStrongPasswordHere', 'MY_REAL_PASSWORD'];
if ($dbPassword === '' || in_array($dbPassword, $placeholders, true)) {
    die(
        '<p><strong>Database connection failed:</strong> MySQL password not configured in <code>db.config.php</code>.</p>'
        . '<p>Open that file and paste the <strong>exact</strong> password from your DBeaver connection (not a placeholder).</p>'
    );
}

echo '<p>PHP is trying: <code>' . htmlspecialchars($dbHost . ':' . $dbPort, ENT_QUOTES, 'UTF-8') . '</code> → database <code>' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</code></p>';

$probePorts = array_unique([$dbPort, '3307', '3306', '3308', '33060']);
echo '<h2>Local port check</h2><ul>';
$openPorts = [];
foreach ($probePorts as $port) {
    $open = @fsockopen($dbHost, (int) $port, $errno, $errstr, 1);
    if ($open) {
        fclose($open);
        $openPorts[] = $port;
        echo '<li><strong>Port ' . htmlspecialchars($port, ENT_QUOTES, 'UTF-8') . ': OPEN</strong> — something is listening</li>';
    } else {
        echo '<li>Port ' . htmlspecialchars($port, ENT_QUOTES, 'UTF-8') . ': closed</li>';
    }
}
echo '</ul>';

if (!in_array($dbPort, $openPorts, true)) {
    echo '<p style="color:#b91c1c"><strong>Problem:</strong> Your app uses port <code>' . htmlspecialchars($dbPort, ENT_QUOTES, 'UTF-8')
        . '</code> but that port is not open. DBeaver’s tunnel often uses a <em>different</em> port.</p>';
    if ($openPorts !== []) {
        echo '<p>If DBeaver is connected, try setting <code>\'port\' => \'' . htmlspecialchars($openPorts[0], ENT_QUOTES, 'UTF-8')
            . '\'</code> in <code>db.config.php</code> and reload this page.</p>';
    }
    echo '<p><strong>Or</strong> install auto-start (run once in Terminal from the stories folder):</p>';
    echo '<pre>./install-db-tunnel-autostart.sh</pre>';
    echo '<p>That starts the tunnel on login so you do not need to run SSH manually.</p>';
}

echo '<h2>MySQL login test</h2>';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $count = $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
    echo '<p><strong>Database connected successfully.</strong> Books in library: ' . (int) $count . '</p>';
    echo '<p><a href="index.php">Open website</a></p>';
} catch (PDOException $e) {
    echo '<p><strong>Database connection failed:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    if (str_contains($e->getMessage(), '1045')) {
        echo '<p>Password rejected. Copy the exact password from DBeaver into <code>db.config.php</code>.</p>';
    }
}
