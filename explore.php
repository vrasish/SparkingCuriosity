<?php
require_once __DIR__ . '/cache-lib.php';

$topicFilter = trim((string) ($_GET['topic'] ?? ''));
$topicAliases = [
    'Ocean' => 'Earth Science',
    'Weather and Atmosphere' => 'Earth Science',
    'Plants & Animals' => 'Plants',
];
if (isset($topicAliases[$topicFilter])) {
    $topicFilter = $topicAliases[$topicFilter];
}
$searchQuery = trim((string) ($_GET['q'] ?? ''));

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/favorites-lib.php';

$exploreLocked = !is_logged_in();
$books = [];
$dbError = null;
$ratingSummaries = [];

if (!$exploreLocked) {
    $pageCache = explore_page_cache_get($topicFilter, $searchQuery);
    if ($pageCache !== null) {
        define('STORIES_SKIP_DB', true);
        $books = $pageCache['books'];
        $ratingSummaries = $pageCache['ratings'];
    } else {
        cart_bootstrap();

        try {
            $sql = "
                SELECT
                    b.book_id,
                    b.title,
                    b.author_name,
                    b.description,
                    b.science_element,
                    b.cover_image_url,
                    b.age_group,
                    b.book_format,
                    b.price_cents,
                    GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
                FROM books b
                LEFT JOIN book_categories bc ON b.book_id = bc.book_id
                LEFT JOIN categories c ON bc.category_id = c.category_id
                WHERE b.status = 'approved'
            ";
            $params = [];
            if ($topicFilter !== '') {
                $sql .= ' AND c.category_name = ?';
                $params[] = $topicFilter;
            }
            $sql .= '
                GROUP BY b.book_id
                ORDER BY b.created_at DESC
            ';
            $stmt = stories_connect()->prepare($sql);
            $stmt->execute($params);
            $books = $stmt->fetchAll();

            if ($searchQuery !== '') {
                $books = explore_books_filter_by_search($books, $searchQuery);
            }
        } catch (PDOException $ex) {
            $dbError = 'The story library could not be loaded.';
            error_log($ex->getMessage());
        }

        $ratingSummaries = get_book_rating_summaries(stories_connect(), array_column($books, 'book_id'));
        explore_page_cache_set($topicFilter, $searchQuery, $books, $ratingSummaries);
    }
}

cart_bootstrap();
release_session_lock();

$hasFilters = $topicFilter !== '' || $searchQuery !== '';
$topicTiles = home_topic_tiles();
$exploreRedirect = app_url('explore.php' . ($hasFilters ? '?' . http_build_query(array_filter([
    'topic' => $topicFilter !== '' ? $topicFilter : null,
    'q' => $searchQuery !== '' ? $searchQuery : null,
])) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Stories | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('explore-library-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <h2 class="section-title section-title-compact">Story Library</h2>

    <?php if ($exploreLocked): ?>
        <div class="empty-state empty-state-compact explore-locked-state" role="status">
            <p class="explore-locked-message">
                All Stories are Locked.
                <a href="<?= e(app_url('register.php?redirect=' . rawurlencode($exploreRedirect))) ?>">Sign up</a>
                or
                <a href="<?= e(app_url('login.php?redirect=' . rawurlencode($exploreRedirect))) ?>">login</a>
                to view them.
            </p>
        </div>
    <?php else: ?>
    <section class="explore-search-panel" aria-label="Search and filter stories">
        <form class="search-form explore-search-form" method="get" action="<?= e(app_url('explore.php')) ?>">
            <?php if ($topicFilter !== ''): ?>
                <input type="hidden" name="topic" value="<?= e($topicFilter) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="explore-search">Search stories by topic or keyword</label>
            <input
                type="search"
                id="explore-search"
                name="q"
                class="form-control explore-search-input"
                value="<?= e($searchQuery) ?>"
                placeholder="Search by title, topic, or any word from the story…"
                autocomplete="off"
            >
            <button type="submit" class="btn btn-primary btn-sm explore-search-btn">Search</button>
            <?php if ($hasFilters): ?>
                <a href="<?= e(app_url('explore.php')) ?>" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="filter-chips explore-topic-chips" aria-label="Browse by topic">
            <?php foreach ($topicTiles as $tile): ?>
                <?php
                $isActive = $topicFilter === $tile['slug'];
                $chipUrl = app_url('explore.php?topic=' . rawurlencode($tile['slug']));
                if ($searchQuery !== '') {
                    $chipUrl = app_url('explore.php?topic=' . rawurlencode($tile['slug']) . '&q=' . rawurlencode($searchQuery));
                }
                ?>
                <a
                    href="<?= e($chipUrl) ?>"
                    class="filter-chip category-btn <?= e($tile['class']) ?><?= $isActive ? ' active' : '' ?>"
                ><?= e($tile['label']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($hasFilters): ?>
            <p class="explore-results-meta">
                <?php if ($topicFilter !== '' && $searchQuery !== ''): ?>
                    Stories in <strong><?= e($topicFilter) ?></strong> matching “<?= e($searchQuery) ?>”
                <?php elseif ($topicFilter !== ''): ?>
                    Stories about <strong><?= e($topicFilter) ?></strong>
                <?php else: ?>
                    Results for “<?= e($searchQuery) ?>”
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </section>

    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php elseif (empty($books)): ?>
        <div class="empty-state empty-state-compact">
            <?php if ($hasFilters): ?>
                <p>No stories matched your search. Try another topic or keyword.</p>
            <?php else: ?>
                <p>No stories yet. Check back soon!</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="story-grid story-grid-compact">
            <?php foreach ($books as $book): ?>
                <?php $cover = cover_image_src($book['cover_image_url'] ?? null, $book['title']); ?>
                <article class="story-card story-card-compact">
                    <?php render_story_card_cover((int) $book['book_id'], $cover, (string) $book['title'], $exploreRedirect); ?>
                    <div class="story-card-content">
                        <?php render_topic_tags($book['categories'] ?? ''); ?>
                        <h3 class="story-card-title"><?= e($book['title']) ?></h3>
                        <p class="story-card-desc"><?= e($book['description']) ?></p>
                        <div class="story-card-bottom">
                            <p class="story-card-meta">By <?= e($book['author_name']) ?> · Ages <?= e($book['age_group']) ?></p>
                            <?php
                            $cardRating = $ratingSummaries[(int) $book['book_id']] ?? null;
                            render_story_card_actions($book, $cardRating);
                            ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</main>

<?php render_site_footer(true); ?>
</body>
</html>
