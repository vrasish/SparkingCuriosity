<?php

declare(strict_types=1);

require_once __DIR__ . '/mail-lib.php';

/** @return list<string> */
function topic_request_statuses(): array
{
    return ['new', 'in_progress', 'completed'];
}

function topic_request_status_label(string $status): string
{
    return match ($status) {
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        default => 'New',
    };
}

function ensure_topic_requests_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $readyFlag = __DIR__ . '/data/.topic-requests-schema-ready';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS topic_requests (
            request_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            science_topic VARCHAR(255) NOT NULL,
            age_group VARCHAR(50) NULL,
            additional_details TEXT NULL,
            status ENUM('new', 'in_progress', 'completed') NOT NULL DEFAULT 'new',
            notified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_topic_requests_user (user_id),
            INDEX idx_topic_requests_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'topic_requests'
          AND COLUMN_NAME = 'completion_email_sent_at'
    ");
    if ($stmt->fetchColumn()) {
        $notifiedStmt = $pdo->query("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'topic_requests'
              AND COLUMN_NAME = 'notified_at'
        ");
        if (!$notifiedStmt->fetchColumn()) {
            $pdo->exec('ALTER TABLE topic_requests ADD COLUMN notified_at DATETIME NULL AFTER status');
        }
        $pdo->exec('
            UPDATE topic_requests
            SET notified_at = completion_email_sent_at
            WHERE notified_at IS NULL AND completion_email_sent_at IS NOT NULL
        ');
        $pdo->exec('ALTER TABLE topic_requests DROP COLUMN completion_email_sent_at');
    }

    if (!is_dir(dirname($readyFlag))) {
        mkdir(dirname($readyFlag), 0775, true);
    }
    if (!is_file($readyFlag)) {
        touch($readyFlag);
    }

    $checked = true;
}

/** @return array{user_id: int, full_name: string, email: string}|null */
function topic_request_lookup_user(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT user_id, full_name, email FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'user_id' => (int) $row['user_id'],
        'full_name' => (string) $row['full_name'],
        'email' => (string) $row['email'],
    ];
}

/** @return array{ok: bool, request_id?: int, error: string} */
function topic_request_create(
    PDO $pdo,
    int $userId,
    string $userEmail,
    string $userName,
    string $topic,
    string $ageGroup,
    string $details
): array {
    ensure_topic_requests_schema($pdo);

    $topic = trim($topic);
    if ($topic === '') {
        return ['ok' => false, 'error' => 'Please enter a science topic.'];
    }
    if (mb_strlen($topic) > 255) {
        return ['ok' => false, 'error' => 'Science topic must be 255 characters or fewer.'];
    }

    $user = topic_request_lookup_user($pdo, $userId);
    if ($user !== null) {
        $userEmail = $user['email'];
        $userName = $user['full_name'];
    }

    $userEmail = trim($userEmail);
    $userName = trim($userName);
    if ($userId <= 0 || $userEmail === '') {
        return ['ok' => false, 'error' => 'Your account could not be verified. Please log in again.'];
    }

    $ageGroup = trim($ageGroup);
    $details = trim($details);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO topic_requests (user_id, user_email, user_name, science_topic, age_group, additional_details, status)
            VALUES (?, ?, ?, ?, ?, ?, 'new')
        ");
        $stmt->execute([
            $userId,
            $userEmail,
            $userName,
            $topic,
            $ageGroup !== '' ? $ageGroup : null,
            $details !== '' ? $details : null,
        ]);

        $requestId = (int) $pdo->lastInsertId();
        topic_request_log_submission($requestId, $userId, $userEmail, $userName, $topic, $ageGroup, $details);

        return ['ok' => true, 'request_id' => $requestId, 'error' => ''];
    } catch (PDOException $ex) {
        error_log('topic_request_create failed: ' . $ex->getMessage());
        return ['ok' => false, 'error' => 'Could not save your request. Please try again.'];
    }
}

