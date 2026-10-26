<?php
require_once __DIR__ . '/auth.php';

$partnerEmail = 'scifables2026@gmail.com';
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title(t('mission.page_title'))) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('mission-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact mission-main">
    <div class="mission-impact-badge" role="note">
        <span class="mission-impact-icon" aria-hidden="true">🌍</span>
        <div>
            <strong><?= e(t('mission.commitment_title')) ?></strong>
            <p><?= e(t('mission.commitment_body')) ?></p>
        </div>
    </div>

    <header class="mission-hero">
        <p class="mission-kicker"><?= e(t('mission.kicker')) ?></p>
        <h1 class="mission-hero-title"><?= e(t('mission.hero_title')) ?></h1>
        <p class="mission-hero-lead">
            <?= e(t('mission.hero_lead')) ?>
        </p>
        <p class="mission-hero-support">
            <?= e(t('mission.hero_support')) ?>
        </p>
        <div class="mission-cta-row">
            <a href="<?= e(app_url('search.php')) ?>" class="btn btn-primary"><?= e(t('mission.explore')) ?></a>
            <a href="#join-mission" class="btn btn-outline"><?= e(t('mission.join')) ?></a>
        </div>
    </header>

    <section class="mission-section mission-founder" aria-labelledby="founder-heading">
        <h2 id="founder-heading"><?= e(t('mission.founder')) ?></h2>
        <div class="mission-founder-layout">
            <img
                src="<?= e(asset_url('assets/vaishnavi-renduchintala.jpg')) ?>"
                alt="<?= e(t('mission.founder_alt')) ?>"
                class="mission-founder-photo"
                width="240"
                height="300"
            >
            <div class="mission-founder-copy">
                <p class="mission-founder-name">Vaishnavi Renduchintala</p>
                <p><?= e(t('mission.founder_p1')) ?></p>
                <p><?= e(t('mission.founder_p2')) ?></p>
            </div>
        </div>
    </section>

    <section class="mission-section" aria-labelledby="mission-heading">
        <h2 id="mission-heading"><?= e(t('mission.our_mission')) ?></h2>
        <p><?= e(t('mission.believe')) ?></p>
        <p><?= e(t('mission.transform')) ?></p>
        <p class="mission-list-intro"><?= e(t('mission.mission_is')) ?></p>
        <ul class="mission-checklist">
            <li><?= e(t('mission.bullet_curiosity')) ?></li>
            <li><?= e(t('mission.bullet_reading')) ?></li>
            <li><?= e(t('mission.bullet_accessible')) ?></li>
            <li><?= e(t('mission.bullet_support')) ?></li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="creative-process-heading">
        <h2 id="creative-process-heading"><?= e(t('mission.creative')) ?></h2>
        <p><?= e(t('mission.creative_p1')) ?></p>
        <p><?= e(t('mission.creative_p2')) ?></p>
        <p><?= e(t('mission.creative_p3')) ?></p>
        <p class="mission-highlight"><?= e(t('mission.creative_highlight')) ?></p>
        <p><?= e(t('mission.creative_p4')) ?></p>
    </section>

    <section class="mission-section" aria-labelledby="serve-heading">
        <h2 id="serve-heading"><?= e(t('mission.who_serve')) ?></h2>
        <div class="mission-audience-grid">
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">👧</span>
                <h3><?= e(t('mission.audience_children')) ?></h3>
                <p><?= e(t('mission.audience_children_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">👨‍👩‍👧</span>
                <h3><?= e(t('mission.audience_parents')) ?></h3>
                <p><?= e(t('mission.audience_parents_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">🍎</span>
                <h3><?= e(t('mission.audience_teachers')) ?></h3>
                <p><?= e(t('mission.audience_teachers_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">🤝</span>
                <h3><?= e(t('mission.audience_nonprofits')) ?></h3>
                <p><?= e(t('mission.audience_nonprofits_desc')) ?></p>
            </article>
            <article class="mission-audience-card">
                <span class="mission-audience-icon" aria-hidden="true">📚</span>
                <h3><?= e(t('mission.audience_libraries')) ?></h3>
                <p><?= e(t('mission.audience_libraries_desc')) ?></p>
            </article>
        </div>
    </section>

    <section class="mission-section" aria-labelledby="free-heading">
        <h2 id="free-heading"><?= e(t('mission.why_free')) ?></h2>
        <p class="mission-highlight"><?= e(t('mission.goal_simple')) ?></p>
        <p><?= e(t('mission.why_free_p1')) ?></p>
        <p class="mission-list-intro"><?= e(t('mission.why_free_intro')) ?></p>
        <ul class="mission-checklist">
            <li><?= e(t('mission.free_inspire')) ?></li>
            <li><?= e(t('mission.free_stem')) ?></li>
            <li><?= e(t('mission.free_lifelong')) ?></li>
            <li><?= e(t('mission.free_communities')) ?></li>
        </ul>
        <p><?= e(t('mission.why_free_p2')) ?></p>
    </section>

    <section class="mission-section" aria-labelledby="receive-heading">
        <h2 id="receive-heading"><?= e(t('mission.receive')) ?></h2>
        <p class="mission-list-intro"><?= e(t('mission.receive_intro')) ?></p>
        <ul class="mission-receive-list">
            <li><span aria-hidden="true">📚</span> <?= e(t('mission.receive_library')) ?></li>
            <li><span aria-hidden="true">🔬</span> <?= e(t('mission.receive_new')) ?></li>
            <li><span aria-hidden="true">🖨️</span> <?= e(t('mission.receive_pdfs')) ?></li>
            <li><span aria-hidden="true">📩</span> <?= e(t('mission.receive_early')) ?></li>
            <li><span aria-hidden="true">🌍</span> <?= e(t('mission.receive_collab')) ?></li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="ask-heading">
        <h2 id="ask-heading"><?= e(t('mission.ask')) ?></h2>
        <p><?= e(t('mission.ask_p1')) ?></p>
        <p class="mission-list-intro"><?= e(t('mission.ask_intro')) ?></p>
        <ul class="mission-checklist">
            <li><?= e(t('mission.ask_share')) ?></li>
            <li><?= e(t('mission.ask_newsletters')) ?></li>
            <li><?= e(t('mission.ask_recommend')) ?></li>
            <li><?= e(t('mission.ask_feedback')) ?></li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="use-heading">
        <h2 id="use-heading"><?= e(t('mission.how_use')) ?></h2>
        <p><?= e(t('mission.how_use_p1')) ?></p>
        <p class="mission-list-intro"><?= e(t('mission.how_use_intro')) ?></p>
        <ul class="mission-checklist mission-checklist-columns">
            <li><?= e(t('mission.use_stem')) ?></li>
            <li><?= e(t('mission.use_after')) ?></li>
            <li><?= e(t('mission.use_summer')) ?></li>
            <li><?= e(t('mission.use_library')) ?></li>
            <li><?= e(t('mission.use_homeschool')) ?></li>
            <li><?= e(t('mission.use_family')) ?></li>
            <li><?= e(t('mission.use_classroom')) ?></li>
            <li><?= e(t('mission.use_community')) ?></li>
        </ul>
    </section>

    <section class="mission-section" aria-labelledby="promise-heading">
        <h2 id="promise-heading"><?= e(t('mission.promise')) ?></h2>
        <p><?= e(t('mission.promise_p1')) ?></p>
        <p class="mission-highlight"><?= e(t('mission.goal_simple')) ?></p>
        <p class="mission-promise-line"><?= e(t('mission.promise_line')) ?></p>
    </section>

    <section class="mission-section" aria-labelledby="impact-heading">
        <h2 id="impact-heading"><?= e(t('mission.impact_heading')) ?></h2>
        <p><?= e(t('mission.impact_p1')) ?></p>
        <p class="mission-list-intro"><?= e(t('mission.impact_intro')) ?></p>
        <ul class="mission-checklist">
            <li><?= e(t('mission.impact_stem')) ?></li>
            <li><?= e(t('mission.impact_reading')) ?></li>
            <li><?= e(t('mission.impact_teachers')) ?></li>
            <li><?= e(t('mission.impact_worldwide')) ?></li>
        </ul>
    </section>

    <section class="mission-section mission-join" id="join-mission" aria-labelledby="join-heading">
        <h2 id="join-heading"><?= e(t('mission.join_heading')) ?></h2>
        <p><?= e(t('mission.join_p1')) ?></p>
        <p class="mission-form-note">
            <?= e(t('mission.join_email')) ?>
            <a href="mailto:<?= e($partnerEmail) ?>?subject=<?= e(rawurlencode('Science Fables Partnership')) ?>"><?= e($partnerEmail) ?></a>
        </p>
    </section>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
