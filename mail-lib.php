<?php

declare(strict_types=1);

/** @return array<string, string> */
function mail_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $keys = [
        'MAIL_SMTP_HOST',
        'MAIL_SMTP_PORT',
        'MAIL_SMTP_USER',
        'MAIL_SMTP_PASSWORD',
        'MAIL_FROM',
        'MAIL_FROM_NAME',
        'MAIL_NOTIFY_TO',
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
            if (!in_array($name, $keys, true) || $values[$name] !== '') {
                continue;
            }
            $values[$name] = trim($val, " \t\"'");
        }
    }

    $cfg = [
        'smtp_host' => $values['MAIL_SMTP_HOST'],
        'smtp_port' => $values['MAIL_SMTP_PORT'] !== '' ? $values['MAIL_SMTP_PORT'] : '587',
        'smtp_user' => $values['MAIL_SMTP_USER'],
        'smtp_password' => $values['MAIL_SMTP_PASSWORD'],
        'from' => $values['MAIL_FROM'] !== '' ? $values['MAIL_FROM'] : 'scifables2026@gmail.com',
        'from_name' => $values['MAIL_FROM_NAME'] !== '' ? $values['MAIL_FROM_NAME'] : 'SciFables',
        'notify_to' => $values['MAIL_NOTIFY_TO'] !== '' ? $values['MAIL_NOTIFY_TO'] : 'scifables2026@gmail.com',
    ];

    return $cfg;
}

function mail_send(string $to, string $subject, string $bodyPlain, ?string $replyTo = null): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $cfg = mail_config();
    if ($cfg['smtp_host'] !== '' && $cfg['smtp_user'] !== '' && $cfg['smtp_password'] !== '') {
        return mail_send_smtp($to, $subject, $bodyPlain, $replyTo, $cfg);
    }

    return mail_send_php_mail($to, $subject, $bodyPlain, $replyTo, $cfg);
}

/** @param array<string, string> $cfg */
function mail_send_php_mail(string $to, string $subject, string $bodyPlain, ?string $replyTo, array $cfg): bool
{
    $from = $cfg['from'];
    $fromName = $cfg['from_name'];
    $headers = [
        'From: ' . mail_format_address($from, $fromName),
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
        'X-Mailer: SciFables',
    ];
    if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return @mail($to, mail_encode_subject($subject), $bodyPlain, implode("\r\n", $headers));
}

/** @param array<string, string> $cfg */
function mail_send_smtp(string $to, string $subject, string $bodyPlain, ?string $replyTo, array $cfg): bool
{
    $host = $cfg['smtp_host'];
    $port = (int) $cfg['smtp_port'];
    $user = $cfg['smtp_user'];
    $pass = $cfg['smtp_password'];
    $from = $cfg['from'];
    $fromName = $cfg['from_name'];

    $socket = @stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errno,
        $errstr,
        20
    );
    if ($socket === false) {
        error_log('SMTP connect failed: ' . $errstr);
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        if (!mail_smtp_expect($socket, [220])) {
            throw new RuntimeException('SMTP greeting failed');
        }
        mail_smtp_cmd($socket, 'EHLO scifables.local');
        if (!mail_smtp_expect($socket, [250])) {
            throw new RuntimeException('SMTP EHLO failed');
        }

        if (!mail_smtp_starttls($socket, $host)) {
            throw new RuntimeException('SMTP STARTTLS failed');
        }

        mail_smtp_cmd($socket, 'EHLO scifables.local');
        if (!mail_smtp_expect($socket, [250])) {
            throw new RuntimeException('SMTP EHLO after TLS failed');
        }

        mail_smtp_cmd($socket, 'AUTH LOGIN');
        if (!mail_smtp_expect($socket, [334])) {
            throw new RuntimeException('SMTP AUTH failed');
        }
        mail_smtp_cmd($socket, base64_encode($user));
        if (!mail_smtp_expect($socket, [334])) {
            throw new RuntimeException('SMTP username rejected');
        }
        mail_smtp_cmd($socket, base64_encode($pass));
        if (!mail_smtp_expect($socket, [235])) {
            throw new RuntimeException('SMTP password rejected');
        }

        mail_smtp_cmd($socket, 'MAIL FROM:<' . $from . '>');
        if (!mail_smtp_expect($socket, [250])) {
            throw new RuntimeException('SMTP MAIL FROM failed');
        }
        mail_smtp_cmd($socket, 'RCPT TO:<' . $to . '>');
        if (!mail_smtp_expect($socket, [250, 251])) {
            throw new RuntimeException('SMTP RCPT TO failed');
        }
        mail_smtp_cmd($socket, 'DATA');
        if (!mail_smtp_expect($socket, [354])) {
            throw new RuntimeException('SMTP DATA failed');
        }

        $message = 'From: ' . mail_format_address($from, $fromName) . "\r\n";
        $message .= 'To: <' . $to . ">\r\n";
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $message .= 'Reply-To: ' . $replyTo . "\r\n";
        }
        $message .= 'Subject: ' . mail_encode_subject($subject) . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], str_replace("\n", "\r\n", $bodyPlain));
        $message .= "\r\n.\r\n";

        fwrite($socket, $message);
        if (!mail_smtp_expect($socket, [250])) {
            throw new RuntimeException('SMTP message rejected');
        }

        mail_smtp_cmd($socket, 'QUIT');
        mail_smtp_expect($socket, [221]);
        fclose($socket);

        return true;
    } catch (Throwable $ex) {
        error_log($ex->getMessage());
        @fclose($socket);
        return false;
    }
}

function mail_format_address(string $email, string $name): string
{
    $email = trim($email);
    $name = trim(str_replace(["\r", "\n"], '', $name));
    if ($name === '') {
        return $email;
    }

    return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
}

function mail_encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

/** @param resource $socket */
function mail_smtp_cmd($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

/** @param resource $socket */
/** @param list<int> $codes */
function mail_smtp_expect($socket, array $codes): bool
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr(trim($response), 0, 3);
    return in_array($code, $codes, true);
}

/** @param resource $socket */
function mail_smtp_starttls($socket, string $host): bool
{
    mail_smtp_cmd($socket, 'STARTTLS');
    if (!mail_smtp_expect($socket, [220])) {
        return false;
    }

    $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    return $crypto === true;
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
