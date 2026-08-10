<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/impact-lib.php';

$partnerEmail = 'scifables2026@gmail.com';
$impactStories = impact_stories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Get Involved')) ?></title>
    <meta name="description" content="Get involved with Science Fables—meet our partners and partner with us to bring free science storytelling to children worldwide.">
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('get-involved-page partners-page mission-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact mission-main partners-main">
    <header class="mission-hero">
        <p class="mission-kicker">Join Science Fables</p>
        <h1 class="mission-hero-title">Get Involved</h1>
        <p class="mission-hero-lead">
            Help bring free science storytelling, quizzes, and hands-on learning to children around the world.
        </p>
        <div class="mission-cta-row">
            <a href="#partners" class="btn btn-primary">Partners</a>
            <a href="#partner-with-us" class="btn btn-outline">Partner with Us</a>
        </div>
    </header>

    <section class="mission-section" id="partners" aria-labelledby="partners-heading">
        <h2 id="partners-heading">Partners</h2>
        <p class="mission-list-intro">Organizations working with Science Fables to spark curiosity in children.</p>
        <?php if ($impactStories === []): ?>
            <p>We’re building our partner network. Check back soon—or reach out to start a collaboration.</p>
        <?php else: ?>
            <div class="partners-grid">
                <?php foreach ($impactStories as $story): ?>
                    <?php
                    $slug = (string) ($story['slug'] ?? '');
                    $partner = (string) ($story['partner'] ?? 'Partner');
                    $partnerWebsite = trim((string) ($story['partner_website'] ?? ''));
                    $location = (string) ($story['location'] ?? '');
                    $summary = (string) ($story['summary'] ?? '');
                    $reached = (int) ($story['children_reached'] ?? 0);
                    ?>
                    <article class="partners-card">
                        <div class="partners-card-body">
                            <h3 class="partners-card-title"><?= e($partner) ?></h3>
                            <?php if ($location !== ''): ?>
                                <p class="partners-card-location"><?= e($location) ?></p>
                            <?php endif; ?>
                            <?php if ($partnerWebsite !== ''): ?>
                                <p class="partners-card-website">
                                    <a href="<?= e($partnerWebsite) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://#', '', rtrim($partnerWebsite, '/'))) ?></a>
                                </p>
                            <?php endif; ?>
                            <?php if ($summary !== ''): ?>
                                <p class="partners-card-summary"><?= e($summary) ?></p>
                            <?php endif; ?>
                            <?php if ($reached > 0): ?>
                                <p class="partners-card-metric"><?= number_format($reached) ?> students reached</p>
                            <?php endif; ?>
                            <div class="partners-card-actions">
                                <a href="<?= e(impact_story_url($slug)) ?>" class="btn btn-outline btn-sm">View Impact Story</a>
                                <?php if ($partnerWebsite !== ''): ?>
                                    <a href="<?= e($partnerWebsite) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener noreferrer">Visit Website</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="mission-section" id="partner-with-us" aria-labelledby="partner-with-us-heading">
        <h2 id="partner-with-us-heading">Partner with Us</h2>
        <p class="mission-list-intro">
            We collaborate with nonprofits, schools, libraries, and community organizations. If you’d like to bring Science Fables to the children you serve, we’d love to work with you.
        </p>

        <h3 class="get-involved-subheading">Who We Partner With</h3>
        <div class="mission-audience-grid">
            <article class="mission-audience-card">
                <h3>Nonprofits</h3>
                <p>Bring free STEM literacy resources to the communities you serve.</p>
            </article>
            <article class="mission-audience-card">
                <h3>Schools &amp; Teachers</h3>
                <p>Use science stories and quizzes to enrich classroom learning.</p>
            </article>
            <article class="mission-audience-card">
                <h3>Libraries &amp; Museums</h3>
                <p>Add engaging science reading to programs and outreach events.</p>
            </article>
            <article class="mission-audience-card">
                <h3>Community Groups</h3>
                <p>Host story sessions, quizzes, and hands-on science activities.</p>
            </article>
        </div>

        <h3 class="get-involved-subheading">What Partners Receive</h3>
        <ul class="mission-receive-list">
            <li><span aria-hidden="true">📚</span> Free access to our complete library of science stories</li>
            <li><span aria-hidden="true">🎧</span> Read-aloud audio and printable PDFs for programs</li>
            <li><span aria-hidden="true">📝</span> Story quizzes and downloadable quiz PDFs</li>
            <li><span aria-hidden="true">🔬</span> New science stories as they are published</li>
            <li><span aria-hidden="true">🌍</span> Opportunities to collaborate on STEM literacy initiatives</li>
        </ul>
    </section>

    <section class="mission-section mission-join" aria-labelledby="reach-out-heading">
        <h2 id="reach-out-heading">Ready to Partner?</h2>
        <p>Email us and tell us a little about your organization and the children you serve.</p>
        <p class="mission-form-note">
            <a href="mailto:<?= e($partnerEmail) ?>?subject=<?= e(rawurlencode('Science Fables Partnership')) ?>"><?= e($partnerEmail) ?></a>
        </p>
        <div class="mission-cta-row" style="margin-top: 18px;">
            <a href="<?= e(app_url('impact.php')) ?>" class="btn btn-outline">See Our Impact</a>
            <a href="<?= e(app_url('mission.php')) ?>" class="btn btn-outline">Our Mission</a>
        </div>
    </section>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
