<?php

declare(strict_types=1);

/**
 * Send a test email through SendGrid to ADMIN_EMAIL.
 *
 *   php tools/test-sendgrid-email.php
 *
 * Disable or delete this script after verifying delivery.
 */

require_once dirname(__DIR__) . '/mail-lib.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

if (!mail_is_configured()) {
    fwrite(STDERR, "SendGrid is not configured. Set SENDGRID_API_KEY in .env\n");
    exit(1);
}

$cfg = mail_config();
$subject = 'SciFables SendGrid Test';
$body = implode("\n", [
    'This is a test email from the SciFables SendGrid integration.',
    '',
    'Sent at: ' . date('c'),
    'From: ' . $cfg['from_email'],
    'To: ' . $cfg['admin_email'],
]);

if (mail_send($cfg['admin_email'], $subject, $body)) {
    echo "Test email sent to {$cfg['admin_email']}\n";
    exit(0);
}

fwrite(STDERR, "Test email failed. Check PHP error logs for details.\n");
exit(1);
