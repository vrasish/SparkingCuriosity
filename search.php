<?php
require_once __DIR__ . '/cache-lib.php';

$topicFilter = trim((string) ($_GET['topic'] ?? ''));
$topicAliases = [
    'Ocean' => 'Earth Science',
    'Weather and Atmosphere' => 'Earth Science',
    'Plants & Animals' => 'Plants',
    'Germs' => 'Microbes',
    'Body' => 'Human Body',
];
if (isset($topicAliases[$topicFilter])) {
    $topicFilter = $topicAliases[$topicFilter];
}
$searchQuery = trim((string) ($_GET['q'] ?? ''));

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/favorites-lib.php';
require_once __DIR__ . '/classroom-lib.php';

$classroomContext = classroom_current(stories_connect());
// Search is open to kids without signup; login remains available at /login for teachers.
$searchLocked = false;
$books = [];
$dbError = null;
$ratingSummaries = [];

if (!$searchLocked) {
    $pageCache = explore_page_cache_get($topicFilter, $searchQuery);
    if ($pageCache !== null) {
        $books = $pageCache['books'];
        $ratingSummaries = $pageCache['ratings'];
    } else {
        cart_bootstrap();

        try {
            ensure_book_i18n_schema(stories_connect());
            $sql = "
                SELECT
                    b.book_id,
                    b.title,
                    b.title_es,
                    b.author_name,
                    b.description,
                    b.description_es,
                    b.science_element,
                    b.cover_image_url,
                    b.age_group,
                    b.book_format,
                    b.price_cents,
                    b.story_topic,
                    b.pdf_file_path_es,
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
                ORDER BY b.created_at ASC, b.book_id ASC
            ';
            $stmt = stories_connect()->prepare($sql);
            $stmt->execute($params);
            $books = $stmt->fetchAll();

            if ($searchQuery !== '') {
                $books = explore_books_filter_by_search($books, $searchQuery);
            }
            $books = filter_books_for_site_lang($books);
        } catch (PDOException $ex) {
            $dbError = 'The story library could not be loaded.';
            error_log($ex->getMessage());
        }

        $ratingSummaries = get_book_rating_summaries(stories_connect(), array_column($books, 'book_id'));
        explore_page_cache_set($topicFilter, $searchQuery, $books, $ratingSummaries);
    }

    $books = filter_books_for_site_lang($books);
}

cart_bootstrap();
release_session_lock();

