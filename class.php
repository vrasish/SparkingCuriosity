<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/classroom-lib.php';

$pdo = stories_connect();
ensure_classrooms_schema($pdo);
classroom_seed_ms_kim($pdo);

$slug = trim((string) ($_GET['slug'] ?? ''));
$classroom = $slug !== '' ? classroom_by_slug($pdo, $slug) : null;

if (!$classroom) {
    http_response_code(404);
}

if ($classroom) {
    classroom_enter_session($classroom);
}

$teacherName = (string) ($classroom['teacher_display_name'] ?? 'Your teacher');
$assignedTopic = (string) ($classroom['assigned_topic'] ?? '');
$assignedIcon = (string) ($classroom['assigned_topic_icon'] ?? '🌱');
$assignedBookId = (int) ($classroom['assigned_book_id'] ?? 0);
$schoolName = trim((string) ($classroom['school_name'] ?? ''));
$topicTiles = home_topic_tiles();

$assignedBook = null;
if ($classroom && $assignedBookId > 0) {
    try {
        $books = home_fetch_books_by_ids($pdo, [$assignedBookId]);
        $assignedBook = $books[0] ?? null;
    } catch (Throwable $ex) {
        error_log($ex->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title($classroom ? ($teacherName . ' Classroom') : 'Classroom not found')) ?></title>
    <meta name="robots" content="noindex">
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('class-page mission-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact mission-main class-main">
    <?php if (!$classroom): ?>
        <section class="mission-section">
            <h1 class="mission-hero-title">Classroom not found</h1>
            <p>Ask your teacher for the correct SciFables classroom link.</p>
            <p><a href="<?= e(app_url('index.php')) ?>" class="btn btn-primary">Go to SciFables</a></p>
        </section>
    <?php else: ?>
        <header class="mission-hero class-hero">
            <p class="mission-kicker">Classroom link · no signup needed</p>
            <h1 class="mission-hero-title">Assigned by <?= e($teacherName) ?></h1>
            <?php if ($schoolName !== ''): ?>
                <p class="mission-hero-lead"><?= e($schoolName) ?></p>
            <?php endif; ?>
            <?php if ($assignedTopic !== ''): ?>
                <p class="class-assigned-topic">
                    <span class="class-assigned-icon" aria-hidden="true"><?= e($assignedIcon) ?></span>
                    <span><?= e($assignedTopic) ?></span>
                </p>
            <?php endif; ?>
            <p class="mission-hero-support">
                You can read every SciFables story and take quizzes — no email or password needed.
            </p>
        </header>

        <?php if ($assignedBook): ?>
            <section class="mission-section class-assigned-section" aria-labelledby="assigned-heading">
                <h2 id="assigned-heading">Start here</h2>
                <article class="class-assigned-card">
                    <a href="<?= e(story_book_url((int) $assignedBook['book_id'])) ?>" class="class-assigned-media">
                        <img
                            src="<?= e(cover_image_src($assignedBook['cover_image_url'] ?? null, $assignedBook['title'] ?? '')) ?>"
                            alt=""
                            width="240"
                            height="320"
                            loading="eager"
                        >
                    </a>
                    <div class="class-assigned-body">
                        <p class="class-assigned-label"><?= e($assignedIcon) ?> <?= e($assignedTopic) ?></p>
                        <h3 class="class-assigned-title">
                            <a href="<?= e(story_book_url((int) $assignedBook['book_id'])) ?>"><?= e((string) $assignedBook['title']) ?></a>
                        </h3>
                        <?php if (!empty($assignedBook['description'])): ?>
                            <p class="class-assigned-summary"><?= e((string) $assignedBook['description']) ?></p>
                        <?php endif; ?>
                        <a href="<?= e(story_book_url((int) $assignedBook['book_id'])) ?>" class="btn btn-primary">Read assigned story</a>
                    </div>
                </article>
            </section>
        <?php endif; ?>

        <section class="mission-section" aria-labelledby="explore-heading">
            <h2 id="explore-heading">Explore More SciFables</h2>
            <p class="mission-list-intro">Pick a topic to browse stories. Everything is open for your class.</p>
            <div class="class-topic-grid">
                <?php foreach ($topicTiles as $tile): ?>
                    <a
                        class="class-topic-tile <?= e($tile['class']) ?>"
                        href="<?= e(app_url('search.php?topic=' . rawurlencode($tile['slug']))) ?>"
                    >
                        <span class="class-topic-icon" aria-hidden="true"><?= e($tile['icon']) ?></span>
                        <span class="class-topic-label"><?= e($tile['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="mission-cta-row" style="margin-top: 20px;">
                <a href="<?= e(app_url('search.php')) ?>" class="btn btn-outline">Browse all stories</a>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
