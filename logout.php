<?php
require_once __DIR__ . '/auth.php';

$wasAdmin = is_admin_user();
logout_user();

header('Location: ' . app_url('login.php?logout=1'));
exit;
