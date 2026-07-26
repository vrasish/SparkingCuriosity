<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/topic-requests-lib.php';

require_admin_login();

$flash = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['status'])) {
    $requestId = (int) $_POST['request_id'];
    $status = trim((string) $_POST['status']);

    $result = topic_request_update_status($pdo, $requestId, $status);
    if ($result['ok']) {
        $flash = $result['error'];
    } else {
        $flashError = $result['error'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action']) && $_POST['action'] === 'resend_notification') {
    $requestId = (int) $_POST['request_id'];
    $request = topic_request_get($pdo, $requestId);
    if ($request === null) {
        $flashError = 'Request not found.';
    } elseif (!mail_is_configured()) {
        $flashError = 'SendGrid is not configured. Add SENDGRID_API_KEY to .env on the server.';
    } elseif (topic_request_send_admin_notification($pdo, $request)) {
        $mailCfg = mail_config();
        $flash = 'Notification email sent to ' . $mailCfg['admin_email'] . '.';
    } else {
        $flashError = 'Could not send notification email. Verify your SendGrid sender and API key.';
    }
}

$requests = [];
$dbError = null;

try {
    $requests = topic_request_list_all($pdo);
} catch (PDOException $ex) {
    $dbError = 'Topic requests could not be loaded.';
    error_log($ex->getMessage());
}

$sendgridReady = mail_is_configured();
$mailCfg = mail_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Topic Requests Admin')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main">
    <?php render_page_header('Topic Requests', 'Review science topic requests and update their status.'); ?>

    <div class="page-section">
        <?php if (!$sendgridReady): ?>
            <div class="alert alert-error">
                Topic request emails require SendGrid on the production server (SMTP ports are blocked).
                Add <code>SENDGRID_API_KEY</code>, <code>ADMIN_EMAIL</code>, <code>FROM_EMAIL</code>, and
                <code>FROM_NAME</code> to <code>.env</code>, and verify
                <code><?= e($mailCfg['from_email']) ?></code> as a sender in SendGrid.
            </div>
        <?php endif; ?>

        <?php if ($flash !== ''): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="alert alert-error"><?= e($flashError) ?></div>
        <?php endif; ?>

        <?php if ($dbError !== null): ?>
            <div class="alert alert-error"><?= e($dbError) ?></div>
        <?php elseif ($requests === []): ?>
            <div class="empty-state"><p>No topic requests yet.</p></div>
        <?php else: ?>
            <div class="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Topic</th>
                            <th>User</th>
                            <th>Age Group</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $status = (string) ($request['status'] ?? 'new');
                            $notified = !empty($request['notified_at']);
                            ?>
                            <tr>
                                <td><strong><?= e((string) $request['science_topic']) ?></strong></td>
                                <td>
                                    <?= e((string) $request['user_name']) ?><br>
                                    <span class="story-card-meta"><?= e((string) $request['user_email']) ?></span>
                                </td>
                                <td><?= e((string) ($request['age_group'] ?? '')) !== '' ? e((string) $request['age_group']) : '—' ?></td>
                                <td class="topic-request-details-cell"><?= e((string) ($request['additional_details'] ?? '')) !== '' ? e((string) $request['additional_details']) : '—' ?></td>
                                <td>
                                    <span class="status-tag status-<?= e($status) ?>"><?= e(topic_request_status_label($status)) ?></span>
                                    <?php if ($notified): ?>
                                        <div class="story-card-meta">Completion email sent</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) $request['created_at']) ?></td>
                                <td>
                                    <form method="post" action="<?= e(app_url('admin-topic-requests.php')) ?>" class="topic-request-status-form">
                                        <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                                        <select name="status" class="form-control">
                                            <?php foreach (topic_request_statuses() as $statusOption): ?>
                                                <option value="<?= e($statusOption) ?>" <?= $status === $statusOption ? 'selected' : '' ?>>
                                                    <?= e(topic_request_status_label($statusOption)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    </form>
                                    <form method="post" action="<?= e(app_url('admin-topic-requests.php')) ?>" class="topic-request-resend-form">
                                        <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                                        <input type="hidden" name="action" value="resend_notification">
                                        <button type="submit" class="btn btn-outline btn-sm">Resend Admin Email</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php render_site_footer(); ?>
</body>
</html>
