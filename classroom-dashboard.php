<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/classroom-lib.php';

require_login('classroom-dashboard.php');

$pdo = stories_connect();
ensure_classrooms_schema($pdo);
classroom_seed_ms_kim($pdo);

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$classrooms = classrooms_for_teacher($pdo, $userId);
$error = null;
$created = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_classroom'])) {
    $result = classroom_create(
        $pdo,
        $userId,
        (string) ($_POST['slug'] ?? ''),
        (string) ($_POST['teacher_display_name'] ?? ($user['full_name'] ?? '')),
        (string) ($_POST['school_name'] ?? ''),
        (int) ($_POST['class_size'] ?? 0),
        (string) ($_POST['assigned_topic'] ?? ''),
        (string) ($_POST['assigned_topic_icon'] ?? '🌱'),
        ((int) ($_POST['assigned_book_id'] ?? 0)) ?: null
    );
    if ($result['ok']) {
        $created = true;
        $classrooms = classrooms_for_teacher($pdo, $userId);
    } else {
        $error = (string) ($result['error'] ?? 'Could not create classroom.');
    }
}

$topicTiles = home_topic_tiles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('My Classroom')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('classroom-dashboard-page mission-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact mission-main">
    <header class="mission-hero">
        <p class="mission-kicker">Teacher tools</p>
        <h1 class="mission-hero-title">My Classroom</h1>
        <p class="mission-hero-lead">
            Share your special classroom link. Students read all SciFables stories with no signup — and activity is counted for your class as a whole.
        </p>
    </header>

    <?php if ($error): ?>
        <section class="mission-section">
            <p class="form-error"><?= e($error) ?></p>
        </section>
    <?php endif; ?>
    <?php if ($created): ?>
        <section class="mission-section">
            <p class="form-success">Classroom created. Copy the link below into Google Classroom.</p>
        </section>
    <?php endif; ?>

    <?php if ($classrooms === []): ?>
        <section class="mission-section" aria-labelledby="create-heading">
            <h2 id="create-heading">Create your classroom</h2>
            <form method="post" class="class-create-form">
                <input type="hidden" name="create_classroom" value="1">
                <div class="form-group">
                    <label for="teacher_display_name">Display name students see</label>
                    <input type="text" id="teacher_display_name" name="teacher_display_name" class="form-control" required value="<?= e((string) ($user['full_name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="school_name">School</label>
                    <input type="text" id="school_name" name="school_name" class="form-control" placeholder="e.g. Loyola Elementary">
                </div>
                <div class="form-group">
                    <label for="slug">Classroom link name</label>
                    <div class="class-slug-row">
                        <span class="class-slug-prefix">scifables.com/class/</span>
                        <input type="text" id="slug" name="slug" class="form-control" required pattern="[A-Za-z][A-Za-z0-9_-]{1,79}" placeholder="MsKim">
                    </div>
                </div>
                <div class="form-group">
                    <label for="class_size">Class size</label>
                    <input type="number" id="class_size" name="class_size" class="form-control" min="1" max="200" value="24" required>
                </div>
                <div class="form-group">
                    <label for="assigned_topic">Assigned topic</label>
                    <input type="text" id="assigned_topic" name="assigned_topic" class="form-control" placeholder="e.g. Germination" required>
                </div>
                <div class="form-group">
                    <label for="assigned_topic_icon">Topic emoji</label>
                    <input type="text" id="assigned_topic_icon" name="assigned_topic_icon" class="form-control" value="🌱" maxlength="8">
                </div>
                <button type="submit" class="btn btn-primary">Create classroom link</button>
            </form>
        </section>
    <?php endif; ?>

    <?php foreach ($classrooms as $classroom): ?>
        <?php
        $stats = classroom_stats($pdo, $classroom);
        $link = classroom_absolute_url((string) $classroom['slug']);
        $teacherName = (string) ($classroom['teacher_display_name'] ?? '');
        $school = trim((string) ($classroom['school_name'] ?? ''));
        ?>
        <section class="mission-section class-dash-card" aria-labelledby="class-<?= (int) $classroom['classroom_id'] ?>-heading">
            <h2 id="class-<?= (int) $classroom['classroom_id'] ?>-heading">
                <?= e($teacherName) ?><?= $school !== '' ? ' — ' . e($school) : '' ?>
            </h2>
            <p class="class-dash-assigned">
                Assigned:
                <strong><?= e((string) ($classroom['assigned_topic_icon'] ?? '🌱')) ?> <?= e((string) ($classroom['assigned_topic'] ?? '')) ?></strong>
            </p>

            <div class="class-link-box">
                <label for="class-link-<?= (int) $classroom['classroom_id'] ?>">Classroom link</label>
                <div class="class-link-row">
                    <input
                        id="class-link-<?= (int) $classroom['classroom_id'] ?>"
                        class="form-control"
                        type="text"
                        readonly
                        value="<?= e($link) ?>"
                    >
                    <a href="<?= e(classroom_public_url((string) $classroom['slug'])) ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener">Open</a>
                </div>
                <p class="class-link-note">Put this link in Google Classroom. Students do not need accounts.</p>
            </div>

            <dl class="class-stats">
                <div class="class-stat">
                    <dt>👥 Class size</dt>
                    <dd><?= (int) ($classroom['class_size'] ?? 0) ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📖 Stories read</dt>
                    <dd><?= (int) $stats['stories_read'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>🧠 Quizzes completed</dt>
                    <dd><?= (int) $stats['quizzes_completed'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>⭐ Average quiz score</dt>
                    <dd><?= $stats['average_quiz_score'] === null ? '—' : ((int) $stats['average_quiz_score'] . '%') ?></dd>
                </div>
                <div class="class-stat">
                    <dt>🔎 Additional stories explored</dt>
                    <dd><?= (int) $stats['additional_stories_explored'] ?></dd>
                </div>
            </dl>
        </section>
    <?php endforeach; ?>

    <?php if ($classrooms !== []): ?>
        <section class="mission-section">
            <h2>Topic ideas for Explore More</h2>
            <ul class="mission-checklist">
                <?php foreach ($topicTiles as $tile): ?>
                    <li><?= e($tile['icon']) ?> <?= e($tile['label']) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
