<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/impact-lib.php';

$partnerEmail = 'scifables2026@gmail.com';
$impactStories = impact_stories();
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title(t('involved.page_title'))) ?></title>
    <meta name="description" content="<?= e(t('involved.meta')) ?>">
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('get-involved-page partners-page mission-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact mission-main partners-main">
    <header class="mission-hero">
        <p class="mission-kicker"><?= e(t('involved.kicker')) ?></p>
        <h1 class="mission-hero-title"><?= e(t('involved.page_title')) ?></h1>
        <p class="mission-hero-lead">
            <?= e(t('involved.lead')) ?>
        </p>
        <div class="mission-cta-row">
            <a href="#partners" class="btn btn-primary"><?= e(t('involved.partners_cta')) ?></a>
            <a href="#partner-with-us" class="btn btn-outline"><?= e(t('involved.partner_with_us_cta')) ?></a>
        </div>
    </header>

    <section class="mission-section" id="partners" aria-labelledby="partners-heading">
        <h2 id="partners-heading"><?= e(t('involved.partners')) ?></h2>
        <p class="mission-list-intro"><?= e(t('involved.partners_intro')) ?></p>
        <?php if ($impactStories === []): ?>
            <p><?= e(t('involved.partners_empty')) ?></p>
        <?php else: ?>
            <div class="partners-grid">
                <?php foreach ($impactStories as $story): ?>
                    <?php
                    $slug = (string) ($story['slug'] ?? '');
                    $partner = impact_text($story, 'partner', 'Partner');
                    $partnerWebsite = trim((string) ($story['partner_website'] ?? ''));
                    $location = impact_text($story, 'location');
                    $summary = impact_text($story, 'summary');
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
                                <p class="partners-card-metric"><?= e(t('involved.students_reached', ['count' => number_format($reached)])) ?></p>
                            <?php endif; ?>
                            <div class="partners-card-actions">
                                <a href="<?= e(impact_story_url($slug)) ?>" class="btn btn-outline btn-sm"><?= e(t('involved.view_impact')) ?></a>
                                <?php if ($partnerWebsite !== ''): ?>
                                    <a href="<?= e($partnerWebsite) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener noreferrer"><?= e(t('involved.visit_website')) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="mission-section" id="partner-with-us" aria-labelledby="partner-with-us-heading">
        <h2 id="partner-with-us-heading"><?= e(t('involved.partner_with_us')) ?></h2>
        <p class="mission-list-intro">
            <?= e(t('involved.partner_intro')) ?>
        </p>

        <h3 class="get-involved-subheading"><?= e(t('involved.who_partner')) ?></h3>
        <div class="mission-audience-grid">
            <article class="mission-audience-card">
                <h3><?= e(t('involved.partner_nonprofits')) ?></h3>
                <p><?= e(t('involved.partner_nonprofits_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <h3><?= e(t('involved.partner_schools')) ?></h3>
                <p><?= e(t('involved.partner_schools_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <h3><?= e(t('involved.partner_libraries')) ?></h3>
                <p><?= e(t('involved.partner_libraries_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <h3><?= e(t('involved.partner_community')) ?></h3>
                <p><?= e(t('involved.partner_community_desc')) ?></p>
            </article>
        </div>

        <h3 class="get-involved-subheading"><?= e(t('involved.what_receive')) ?></h3>
        <ul class="mission-receive-list">
            <li><span aria-hidden="true">📚</span> <?= e(t('involved.receive_library')) ?></li>
            <li><span aria-hidden="true">🎧</span> <?= e(t('involved.receive_audio')) ?></li>
            <li><span aria-hidden="true">📝</span> <?= e(t('involved.receive_quizzes')) ?></li>
            <li><span aria-hidden="true">🔬</span> <?= e(t('involved.receive_new')) ?></li>
            <li><span aria-hidden="true">🌍</span> <?= e(t('involved.receive_collab')) ?></li>
        </ul>
    </section>

    <section class="mission-section mission-join" aria-labelledby="reach-out-heading">
        <h2 id="reach-out-heading"><?= e(t('involved.ready')) ?></h2>
        <p><?= e(t('involved.ready_p1')) ?></p>
        <p class="mission-form-note">
            <a href="mailto:<?= e($partnerEmail) ?>?subject=<?= e(rawurlencode('Science Fables Partnership')) ?>"><?= e($partnerEmail) ?></a>
        </p>
        <div class="mission-cta-row" style="margin-top: 18px;">
            <a href="<?= e(app_url('impact.php')) ?>" class="btn btn-outline"><?= e(t('involved.see_impact')) ?></a>
            <a href="<?= e(app_url('mission.php')) ?>" class="btn btn-outline"><?= e(t('involved.our_mission')) ?></a>
        </div>
    </section>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
