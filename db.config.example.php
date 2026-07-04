<?php
/**
 * Copy to db.config.php and set your real MySQL password.
 * db.config.php is gitignored.
 *
 * SSH tunnel (port 3307) — one-time auto-start on login:
 *   ./install-db-tunnel-autostart.sh
 *
 * Manual tunnel (keep Terminal open):
 *   ./start-db-tunnel.sh
 */
return [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'myappdb',
    'user' => 'dbeaver_user',
    'password' => 'PUT_MY_PASSWORD_HERE',
];
