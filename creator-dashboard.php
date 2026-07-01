<?php
require_once __DIR__ . '/auth.php';
require_creator_login();

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$showAll = is_admin_user();

$stories = [];
$stats = [
    'total' => 0,
    'approved' => 0,
    'under_review' => 0,
    'needs_edits' => 0,
    'rejected' => 0,
];
$dbError = null;

try {
    $bookFilter = $showAll ? '' : ' AND b.created_by = :user_id';
    $params = $showAll ? [] : ['user_id' => $userId];

    $stmt = $pdo->prepare("
        SELECT
            b.book_id,
            b.title,
            b.author_name,
            b.status,
            b.created_at,
            GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
        FROM books b
        LEFT JOIN book_categories bc ON b.book_id = bc.book_id
        LEFT JOIN categories c ON bc.category_id = c.category_id
        WHERE b.status IN ('draft', 'under_review', 'approved', 'rejected', 'needs_edits')
        $bookFilter
        GROUP BY b.book_id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $stories = $stmt->fetchAll();

    $stmtStats = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt
        FROM books b
        WHERE b.status IN ('draft', 'under_review', 'approved', 'rejected', 'needs_edits')
        $bookFilter
        GROUP BY status
    ");
    $stmtStats->execute($params);
    foreach ($stmtStats->fetchAll() as $row) {
        $stats['total'] += (int) $row['cnt'];
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = (int) $row['cnt'];
        }
    }
} catch (PDOException $ex) {
    $dbError = 'Dashboard could not be loaded.';
    error_log($ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creator Dashboard | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <?php render_page_header('Creator Dashboard', 'Track every submitted story and its review status.'); ?>

    <div class="page-section">
    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php else: ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-num"><?= (int) $stats['total'] ?></div>
                <div class="stat-label">Total Stories</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?= (int) $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?= (int) $stats['under_review'] ?></div>
                <div class="stat-label">Under Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?= (int) $stats['needs_edits'] ?></div>
                <div class="stat-label">Needs Edits</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?= (int) $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <?php if (empty($stories)): ?>
            <div class="empty-state">
                <p>No stories yet.</p>
                <p class="mt-2"><a href="<?= e(app_url('submit-story.php')) ?>" class="btn btn-primary">Submit Your First Story</a></p>
            </div>
        <?php else: ?>
            <div class="table-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                            <?php if ($showAll): ?>
                                <th>Actions</th>
                            <?php endif; ?>
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
                                <td><span class="status-tag <?= e(status_class($story['status'])) ?>"><?= e(str_replace('_', ' ', $story['status'])) ?></span></td>
                                <td><?= e($story['created_at'] ?? '') ?></td>
                                <?php if ($showAll): ?>
                                    <td>
                                        <div class="table-actions">
                                            <a href="<?= e(app_url('admin-edit-story.php?id=' . (int) $story['book_id'])) ?>" class="btn btn-outline btn-sm">Edit</a>
                                            <form method="post" action="<?= e(app_url('admin-stories.php')) ?>" class="inline-form" onsubmit="return confirm('Delete this story permanently?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="book_id" value="<?= (int) $story['book_id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
