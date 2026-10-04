<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/classroom-lib.php';

require_admin_login();

$pdo = stories_connect();
ensure_classrooms_schema($pdo);
classroom_seed_ms_kim($pdo);

$detailId = (int) ($_GET['classroom_id'] ?? ($_POST['classroom_id'] ?? 0));
$detail = $detailId > 0 ? classroom_by_id($pdo, $detailId) : null;
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_classroom_analytics'])) {
    $resetId = (int) ($_POST['classroom_id'] ?? 0);
    $result = classroom_reset_analytics($pdo, $resetId);
    if ($result['ok']) {
        $deleted = (int) ($result['deleted'] ?? 0);
        $success = 'Classroom analytics reset to zero'
            . ($deleted > 0 ? ' (' . $deleted . ' event' . ($deleted === 1 ? '' : 's') . ' removed).' : '.');
        $detailId = $resetId;
        $detail = classroom_by_id($pdo, $resetId);
    } else {
        $error = (string) ($result['error'] ?? 'Could not reset analytics.');
        $detailId = $resetId;
        $detail = classroom_by_id($pdo, $resetId);
    }
}

if ($detailId > 0 && !$detail) {
    $error = $error ?: 'Classroom not found.';
    $detail = null;
}

$impact = classroom_platform_impact($pdo);
$rows = classroom_admin_rows_with_stats($pdo);

