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
        'new' => 'New',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function ensure_topic_requests_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $readyFlag = __DIR__ . '/data/.topic-requests-schema-ready';
    if (is_file($readyFlag)) {
        $checked = true;
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS topic_requests (
            request_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            science_topic VARCHAR(255) NOT NULL,
            age_group VARCHAR(50) NULL,
            additional_details TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            completion_email_sent_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_topic_requests_status (status),
            INDEX idx_topic_requests_user (user_id),
            INDEX idx_topic_requests_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!is_dir(dirname($readyFlag))) {
        mkdir(dirname($readyFlag), 0775, true);
    }
    file_put_contents($readyFlag, date('c') . "\n");

    $checked = true;
}

/** @return array{ok: bool, error: string, request_id: int} */
function topic_request_create(
    PDO $pdo,
    int $userId,
    string $userEmail,
    string $userName,
    string $scienceTopic,
    string $ageGroup,
    string $additionalDetails
): array {
    ensure_topic_requests_schema($pdo);

    $scienceTopic = trim($scienceTopic);
    $ageGroup = trim($ageGroup);
    $additionalDetails = trim($additionalDetails);
    $userEmail = trim(strtolower($userEmail));
    $userName = trim($userName);

    if ($userId <= 0 || $userEmail === '' || $userName === '' || $scienceTopic === '') {
        return ['ok' => false, 'error' => 'Please enter a science topic.', 'request_id' => 0];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO topic_requests (
                user_id, user_email, user_name, science_topic, age_group, additional_details, status
            ) VALUES (
                :user_id, :user_email, :user_name, :science_topic, :age_group, :additional_details, 'new'
            )
        ");
        $stmt->execute([
            'user_id' => $userId,
            'user_email' => $userEmail,
            'user_name' => $userName,
            'science_topic' => $scienceTopic,
            'age_group' => $ageGroup !== '' ? $ageGroup : null,
            'additional_details' => $additionalDetails !== '' ? $additionalDetails : null,
        ]);

        $requestId = (int) $pdo->lastInsertId();
        topic_request_log_submission([
            'request_id' => $requestId,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'user_name' => $userName,
            'science_topic' => $scienceTopic,
            'age_group' => $ageGroup,
            'additional_details' => $additionalDetails,
        ]);

        return ['ok' => true, 'error' => '', 'request_id' => $requestId];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not save your request. Please try again.', 'request_id' => 0];
    }
}

/** @param array<string, mixed> $request */
function topic_request_send_admin_notification(array $request): bool
{
    $cfg = mail_config();
    $to = $cfg['notify_to'];
    $topic = trim((string) ($request['science_topic'] ?? ''));
    $subject = 'SciFables topic request: ' . $topic;

    $body = "New SciFables topic request\n\n"
        . 'Topic: ' . $topic . "\n"
        . 'Age group: ' . (trim((string) ($request['age_group'] ?? '')) !== '' ? $request['age_group'] : '(not provided)') . "\n"
        . 'Additional details: ' . (trim((string) ($request['additional_details'] ?? '')) !== '' ? $request['additional_details'] : '(none)') . "\n\n"
        . 'User name: ' . ($request['user_name'] ?? '') . "\n"
        . 'User email: ' . ($request['user_email'] ?? '') . "\n"
        . 'Submitted: ' . date('Y-m-d H:i:s T') . "\n";

    $replyTo = trim((string) ($request['user_email'] ?? ''));
    return mail_send($to, $subject, $body, $replyTo !== '' ? $replyTo : null);
}

/** @param array<string, mixed> $request */
function topic_request_send_completion_email(array $request): bool
{
    $to = trim((string) ($request['user_email'] ?? ''));
    if ($to === '') {
        return false;
    }

    $firstName = mail_first_name((string) ($request['user_name'] ?? ''));
    $topic = trim((string) ($request['science_topic'] ?? ''));
    $subject = 'Your SciFables Topic Is Ready!';
    $siteUrl = function_exists('app_url') ? app_url('index.php') : 'https://scifables.com/';

    $body = "Hello {$firstName},\n\n"
        . "Great news! Your requested topic, \"{$topic},\" has been completed and added to the SciFables library.\n\n"
        . "You can now visit SciFables to read the new story.\n"
        . $siteUrl . "\n\n"
        . "Thank you for helping us grow our science story library!\n\n"
        . "SciFables\n";

    return mail_send($to, $subject, $body);
}

/** @return list<array<string, mixed>> */
function topic_request_list_all(PDO $pdo): array
{
    ensure_topic_requests_schema($pdo);

    $stmt = $pdo->query("
        SELECT
            request_id,
            user_id,
            user_email,
            user_name,
            science_topic,
            age_group,
            additional_details,
            status,
            completion_email_sent_at,
            created_at,
            updated_at
        FROM topic_requests
        ORDER BY created_at DESC
    ");

    return $stmt->fetchAll();
}

/** @return array<string, mixed>|null */
function topic_request_get(PDO $pdo, int $requestId): ?array
{
    ensure_topic_requests_schema($pdo);

    $stmt = $pdo->prepare('
        SELECT
            request_id,
            user_id,
            user_email,
            user_name,
            science_topic,
            age_group,
            additional_details,
            status,
            completion_email_sent_at,
            created_at,
            updated_at
        FROM topic_requests
        WHERE request_id = ?
        LIMIT 1
    ');
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return array{ok: bool, error: string, email_sent: bool} */
function topic_request_update_status(PDO $pdo, int $requestId, string $status): array
{
    ensure_topic_requests_schema($pdo);

    if (!in_array($status, topic_request_statuses(), true)) {
        return ['ok' => false, 'error' => 'Invalid status.', 'email_sent' => false];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            SELECT
                request_id,
                user_id,
                user_email,
                user_name,
                science_topic,
                age_group,
                additional_details,
                status,
                completion_email_sent_at
            FROM topic_requests
            WHERE request_id = ?
            FOR UPDATE
        ');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Request not found.', 'email_sent' => false];
        }

        $emailSent = false;
        $completionSentAt = $request['completion_email_sent_at'] ?? null;

        if ($status === 'completed' && $completionSentAt === null) {
            if (topic_request_send_completion_email($request)) {
                $emailSent = true;
                $completionSentAt = date('Y-m-d H:i:s');
            }
        }

        $update = $pdo->prepare('
            UPDATE topic_requests
            SET status = :status,
                completion_email_sent_at = :completion_email_sent_at
            WHERE request_id = :request_id
        ');
        $update->execute([
            'status' => $status,
            'completion_email_sent_at' => $completionSentAt,
            'request_id' => $requestId,
        ]);

        $pdo->commit();

        $message = 'Request updated.';
        if ($status === 'completed' && !$emailSent && ($request['completion_email_sent_at'] ?? null) === null) {
            $message = 'Request marked completed, but the notification email could not be sent. Check mail settings.';
        } elseif ($emailSent) {
            $message = 'Request marked completed and the user was emailed.';
        }

        return ['ok' => true, 'error' => $message, 'email_sent' => $emailSent];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return ['ok' => false, 'error' => 'Could not update request.', 'email_sent' => false];
    }
}

/** @param array<string, mixed> $payload */
function topic_request_log_submission(array $payload): void
{
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $payload['logged_at'] = date('c');
    @file_put_contents(
        $logDir . '/topic-request-submissions.jsonl',
        json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}
