<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/topic-requests-lib.php';

if (!is_logged_in()) {
    $redirect = urlencode(app_url('request-topic.php'));
    redirect_to('login.php?redirect=' . $redirect);
}

$user = current_user();
$error = '';
$success = false;

$postedTopic = '';
$postedAgeGroup = '';
$postedDetails = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedTopic = trim((string) ($_POST['science_topic'] ?? ''));
    $postedAgeGroup = trim((string) ($_POST['age_group'] ?? ''));
    $postedDetails = trim((string) ($_POST['additional_details'] ?? ''));

    $result = topic_request_create(
        $pdo,
        (int) ($user['user_id'] ?? 0),
        (string) ($user['email'] ?? ''),
        (string) ($user['full_name'] ?? ''),
        $postedTopic,
        $postedAgeGroup,
        $postedDetails
    );

    if ($result['ok']) {
        $request = topic_request_get($pdo, $result['request_id']);
        if ($request !== null) {
            topic_request_send_admin_notification($request);
        }
        $success = true;
        $postedTopic = '';
        $postedAgeGroup = '';
        $postedDetails = '';
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Request a Topic')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <?php render_page_header(
        'Request a Topic',
        'Request any science topic you are interested in. We will create the story, add it to the SciFables library, and email you when it is ready.'
    ); ?>

    <div class="page-section">
        <?php if ($success): ?>
            <div class="alert alert-success">
                Thank you! Your topic request has been submitted. We’ll email you when the story is ready.
            </div>
            <p class="topic-request-account-note">
                We’ll notify <strong><?= e((string) ($user['email'] ?? '')) ?></strong> when your story is ready.
            </p>
            <div class="action-bar">
                <a href="<?= e(app_url('search.php')) ?>" class="btn btn-primary">Explore Stories</a>
                <a href="<?= e(app_url('request-topic.php')) ?>" class="btn btn-outline">Submit Another Request</a>
            </div>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="form-panel topic-request-panel">
                <p class="topic-request-account-note">
                    Submitting as <strong><?= e((string) ($user['full_name'] ?? '')) ?></strong>
                    (<strong><?= e((string) ($user['email'] ?? '')) ?></strong>)
                </p>

                <form method="post" action="<?= e(app_url('request-topic.php')) ?>" class="topic-request-form">
                    <div class="form-group">
                        <label for="science_topic">Science Topic *</label>
                        <input
                            type="text"
                            id="science_topic"
                            name="science_topic"
                            class="form-control"
                            required
                            maxlength="255"
                            placeholder="e.g. Photosynthesis, Volcanoes, The water cycle"
                            value="<?= e($postedTopic) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="age_group">Age Group</label>
                        <input
                            type="text"
                            id="age_group"
                            name="age_group"
                            class="form-control"
                            maxlength="50"
                            placeholder="e.g. 8–12"
                            value="<?= e($postedAgeGroup) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="additional_details">Additional Details</label>
                        <textarea
                            id="additional_details"
                            name="additional_details"
                            class="form-control"
                            rows="4"
                            placeholder="Tell us anything else that would help us create the story."
                        ><?= e($postedDetails) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php render_site_footer(true); ?>
</body>
</html>
