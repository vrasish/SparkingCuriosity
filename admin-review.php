<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'], $_POST['action'])) {
    $bookId = (int) $_POST['book_id'];
    $action = $_POST['action'];

    $statusMap = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'request_edits' => 'needs_edits',
    ];

    if ($bookId > 0 && isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];
        try {
            $pdo->beginTransaction();

            $stmtBook = $pdo->prepare('UPDATE books SET status = ? WHERE book_id = ?');
            $stmtBook->execute([$newStatus, $bookId]);

            if ($action === 'approve') {
                $stmtSub = $pdo->prepare("
                    UPDATE submissions
                    SET status = 'approved', reviewed_at = NOW()
                    WHERE book_id = ?
                ");
                $stmtSub->execute([$bookId]);
            } else {
                $stmtSub = $pdo->prepare('UPDATE submissions SET status = ? WHERE book_id = ?');
                $stmtSub->execute([$newStatus, $bookId]);
            }

            $pdo->commit();
            $flash = 'Story updated: ' . str_replace('_', ' ', $newStatus) . '.';
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $flash = 'Update failed. Please try again.';
        }
    }
}

$queue = [];
$dbError = null;

try {
    $stmt = $pdo->query("
        SELECT
            b.book_id,
            b.title,
            b.author_name,
            b.description,
            b.science_element,
            b.status,
            b.book_format,
            b.pdf_file_path,
            b.cover_image_url,
            GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
        FROM books b
        LEFT JOIN book_categories bc ON b.book_id = bc.book_id
        LEFT JOIN categories c ON bc.category_id = c.category_id
        WHERE b.status IN ('under_review', 'needs_edits')
        GROUP BY b.book_id
        ORDER BY b.created_at ASC
    ");
    $queue = $stmt->fetchAll();

    foreach ($queue as &$item) {
        $isPdf = ($item['book_format'] ?? '') === 'pdf';
        if ($isPdf) {
            $item['preview'] = 'PDF book — open the preview link below to read the full file before approving.';
        } else {
            $stmtPreview = $pdo->prepare("
                SELECT page_text
                FROM book_pages
                WHERE book_id = ?
                ORDER BY page_number ASC
                LIMIT 3
            ");
            $stmtPreview->execute([(int) $item['book_id']]);
            $previewParts = $stmtPreview->fetchAll(PDO::FETCH_COLUMN);
            $previewText = implode("\n\n", $previewParts);
            $item['preview'] = mb_strlen($previewText) > 400 ? mb_substr($previewText, 0, 400) . '…' : $previewText;
        }
    }
    unset($item);
} catch (PDOException $ex) {
    $dbError = 'Review queue could not be loaded.';
    error_log($ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Review | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main">
    <?php render_page_header('Story Review Queue', 'Approve, reject, or request edits for submitted stories.'); ?>

    <div class="page-section">
    <?php if ($flash): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php elseif (empty($queue)): ?>
        <div class="empty-state"><p>No stories waiting for review. Great job!</p></div>
    <?php else: ?>
        <?php foreach ($queue as $book): ?>
            <article class="review-card">
                <h3><?= e($book['title']) ?></h3>
                <p class="review-meta">
                    By <?= e($book['author_name']) ?>
                    <?php if (!empty($book['categories'])): ?> · <?= e($book['categories']) ?><?php endif; ?>
                </p>
                <p><strong>Status:</strong> <span class="status-tag <?= e(status_class($book['status'])) ?>"><?= e(str_replace('_', ' ', $book['status'])) ?></span>
                <?php if (($book['book_format'] ?? '') === 'pdf'): ?>
                    · <span class="badge-format-pdf">PDF Book</span>
                <?php endif; ?>
                </p>
                <p><strong>Description:</strong> <?= e($book['description']) ?></p>
                <p><strong>Science element:</strong> <?= e($book['science_element']) ?></p>
                <div class="review-preview"><?= nl2br(e($book['preview'] ?? '')) ?></div>
                <p><a href="<?= e(app_url('book.php?id=' . (int) $book['book_id'] . '&preview=1')) ?>">Read full preview</a></p>
                <div class="action-bar">
                    <a href="<?= e(app_url('admin-edit-story.php?id=' . (int) $book['book_id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                    <form method="post" action="<?= e(app_url('admin-review.php')) ?>">
                        <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                    </form>
                    <form method="post" action="<?= e(app_url('admin-review.php')) ?>">
                        <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                    <form method="post" action="<?= e(app_url('admin-review.php')) ?>">
                        <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                        <input type="hidden" name="action" value="request_edits">
                        <button type="submit" class="btn btn-warning btn-sm">Request Edits</button>
                    </form>
                    <form method="post" action="<?= e(app_url('admin-stories.php')) ?>" class="inline-form" onsubmit="return confirm('Delete this story permanently?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
