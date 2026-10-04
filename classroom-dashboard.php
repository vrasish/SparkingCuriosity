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
$assignableBooks = classroom_assignable_books($pdo);
$error = null;
$success = null;

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
        $success = 'Classroom created. Copy the link below into Google Classroom.';
        $classrooms = classrooms_for_teacher($pdo, $userId);
    } else {
        $error = (string) ($result['error'] ?? 'Could not create classroom.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $result = classroom_update_assignment(
        $pdo,
        (int) ($_POST['classroom_id'] ?? 0),
        $userId,
        (int) ($_POST['assigned_book_id'] ?? 0),
        (string) ($_POST['assigned_topic'] ?? ''),
        (string) ($_POST['assigned_topic_icon'] ?? '🌱'),
        (int) ($_POST['class_size'] ?? 0)
    );
    if ($result['ok']) {
        $success = 'Assignment updated. Students who open your classroom link will see the new story.';
        $classrooms = classrooms_for_teacher($pdo, $userId);
    } else {
        $error = (string) ($result['error'] ?? 'Could not update assignment.');
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
            Log in → assign a story → share your classroom link. Students need no signup. Your dashboard tracks class activity as a whole.
        </p>
    </header>

    <section class="mission-section">
        <h2>How it works</h2>
        <ol class="class-steps">
            <li><strong>You log in</strong> with your teacher email.</li>
            <li><strong>Assign a story</strong> (and topic) for the class.</li>
            <li><strong>Copy your classroom link</strong> into Google Classroom.</li>
            <li><strong>Students click the link</strong> — no email or password — and can read all SciFables stories.</li>
            <li><strong>Watch this dashboard</strong> for how many students opened the assigned story and completed quizzes.</li>
        </ol>
    </section>

    <?php if ($error): ?>
        <section class="mission-section">
            <p class="form-error"><?= e($error) ?></p>
        </section>
    <?php endif; ?>
    <?php if ($success): ?>
        <section class="mission-section">
            <p class="form-success"><?= e($success) ?></p>
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
                    <label for="assigned_book_id">Assign a story</label>
                    <select id="assigned_book_id" name="assigned_book_id" class="form-control" required>
                        <option value="">Choose a story…</option>
                        <?php foreach ($assignableBooks as $book): ?>
                            <option value="<?= (int) $book['book_id'] ?>">
                                <?= e((string) $book['title']) ?><?= !empty($book['story_topic']) ? ' — ' . e((string) $book['story_topic']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assigned_topic">Topic label students see</label>
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
        $completedStories = classroom_stories_completed($pdo, $classroom);
        $link = classroom_absolute_url((string) $classroom['slug']);
        $teacherName = (string) ($classroom['teacher_display_name'] ?? '');
        $school = trim((string) ($classroom['school_name'] ?? ''));
        $assignedBookId = (int) ($classroom['assigned_book_id'] ?? 0);
        $assignedTitle = '';
        foreach ($assignableBooks as $book) {
            if ((int) $book['book_id'] === $assignedBookId) {
                $assignedTitle = (string) $book['title'];
                break;
            }
        }
        ?>
        <section class="mission-section class-dash-card" aria-labelledby="class-<?= (int) $classroom['classroom_id'] ?>-heading">
            <h2 id="class-<?= (int) $classroom['classroom_id'] ?>-heading">
                <?= e($teacherName) ?><?= $school !== '' ? ' — ' . e($school) : '' ?>
            </h2>

            <div class="class-link-box">
                <label for="class-link-<?= (int) $classroom['classroom_id'] ?>">1. Share this classroom link</label>
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
                <p class="class-link-note">Put this in Google Classroom. Students do not need accounts.</p>
            </div>

            <form method="post" class="class-create-form class-assign-form">
                <input type="hidden" name="update_assignment" value="1">
                <input type="hidden" name="classroom_id" value="<?= (int) $classroom['classroom_id'] ?>">
                <h3>2. Assign a story for your class</h3>
                <?php if ($assignedTitle !== ''): ?>
                    <p class="class-dash-assigned">
                        Currently assigned:
                        <strong><?= e((string) ($classroom['assigned_topic_icon'] ?? '🌱')) ?> <?= e((string) ($classroom['assigned_topic'] ?? '')) ?></strong>
                        — <?= e($assignedTitle) ?>
                    </p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="assigned_book_id_<?= (int) $classroom['classroom_id'] ?>">Story</label>
                    <select
                        id="assigned_book_id_<?= (int) $classroom['classroom_id'] ?>"
                        name="assigned_book_id"
                        class="form-control"
                        required
                    >
                        <?php foreach ($assignableBooks as $book): ?>
                            <option
                                value="<?= (int) $book['book_id'] ?>"
                                <?= (int) $book['book_id'] === $assignedBookId ? 'selected' : '' ?>
                                data-topic="<?= e((string) ($book['story_topic'] ?? '')) ?>"
                            >
                                <?= e((string) $book['title']) ?><?= !empty($book['story_topic']) ? ' — ' . e((string) $book['story_topic']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assigned_topic_<?= (int) $classroom['classroom_id'] ?>">Topic label</label>
                    <input
                        type="text"
                        id="assigned_topic_<?= (int) $classroom['classroom_id'] ?>"
                        name="assigned_topic"
                        class="form-control"
                        required
                        value="<?= e((string) ($classroom['assigned_topic'] ?? '')) ?>"
                    >
                </div>
                <div class="form-group">
                    <label for="assigned_topic_icon_<?= (int) $classroom['classroom_id'] ?>">Topic emoji</label>
                    <input
                        type="text"
                        id="assigned_topic_icon_<?= (int) $classroom['classroom_id'] ?>"
                        name="assigned_topic_icon"
                        class="form-control"
                        maxlength="8"
                        value="<?= e((string) ($classroom['assigned_topic_icon'] ?? '🌱')) ?>"
                    >
                </div>
                <div class="form-group">
                    <label for="class_size_<?= (int) $classroom['classroom_id'] ?>">Class size</label>
                    <input
                        type="number"
                        id="class_size_<?= (int) $classroom['classroom_id'] ?>"
                        name="class_size"
                        class="form-control"
                        min="1"
                        max="200"
                        required
                        value="<?= (int) ($classroom['class_size'] ?? 24) ?>"
                    >
                </div>
                <button type="submit" class="btn btn-primary">Save assignment</button>
            </form>

            <h3 class="class-stats-heading">3. Class activity dashboard</h3>
            <p class="class-stats-note">
                Counts are for your class as a whole (anonymous devices that used your link). Individual students are not named.
            </p>

            <?php if ($completedStories !== []): ?>
                <h4 class="class-completed-heading">Stories completed</h4>
                <ul class="class-completed-list">
                    <?php foreach ($completedStories as $done): ?>
                        <li class="class-completed-item">
                            <div class="class-completed-title">
                                <span class="class-completed-badge">Story completed ✓</span>
                                <strong><?= e((string) $done['title']) ?></strong>
                                <?php if (($done['story_topic'] ?? '') !== ''): ?>
                                    <span class="class-completed-topic"><?= e((string) $done['story_topic']) ?></span>
                                <?php endif; ?>
                            </div>
                            <dl class="class-completed-stats">
                                <div>
                                    <dt>Students finished</dt>
                                    <dd><?= (int) $done['students_completed'] ?> / <?= (int) ($classroom['class_size'] ?? 0) ?></dd>
                                </div>
                                <div>
                                    <dt>Avg. quiz score</dt>
                                    <dd><?= $done['average_quiz_score'] === null ? '—' : ((int) $done['average_quiz_score'] . '%') ?></dd>
                                </div>
                            </dl>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4 class="class-current-heading">Current assigned story</h4>
            <p class="class-dash-assigned class-current-assigned">
                <?php if ($assignedTitle !== ''): ?>
                    <strong><?= e((string) ($classroom['assigned_topic_icon'] ?? '🌱')) ?> <?= e((string) ($classroom['assigned_topic'] ?? '')) ?></strong>
                    — <?= e($assignedTitle) ?>
                <?php else: ?>
                    No story assigned yet.
                <?php endif; ?>
            </p>
            <p class="class-stats-note">These numbers count only the currently assigned story — not completed stories above.</p>
            <dl class="class-stats">
                <div class="class-stat">
                    <dt>👥 Class size</dt>
                    <dd><?= (int) ($classroom['class_size'] ?? 0) ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📖 Students who opened this story</dt>
                    <dd><?= (int) $stats['students_read_assigned'] ?></dd>
                </div>
                <div class="class-stat">
                    <dt>🧠 Students who finished this quiz</dt>
                    <dd><?= (int) ($stats['students_completed_assigned_quiz'] ?? 0) ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📚 Story opens</dt>
                    <dd><?= (int) ($stats['assigned_story_opens'] ?? 0) ?></dd>
                </div>
                <div class="class-stat">
                    <dt>📝 Quizzes completed</dt>
                    <dd><?= (int) ($stats['assigned_quizzes_completed'] ?? 0) ?></dd>
                </div>
                <div class="class-stat">
                    <dt>⭐ Average quiz score</dt>
                    <dd><?= ($stats['assigned_average_quiz_score'] ?? null) === null ? '—' : ((int) $stats['assigned_average_quiz_score'] . '%') ?></dd>
                </div>
            </dl>
        </section>
    <?php endforeach; ?>

    <?php if ($classrooms !== []): ?>
        <section class="mission-section">
            <h2>Topics students can explore</h2>
            <ul class="mission-checklist">
                <?php foreach ($topicTiles as $tile): ?>
                    <li><?= e($tile['icon']) ?> <?= e($tile['label']) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
<?php render_site_footer(true); ?>
<script>
(function () {
    document.querySelectorAll('select[name="assigned_book_id"]').forEach(function (select) {
        select.addEventListener('change', function () {
            var opt = select.options[select.selectedIndex];
            var topic = opt ? (opt.getAttribute('data-topic') || '') : '';
            if (!topic) { return; }
            var form = select.closest('form');
            if (!form) { return; }
            var topicInput = form.querySelector('input[name="assigned_topic"]');
            if (topicInput && !topicInput.value) {
                topicInput.value = topic;
            }
        });
    });
})();
</script>
</body>
</html>
