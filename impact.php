<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/impact-lib.php';

$studentsReached = impact_total_children_reached();
$schoolsReached = impact_total_schools();
$mediaBase = 'assets/impact/collage-2026';

// Temporarily show only NGCP below the collage.
$stories = array_values(array_filter(
    impact_stories(),
    static fn(array $story): bool => ($story['slug'] ?? '') === 'national-girls-collaborative-project'
));

$collagePhotos = [
    ['file' => 'photo-classroom-group.jpg', 'alt_key' => 'impact.collage_alt_classroom', 'title_key' => 'impact.media_title_classroom', 'caption_key' => 'impact.media_caption_classroom'],
    ['file' => 'photo-matter-projection.png', 'alt_key' => 'impact.collage_alt_matter', 'title_key' => 'impact.media_title_matter', 'caption_key' => 'impact.media_caption_matter'],
    ['file' => 'photo-celebration.jpg', 'alt_key' => 'impact.collage_alt_celebration', 'title_key' => 'impact.media_title_celebration', 'caption_key' => 'impact.media_caption_celebration'],
    ['file' => 'photo-loyola-presentation.jpg', 'alt_key' => 'impact.collage_alt_loyola', 'title_key' => 'impact.media_title_loyola', 'caption_key' => 'impact.media_caption_loyola'],
    ['file' => 'photo-risa-training-zoom.jpg', 'alt_key' => 'impact.collage_alt_training', 'title_key' => 'impact.media_title_training', 'caption_key' => 'impact.media_caption_training'],
    ['file' => 'photo-garden-tools.png', 'alt_key' => 'impact.collage_alt_garden', 'title_key' => 'impact.media_title_garden', 'caption_key' => 'impact.media_caption_garden'],
    ['file' => 'photo-laptop-session.jpg', 'alt_key' => 'impact.collage_alt_laptop', 'title_key' => 'impact.media_title_laptop', 'caption_key' => 'impact.media_caption_laptop'],
    ['file' => 'photo-soil-circle.png', 'alt_key' => 'impact.collage_alt_soil_circle', 'title_key' => 'impact.media_title_soil_circle', 'caption_key' => 'impact.media_caption_soil_circle'],
    ['file' => 'photo-soil-pour.png', 'alt_key' => 'impact.collage_alt_soil_pour', 'title_key' => 'impact.media_title_soil_pour', 'caption_key' => 'impact.media_caption_soil_pour'],
    ['file' => 'photo-matter-demo.jpg', 'alt_key' => 'impact.collage_alt_matter_demo', 'title_key' => 'impact.media_title_matter_demo', 'caption_key' => 'impact.media_caption_matter_demo'],
];

