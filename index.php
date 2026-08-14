<?php
require_once __DIR__ . '/cache-lib.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/favorites-lib.php';

$pageCache = home_page_cache_get();

$latestBooks = [];
$topPickBooks = [];
$dbError = null;
$ratingSummaries = [];

if ($pageCache !== null) {
    $latestBooks = $pageCache['books'];
    $topPickBooks = $pageCache['top_picks'];
    $ratingSummaries = $pageCache['ratings'];
} else {
    cart_bootstrap();

    try {
        $pdo = stories_connect();
        $latestBooks = home_fetch_books_by_ids($pdo, home_featured_story_ids());
        $topPickBooks = home_fetch_books_by_ids($pdo, home_top_pick_story_ids());
    } catch (PDOException $ex) {
        $dbError = 'Stories could not be loaded right now.';
        error_log($ex->getMessage());
    }

    $allBookIds = array_merge(
        array_column($latestBooks, 'book_id'),
        array_column($topPickBooks, 'book_id')
    );
    $ratingSummaries = get_book_rating_summaries(stories_connect(), $allBookIds);
    home_page_cache_set($latestBooks, $ratingSummaries, $topPickBooks);
}

cart_bootstrap();
release_session_lock();

$topicTiles = home_topic_tiles();
$latestRowOne = array_slice($latestBooks, 0, 3);
$latestRowTwo = array_slice($latestBooks, 3);

/**
 * @param array<string, mixed> $book
 * @param array<int, array<string, mixed>> $ratingSummaries
 */
function render_home_story_card(array $book, array $ratingSummaries): void
{
    $cover = cover_image_src($book['cover_image_url'] ?? null, $book['title']);
    ?>
    <article class="home-story-card">
        <div class="home-story-cover-wrap">
            <?php render_story_favorite_button((int) $book['book_id'], app_url('index.php')); ?>
            <a href="<?= e(story_book_url((int) $book['book_id'])) ?>" class="home-story-cover-link">
                <img src="<?= e($cover) ?>" alt="Cover of <?= e($book['title']) ?>" class="home-story-img" loading="lazy">
            </a>
        </div>
        <div class="home-story-body">
            <?php render_story_card_bubbles($book['categories'] ?? '', $book['story_topic'] ?? null, true, (int) $book['book_id']); ?>
            <a href="<?= e(story_book_url((int) $book['book_id'])) ?>" class="home-story-title"><?= e($book['title']) ?></a>
            <p class="home-story-desc"><?= e($book['description']) ?></p>
            <div class="home-story-card-footer">
                <div class="home-story-meta">
                    <span>Ages <?= e($book['age_group']) ?></span>
                </div>
                <div class="home-story-actions">
                    <?php
                    $cardRating = $ratingSummaries[(int) $book['book_id']] ?? null;
                    render_story_card_actions($book, $cardRating);
                    ?>
                </div>
            </div>
        </div>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_brand_name()) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('home-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public', true); ?>

<main class="container page-main home-main">
<div class="home-shell">
    <section class="home-topics" id="topics" aria-label="Explore by topic">
        <h2 class="home-section-title home-topics-title">Explore by Topic</h2>
        <div class="home-topic-grid">
            <?php foreach ($topicTiles as $tile): ?>
                <a href="<?= e(app_url('search.php?topic=' . rawurlencode($tile['slug']))) ?>" class="home-topic-tile category-btn <?= e($tile['class']) ?>">
                    <span class="home-topic-icon" aria-hidden="true"><span class="home-topic-icon-glyph"><?= $tile['icon'] ?></span></span>
                    <span class="home-topic-label"><?= e($tile['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="home-latest">
        <div class="home-section-head">
            <h2 class="home-section-title">Latest Stories</h2>
            <a href="<?= e(app_url('search.php')) ?>" class="home-view-all">View all stories</a>
        </div>

        <?php if ($dbError): ?>
            <div class="alert alert-error"><?= e($dbError) ?></div>
        <?php elseif (empty($latestBooks)): ?>
            <div class="empty-state home-empty">
                <p>No approved stories yet. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="home-latest-rows">
                <?php if ($latestRowOne !== []): ?>
                    <div class="home-story-grid home-story-grid--row">
                        <?php foreach ($latestRowOne as $book): ?>
                            <?php render_home_story_card($book, $ratingSummaries); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($topPickBooks !== []): ?>
                    <section class="home-top-picks" aria-label="Top Picks">
                        <div class="home-top-picks-head">
                            <h3 class="home-top-picks-title">Top Picks</h3>
                            <p class="home-top-picks-lead">Stories kids love to read again and again</p>
                        </div>
                        <div class="home-story-grid home-story-grid--picks">
                            <?php foreach ($topPickBooks as $book): ?>
                                <?php render_home_story_card($book, $ratingSummaries); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($latestRowTwo !== []): ?>
                    <div class="home-story-grid home-story-grid--row">
                        <?php foreach ($latestRowTwo as $book): ?>
                            <?php render_home_story_card($book, $ratingSummaries); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

</div>
</main>
<?php render_site_footer(true); ?>
</body>
</html>
