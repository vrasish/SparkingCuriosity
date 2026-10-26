<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/reviews-lib.php';
require_once __DIR__ . '/recommendations-lib.php';
require_once __DIR__ . '/favorites-lib.php';
require_once __DIR__ . '/read-aloud-lib.php';
require_once __DIR__ . '/quiz-lib.php';

ensure_book_pdf_schema($pdo);
ensure_book_pricing_schema($pdo);
ensure_book_i18n_schema($pdo);
cart_bootstrap();

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';

if ($bookId <= 0) {
    http_response_code(400);
    $error = 'Invalid story link.';
    $book = null;
} else {
    $error = null;
    $book = null;
    $pages = [];
    $bookLocked = false;

    try {
        $stmt = $pdo->prepare("
            SELECT
                b.book_id,
                b.title,
                b.title_es,
                b.author_name,
                b.description,
                b.description_es,
                b.cover_image_url,
                b.age_group,
                b.science_element,
                b.science_element_es,
                b.status,
                b.book_format,
                b.pdf_file_path,
                b.pdf_file_path_es,
                b.price_cents,
                b.story_topic,
                b.story_topic_es,
                GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
            FROM books b
            LEFT JOIN book_categories bc ON b.book_id = bc.book_id
            LEFT JOIN categories c ON bc.category_id = c.category_id
            WHERE b.book_id = ?
            GROUP BY b.book_id
        ");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            http_response_code(404);
            $error = 'Story not found.';
        } elseif ($book['status'] !== 'approved' && !$preview) {
            http_response_code(403);
            $error = 'This story is not available for public reading yet.';
            $book = null;
        } elseif (!$preview && story_requires_signup($bookId)) {
            redirect_guest_to_signup_for_story($bookId);
        } elseif (!$preview && !can_read_book($book)) {
            $bookLocked = true;
            $pages = [];
        } else {
            $bookLocked = false;
            $bookFormat = $book['book_format'] ?? 'pages';
            $isPdfBook = $bookFormat === 'pdf' && is_safe_pdf_path($book['pdf_file_path'] ?? null);

            if (!$isPdfBook) {
                $stmtPages = $pdo->prepare("
                    SELECT page_number, page_text
                    FROM book_pages
                    WHERE book_id = ?
                    ORDER BY page_number ASC
                ");
                $stmtPages->execute([$bookId]);
                $pages = $stmtPages->fetchAll();
            }
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error = 'Could not load this story.';
        $book = null;
    }
}

$localized = $book ? book_localized_fields($book) : null;
$displayTitle = $localized['title'] ?? (string) ($book['title'] ?? '');
$displayDescription = $localized['description'] ?? (string) ($book['description'] ?? '');
$displayScience = $localized['science_element'] ?? (string) ($book['science_element'] ?? '');
$displayTopic = $localized['story_topic'] ?? ($book['story_topic'] ?? null);
$contentLang = $localized['content_lang'] ?? 'en';
$wantsSpanish = (bool) ($localized['wants_spanish'] ?? false);
$spanishComingSoon = (bool) ($localized['spanish_coming_soon'] ?? false);
$activePdfPath = $localized['pdf_file_path'] ?? (string) ($book['pdf_file_path'] ?? '');

$cover = $book ? cover_image_src($book['cover_image_url'] ?? null, $displayTitle !== '' ? $displayTitle : (string) $book['title']) : default_cover();
$bookLocked = $bookLocked ?? false;
$bookId = $book ? (int) $book['book_id'] : $bookId;

$classroomContext = classroom_current($pdo);
if ($book && !$bookLocked && !$preview && !$error && $classroomContext) {
    classroom_record_event(
        $pdo,
        (int) $classroomContext['classroom_id'],
        'story_open',
        $bookId
    );
}
$isPdfBook = $book && !$bookLocked && ($book['book_format'] ?? 'pages') === 'pdf' && is_safe_pdf_path($activePdfPath);
$pdfUrl = $isPdfBook ? pdf_reader_url($bookId, $preview, $contentLang) : '';
$pdfDownloadUrl = $isPdfBook ? pdf_download_url($bookId, $preview, $contentLang) : '';
$pdfExists = $isPdfBook && is_file(__DIR__ . '/' . $activePdfPath);
// English read-aloud only — do not offer or play English audio on Spanish stories.
$readAloudEnabled = $isPdfBook && !$bookLocked && $contentLang === 'en';
$audioDownloadAvailable = $readAloudEnabled && read_aloud_full_story_audio_available($bookId);
$audioDownloadUrl = $audioDownloadAvailable ? read_aloud_download_url($bookId, $preview) : '';
$quizDownloadLang = 'en';
if ($contentLang === 'es' && quiz_has_story($bookId, 'es') && quiz_public_questions($bookId, 'es') !== []) {
    $quizDownloadLang = 'es';
}
$quizDownloadAvailable = $isPdfBook && !$bookLocked && (
    ($quizDownloadLang === 'es')
    || (quiz_has_story($bookId, 'en') && quiz_public_questions($bookId, 'en') !== [])
);
$quizDownloadUrl = $quizDownloadAvailable ? quiz_download_url($bookId, $preview, $quizDownloadLang) : '';
$inCart = cart_enabled() && $book && in_array($bookId, cart_book_ids(), true);
$bookOwned = $book && cart_enabled() && is_book_purchased($bookId) && !is_book_free($book);

$ratingSummary = $book ? get_book_rating_summary($pdo, $bookId) : null;
$bookReviews = $book ? get_book_reviews($pdo, $bookId) : [];
$userReview = null;
if ($book && is_logged_in()) {
    $userReview = get_user_book_review($pdo, $bookId, (int) current_user()['user_id']);
} elseif ($book && !$bookLocked && !$preview) {
    $userReview = get_visitor_book_review($pdo, $bookId, review_visitor_token());
}
$canSubmitReview = $book && !$preview && !$bookLocked;
$reviewSaved = isset($_GET['review_saved']);
$reviewError = trim((string) ($_GET['review_error'] ?? ''));
$showStoryRating = $book && !$error && !$bookLocked && !$preview;
$storyRatingAttrs = '';
if ($showStoryRating) {
    $storyRatingAttrs =
        ' data-story-rating="1"'
        . ' data-book-id="' . $bookId . '"'
        . ' data-rating-api="' . e(app_api_url('review-api.php')) . '"'
        . ' data-can-submit="' . ($canSubmitReview ? '1' : '0') . '"'
        . ' data-existing-rating="' . (int) ($userReview['rating'] ?? 0) . '"';
}

$recommendedBooks = [];
$recommendedRatingSummaries = [];
if ($book && !$preview && !$bookLocked) {
    $recommendedBooks = get_recommended_books($pdo, $bookId, 3);
    if ($recommendedBooks !== []) {
        $recommendedRatingSummaries = get_book_rating_summaries($pdo, array_column($recommendedBooks, 'book_id'));
    }
}
?>
<?php
$quizApiUrl = app_api_url('quiz-api.php') . ($contentLang === 'es' ? '?lang=es' : '');
?>
<!DOCTYPE html>
<html lang="<?= e(html_lang_attr()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $book ? e($displayTitle . ' | ' . site_brand_name()) : e(site_page_title('Story')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>
<?php render_classroom_banner($classroomContext); ?>

<main class="container page-main">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <a href="<?= e(app_url('search.php')) ?>" class="btn btn-outline"><?= e(t('book.back_search')) ?></a>
    <?php else: ?>
        <div class="page-section">
        <div class="book-header">
            <div class="book-cover-wrap">
                <?php render_story_favorite_button($bookId, app_url('book.php?id=' . $bookId)); ?>
                <img src="<?= e($cover) ?>" alt="<?= e(t('home.cover_alt', ['title' => $displayTitle])) ?>" class="book-cover">
            </div>
            <div>
                <?php render_book_language_toggle($bookId, $book, $wantsSpanish); ?>
                <?php render_topic_tags($book['categories'] ?? '', $displayTopic, (int) $bookId); ?>
                <h1><?= e($displayTitle) ?></h1>
                <div class="book-meta-row">
                    <span class="book-meta-item story-card-meta"><?= e(t('book.by_ages', ['author' => $book['author_name'], 'ages' => $book['age_group']])) ?></span>
                    <?php if ($isPdfBook): ?>
                        <span class="book-meta-item badge-format-pdf"><?= e(t('book.pdf_book')) ?></span>
                    <?php endif; ?>
                    <?php if ($preview): ?>
                        <span class="book-meta-item status-tag <?= e(status_class($book['status'])) ?>"><?= e(str_replace('_', ' ', $book['status'])) ?></span>
                        <span class="book-meta-item book-meta-note">(preview)</span>
                    <?php elseif (is_book_free($book)): ?>
                        <span class="book-meta-item badge-free"><?= e(t('book.free')) ?></span>
                    <?php elseif (is_book_purchased($bookId)): ?>
                        <span class="book-meta-item badge-owned"><?= e(t('book.owned')) ?></span>
                    <?php else: ?>
                        <span class="book-meta-item badge-price"><?= e(format_book_price($book)) ?></span>
                    <?php endif; ?>
                    <?php render_book_rating_summary($ratingSummary); ?>
                </div>
                <p class="mt-2"><?= e($displayDescription) ?></p>
                <?php if ($spanishComingSoon): ?>
                    <?php render_spanish_coming_soon_notice(); ?>
                <?php endif; ?>
                <?php if ($bookLocked): ?>
                    <div class="book-purchase-cta mt-2">
                        <?php if (cart_enabled()): ?>
                        <p class="page-lead"><?= e(t('book.purchase_cta')) ?></p>
                        <?php if ($inCart): ?>
                            <a href="<?= e(app_url('cart-page.php')) ?>" class="btn btn-primary"><?= e(t('book.view_cart')) ?></a>
                        <?php else: ?>
                            <form method="post" action="<?= e(app_url('cart-action.php')) ?>" class="inline-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                                <input type="hidden" name="redirect" value="<?= e(app_url('book.php?id=' . $bookId)) ?>">
                                <button type="submit" class="btn btn-primary"><?= e(t('book.add_to_cart', ['price' => format_book_price($book)])) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="page-lead"><?= e(t('book.unavailable')) ?></p>
                        <a href="<?= e(app_url('search.php')) ?>" class="btn btn-primary"><?= e(t('book.browse')) ?></a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($bookOwned): ?>
                    <div class="book-owned-actions mt-2">
                        <a href="<?= e(app_url('book.php?id=' . $bookId)) ?>" class="btn btn-primary btn-sm"><?= e(t('book.read_story')) ?></a>
                        <form method="post" action="<?= e(app_url('cart-action.php')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(t('book.return_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                            <input type="hidden" name="action" value="return">
                            <input type="hidden" name="book_id" value="<?= $bookId ?>">
                            <input type="hidden" name="redirect" value="book.php?id=<?= $bookId ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?= e(t('book.return_refund')) ?></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <div class="page-section book-content<?= $isPdfBook ? ' book-content-pdf' : ' book-content-text' ?>"<?= !$isPdfBook && !$bookLocked ? ' data-book-id="' . (int) $bookId . '" data-quiz-api="' . e($quizApiUrl) . '"' . $storyRatingAttrs : '' ?>>
        <?php if ($bookLocked): ?>
            <div class="empty-state">
                <?php if (cart_enabled()): ?>
                <p><?= e(t('book.locked_cta', ['price' => format_book_price($book)])) ?></p>
                <?php else: ?>
                <p><?= e(t('book.unavailable')) ?></p>
                <p class="mt-2"><a href="<?= e(app_url('search.php')) ?>" class="btn btn-primary"><?= e(t('book.browse')) ?></a></p>
                <?php endif; ?>
            </div>
        <?php elseif ($spanishComingSoon): ?>
            <div class="empty-state book-spanish-soon-content">
                <?php render_spanish_coming_soon_notice(); ?>
                <p class="mt-2">
                    <a href="<?= e(app_url('book.php?id=' . $bookId . '&story_lang=en')) ?>" class="btn btn-outline">
                        <?= e(t('book.lang_english')) ?>
                    </a>
                </p>
            </div>
            <?php render_book_recommendations($recommendedBooks, $recommendedRatingSummaries); ?>
        <?php elseif ($isPdfBook): ?>
            <?php if ($pdfExists): ?>
                <section class="pdf-viewer-wrap">
                    <div class="pdf-viewer-layout">
                        <div class="pdf-viewer-column">
                            <div class="pdf-viewer-actions">
                                <a href="<?= e($pdfDownloadUrl) ?>" class="btn btn-primary btn-sm btn-download-pdf"><?= e(t('book.download_pdf')) ?></a>
                                <?php if ($audioDownloadAvailable): ?>
                                <a href="<?= e($audioDownloadUrl) ?>" class="btn btn-primary btn-sm btn-download-audio"><?= e(t('book.download_audio')) ?></a>
                                <?php endif; ?>
                                <?php if ($quizDownloadAvailable): ?>
                                <a href="<?= e($quizDownloadUrl) ?>" class="btn btn-primary btn-sm btn-download-quiz"><?= e(t('book.download_quiz')) ?></a>
                                <?php endif; ?>
                                <a href="<?= e($pdfUrl) ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener"><?= e(t('book.open_tab')) ?></a>
                            </div>
                            <div
                                id="pdf-reader"
                                class="pdf-reader"
                                data-pdf-url="<?= e($pdfUrl) ?>"
                                data-story-id="<?= (int) $bookId ?>"
                                <?php if ($readAloudEnabled): ?>
                                data-read-aloud-api="<?= e(app_api_url('read-aloud-api.php')) ?>"
                                <?php endif; ?>
                                data-quiz-api="<?= e($quizApiUrl) ?>"
                                <?= $storyRatingAttrs ?>
                            >
                                <div class="pdf-reader-loading" role="status" aria-live="polite" aria-busy="true">
                                    <div class="pdf-reader-loading-spinner" aria-hidden="true"></div>
                                    <p class="pdf-reader-loading-text"><?= e(t('book.opening')) ?></p>
                                </div>
                            </div>
                        </div>
                        <aside class="pdf-viewer-sidebar" id="pdf-quiz-sidebar" aria-label="Story quiz"></aside>
                    </div>
                </section>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
                <script src="<?= e(asset_url('pdf-reader.js')) ?>"></script>
                <?php if ($readAloudEnabled): ?>
                <script src="<?= e(asset_url('read-aloud.js')) ?>"></script>
                <?php endif; ?>
                <script src="<?= e(asset_url('quiz.js')) ?>"></script>
                <script src="<?= e(asset_url('story-rating.js')) ?>"></script>
            <?php else: ?>
                <div class="alert alert-error">PDF file not found on the server.</div>
            <?php endif; ?>
            <?php render_book_recommendations($recommendedBooks, $recommendedRatingSummaries); ?>
        <?php elseif (empty($pages)): ?>
            <div class="empty-state"><p>This story has no pages yet.</p></div>
        <?php else: ?>
            <?php foreach ($pages as $page): ?>
                <article class="story-page">
                    <div class="story-page-num">Page <?= (int) $page['page_number'] ?></div>
                    <?php
                    $paragraphs = preg_split('/\n\s*\n/', trim($page['page_text']));
                    if (count($paragraphs) === 1) {
                        $paragraphs = array_filter(array_map('trim', explode("\n", $page['page_text'])));
                    }
                    foreach ($paragraphs as $para):
                        if ($para === '') {
                            continue;
                        }
                    ?>
                        <p><?= nl2br(e($para)) ?></p>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
            <?php render_book_recommendations($recommendedBooks, $recommendedRatingSummaries); ?>
            <script src="<?= e(asset_url('quiz.js')) ?>"></script>
            <script src="<?= e(asset_url('story-rating.js')) ?>"></script>
        <?php endif; ?>

        <?php if (!$bookLocked && $displayScience !== ''): ?>
            <section class="science-box">
                <h2><?= e(t('book.science_element')) ?></h2>
                <p><?= e($displayScience) ?></p>
            </section>
        <?php endif; ?>

        <?php if (!$preview): ?>
            <?php if ($reviewSaved): ?>
                <div class="alert alert-success">Thank you! Your review has been saved.</div>
            <?php elseif ($reviewError !== '' && $reviewError !== '1'): ?>
                <div class="alert alert-error"><?= e($reviewError) ?></div>
            <?php elseif ($reviewError === 'locked'): ?>
                <div class="alert alert-error"><?= e(t('book.review_purchase_first')) ?></div>
            <?php endif; ?>

            <?php render_review_form($bookId, $userReview, $canSubmitReview); ?>
            <?php render_reviews_list($bookReviews); ?>
        <?php endif; ?>

        <div class="page-actions">
            <a href="<?= e(app_url('search.php')) ?>" class="btn btn-outline">&larr; <?= e(t('book.back_search')) ?></a>
            <a href="<?= e(app_url('report-story.php?id=' . (int) $book['book_id'])) ?>" class="report-story-link"><span class="report-story-flag" aria-hidden="true">🚩</span> Report Story</a>
        </div>
        </div>
    <?php endif; ?>
</main>
<?php render_site_footer(); ?>
<?php if ($book && !$error): ?>
<script>
(function () {
    if (!window.posthog) { return; }

    posthog.capture('story_viewed', {
        story_id: <?= (int) $book['book_id'] ?>,
        title: <?= json_encode($book['title']) ?>,
        age_group: <?= json_encode($book['age_group'] ?? '') ?>,
        categories: <?= json_encode($book['categories'] ?? '') ?>,
        is_locked: <?= $bookLocked ? 'true' : 'false' ?>,
        is_pdf: <?= $isPdfBook ? 'true' : 'false' ?>,
    });

    <?php if ($reviewSaved): ?>
    posthog.capture('review_submitted', {
        story_id: <?= (int) $book['book_id'] ?>,
    });
    <?php endif; ?>

    var addToCartForm = document.querySelector('form .btn-primary[type="submit"]');
    var cartForms = document.querySelectorAll('form[action*="cart-action"]');
    cartForms.forEach(function (form) {
        var actionInput = form.querySelector('input[name="action"]');
        if (actionInput && actionInput.value === 'add') {
            form.addEventListener('submit', function () {
                posthog.capture('story_add_to_cart_clicked', {
                    story_id: <?= (int) $book['book_id'] ?>,
                    title: <?= json_encode($book['title']) ?>,
                    price_cents: <?= (int) ($book['price_cents'] ?? 0) ?>,
                });
            });
        }
    });
}());
</script>
<?php endif; ?>
</body>
</html>
