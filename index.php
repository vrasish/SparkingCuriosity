<?php
require_once __DIR__ . '/cache-lib.php';

$pageCache = home_page_cache_get();
if ($pageCache !== null) {
    define('STORIES_SKIP_DB', true);
}

require_once __DIR__ . '/auth.php';

$latestBooks = [];
$dbError = null;
$ratingSummaries = [];

if ($pageCache !== null) {
    $latestBooks = $pageCache['books'];
    $ratingSummaries = $pageCache['ratings'];
} else {
    cart_bootstrap();

    try {
        $featuredIds = home_featured_story_ids();
        $idList = implode(',', array_map('intval', $featuredIds));
        $stmt = stories_connect()->prepare("
            SELECT
                b.book_id,
                b.title,
                b.author_name,
                b.description,
                b.cover_image_url,
                b.age_group,
                b.price_cents,
                b.book_format,
                GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
            FROM books b
            LEFT JOIN book_categories bc ON b.book_id = bc.book_id
            LEFT JOIN categories c ON bc.category_id = c.category_id
            WHERE b.status = 'approved'
              AND b.book_id IN ({$idList})
            GROUP BY b.book_id
            ORDER BY FIELD(b.book_id, {$idList})
        ");
        $stmt->execute();
        $latestBooks = $stmt->fetchAll();
    } catch (PDOException $ex) {
        $dbError = 'Stories could not be loaded right now.';
        error_log($ex->getMessage());
    }

    $ratingSummaries = get_book_rating_summaries(stories_connect(), array_column($latestBooks, 'book_id'));
    home_page_cache_set($latestBooks, $ratingSummaries);
}

cart_bootstrap();
release_session_lock();

$topicTiles = home_topic_tiles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sparking Curiosity</title>
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
                <a href="<?= e(app_url('explore.php?topic=' . rawurlencode($tile['slug']))) ?>" class="home-topic-tile category-btn <?= e($tile['class']) ?>">
                    <span class="home-topic-icon" aria-hidden="true"><span class="home-topic-icon-glyph"><?= $tile['icon'] ?></span></span>
                    <span class="home-topic-label"><?= e($tile['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="home-latest">
        <div class="home-section-head">
            <h2 class="home-section-title">Latest Stories</h2>
            <a href="<?= e(app_url('explore.php')) ?>" class="home-view-all">View all stories</a>
        </div>

        <?php if ($dbError): ?>
            <div class="alert alert-error"><?= e($dbError) ?></div>
        <?php elseif (empty($latestBooks)): ?>
            <div class="empty-state home-empty">
                <p>No approved stories yet. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="home-story-grid">
                <?php foreach ($latestBooks as $book): ?>
                    <?php
                    $cover = cover_image_src($book['cover_image_url'] ?? null, $book['title']);
                    $primaryCat = primary_category($book['categories'] ?? '');
                    $readMins = estimate_reading_minutes((string) ($book['description'] ?? ''));
                    ?>
                    <article class="home-story-card">
                        <a href="<?= e(app_url('book.php?id=' . (int) $book['book_id'])) ?>" class="home-story-cover-link">
                            <img src="<?= e($cover) ?>" alt="Cover of <?= e($book['title']) ?>" class="home-story-img" loading="lazy">
                        </a>
                        <div class="home-story-body">
                            <?php if ($primaryCat !== ''): ?>
                                <span class="home-story-tag topic-tag category-btn <?= e(topic_class($primaryCat)) ?>"><?= e(strtoupper($primaryCat)) ?></span>
                            <?php endif; ?>
                            <a href="<?= e(app_url('book.php?id=' . (int) $book['book_id'])) ?>" class="home-story-title"><?= e($book['title']) ?></a>
                            <p class="home-story-desc"><?= e($book['description']) ?></p>
                            <div class="home-story-card-footer">
                                <div class="home-story-meta">
                                    <span>Ages <?= e($book['age_group']) ?></span>
                                    <span><?= (int) $readMins ?> min</span>
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
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