function topic_request_log_submission(
    int $requestId,
    int $userId,
    string $userEmail,
    string $userName,
    string $topic,
    string $ageGroup,
    string $details
): void {
    $logPath = __DIR__ . '/data/topic-request-submissions.jsonl';
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $entry = json_encode([
        'request_id' => $requestId,
        'user_id' => $userId,
        'user_email' => $userEmail,
        'user_name' => $userName,
        'science_topic' => $topic,
        'age_group' => $ageGroup,
        'additional_details' => $details,
        'created_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($entry !== false) {
        file_put_contents($logPath, $entry . "\n", FILE_APPEND | LOCK_EX);
    }
}

/** @return array<string, mixed>|null */
function topic_request_get(PDO $pdo, int $requestId): ?array
{
    ensure_topic_requests_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM topic_requests WHERE request_id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function topic_request_list_all(PDO $pdo): array
{
    ensure_topic_requests_schema($pdo);

    $stmt = $pdo->query('
        SELECT *
        FROM topic_requests
        ORDER BY created_at DESC, request_id DESC
    ');

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @param array<string, mixed> $request */
function topic_request_admin_email_body(array $request, string $userName, string $userEmail): string
{
    $topic = (string) ($request['science_topic'] ?? '');
    $ageGroup = trim((string) ($request['age_group'] ?? ''));
    $details = trim((string) ($request['additional_details'] ?? ''));

    return implode("\n", [
        'New Topic Request',
        '',
        'Topic: ' . $topic,
        'Age Group: ' . ($ageGroup !== '' ? $ageGroup : '—'),
        'Additional Details: ' . ($details !== '' ? $details : '—'),
        '',
        'Requested By:',
        'Name: ' . $userName,
        'Email: ' . $userEmail,
    ]);
}

/** @param array<string, mixed> $request */
function topic_request_completion_email_body(array $request, string $firstName): string
{
    $topic = (string) ($request['science_topic'] ?? '');

    return implode("\n", [
        'Hello ' . $firstName . ',',
        '',
        'Great news! Your requested topic, "' . $topic . '," has been completed and added to the SciFables library.',
        '',
        'Visit SciFables to read the new story:',
        'https://www.scifables.com',
        '',
        'Thank you for helping us grow our science story library!',
        '',
        'SciFables',
    ]);
}

/** @param array<string, mixed> $request */
function topic_request_send_admin_notification(PDO $pdo, array $request): bool
{
    $userId = (int) ($request['user_id'] ?? 0);
    $user = topic_request_lookup_user($pdo, $userId);

    $userName = $user['full_name'] ?? (string) ($request['user_name'] ?? '');
    $userEmail = $user['email'] ?? (string) ($request['user_email'] ?? '');
    $topic = trim((string) ($request['science_topic'] ?? ''));

    if ($topic === '') {
        return false;
    }

    $cfg = mail_config();
    $subject = 'New SciFables Topic Request: ' . $topic;
    $body = topic_request_admin_email_body($request, $userName, $userEmail);

    return mail_send($cfg['admin_email'], $subject, $body, $userEmail !== '' ? $userEmail : null);
}

/** @param array<string, mixed> $request */
function topic_request_send_completion_notification(PDO $pdo, array $request): bool
{
    if (!empty($request['notified_at'])) {
        return true;
    }

    $userId = (int) ($request['user_id'] ?? 0);
    $user = topic_request_lookup_user($pdo, $userId);
    if ($user === null) {
        mail_log_error('Completion email skipped: user not found for request #' . (int) ($request['request_id'] ?? 0));
        return false;
    }

    $topic = trim((string) ($request['science_topic'] ?? ''));
    if ($topic === '') {
        return false;
    }

    $firstName = mail_first_name($user['full_name']);
    $subject = 'Your SciFables Topic Is Ready!';
    $body = topic_request_completion_email_body($request, $firstName);

    return mail_send($user['email'], $subject, $body, null, $user['full_name']);
}

function topic_request_mark_notified(PDO $pdo, int $requestId): void
{
    $stmt = $pdo->prepare('UPDATE topic_requests SET notified_at = NOW() WHERE request_id = ? AND notified_at IS NULL');
    $stmt->execute([$requestId]);
}

/** @return array{ok: bool, error: string} */
function topic_request_update_status(PDO $pdo, int $requestId, string $status): array
{
    ensure_topic_requests_schema($pdo);

    $status = strtolower(trim($status));
    if (!in_array($status, topic_request_statuses(), true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }

    $request = topic_request_get($pdo, $requestId);
    if ($request === null) {
        return ['ok' => false, 'error' => 'Request not found.'];
    }

    $previousStatus = (string) ($request['status'] ?? 'new');
    $alreadyNotified = !empty($request['notified_at']);

    try {
        $stmt = $pdo->prepare('UPDATE topic_requests SET status = ? WHERE request_id = ?');
        $stmt->execute([$status, $requestId]);
    } catch (PDOException $ex) {
        error_log('topic_request_update_status failed: ' . $ex->getMessage());
        return ['ok' => false, 'error' => 'Could not update status. Please try again.'];
    }

    $request['status'] = $status;
    $message = 'Status updated to ' . topic_request_status_label($status) . '.';

    $shouldNotifyCompletion = $status === 'completed'
        && in_array($previousStatus, ['new', 'in_progress'], true)
        && !$alreadyNotified;

    if ($shouldNotifyCompletion) {
        if (topic_request_send_completion_notification($pdo, $request)) {
            topic_request_mark_notified($pdo, $requestId);
            $message .= ' Completion email sent.';
        } else {
            $message .= ' Status saved, but the completion email could not be sent. Check server logs.';
        }
    }

    return ['ok' => true, 'error' => $message];
}
