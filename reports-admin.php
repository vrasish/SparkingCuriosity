<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$allowedStatuses = ['open', 'reviewed', 'resolved', 'dismissed'];
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'], $_POST['status'])) {
    $reportId = (int) $_POST['report_id'];
    $newStatus = $_POST['status'];

    if ($reportId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        try {
            $stmt = $pdo->prepare('UPDATE reports SET status = ? WHERE report_id = ?');
            $stmt->execute([$newStatus, $reportId]);
            $flash = 'Report status updated.';
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $flash = 'Could not update report.';
        }
    }
}

$reports = [];
$dbError = null;

try {
    $stmt = $pdo->query("
        SELECT
            r.report_id,
            r.book_id,
            r.reason,
            r.notes,
            r.status,
            r.created_at,
            b.title AS book_title
        FROM reports r
        JOIN books b ON r.book_id = b.book_id
        ORDER BY r.created_at DESC
    ");
    $reports = $stmt->fetchAll();
} catch (PDOException $ex) {
    $dbError = 'Reports could not be loaded.';
    error_log($ex->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Reports Admin')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main">
    <?php render_page_header('Story Reports', 'Review and update user reports.'); ?>

    <div class="page-section">
    <?php if ($flash): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php elseif (empty($reports)): ?>
        <div class="empty-state"><p>No reports yet.</p></div>
    <?php else: ?>
        <div class="table-panel">
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Reason</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <a href="<?= e(app_url('book.php?id=' . (int) $report['book_id'])) ?>"><?= e($report['book_title']) ?></a>
                            </td>
                            <td><?= e($report['reason']) ?></td>
                            <td><?= e($report['notes'] ?? '—') ?></td>
                            <td><span class="status-tag status-<?= e($report['status']) ?>"><?= e($report['status']) ?></span></td>
                            <td><?= e($report['created_at']) ?></td>
                            <td>
                                <form method="post" action="<?= e(app_url('reports-admin.php')) ?>" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>">
                                    <select name="status" class="form-control" style="width:auto; min-width:120px; padding:6px 10px; font-size:0.875rem;">
                                        <?php foreach ($allowedStatuses as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $report['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
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
