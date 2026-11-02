<?php
require_once __DIR__ . '/auth.php';

$partnerEmail = 'scifables2026@gmail.com';
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, viewport-fit=cover">
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
            <a href="#partner-with-us" class="btn btn-primary"><?= e(t('involved.partner_with_us_cta')) ?></a>
        </div>
    </header>

    <?php /* Partner details temporarily hidden. */ ?>

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
