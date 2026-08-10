<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/impact-lib.php';

$stories = impact_stories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Impact')) ?></title>
    <meta name="description" content="See SciFables in action around the world — partner sessions, stories, quizzes, and hands-on science with children.">
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('impact-page impact-index-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact impact-main">
    <header class="impact-hero">
        <p class="impact-kicker">SciFables in Action</p>
        <div class="impact-hero-title-row">
            <h1 class="impact-hero-title">Our Impact</h1>
            <?php render_current_impact_badge(); ?>
        </div>
        <p class="impact-hero-lead">
            Open a folder to see how partners are bringing SciFables stories, quizzes, and hands-on science to children around the world.
        </p>
    </header>

    <section class="impact-section" aria-labelledby="folders-heading">
        <h2 id="folders-heading">Impact Folders</h2>
        <ul class="impact-folder-list">
            <?php foreach ($stories as $story): ?>
                <?php
                $slug = (string) ($story['slug'] ?? '');
                $title = (string) ($story['folder_title'] ?? '');
                $summary = (string) ($story['summary'] ?? '');
                $cover = (string) ($story['cover'] ?? '');
                $dateLabel = (string) ($story['date_label'] ?? '');
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
                                        students reached
                                    </span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
<?php render_site_footer(true); ?>
<script src="<?= e(asset_url('impact.js')) ?>"></script>
</body>
</html>