$collageVideos = [
    ['file' => 'quiz-clip-2.mp4', 'poster' => 'quiz-clip-2.jpg', 'orientation' => 'landscape', 'title_key' => 'impact.media_title_quiz_2', 'caption_key' => 'impact.media_caption_quiz_2'],
    ['file' => 'risa-clip-2.mp4', 'poster' => 'risa-clip-2.jpg', 'orientation' => 'portrait', 'title_key' => 'impact.media_title_risa_2', 'caption_key' => 'impact.media_caption_risa_2'],
    ['file' => 'risa-clip-3.mp4', 'poster' => 'risa-clip-3.jpg', 'orientation' => 'portrait', 'title_key' => 'impact.media_title_risa_3', 'caption_key' => 'impact.media_caption_risa_3'],
];
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, viewport-fit=cover">
    <title><?= e(site_page_title(t('impact.title'))) ?></title>
    <meta name="description" content="<?= e(t('impact.lead')) ?>">
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('impact-page impact-index-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact impact-main">
    <header class="impact-hero">
        <p class="impact-kicker"><?= e(t('impact.kicker')) ?></p>
        <div class="impact-hero-title-row">
            <h1 class="impact-hero-title"><?= e(t('impact.title')) ?></h1>
            <?php render_current_impact_badge(); ?>
        </div>
        <p class="impact-hero-lead impact-hero-writeup">
            <?= e(t('impact.support_writeup', [
                'schools' => number_format($schoolsReached),
                'students' => number_format($studentsReached),
            ])) ?>
        </p>
    </header>

    <?php if ($stories !== []): ?>
    <section class="impact-section" aria-labelledby="folders-heading">
        <h2 id="folders-heading"><?= e(t('impact.folders')) ?></h2>
        <ul class="impact-folder-list">
            <?php foreach ($stories as $story): ?>
                <?php
                $slug = (string) ($story['slug'] ?? '');
                $title = impact_text($story, 'folder_title');
                $summary = impact_text($story, 'summary');
                $cover = (string) ($story['cover'] ?? '');
                $dateLabel = impact_text($story, 'date_label');
                $reached = (int) ($story['children_reached'] ?? 0);
                ?>
                <li>
                    <a class="impact-folder" href="<?= e(impact_story_url($slug)) ?>">
                        <span class="impact-folder-tab" aria-hidden="true"></span>
                        <span class="impact-folder-body">
                            <?php if ($cover !== ''): ?>
                                <img
                                    class="impact-folder-thumb"
                                    src="<?= e(asset_url($cover)) ?>"
                                    alt=""
                                    width="320"
                                    height="200"
                                    loading="lazy"
                                >
                            <?php endif; ?>
                            <span class="impact-folder-copy">
                                <span class="impact-folder-date"><?= e($dateLabel) ?></span>
                                <span class="impact-folder-title"><?= e($title) ?></span>
                                <span class="impact-folder-summary"><?= e($summary) ?></span>
                                <?php if ($reached > 0): ?>
                                    <span class="impact-folder-metric">
                                        <span
                                            class="impact-count"
                                            data-count-to="<?= $reached ?>"
                                            data-count-duration="1800"
                                        >1</span>
                                        <?= e(t('impact.students_reached')) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="impact-section impact-collage-section" aria-labelledby="collage-heading">
        <h2 id="collage-heading"><?= e(t('impact.collage_heading')) ?></h2>
        <p class="impact-media-intro"><?= e(t('impact.collage_intro')) ?></p>
        <div class="impact-carousel" data-impact-carousel>
            <button type="button" class="impact-carousel-nav impact-carousel-prev" data-carousel-prev aria-label="<?= e(t('impact.carousel_prev')) ?>">‹</button>
            <div class="impact-carousel-viewport">
                <div class="impact-carousel-track">
                    <?php foreach ($collagePhotos as $index => $item): ?>
                        <?php
                        $file = trim((string) ($item['file'] ?? ''));
                        if ($file === '') {
                            continue;
                        }
                        $src = asset_url($mediaBase . '/' . $file);
                        $altKey = (string) ($item['alt_key'] ?? '');
                        $alt = $altKey !== '' ? t($altKey) : t('impact.collage_alt_default');
                        $title = t((string) ($item['title_key'] ?? 'impact.collage_heading'));
                        $caption = t((string) ($item['caption_key'] ?? 'impact.collage_intro'));
                        $isActive = $index === 0;
                        ?>
                        <figure
                            class="impact-carousel-slide impact-collage-photo<?= $isActive ? ' is-active' : '' ?>"
                            data-carousel-slide
                            data-title="<?= e($title) ?>"
                            data-caption="<?= e($caption) ?>"
                            <?= $isActive ? '' : 'hidden' ?>
                        >
                            <img
                                src="<?= e($src) ?>"
                                alt="<?= e($alt) ?>"
                                width="1200"
                                height="800"
                                <?= $isActive ? '' : 'loading="lazy"' ?>
                            >
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" class="impact-carousel-nav impact-carousel-next" data-carousel-next aria-label="<?= e(t('impact.carousel_next')) ?>">›</button>
            <div class="impact-carousel-copy">
                <h3 data-carousel-title><?= e(t((string) ($collagePhotos[0]['title_key'] ?? 'impact.collage_heading'))) ?></h3>
                <p data-carousel-caption><?= e(t((string) ($collagePhotos[0]['caption_key'] ?? 'impact.collage_intro'))) ?></p>
            </div>
            <div class="impact-carousel-dots" data-carousel-dots aria-hidden="true"></div>
        </div>
    </section>

    <section class="impact-section impact-videos-section" aria-labelledby="videos-heading">
        <h2 id="videos-heading"><?= e(t('impact.videos_heading')) ?></h2>
        <p class="impact-media-intro"><?= e(t('impact.videos_intro')) ?></p>
        <div class="impact-carousel impact-carousel-videos" data-impact-carousel>
            <button type="button" class="impact-carousel-nav impact-carousel-prev" data-carousel-prev aria-label="<?= e(t('impact.carousel_prev')) ?>">‹</button>
            <div class="impact-carousel-viewport">
                <div class="impact-carousel-track">
                    <?php foreach ($collageVideos as $index => $item): ?>
                        <?php
                        $file = trim((string) ($item['file'] ?? ''));
                        if ($file === '') {
                            continue;
                        }
                        $poster = trim((string) ($item['poster'] ?? ''));
                        $orientation = trim((string) ($item['orientation'] ?? 'landscape'));
                        $isPortrait = $orientation === 'portrait';
                        $src = asset_url($mediaBase . '/' . $file);
                        $posterUrl = $poster !== '' ? asset_url($mediaBase . '/' . $poster) : '';
                        $title = t((string) ($item['title_key'] ?? 'impact.videos_heading'));
                        $caption = t((string) ($item['caption_key'] ?? 'impact.videos_intro'));
                        $isActive = $index === 0;
                        ?>
                        <figure
                            class="impact-carousel-slide impact-collage-video<?= $isPortrait ? ' is-portrait' : ' is-landscape' ?><?= $isActive ? ' is-active' : '' ?>"
                            data-carousel-slide
                            data-title="<?= e($title) ?>"
                            data-caption="<?= e($caption) ?>"
                            <?= $isActive ? '' : 'hidden' ?>
                        >
                            <video
                                controls
                                playsinline
                                preload="<?= $isActive ? 'metadata' : 'none' ?>"
                                <?php if ($posterUrl !== ''): ?>
                                poster="<?= e($posterUrl) ?>"
                                <?php endif; ?>
                            >
                                <source src="<?= e($src) ?>" type="video/mp4">
                            </video>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" class="impact-carousel-nav impact-carousel-next" data-carousel-next aria-label="<?= e(t('impact.carousel_next')) ?>">›</button>
            <div class="impact-carousel-copy">
                <h3 data-carousel-title><?= e(t((string) ($collageVideos[0]['title_key'] ?? 'impact.videos_heading'))) ?></h3>
                <p data-carousel-caption><?= e(t((string) ($collageVideos[0]['caption_key'] ?? 'impact.videos_intro'))) ?></p>
            </div>
            <div class="impact-carousel-dots" data-carousel-dots aria-hidden="true"></div>
        </div>
    </section>
</main>

<?php render_site_footer(true); ?>
<script src="<?= e(asset_url('impact.js')) ?>"></script>
</body>
</html>