$detailStats = null;
$detailTeacher = null;
$detailBookTitle = '';
if ($detail) {
    $detailStats = classroom_stats($pdo, $detail);
    foreach ($rows as $row) {
        if ((int) ($row['classroom_id'] ?? 0) === (int) $detail['classroom_id']) {
            $detailTeacher = $row;
            $detailBookTitle = (string) ($row['assigned_book_title'] ?? '');
            break;
        }
    }
    if ($detailBookTitle === '' && !empty($detail['assigned_book_id'])) {
        $bookStmt = $pdo->prepare('SELECT title FROM books WHERE book_id = ? LIMIT 1');
        $bookStmt->execute([(int) $detail['assigned_book_id']]);
        $detailBookTitle = (string) ($bookStmt->fetchColumn() ?: '');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title($detail ? 'Classroom Detail' : 'Classroom Analytics')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main sales-page admin-classroom-page">
    <?php if ($detail && $detailStats): ?>
        <?php
        $displayName = (string) ($detail['teacher_display_name'] ?? 'Classroom');
        $school = trim((string) ($detail['school_name'] ?? ''));
        $accountName = trim((string) ($detailTeacher['teacher_account_name'] ?? ''));
        $accountEmail = trim((string) ($detailTeacher['teacher_email'] ?? ''));
        $link = classroom_absolute_url((string) $detail['slug']);
        ?>
        <?php render_page_header(
            $displayName,
            'Aggregate classroom activity only — no student names or personal information.'
        ); ?>

        <p class="admin-class-back">
            <a href="<?= e(app_url('admin-classroom-analytics.php')) ?>">← All classrooms</a>
        </p>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="page-section admin-class-detail-meta">
            <dl class="admin-class-meta">
                <div>
                    <dt>Classroom</dt>
                    <dd><?= e($displayName) ?></dd>
                </div>
                <div>
                    <dt>Teacher account</dt>
                    <dd>
                        <?= e($accountName !== '' ? $accountName : '—') ?>
                        <?php if ($accountEmail !== ''): ?>
                            <span class="admin-class-meta-email"><?= e($accountEmail) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>School</dt>
                    <dd><?= e($school !== '' ? $school : '—') ?></dd>
                </div>
                <div>
                    <dt>Class size</dt>
                    <dd><?= (int) ($detail['class_size'] ?? 0) ?></dd>
                </div>
                <div>
                    <dt>Assigned story</dt>
                    <dd>
                        <?php if ($detailBookTitle !== ''): ?>
                            <?= e((string) ($detail['assigned_topic_icon'] ?? '🌱')) ?>
                            <?= e((string) ($detail['assigned_topic'] ?? '')) ?>
                            — <?= e($detailBookTitle) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>Student link</dt>
                    <dd>
                        <a href="<?= e(classroom_public_url((string) $detail['slug'])) ?>" target="_blank" rel="noopener">
                            <?= e($link) ?>
                        </a>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="page-section">
            <h2 class="sales-section-title">Class activity dashboard</h2>
            <p class="class-stats-note">
                Same aggregate view the teacher sees. Counts are anonymous devices that used this classroom link.
                Activity while you are logged in as SciFables admin is not recorded.
            </p>
            <dl class="class-stats">
                <div class="class-stat">
                    <dt>👥 Class size</dt>
                    <dd><?= (int) ($detail['class_size'] ?? 0) ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📖 Students who opened assigned story</dt>
                    <dd><?= (int) $detailStats['students_read_assigned'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>🧠 Students who completed a quiz</dt>
                    <dd><?= (int) $detailStats['students_completed_quiz'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📚 Total story opens</dt>
                    <dd><?= (int) $detailStats['stories_read'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📝 Quizzes completed</dt>
                    <dd><?= (int) $detailStats['quizzes_completed'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>⭐ Average quiz score</dt>
                    <dd><?= $detailStats['average_quiz_score'] === null ? '—' : ((int) $detailStats['average_quiz_score'] . '%') ?></dd>
                </div>
                <div class="class-stat">
                    <dt>🔎 Extra stories explored</dt>
                    <dd><?= (int) $detailStats['additional_stories_explored'] ?></dd>
                </div>
            </dl>
        </section>

        <section class="page-section">
            <h2 class="sales-section-title">SciFables-wide context</h2>
            <div class="sales-stats-grid admin-class-impact-grid">
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= (int) $impact['schools'] ?></div>
                    <div class="sales-stat-label">Schools</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= (int) $impact['classrooms'] ?></div>
                    <div class="sales-stat-label">Classrooms</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= (int) $impact['students_reached'] ?></div>
                    <div class="sales-stat-label">Students reached</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= (int) $impact['stories_opened'] ?></div>
                    <div class="sales-stat-label">Stories opened</div>
                </div>
            </div>
        </section>

        <section class="page-section admin-class-reset">
            <h2 class="sales-section-title">Reset analytics</h2>
            <p class="class-stats-note">
                Clears all story opens and quiz completions for this classroom. Class size, assigned story, and the student link stay the same.
                Use this after testing so Ms. Kim’s real student activity starts at zero.
            </p>
            <form method="post" class="admin-class-reset-form" onsubmit="return confirm('Reset all analytics for this classroom to zero? This cannot be undone.');">
                <input type="hidden" name="reset_classroom_analytics" value="1">
                <input type="hidden" name="classroom_id" value="<?= (int) $detail['classroom_id'] ?>">
                <button type="submit" class="btn btn-danger">Reset classroom analytics</button>
            </form>
        </section>
    <?php else: ?>
        <?php render_page_header(
            'Classroom Analytics',
            'Aggregate impact across every teacher classroom. No student names or personal information.'
        ); ?>

        <div class="page-section">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <h2 class="sales-section-title">SciFables Classroom Impact</h2>
            <div class="sales-stats-grid admin-class-impact-grid">
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= (int) $impact['schools'] ?></div>
                    <div class="sales-stat-label">Schools</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= (int) $impact['classrooms'] ?></div>
                    <div class="sales-stat-label">Classrooms</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= number_format((int) $impact['students_reached']) ?></div>
                    <div class="sales-stat-label">Students reached</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= number_format((int) $impact['stories_opened']) ?></div>
                    <div class="sales-stat-label">Stories opened</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= number_format((int) $impact['quizzes_completed']) ?></div>
                    <div class="sales-stat-label">Quizzes completed</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= $impact['average_quiz_score'] === null ? '—' : ((int) $impact['average_quiz_score'] . '%') ?></div>
                    <div class="sales-stat-label">Average quiz score</div>
                </div>
                <div class="sales-stat-card">
                    <div class="sales-stat-num"><?= number_format((int) $impact['additional_stories_explored']) ?></div>
                    <div class="sales-stat-label">Additional stories explored</div>
                </div>
            </div>
        </div>

        <section class="page-section">
            <h2 class="sales-section-title">All classrooms</h2>
            <?php if ($rows === []): ?>
                <div class="empty-state">
                    <p>No classrooms yet.</p>
                </div>
            <?php else: ?>
                <div class="table-panel admin-class-table-wrap">
                    <table class="sales-table admin-class-table">
                        <thead>
                            <tr>
                                <th>Classroom</th>
                                <th>Teacher</th>
                                <th>School</th>
                                <th>Class size</th>
                                <th>Assigned story</th>
                                <th>Story opens</th>
                                <th>Quizzes</th>
                                <th>Avg. score</th>
                                <th>Extra stories</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $stats = is_array($row['stats'] ?? null) ? $row['stats'] : [];
                                $cid = (int) ($row['classroom_id'] ?? 0);
                                $teacherLabel = trim((string) ($row['teacher_account_name'] ?? ''));
                                if ($teacherLabel === '') {
                                    $teacherLabel = trim((string) ($row['teacher_email'] ?? ''));
                                }
                                $assignedLabel = trim((string) ($row['assigned_book_title'] ?? ''));
                                if ($assignedLabel === '' && !empty($row['assigned_topic'])) {
                                    $assignedLabel = (string) $row['assigned_topic'];
                                }
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(app_url('admin-classroom-analytics.php?classroom_id=' . $cid)) ?>">
                                            <?= e((string) ($row['teacher_display_name'] ?? 'Classroom')) ?>
                                        </a>
                                    </td>
                                    <td><?= e($teacherLabel !== '' ? $teacherLabel : '—') ?></td>
                                    <td><?= e(trim((string) ($row['school_name'] ?? '')) !== '' ? (string) $row['school_name'] : '—') ?></td>
                                    <td><?= (int) ($row['class_size'] ?? 0) ?></td>
                                    <td><?= e($assignedLabel !== '' ? $assignedLabel : '—') ?></td>
                                    <td><?= (int) ($stats['stories_read'] ?? 0) ?></td>
                                    <td><?= (int) ($stats['quizzes_completed'] ?? 0) ?></td>
                                    <td><?= ($stats['average_quiz_score'] ?? null) === null ? '—' : ((int) $stats['average_quiz_score'] . '%') ?></td>
                                    <td><?= (int) ($stats['additional_stories_explored'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
