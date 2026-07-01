<?php
require_once __DIR__ . '/auth.php';

require_admin_login();
ensure_book_pdf_schema($pdo);

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $bookId = (int) ($_POST['book_id'] ?? 0);
    if ($bookId > 0 && delete_book_by_id($pdo, $bookId)) {
        $flash = 'Story deleted.';
    } else {
        $error = 'Could not delete that story.';
    }
}

$stories = [];
$dbError = null;

try {
    $stmt = $pdo->query("
        SELECT
            b.book_id,
            b.title,
            b.author_name,
            b.status,
            b.book_format,
            b.created_at,
            GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
        FROM books b
        LEFT JOIN book_categories bc ON b.book_id = bc.book_id
        LEFT JOIN categories c ON bc.category_id = c.category_id
        GROUP BY b.book_id
        ORDER BY b.created_at DESC
    ");
    $stories = $stmt->fetchAll();
} catch (PDOException $ex) {
    $dbError = 'Stories could not be loaded.';
    error_log($ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Stories | Admin | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main">
    <?php render_page_header('All Stories', 'Edit or delete any story — including approved and rejected ones.'); ?>

    <div class="page-section">
        <?php if ($flash): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="alert alert-error"><?= e($dbError) ?></div>
        <?php elseif (empty($stories)): ?>
            <div class="empty-state"><p>No stories in the library yet.</p></div>
        <?php else: ?>
            <div class="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Format</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stories as $story): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(app_url('book.php?id=' . (int) $story['book_id'] . '&preview=1')) ?>"><?= e($story['title']) ?></a>
                                </td>
                                <td><?= e($story['author_name']) ?></td>
                                <td><?= e($story['categories'] ?? '—') ?></td>
                                <td><?= ($story['book_format'] ?? '') === 'pdf' ? 'PDF' : 'Pages' ?></td>
                                <td>
                                    <span class="status-tag <?= e(status_class($story['status'])) ?>">
                                        <?= e(str_replace('_', ' ', $story['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= e(app_url('admin-edit-story.php?id=' . (int) $story['book_id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                                        <form method="post" action="<?= e(app_url('admin-stories.php')) ?>" class="inline-form" onsubmit="return confirm('Delete this story permanently? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="book_id" value="<?= (int) $story['book_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
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
