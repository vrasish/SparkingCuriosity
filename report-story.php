<?php
require_once __DIR__ . '/auth.php';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$reasons = [
    'Inappropriate content',
    'Incorrect science',
    'Too scary',
    'Copyright concern',
    'Other',
];

$book = null;
$error = '';
$message = '';

if ($bookId <= 0) {
    $error = 'Invalid story.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT book_id, title, status FROM books WHERE book_id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            $error = 'Story not found.';
        } elseif ($book['status'] !== 'approved') {
            $error = 'Only published stories can be reported.';
            $book = null;
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error = 'Could not load story details.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $book) {
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($reason, $reasons, true)) {
        $error = 'Please select a valid reason.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reports (book_id, reason, notes, status)
                VALUES (?, ?, ?, 'open')
            ");
            $stmt->execute([
                $bookId,
                $reason,
                $notes !== '' ? $notes : null,
            ]);
            $message = 'Thank you. This report will be reviewed.';
            $book = null;
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $error = 'Report could not be submitted. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Report Story')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main">
    <?php render_page_header('Report a Story', 'Help us keep stories safe and fun for kids.'); ?>

    <div class="page-section">
    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
        <a href="<?= e(app_url('search.php')) ?>" class="btn btn-outline">Back to Search</a>
    <?php elseif ($error && !$book): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <a href="<?= e(app_url('search.php')) ?>" class="btn btn-outline">Back to Search</a>
    <?php else: ?>
        <p class="mb-2">Reporting: <strong><?= e($book['title']) ?></strong></p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="form-panel">
            <form method="post" action="<?= e(app_url('report-story.php?id=' . (int) $bookId)) ?>">
                <div class="form-group">
                    <label for="reason">Reason *</label>
                    <select id="reason" name="reason" class="form-control" required>
                        <option value="">Select a reason</option>
                        <?php foreach ($reasons as $reason): ?>
                            <option value="<?= e($reason) ?>" <?= (($_POST['reason'] ?? '') === $reason) ? 'selected' : '' ?>><?= e($reason) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Additional notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="5"><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-danger">Submit Report</button>
                <a href="<?= e(app_url('book.php?id=' . (int) $bookId)) ?>" class="btn btn-outline" style="margin-left: 8px;">Cancel</a>
            </form>
        </div>
    <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
