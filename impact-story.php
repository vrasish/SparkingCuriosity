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
$photoCaption = t('impact.seed_caption');
$quizCaption = t('impact.quiz_caption');
$plantCaption = t('impact.plant_caption');
$matterCaption = t('impact.matter_caption');
$seedStoryName = t('impact.seed_story');
$matterStoryName = t('impact.matter_story');
$seedStoryLink = '<a href="' . e($seedStoryUrl) . '">' . e($seedStoryName) . '</a>';
$matterStoryLink = '<a href="' . e($matterStoryUrl) . '">' . e($matterStoryName) . '</a>';
$matterPhotos = [
    'matter-1.png' => t('impact.alt_matter_1'),
    'matter-2.png' => t('impact.alt_matter_2'),
    'matter-3.png' => t('impact.alt_matter_3'),
    'matter-4.png' => t('impact.alt_matter_4'),
    'matter-5.png' => t('impact.alt_matter_5'),
    'matter-6.png' => t('impact.alt_matter_6'),
];
$childrenReached = (int) ($storyMeta['children_reached'] ?? 350);
$pageTitle = $sessionMeta !== null
    ? impact_text($sessionMeta, 'title', 'Impact Session')
    : impact_text($storyMeta ?? [], 'folder_title', 'Impact Story');
