<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use SendGrid\Mail\Mail;

/** @return array{api_key: string, admin_email: string, from_email: string, from_name: string} */
function mail_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $keys = [
        'SENDGRID_API_KEY',
        'ADMIN_EMAIL',
        'FROM_EMAIL',
        'FROM_NAME',
    ];
    $values = array_fill_keys($keys, '');

    foreach ($keys as $key) {
        $env = getenv($key);
        if (is_string($env) && $env !== '') {
            $values[$key] = $env;
        }
    }

    $envFile = __DIR__ . '/.env';
    if (is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $val] = explode('=', $line, 2);
            $name = trim($name);
            if (!in_array($name, $keys, true)) {
                continue;
            }
            if ($values[$name] === '') {
                $values[$name] = trim($val, " \t\"'");
            }
        }
    }

    $cfg = [
        'api_key' => $values['SENDGRID_API_KEY'],
        'admin_email' => $values['ADMIN_EMAIL'] !== '' ? $values['ADMIN_EMAIL'] : 'scifables2026@gmail.com',
        'from_email' => $values['FROM_EMAIL'] !== '' ? $values['FROM_EMAIL'] : 'notifications@scifables.com',
        'from_name' => $values['FROM_NAME'] !== '' ? $values['FROM_NAME'] : 'SciFables',
    ];

    return $cfg;
}

function mail_is_configured(): bool
{
    return mail_config()['api_key'] !== '';
}

function mail_send(string $to, string $subject, string $bodyPlain, ?string $replyTo = null, ?string $toName = null): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_log_error('Invalid recipient email address.');
        return false;
    }

    $cfg = mail_config();
    if ($cfg['api_key'] === '') {
        mail_log_error('SendGrid API key is not configured.');
        return false;
    }

    try {
        $email = new Mail();
        $email->setFrom($cfg['from_email'], $cfg['from_name']);
        $email->setSubject($subject);
        $email->addTo($to, $toName ?? '');
        $email->addContent('text/plain', $bodyPlain);

        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $email->setReplyTo($replyTo);
        }

        $sendgrid = new \SendGrid($cfg['api_key']);
        $response = $sendgrid->send($email);
        $statusCode = $response->statusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return true;
        }

        mail_log_error('SendGrid HTTP ' . $statusCode . ': ' . mail_sanitize_log((string) $response->body()));
        return false;
    } catch (Throwable $ex) {
        mail_log_error('SendGrid send failed: ' . mail_sanitize_log($ex->getMessage()));
        return false;
    }
}

function mail_log_error(string $message): void
{
    $cfg = mail_config();
    $safe = mail_sanitize_log($message);
    if ($cfg['api_key'] !== '') {
        $safe = str_replace($cfg['api_key'], '[REDACTED]', $safe);
    }

    error_log('[SciFables mail] ' . $safe);
}

function mail_sanitize_log(string $message): string
{
    return preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $message) ?? $message;
}

function mail_first_name(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return 'Reader';
    }

    $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === []) {
        return 'Reader';
    }

    return (string) $parts[0];
}
