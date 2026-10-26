<?php

declare(strict_types=1);

/**
 * @return list<array<string, mixed>>
 */
function get_recommended_books(PDO $pdo, int $bookId, int $limit = 3): array
{
    if ($bookId <= 0) {
        return [];
    }

    $limit = max(1, min(6, $limit));

    $baseSelect = "
        SELECT
            b.book_id,
            b.title,
            b.title_es,
            b.author_name,
            b.description,
            b.description_es,
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
    ";

    try {
        $stmt = $pdo->prepare($baseSelect . "
            INNER JOIN book_categories bc_match ON bc_match.book_id = b.book_id
            WHERE b.status = 'approved'
              AND b.book_id != ?
              AND bc_match.category_id IN (
                  SELECT category_id FROM book_categories WHERE book_id = ?
              )
            GROUP BY
                b.book_id,
                b.title,
                b.author_name,
                b.description,
                b.cover_image_url,
                b.age_group,
                b.book_format,
                b.price_cents,
                b.created_at
            ORDER BY b.created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$bookId, $bookId]);
        $books = $stmt->fetchAll();

        if (count($books) >= $limit) {
            return array_slice($books, 0, $limit);
        }

        $excludeIds = array_map('intval', array_column($books, 'book_id'));
        $excludeIds[] = $bookId;
        $excludeIds = array_values(array_unique($excludeIds));
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $need = $limit - count($books);

        $stmt = $pdo->prepare($baseSelect . "
            WHERE b.status = 'approved'
              AND b.book_id NOT IN ({$placeholders})
            GROUP BY
                b.book_id,
                b.title,
                b.author_name,
                b.description,
                b.cover_image_url,
                b.age_group,
                b.book_format,
                b.price_cents,
                b.created_at
            ORDER BY b.created_at DESC
            LIMIT {$need}
        ");
        $stmt->execute($excludeIds);

        $merged = array_slice(array_merge($books, $stmt->fetchAll()), 0, $limit);
        return function_exists('filter_books_for_site_lang') ? filter_books_for_site_lang($merged) : $merged;
    } catch (PDOException $ex) {
        error_log('get_recommended_books: ' . $ex->getMessage());

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
                    b.book_format,
                    b.price_cents,
                    b.pdf_file_path_es,
                    '' AS categories
                FROM books b
                WHERE b.status = 'approved'
                  AND b.book_id != ?
                ORDER BY b.created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$bookId]);
            $fallback = $stmt->fetchAll();

            return function_exists('filter_books_for_site_lang') ? filter_books_for_site_lang($fallback) : $fallback;
        } catch (PDOException $fallbackEx) {
            error_log('get_recommended_books fallback: ' . $fallbackEx->getMessage());
            return [];
        }
    }
}

function render_book_recommendations(array $books, array $ratingSummaries): void
{
    if ($books === []) {
        return;
    }

    echo '<section class="book-recommendations">';
    echo '<h2 class="section-subtitle">' . e(function_exists('t') ? t('book.also_read') : 'People who read this book also read') . '</h2>';
    echo '<div class="story-grid story-grid-compact story-grid-recommended">';

    foreach ($books as $recBook) {
        $display = function_exists('book_card_display_fields')
            ? book_card_display_fields($recBook)
            : ['title' => (string) ($recBook['title'] ?? ''), 'description' => (string) ($recBook['description'] ?? '')];
        $recCover = cover_image_src($recBook['cover_image_url'] ?? null, $display['title']);
        $recId = (int) ($recBook['book_id'] ?? 0);
        $recRating = $ratingSummaries[$recId] ?? null;

        echo '<article class="story-card story-card-compact">';
        render_story_card_cover($recId, $recCover, $display['title'], app_url('book.php?id=' . $recId));
        echo '<div class="story-card-content">';
        render_topic_tags($recBook['categories'] ?? '', $recBook['story_topic'] ?? null, (int) ($recBook['book_id'] ?? 0));
        echo '<h3 class="story-card-title">' . e($display['title']) . '</h3>';
        echo '<p class="story-card-desc">' . e($display['description']) . '</p>';
        echo '<div class="story-card-bottom">';
        echo '<p class="story-card-meta">' . e(function_exists('t')
            ? t('book.by_ages', ['author' => (string) ($recBook['author_name'] ?? ''), 'ages' => (string) ($recBook['age_group'] ?? '')])
            : ('By ' . (string) ($recBook['author_name'] ?? '') . ' · Ages ' . (string) ($recBook['age_group'] ?? ''))) . '</p>';
        render_story_card_actions($recBook, $recRating);
        echo '</div></div></article>';
    }

    echo '</div></section>';
}
