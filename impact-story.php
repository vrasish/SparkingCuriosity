<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/impact-lib.php';

$slug = trim((string) ($_GET['id'] ?? ''));
$storyMeta = impact_story_by_slug($slug);
$sessionSlug = trim((string) ($_GET['session'] ?? ''));
$sessionMeta = $storyMeta !== null && $sessionSlug !== ''
    ? impact_session_by_slug($storyMeta, $sessionSlug)
    : null;

if ($storyMeta === null || ($sessionSlug !== '' && $sessionMeta === null)) {
    http_response_code(404);
}

$seedStoryUrl = app_url('book.php?id=40');
$matterStoryUrl = app_url('book.php?id=66');
$partnerEmail = 'scifables2026@gmail.com';
$mediaBase = (string) ($storyMeta['media_base'] ?? 'assets/impact/dzaleka-2026');
$photoCaption = 'Children learning about seed germination through SciFables.';
$quizCaption = 'Students answering questions after listening to the story.';
$plantCaption = 'Turning the story into a hands-on-science experience by planting and observing seeds.';
$matterCaption = 'Exploring solids, liquids, and gases through The Matter Mystery.';
$matterPhotos = [
    'matter-1.png' => 'The Matter Mystery story page projected on the classroom wall for students at Dzaleka Refugee Camp',
    'matter-2.png' => 'Students sitting together on the floor watching The Matter Mystery on the projected SciFables page',
    'matter-3.png' => 'A projector beaming The Matter Mystery onto the wall while students follow along',
    'matter-4.png' => 'A laptop showing The Matter Mystery storybook page beside students watching the projection',
    'matter-5.png' => 'Students listening as the story explains how ice, water, and air are all examples of matter',
    'matter-6.png' => 'Students turning toward the projected story text during the matter session',
];
$childrenReached = (int) ($storyMeta['children_reached'] ?? 350);
$pageTitle = $sessionMeta !== null
    ? (string) ($sessionMeta['title'] ?? 'Impact Session')
    : (string) ($storyMeta['folder_title'] ?? 'Impact Story');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title($pageTitle)) ?></title>
    <?php if ($storyMeta): ?>
    <meta name="description" content="<?= e((string) (
        $sessionMeta['summary'] ?? $storyMeta['summary'] ?? ''
    )) ?>">
    <?php endif; ?>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('impact-page impact-story-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact impact-main">
    <?php if (!$storyMeta || ($sessionSlug !== '' && !$sessionMeta)): ?>
        <section class="impact-section">
            <h1 class="impact-hero-title">Impact story not found</h1>
            <p><a href="<?= e(app_url('impact.php')) ?>">Back to Impact folders</a></p>
        </section>
    <?php elseif (!$sessionMeta): ?>
    <p class="impact-back">
        <a href="<?= e(app_url('impact.php')) ?>">← All Impact folders</a>
    </p>

    <header class="impact-hero">
        <p class="impact-kicker">Art for Hope · Dzaleka Refugee Camp, Malawi</p>
        <h1 class="impact-hero-title"><?= e($pageTitle) ?></h1>
        <p class="impact-hero-lead">
            Open a session folder to see the story, learning activities, photos, and videos from that experience.
        </p>
    </header>

    <section class="impact-section" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading">Session Folders</h2>
        <ul class="impact-folder-list">
            <?php foreach (($storyMeta['sessions'] ?? []) as $session): ?>
                <?php
                $folderSessionSlug = (string) ($session['slug'] ?? '');
                $folderTitle = (string) ($session['title'] ?? '');
                $folderSubtitle = (string) ($session['subtitle'] ?? '');
                $folderSummary = (string) ($session['summary'] ?? '');
                $folderCover = (string) ($session['cover'] ?? '');
                ?>
                <li>
                    <a class="impact-folder" href="<?= e(impact_session_url($slug, $folderSessionSlug)) ?>">
                        <span class="impact-folder-tab" aria-hidden="true"></span>
                        <span class="impact-folder-body">
                            <?php if ($folderCover !== ''): ?>
                                <img
                                    class="impact-folder-thumb"
                                    src="<?= e(asset_url($folderCover)) ?>"
                                    alt=""
                                    width="320"
                                    height="200"
                                    loading="lazy"
                                >
                            <?php endif; ?>
                            <span class="impact-folder-copy">
                                <span class="impact-folder-date"><?= e($folderSubtitle) ?></span>
                                <span class="impact-folder-title"><?= e($folderTitle) ?></span>
                                <span class="impact-folder-summary"><?= e($folderSummary) ?></span>
                            </span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php else: ?>
    <p class="impact-back">
        <a href="<?= e(impact_story_url($slug)) ?>">← Art for Hope session folders</a>
    </p>

    <header class="impact-hero">
        <p class="impact-kicker"><?= e((string) ($sessionMeta['subtitle'] ?? 'SciFables in Action')) ?></p>
        <h1 class="impact-hero-title"><?= e($pageTitle) ?></h1>
        <?php if ($sessionSlug === 'seed-germination'): ?>
        <p class="impact-hero-lead">
            In August 2026, approximately
            <strong
                class="impact-count"
                data-count-to="<?= $childrenReached ?>"
                data-count-duration="2200"
            >1</strong>
            students at Dzaleka Refugee Camp explored seed germination with the Art for Hope team.
        </p>
        <?php else: ?>
        <p class="impact-hero-lead">
            Students at Dzaleka Refugee Camp explored solids, liquids, and gases through
            <a href="<?= e($matterStoryUrl) ?>">The Matter Mystery</a>, a hands-on demonstration,
            and the story quiz with the Art for Hope team.
        </p>
        <?php endif; ?>
    </header>

    <?php if ($sessionSlug === 'seed-germination'): ?>
    <figure class="impact-feature">
        <img
            src="<?= e(asset_url($mediaBase . '/session-1.png')) ?>"
            alt="Children at Dzaleka Refugee Camp watching The Seed That Slept Underground on a projected SciFables page, with an Art for Hope sign on the wall"
            width="1600"
            height="900"
            loading="eager"
        >
        <figcaption><?= e($photoCaption) ?></figcaption>
    </figure>

    <section class="impact-section" aria-labelledby="session-heading">
        <h2 id="session-heading">The Seed Germination Session</h2>
        <p>
            Students listened to
            <a href="<?= e($seedStoryUrl) ?>">The Seed That Slept Underground</a>,
            a story about seed germination, and then answered questions from the accompanying quiz.
        </p>
        <p>
            The children were divided into groups of about 30 students to make the session more interactive.
        </p>
        <p>
            To continue the learning at home, each child was asked to bring a plastic bottle. They will each receive a seed to plant and observe as it germinates.
        </p>
    </section>
    <?php endif; ?>

    <?php if ($sessionSlug === 'seed-germination'): ?>
    <section class="impact-section impact-glance" aria-labelledby="glance-heading">
        <h2 id="glance-heading">Impact at a Glance</h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt>Students reached</dt>
                <dd>
                    <span
                        class="impact-count"
                        data-count-to="<?= $childrenReached ?>"
                        data-count-duration="2200"
                    >1</span>
                </dd>
            </div>
            <div class="impact-stat">
                <dt>Students per group</dt>
                <dd>About 30</dd>
            </div>
            <div class="impact-stat">
                <dt>Story</dt>
                <dd><a href="<?= e($seedStoryUrl) ?>">The Seed That Slept Underground</a></dd>
            </div>
            <div class="impact-stat">
                <dt>Topic</dt>
                <dd>Seed Germination</dd>
            </div>
            <div class="impact-stat">
                <dt>Activities</dt>
                <dd>Story, Quiz, and Hands-on Planting</dd>
            </div>
            <div class="impact-stat">
                <dt>Partner</dt>
                <dd>Art for Hope</dd>
            </div>
            <div class="impact-stat impact-stat-wide">
                <dt>Location</dt>
                <dd>Dzaleka Refugee Camp, Malawi</dd>
            </div>
        </dl>
    </section>
    <?php endif; ?>

    <?php if ($sessionSlug === 'seed-germination'): ?>
    <section class="impact-section impact-media" aria-labelledby="media-heading">
        <h2 id="media-heading">From the Classroom</h2>
        <p class="impact-media-intro"><?= e($photoCaption) ?></p>
        <div class="impact-media-grid">
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/session-2.png')) ?>"
                    alt="Children sitting on the floor watching a SciFables story projected on the classroom wall"
                    width="1200"
                    height="900"
                    loading="lazy"
                >
            </figure>
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/session-3.png')) ?>"
                    alt="Children watching a SciFables germination story while a projector stands beside them"
                    width="1200"
                    height="900"
                    loading="lazy"
                >
            </figure>
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/session-1.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/session-clip-1.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/session-2.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/session-clip-2.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
        </div>
    </section>

    <section class="impact-section impact-media" aria-labelledby="quiz-media-heading">
        <h2 id="quiz-media-heading">Quiz Time</h2>
        <p class="impact-media-intro"><?= e($quizCaption) ?></p>
        <div class="impact-media-grid impact-media-grid-3">
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/session-1.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/quiz-clip-1.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/session-2.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/quiz-clip-2.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/session-3.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/quiz-clip-3.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
        </div>
    </section>

    <section class="impact-section impact-media" aria-labelledby="plant-media-heading">
        <h2 id="plant-media-heading">Hands-on Planting</h2>
        <p class="impact-media-intro"><?= e($plantCaption) ?></p>
        <div class="impact-media-grid impact-media-grid-3">
            <?php for ($i = 1; $i <= 9; $i++): ?>
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/plant-' . $i . '.png')) ?>"
                    alt="Children planting and observing seeds with SciFables and Art for Hope at Dzaleka Refugee Camp"
                    width="1200"
                    height="900"
                    loading="lazy"
                >
            </figure>
            <?php endfor; ?>
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/plant-' . min($i, 9) . '.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/plant-clip-' . $i . '.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
            <?php endfor; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($sessionSlug === 'matter-mystery'): ?>
    <section class="impact-section impact-glance" aria-labelledby="matter-glance-heading">
        <h2 id="matter-glance-heading">Session at a Glance</h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt>Story</dt>
                <dd><a href="<?= e($matterStoryUrl) ?>">The Matter Mystery</a></dd>
            </div>
            <div class="impact-stat">
                <dt>Topic</dt>
                <dd>Solids, Liquids, and Gases</dd>
            </div>
            <div class="impact-stat">
                <dt>Demonstration</dt>
                <dd>Ice in a Bottle</dd>
            </div>
            <div class="impact-stat">
                <dt>Activities</dt>
                <dd>Story, Matter Demonstration, and Quiz</dd>
            </div>
            <div class="impact-stat">
                <dt>Partner</dt>
                <dd>Art for Hope</dd>
            </div>
            <div class="impact-stat impact-stat-wide">
                <dt>Location</dt>
                <dd>Dzaleka Refugee Camp, Malawi</dd>
            </div>
        </dl>
    </section>

    <section class="impact-section impact-media" aria-labelledby="matter-heading">
        <h2 id="matter-heading">The Matter Mystery Session</h2>
        <p>
            In a following session, students explored
            <a href="<?= e($matterStoryUrl) ?>">The Matter Mystery</a>,
            a story about how everything around us is made of tiny particles that can shift and transform.
        </p>
        <p>
            Together they followed the characters through the three states of matter — solids that hold their shape, liquids that flow and take the shape of their container, and gases that spread out to fill any space.
        </p>
        <p>
            The students also saw ice in a bottle, watching the same substance appear as a solid and then melt into a liquid, before finishing the session with the story quiz.
        </p>
        <p class="impact-media-intro"><?= e($matterCaption) ?></p>
        <div class="impact-media-grid impact-media-grid-3">
            <?php foreach ($matterPhotos as $matterFile => $matterAlt): ?>
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/' . $matterFile)) ?>"
                    alt="<?= e($matterAlt) ?>"
                    width="1024"
                    height="768"
                    loading="lazy"
                >
            </figure>
            <?php endforeach; ?>
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <figure class="impact-media-item impact-media-video">
                <video controls playsinline preload="metadata" poster="<?= e(asset_url($mediaBase . '/matter-' . $i . '.png')) ?>">
                    <source src="<?= e(asset_url($mediaBase . '/matter-clip-' . $i . '.mp4')) ?>" type="video/mp4">
                </video>
            </figure>
            <?php endfor; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="impact-section impact-cta" aria-labelledby="cta-heading">
        <h2 id="cta-heading">Keep the Curiosity Growing</h2>
        <p>
            Read the same story these students explored, or partner with SciFables to bring science storytelling to more students around the world.
        </p>
        <div class="impact-cta-row">
            <?php if ($sessionSlug === 'seed-germination'): ?>
            <a href="<?= e($seedStoryUrl) ?>" class="btn btn-primary">Read The Seed That Slept Underground</a>
            <?php else: ?>
            <a href="<?= e($matterStoryUrl) ?>" class="btn btn-primary">Read The Matter Mystery</a>
            <?php endif; ?>
            <a href="<?= e(app_url('mission.php')) ?>" class="btn btn-outline">Our Mission</a>
            <a href="<?= e(app_url('get-involved.php')) ?>#partner-with-us" class="btn btn-outline">Partner with Us</a>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php render_site_footer(true); ?>
<?php if ($storyMeta): ?>
<script src="<?= e(asset_url('impact.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