$pageDescription = $sessionMeta !== null
    ? impact_text($sessionMeta, 'summary')
    : impact_text($storyMeta ?? [], 'summary');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title($pageTitle)) ?></title>
    <?php if ($storyMeta): ?>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php endif; ?>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('impact-page impact-story-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact impact-main">
    <?php if (!$storyMeta || ($sessionSlug !== '' && !$sessionMeta)): ?>
        <section class="impact-section">
            <h1 class="impact-hero-title"><?= e(t('impact.not_found')) ?></h1>
            <p><a href="<?= e(app_url('impact.php')) ?>"><?= e(t('impact.back')) ?></a></p>
        </section>
    <?php elseif (!$sessionMeta): ?>
    <?php
        $partnerName = impact_text($storyMeta, 'partner', 'Partner');
        $locationLabel = impact_text($storyMeta, 'location');
        $partnerKicker = $locationLabel !== '' ? ($partnerName . ' · ' . $locationLabel) : $partnerName;
        $sessions = is_array($storyMeta['sessions'] ?? null) ? $storyMeta['sessions'] : [];
    ?>
    <p class="impact-back">
        <a href="<?= e(app_url('impact.php')) ?>"><?= e(t('impact.all_folders')) ?></a>
    </p>

    <header class="impact-hero">
        <p class="impact-kicker"><?= e($partnerKicker) ?></p>
        <h1 class="impact-hero-title"><?= e($pageTitle) ?></h1>
        <p class="impact-hero-lead">
            <?php if ($slug === 'risa-malawi'): ?>
                <?= e(t('impact.bringing_to')) ?>
                <strong
                    class="impact-count"
                    data-count-to="<?= (int) ($storyMeta['children_reached'] ?? 27000) ?>"
                    data-count-duration="2200"
                >1</strong>
                <?= e(t('impact.students_across_schools', ['schools' => (string) ($storyMeta['member_schools'] ?? '61')])) ?>
            <?php elseif ($sessions !== []): ?>
                <?= e(t('impact.open_session')) ?>
            <?php else: ?>
                <?= e(impact_text($storyMeta, 'summary')) ?>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($slug === 'risa-malawi'): ?>
    <section class="impact-section impact-glance" aria-labelledby="risa-glance-heading">
        <h2 id="risa-glance-heading"><?= e(t('impact.glance')) ?></h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt><?= e(t('impact.students_reached_label')) ?></dt>
                <dd>
                    <span
                        class="impact-count"
                        data-count-to="<?= (int) ($storyMeta['children_reached'] ?? 27000) ?>"
                        data-count-duration="2200"
                    >1</span>
                </dd>
                <p class="impact-stat-note"><?= e(t('impact.refugee_host')) ?></p>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.member_schools')) ?></dt>
                <dd><?= e((string) ($storyMeta['member_schools'] ?? '61')) ?></dd>
                <p class="impact-stat-note"><?= e(t('impact.across_risa')) ?></p>
            </div>
        </dl>
    </section>

    <section class="impact-section" aria-labelledby="risa-story-heading">
        <h2 id="risa-story-heading"><?= e(t('impact.partnership_story')) ?></h2>
        <?php
        $risaPartnerLink = '<a href="https://risamalawi.org/" target="_blank" rel="noopener noreferrer">'
            . e(t('impact.risa_partner_name'))
            . '</a>';
        ?>
        <p><?= str_replace(':partner', $risaPartnerLink, e(t('impact.risa_p1'))) ?></p>
        <p><?= e(t('impact.risa_p2')) ?></p>
        <p><?= e(t('impact.risa_p3')) ?></p>
        <p><?= e(t('impact.risa_p4')) ?></p>
    </section>
    <?php endif; ?>

    <?php
    $isPressFolder = (($storyMeta['kind'] ?? '') === 'press');
    $article = is_array($storyMeta['article'] ?? null) ? $storyMeta['article'] : null;
    if ($isPressFolder && $article !== null):
        $articleHeadline = impact_text($article, 'headline');
        $articleDate = impact_text($article, 'date_label');
        $articleCta = impact_text($article, 'cta_label', 'Read more');
        $articleUrl = trim((string) ($article['external_url'] ?? ''));
        $articleParagraphs = [];
        if (function_exists('is_spanish') && is_spanish() && !empty($article['paragraphs_es']) && is_array($article['paragraphs_es'])) {
            $articleParagraphs = $article['paragraphs_es'];
        } elseif (!empty($article['paragraphs']) && is_array($article['paragraphs'])) {
            $articleParagraphs = $article['paragraphs'];
        }
        $articleCover = trim((string) ($storyMeta['cover'] ?? ''));
    ?>
    <section class="impact-section impact-press" aria-labelledby="ngcp-press-heading">
        <?php if ($articleCover !== ''): ?>
            <figure class="impact-feature impact-press-cover">
                <img
                    src="<?= e(asset_url($articleCover)) ?>"
                    alt="<?= e($articleHeadline !== '' ? $articleHeadline : impact_text($storyMeta, 'folder_title')) ?>"
                    width="1200"
                    height="800"
                    loading="eager"
                >
            </figure>
        <?php endif; ?>
        <h2 id="ngcp-press-heading"><?= e($articleHeadline) ?></h2>
        <?php if ($articleDate !== ''): ?>
            <p class="impact-press-date"><?= e($articleDate) ?></p>
        <?php endif; ?>
        <?php foreach ($articleParagraphs as $paragraph): ?>
            <?php
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }
            ?>
            <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
        <?php if ($articleUrl !== ''): ?>
            <p class="impact-press-cta">
                <a
                    class="btn btn-primary"
                    href="<?= e($articleUrl) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                ><?= e($articleCta) ?></a>
            </p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php
    $highlights = is_array($storyMeta['highlights'] ?? null) ? $storyMeta['highlights'] : [];
    if ($highlights !== [] && $slug !== 'risa-malawi'):
    ?>
    <section class="impact-section" aria-labelledby="highlights-heading">
        <h2 id="highlights-heading"><?= e(t('impact.highlights')) ?></h2>
        <div class="impact-highlights">
            <?php foreach ($highlights as $highlight): ?>
                <?php
                $highlightImage = trim((string) ($highlight['image'] ?? ''));
                $highlightCaption = impact_text($highlight, 'caption');
                if ($highlightImage === '') {
                    continue;
                }
                ?>
                <figure class="impact-feature">
                    <img
                        src="<?= e(asset_url($highlightImage)) ?>"
                        alt="<?= e($highlightCaption !== '' ? $highlightCaption : t('impact.alt_highlight')) ?>"
                        width="1600"
                        height="900"
                        loading="eager"
                    >
                    <?php if ($highlightCaption !== ''): ?>
                        <figcaption><?= e($highlightCaption) ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$isPressFolder): ?>
    <section class="impact-section" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading"><?= $slug === 'risa-malawi' ? e(t('impact.risa_folders')) : e(t('impact.session_folders')) ?></h2>
        <?php if ($sessions === []): ?>
            <p class="impact-empty-sessions"><?= e(t('impact.empty_sessions')) ?></p>
        <?php else: ?>
        <ul class="impact-folder-list">
            <?php foreach ($sessions as $session): ?>
                <?php
                $folderSessionSlug = (string) ($session['slug'] ?? '');
                $folderTitle = impact_text($session, 'title');
                $folderSubtitle = impact_text($session, 'subtitle');
                $folderSummary = impact_text($session, 'summary');
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
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php else: ?>
    <?php
        $isArtofHope = in_array($slug, ['artofhope-dzaleka-malawi', 'art-for-hope-dzaleka-malawi'], true)
            || (($storyMeta['slug'] ?? '') === 'artofhope-dzaleka-malawi');
        $isLoyolaStem = $slug === 'loyola-elementary-los-altos' && $sessionSlug === 'stem-seed-session';
        $isOnlineMatter = $isArtofHope && $sessionSlug === 'online-states-of-matter';
        $isComfortPrimary = $slug === 'risa-malawi' && $sessionSlug === 'comfort-primary-school';
        $isRisaTeacherTraining = $slug === 'risa-malawi' && $sessionSlug === 'teacher-training';
        $isRisaClassrooms = $slug === 'risa-malawi' && $sessionSlug === 'risa-classrooms';
        $isRisaSession = $isComfortPrimary || $isRisaTeacherTraining || $isRisaClassrooms;
    ?>
    <p class="impact-back">
        <a href="<?= e(impact_story_url($slug)) ?>"><?= e(t('impact.partner_sessions', ['partner' => impact_text($storyMeta, 'partner', 'Partner')])) ?></a>
    </p>

    <header class="impact-hero">
        <p class="impact-kicker"><?= e(impact_text($sessionMeta, 'subtitle', t('impact.kicker'))) ?></p>
        <h1 class="impact-hero-title"><?= e($pageTitle) ?></h1>
        <?php if ($isRisaTeacherTraining): ?>
        <p class="impact-hero-lead"><?= e(t('impact.risa_training_hero')) ?></p>
        <?php elseif ($isRisaClassrooms): ?>
        <p class="impact-hero-lead"><?= e(t('impact.risa_videos_intro')) ?></p>
        <?php elseif ($isComfortPrimary): ?>
        <p class="impact-hero-lead"><?= e(t('impact.comfort_hero')) ?></p>
        <?php elseif ($isLoyolaStem): ?>
        <p class="impact-hero-lead">
            <?= str_replace(
                ':count',
                '<strong class="impact-count" data-count-to="' . $childrenReached . '" data-count-duration="1800">1</strong>',
                e(t('impact.loyola_hero'))
            ) ?>
        </p>
        <?php elseif ($isArtofHope && $sessionSlug === 'seed-germination'): ?>
        <p class="impact-hero-lead">
            <?= str_replace(
                ':count',
                '<strong class="impact-count" data-count-to="' . $childrenReached . '" data-count-duration="2200">1</strong>',
                e(t('impact.seed_hero'))
            ) ?>
        </p>
        <?php elseif ($isArtofHope && $sessionSlug === 'matter-mystery'): ?>
        <p class="impact-hero-lead">
            <?= str_replace(':story', $matterStoryLink, e(t('impact.matter_hero'))) ?>
        </p>
        <?php elseif ($isOnlineMatter): ?>
        <p class="impact-hero-lead">
            <?= e(t('impact.online_hero')) ?>
        </p>
        <?php else: ?>
        <p class="impact-hero-lead">
            <?= e(impact_text($sessionMeta ?? [], 'summary', impact_text($storyMeta, 'summary'))) ?>
        </p>
        <?php endif; ?>
    </header>

    <?php if ($isOnlineMatter): ?>
    <figure class="impact-feature impact-feature-compact">
        <img
            src="<?= e(asset_url($mediaBase . '/online-matter-1-full.jpg')) ?>"
            alt="<?= e(t('impact.alt_online_session')) ?>"
            width="887"
            height="1024"
            loading="eager"
        >
        <figcaption><?= e(t('impact.online_caption')) ?></figcaption>
    </figure>

    <div class="impact-feature impact-feature-pair">
        <div class="impact-media-grid impact-media-grid-pair">
            <figure class="impact-media-card">
                <div class="impact-media-item">
                    <img
                        src="<?= e(asset_url($mediaBase . '/online-matter-2-full.jpg')) ?>"
                        alt="<?= e(t('impact.alt_online_demo')) ?>"
                        width="1024"
                        height="576"
                        loading="lazy"
                    >
                </div>
                <figcaption><?= e(t('impact.online_demo_caption')) ?></figcaption>
            </figure>
            <figure class="impact-media-card">
                <div class="impact-media-item">
                    <img
                        src="<?= e(asset_url($mediaBase . '/online-matter-3-full.jpg')) ?>"
                        alt="<?= e(t('impact.alt_online_group')) ?>"
                        width="1024"
                        height="574"
                        loading="lazy"
                    >
                </div>
                <figcaption><?= e(t('impact.online_group_caption')) ?></figcaption>
            </figure>
        </div>
    </div>

    <section class="impact-section" aria-labelledby="online-matter-heading">
        <h2 id="online-matter-heading"><?= e(t('impact.online_heading')) ?></h2>
        <p><?= e(t('impact.online_p1')) ?></p>
        <p><?= e(t('impact.online_p2')) ?></p>
        <?php
            $onlineInstagramUrl = trim((string) ($sessionMeta['instagram_url'] ?? ''));
        ?>
        <?php if ($onlineInstagramUrl !== ''): ?>
        <p class="impact-external-note"><?= e(t('impact.online_instagram_note')) ?></p>
        <p class="impact-cta-row">
            <a
                href="<?= e($onlineInstagramUrl) ?>"
                class="btn btn-primary"
                target="_blank"
                rel="noopener noreferrer"
            ><?= e(t('impact.online_instagram')) ?></a>
        </p>
        <?php endif; ?>
    </section>

    <figure class="impact-feature impact-feature-compact">
        <img
            src="<?= e(asset_url($mediaBase . '/online-matter-quiz.jpg')) ?>"
            alt="<?= e(t('impact.online_quiz_caption')) ?>"
            width="1024"
            height="571"
            loading="lazy"
        >
        <figcaption><?= e(t('impact.online_quiz_caption')) ?></figcaption>
    </figure>

    <figure class="impact-feature impact-feature-compact">
        <img
            src="<?= e(asset_url($mediaBase . '/online-matter-quiz-complete.jpg')) ?>"
            alt="<?= e(t('impact.online_complete_caption')) ?>"
            width="1024"
            height="475"
            loading="lazy"
        >
        <figcaption><?= e(t('impact.online_complete_caption')) ?></figcaption>
    </figure>

    <section class="impact-section impact-glance" aria-labelledby="online-matter-glance-heading">
        <h2 id="online-matter-glance-heading"><?= e(t('impact.session_glance')) ?></h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_format')) ?></dt>
                <dd><?= e(t('impact.online_format')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_topic')) ?></dt>
                <dd><?= e(t('impact.topic_matter')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_story')) ?></dt>
                <dd><?= $matterStoryLink ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_partner')) ?></dt>
                <dd>ArtofHope</dd>
            </div>
            <div class="impact-stat impact-stat-wide">
                <dt><?= e(t('impact.stat_location')) ?></dt>
                <dd><?= e(t('impact.location_dzaleka_virtual')) ?></dd>
            </div>
        </dl>
    </section>
    <?php endif; ?>

    <?php if ($isRisaTeacherTraining): ?>
    <section class="impact-section" aria-labelledby="risa-training-heading">
        <h2 id="risa-training-heading"><?= e(t('impact.teacher_training')) ?></h2>
        <p><?= e(t('impact.risa_p3')) ?></p>
        <p><?= e(t('impact.risa_p4')) ?></p>
    </section>
    <?php
        $trainingHighlights = is_array($sessionMeta['highlights'] ?? null) ? $sessionMeta['highlights'] : [];
        if ($trainingHighlights !== []):
    ?>
    <section class="impact-section" aria-labelledby="risa-training-photos-heading">
        <h2 id="risa-training-photos-heading"><?= e(t('impact.highlights')) ?></h2>
        <div class="impact-highlights">
            <?php foreach ($trainingHighlights as $highlight): ?>
                <?php
                $highlightImage = trim((string) ($highlight['image'] ?? ''));
                $highlightCaption = impact_text($highlight, 'caption');
                if ($highlightImage === '') {
                    continue;
                }
                ?>
                <figure class="impact-feature">
                    <img
                        src="<?= e(asset_url($highlightImage)) ?>"
                        alt="<?= e($highlightCaption !== '' ? $highlightCaption : t('impact.alt_highlight')) ?>"
                        width="1600"
                        height="900"
                        loading="eager"
                    >
                    <?php if ($highlightCaption !== ''): ?>
                        <figcaption><?= e($highlightCaption) ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($isRisaClassrooms): ?>
    <section class="impact-section impact-media" aria-labelledby="risa-videos-heading">
        <h2 id="risa-videos-heading"><?= e(t('impact.risa_videos_heading')) ?></h2>
        <p class="impact-media-intro"><?= e(t('impact.risa_videos_intro')) ?></p>
        <?php
            $risaClassroomVideos = is_array($sessionMeta['videos'] ?? null) ? $sessionMeta['videos'] : [];
        ?>
        <div class="impact-media-grid impact-media-grid-3 impact-media-grid-risa">
            <?php foreach ($risaClassroomVideos as $video): ?>
                <?php
                $videoFile = trim((string) ($video['file'] ?? ''));
                $videoPoster = trim((string) ($video['poster'] ?? ''));
                $videoOrientation = trim((string) ($video['orientation'] ?? 'portrait'));
                if ($videoFile === '') {
                    continue;
                }
                ?>
                <figure class="impact-media-item impact-media-video impact-media-video-<?= e($videoOrientation === 'landscape' ? 'landscape' : 'portrait') ?>">
                    <video
                        controls
                        playsinline
                        preload="metadata"
                        <?php if ($videoPoster !== ''): ?>
                        poster="<?= e(asset_url($mediaBase . '/' . $videoPoster)) ?>"
                        <?php endif; ?>
                    >
                        <source src="<?= e(asset_url($mediaBase . '/' . $videoFile)) ?>" type="video/mp4">
                    </video>
                </figure>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($isComfortPrimary): ?>
    <section class="impact-section" aria-labelledby="comfort-session-heading">
        <h2 id="comfort-session-heading"><?= e(t('impact.comfort_heading')) ?></h2>
        <p><?= e(t('impact.comfort_p1')) ?></p>
        <p><?= e(t('impact.comfort_p2')) ?></p>
        <p><?= e(t('impact.comfort_p3')) ?></p>
    </section>

    <?php
        $comfortVideos = is_array($sessionMeta['videos'] ?? null) ? $sessionMeta['videos'] : [];
        if ($comfortVideos !== []):
    ?>
    <section class="impact-section impact-media" aria-labelledby="comfort-videos-heading">
        <h2 id="comfort-videos-heading"><?= e(t('impact.comfort_videos_heading')) ?></h2>
        <p class="impact-media-intro"><?= e(t('impact.comfort_videos_intro')) ?></p>
        <div class="impact-media-grid impact-media-grid-3 impact-media-grid-risa">
            <?php foreach ($comfortVideos as $video): ?>
                <?php
                $videoFile = trim((string) ($video['file'] ?? ''));
                $videoPoster = trim((string) ($video['poster'] ?? ''));
                $videoOrientation = trim((string) ($video['orientation'] ?? 'portrait'));
                if ($videoFile === '') {
                    continue;
                }
                ?>
                <figure class="impact-media-item impact-media-video impact-media-video-<?= e($videoOrientation === 'landscape' ? 'landscape' : 'portrait') ?>">
                    <video
                        controls
                        playsinline
                        preload="metadata"
                        <?php if ($videoPoster !== ''): ?>
                        poster="<?= e(asset_url($mediaBase . '/' . $videoPoster)) ?>"
                        <?php endif; ?>
                    >
                        <source src="<?= e(asset_url($mediaBase . '/' . $videoFile)) ?>" type="video/mp4">
                    </video>
                </figure>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($isLoyolaStem): ?>
    <figure class="impact-feature impact-feature-compact">
        <img
            src="<?= e(asset_url($mediaBase . '/session.jpg')) ?>"
            alt="<?= e(t('impact.alt_loyola_student')) ?>"
            width="1024"
            height="768"
            loading="eager"
        >
        <figcaption><?= e(t('impact.loyola_caption')) ?></figcaption>
    </figure>

    <section class="impact-section" aria-labelledby="loyola-session-heading">
        <h2 id="loyola-session-heading"><?= e(t('impact.loyola_heading')) ?></h2>
        <p><?= str_replace(':story', $seedStoryLink, e(t('impact.loyola_p1'))) ?></p>
        <p><?= e(t('impact.loyola_p2')) ?></p>
    </section>

    <section class="impact-section impact-glance" aria-labelledby="loyola-impact-heading">
        <h2 id="loyola-impact-heading"><?= e(t('impact.loyola_impact')) ?></h2>
        <ul class="impact-bullet-list">
            <li><?= e(t('impact.loyola_b1')) ?></li>
            <li><?= e(t('impact.loyola_b2')) ?></li>
            <li><?= e(t('impact.loyola_b3')) ?></li>
            <li><?= e(t('impact.loyola_b4')) ?></li>
            <li><?= e(t('impact.loyola_b5')) ?></li>
            <li><?= e(t('impact.loyola_b6')) ?></li>
            <li><?= e(t('impact.loyola_b7')) ?></li>
        </ul>
    </section>

    <section class="impact-section" aria-labelledby="loyola-feedback-heading">
        <h2 id="loyola-feedback-heading"><?= e(t('impact.loyola_feedback')) ?></h2>
        <figure class="impact-feature impact-feature-compact">
            <img
                src="<?= e(asset_url($mediaBase . '/teacher-feedback.jpg')) ?>"
                alt="<?= e(t('impact.loyola_feedback_caption')) ?>"
                width="1024"
                height="923"
                loading="lazy"
            >
            <figcaption>
                <?= e(t('impact.loyola_feedback_caption')) ?>
            </figcaption>
        </figure>
    </section>

    <section class="impact-section" aria-labelledby="loyola-continue-heading">
        <h2 id="loyola-continue-heading"><?= e(t('impact.loyola_continue')) ?></h2>
        <p><?= e(t('impact.loyola_continue_p')) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($isArtofHope && $sessionSlug === 'seed-germination'): ?>
    <figure class="impact-feature">
        <img
            src="<?= e(asset_url($mediaBase . '/session-1.png')) ?>"
            alt="<?= e(t('impact.alt_seed_projection')) ?>"
            width="1600"
            height="900"
            loading="eager"
        >
        <figcaption><?= e($photoCaption) ?></figcaption>
    </figure>

    <section class="impact-section" aria-labelledby="session-heading">
        <h2 id="session-heading"><?= e(t('impact.seed_session_heading')) ?></h2>
        <p><?= str_replace(':story', $seedStoryLink, e(t('impact.seed_session_p1'))) ?></p>
        <p><?= e(t('impact.seed_session_p2')) ?></p>
        <p><?= e(t('impact.seed_session_p3')) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($isArtofHope && $sessionSlug === 'seed-germination'): ?>
    <section class="impact-section impact-glance" aria-labelledby="glance-heading">
        <h2 id="glance-heading"><?= e(t('impact.glance')) ?></h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt><?= e(t('impact.students_reached_label')) ?></dt>
                <dd>
                    <span
                        class="impact-count"
                        data-count-to="<?= $childrenReached ?>"
                        data-count-duration="2200"
                    >1</span>
                </dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.students_per_group')) ?></dt>
                <dd><?= e(t('impact.about_n', ['n' => '30'])) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_story')) ?></dt>
                <dd><?= $seedStoryLink ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_topic')) ?></dt>
                <dd><?= e(t('impact.topic_seed')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_activities')) ?></dt>
                <dd><?= e(t('impact.activities_seed')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_partner')) ?></dt>
                <dd>ArtofHope</dd>
            </div>
            <div class="impact-stat impact-stat-wide">
                <dt><?= e(t('impact.stat_location')) ?></dt>
                <dd><?= e(t('impact.location_dzaleka')) ?></dd>
            </div>
        </dl>
    </section>
    <?php endif; ?>

    <?php if ($isArtofHope && $sessionSlug === 'seed-germination'): ?>
    <section class="impact-section impact-media" aria-labelledby="media-heading">
        <h2 id="media-heading"><?= e(t('impact.from_classroom')) ?></h2>
        <p class="impact-media-intro"><?= e($photoCaption) ?></p>
        <div class="impact-media-grid">
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/session-2.png')) ?>"
                    alt="<?= e(t('impact.alt_seed_floor')) ?>"
                    width="1200"
                    height="900"
                    loading="lazy"
                >
            </figure>
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/session-3.png')) ?>"
                    alt="<?= e(t('impact.alt_seed_projector')) ?>"
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
        <h2 id="quiz-media-heading"><?= e(t('impact.quiz_time')) ?></h2>
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
        <h2 id="plant-media-heading"><?= e(t('impact.hands_on_planting')) ?></h2>
        <p class="impact-media-intro"><?= e($plantCaption) ?></p>
        <div class="impact-media-grid impact-media-grid-3">
            <?php for ($i = 1; $i <= 9; $i++): ?>
            <figure class="impact-media-item">
                <img
                    src="<?= e(asset_url($mediaBase . '/plant-' . $i . '.png')) ?>"
                    alt="<?= e(t('impact.alt_planting')) ?>"
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

    <?php if ($isArtofHope && $sessionSlug === 'matter-mystery'): ?>
    <section class="impact-section impact-glance" aria-labelledby="matter-glance-heading">
        <h2 id="matter-glance-heading"><?= e(t('impact.session_glance')) ?></h2>
        <dl class="impact-stats">
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_story')) ?></dt>
                <dd><?= $matterStoryLink ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_topic')) ?></dt>
                <dd><?= e(t('impact.topic_matter')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_demo')) ?></dt>
                <dd><?= e(t('impact.demo_ice')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_activities')) ?></dt>
                <dd><?= e(t('impact.activities_matter')) ?></dd>
            </div>
            <div class="impact-stat">
                <dt><?= e(t('impact.stat_partner')) ?></dt>
                <dd>ArtofHope</dd>
            </div>
            <div class="impact-stat impact-stat-wide">
                <dt><?= e(t('impact.stat_location')) ?></dt>
                <dd><?= e(t('impact.location_dzaleka')) ?></dd>
            </div>
        </dl>
    </section>

    <section class="impact-section impact-media" aria-labelledby="matter-heading">
        <h2 id="matter-heading"><?= e(t('impact.matter_session_heading')) ?></h2>
        <p><?= str_replace(':story', $matterStoryLink, e(t('impact.matter_p1'))) ?></p>
        <p><?= e(t('impact.matter_p2')) ?></p>
        <p><?= e(t('impact.matter_p3')) ?></p>
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
        <h2 id="cta-heading"><?= e(t('impact.cta_heading')) ?></h2>
        <p><?= e(t('impact.cta_lead')) ?></p>
        <div class="impact-cta-row">
            <?php if ($isLoyolaStem || ($isArtofHope && $sessionSlug === 'seed-germination')): ?>
            <a href="<?= e($seedStoryUrl) ?>" class="btn btn-primary"><?= e(t('impact.read_seed')) ?></a>
            <?php elseif ($isArtofHope && ($sessionSlug === 'matter-mystery' || $sessionSlug === 'online-states-of-matter')): ?>
            <a href="<?= e($matterStoryUrl) ?>" class="btn btn-primary"><?= e(t('impact.read_matter')) ?></a>
            <?php elseif ($isRisaSession): ?>
            <a href="<?= e(impact_story_url('risa-malawi')) ?>" class="btn btn-primary"><?= e(impact_text($storyMeta, 'folder_title', 'RISA')) ?></a>
            <?php endif; ?>
            <?php
                $ctaInstagramUrl = ($isOnlineMatter ?? false)
                    ? trim((string) ($sessionMeta['instagram_url'] ?? ''))
                    : '';
            ?>
            <?php if ($ctaInstagramUrl !== ''): ?>
            <a
                href="<?= e($ctaInstagramUrl) ?>"
                class="btn btn-outline"
                target="_blank"
                rel="noopener noreferrer"
            ><?= e(t('impact.online_instagram')) ?></a>
            <?php endif; ?>
            <a href="<?= e(app_url('mission.php')) ?>" class="btn btn-outline"><?= e(t('impact.our_mission')) ?></a>
            <a href="<?= e(app_url('get-involved.php')) ?>#partner-with-us" class="btn btn-outline"><?= e(t('impact.partner_with_us')) ?></a>
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
