<?php
require_once __DIR__ . '/auth.php';

$seedStoryUrl = app_url('book.php?id=40');
$partnerEmail = 'scifables2026@gmail.com';
$mediaBase = 'assets/impact/dzaleka-2026';
$photoCaption = 'Children learning about seed germination through SciFables.';
$quizCaption = 'Students answering questions after listening to the story.';
$plantCaption = 'Turning the story into a hands-on-science experience by planting and observing seeds.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Impact')) ?></title>
    <meta name="description" content="SciFables in Action at Dzaleka Refugee Camp, Malawi — 350 children explored seed germination through story, quiz, and hands-on planting with Art for Hope.">
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('impact-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact impact-main">
    <header class="impact-hero">
        <p class="impact-kicker">SciFables in Action</p>
        <h1 class="impact-hero-title">Dzaleka Refugee Camp, Malawi</h1>
        <p class="impact-hero-lead">
            In August 2026, approximately 350 children at Dzaleka Refugee Camp participated in a SciFables science session with the Art for Hope team.
        </p>
    </header>

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
        <h2 id="session-heading">The Session</h2>
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

    <section class="impact-section impact-glance" aria-labelledby="glance-heading">
        <h2 id="glance-heading">Impact at a Glance</h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt>Children reached</dt>
                <dd>350</dd>
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

    <section class="impact-section impact-cta" aria-labelledby="cta-heading">
        <h2 id="cta-heading">Keep the Curiosity Growing</h2>
        <p>
            Read the same story these students explored, or partner with SciFables to bring science storytelling to more children around the world.
        </p>
        <div class="impact-cta-row">
            <a href="<?= e($seedStoryUrl) ?>" class="btn btn-primary">Read The Seed That Slept Underground</a>
            <a href="<?= e(app_url('mission.php')) ?>" class="btn btn-outline">Our Mission</a>
            <a href="mailto:<?= e($partnerEmail) ?>?subject=<?= e(rawurlencode('Science Fables Partnership')) ?>" class="btn btn-outline">Partner with Us</a>
        </div>
    </section>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