$hasFilters = $topicFilter !== '' || $searchQuery !== '';
$topicTiles = home_topic_tiles();
$searchRedirect = app_url('search.php' . ($hasFilters ? '?' . http_build_query(array_filter([
    'topic' => $topicFilter !== '' ? $topicFilter : null,
    'q' => $searchQuery !== '' ? $searchQuery : null,
])) : ''));
?>
<?php
if ($dbError === 'The story library could not be loaded.') {
    $dbError = t('search.load_error');
}
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title(t('search.title'))) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('explore-library-page search-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>
<?php render_classroom_banner($classroomContext); ?>

<main class="container page-main page-main-compact">
    <h2 class="section-title section-title-compact"><?= e(t('search.title')) ?></h2>

    <?php if ($searchLocked): ?>
        <div class="empty-state empty-state-compact explore-locked-state" role="status">
            <p class="explore-locked-message">
                To explore more stories,
                <a href="<?= e(app_url('register.php?redirect=' . rawurlencode($searchRedirect))) ?>">Sign up</a>
                or
                <a href="<?= e(app_url('login.php?redirect=' . rawurlencode($searchRedirect))) ?>">login</a>.
            </p>
        </div>
    <?php else: ?>
    <section class="explore-search-panel" aria-label="<?= e(t('search.title')) ?>">
        <form class="search-form explore-search-form" method="get" action="<?= e(app_url('search.php')) ?>">
            <?php if ($topicFilter !== ''): ?>
                <input type="hidden" name="topic" value="<?= e($topicFilter) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="story-search"><?= e(t('search.title')) ?></label>
            <input
                type="search"
                id="story-search"
                name="q"
                class="form-control explore-search-input"
                value="<?= e($searchQuery) ?>"
                placeholder="<?= e(t('search.placeholder')) ?>"
                autocomplete="off"
                autofocus
            >
            <button type="submit" class="btn btn-primary btn-sm explore-search-btn"><?= e(t('search.button')) ?></button>
            <?php if ($hasFilters): ?>
                <a href="<?= e(app_url('search.php')) ?>" class="btn btn-outline btn-sm"><?= e(t('search.clear')) ?></a>
            <?php endif; ?>
        </form>

        <div class="filter-chips explore-topic-chips" aria-label="<?= e(t('search.browse_topics')) ?>">
            <?php foreach ($topicTiles as $tile): ?>
                <?php
                $isActive = $topicFilter === $tile['slug'];
                $chipUrl = app_url('search.php?topic=' . rawurlencode($tile['slug']));
                if ($searchQuery !== '') {
                    $chipUrl = app_url('search.php?topic=' . rawurlencode($tile['slug']) . '&q=' . rawurlencode($searchQuery));
                }
                ?>
                <a
                    href="<?= e($chipUrl) ?>"
                    class="filter-chip category-btn <?= e($tile['class']) ?><?= $isActive ? ' active' : '' ?>"
                ><?= e(topic_label($tile['slug'])) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($hasFilters): ?>
            <p class="explore-results-meta">
                <?php if ($topicFilter !== '' && $searchQuery !== ''): ?>
                    <?= e(t('search.in_topic_matching', ['topic' => topic_label($topicFilter), 'query' => $searchQuery])) ?>
                <?php elseif ($topicFilter !== ''): ?>
                    <?= e(t('search.about_topic', ['topic' => topic_label($topicFilter)])) ?>
                <?php else: ?>
                    <?= e(t('search.results_for', ['query' => $searchQuery])) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </section>

    <?php if ($dbError): ?>
        <div class="alert alert-error"><?= e($dbError) ?></div>
    <?php elseif (empty($books)): ?>
        <div class="empty-state empty-state-compact">
            <?php if ($hasFilters): ?>
                <p><?= e(is_spanish() ? t('search.empty_spanish') : t('search.empty_filtered')) ?></p>
            <?php else: ?>
                <p><?= e(is_spanish() ? t('search.empty_spanish') : t('search.empty')) ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="story-grid story-grid-compact">
            <?php foreach ($books as $book): ?>
                <?php
                $display = book_card_display_fields($book);
                $cover = cover_image_src($book['cover_image_url'] ?? null, $display['title']);
                ?>
                <article class="story-card story-card-compact">
                    <?php render_story_card_cover((int) $book['book_id'], $cover, $display['title'], $searchRedirect); ?>
                    <div class="story-card-content">
                        <?php render_topic_tags($book['categories'] ?? '', $book['story_topic'] ?? null, (int) ($book['book_id'] ?? 0)); ?>
                        <h3 class="story-card-title"><?= e($display['title']) ?></h3>
                        <p class="story-card-desc"><?= e($display['description']) ?></p>
                        <div class="story-card-bottom">
                            <p class="story-card-meta"><?= e(t('search.by_ages', ['author' => $book['author_name'], 'ages' => $book['age_group']])) ?></p>
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
<?php if (!$searchLocked && $hasFilters): ?>
<script>
(function () {
    if (!window.posthog) { return; }
    <?php if ($searchQuery !== '' && $topicFilter !== ''): ?>
    posthog.capture('story_searched', {
        query: <?= json_encode($searchQuery) ?>,
        topic: <?= json_encode($topicFilter) ?>,
        result_count: <?= count($books) ?>,
    });
    <?php elseif ($searchQuery !== ''): ?>
    posthog.capture('story_searched', {
        query: <?= json_encode($searchQuery) ?>,
        result_count: <?= count($books) ?>,
    });
    <?php elseif ($topicFilter !== ''): ?>
    posthog.capture('topic_filtered', {
        topic: <?= json_encode($topicFilter) ?>,
        result_count: <?= count($books) ?>,
    });
    <?php endif; ?>
}());
</script>
<?php endif; ?>
</body>
</html>
