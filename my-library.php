<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart-lib.php';
require_once __DIR__ . '/favorites-lib.php';

require_login('my-library.php');

$redirect = app_url('my-library.php');
$books = favorite_books_for_user($pdo, (int) current_user()['user_id']);
$ratingSummaries = get_book_rating_summaries($pdo, array_column($books, 'book_id'));

$favoritesError = '';
if (!empty($_SESSION['favorites_flash_error'])) {
    $favoritesError = (string) $_SESSION['favorites_flash_error'];
    unset($_SESSION['favorites_flash_error']);
}

cart_bootstrap();
release_session_lock();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('My Library')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('explore-library-page my-library-page') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <h2 class="section-title section-title-compact">My Library</h2>

    <p class="my-library-tip">
        Click the <span class="my-library-tip-heart" aria-hidden="true">♥</span> red heart in the top right corner of any story to save it here.
    </p>

    <?php if ($favoritesError !== ''): ?>
        <div class="alert alert-error"><?= e($favoritesError) ?></div>
    <?php endif; ?>

    <?php if ($books === []): ?>
        <div class="empty-state empty-state-compact my-library-empty">
            <p>You have not saved any stories yet.</p>
            <p>Browse <a href="<?= e(app_url('explore.php')) ?>">Explore</a> and tap the red heart on stories you want to keep.</p>
        </div>
    <?php else: ?>
        <div class="story-grid story-grid-compact">
            <?php foreach ($books as $book): ?>
                <?php $cover = cover_image_src($book['cover_image_url'] ?? null, $book['title']); ?>
                <article class="story-card story-card-compact">
                    <?php render_story_card_cover((int) $book['book_id'], $cover, (string) $book['title'], $redirect); ?>
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
</main>

<?php render_site_footer(true); ?>
</body>
</html>
