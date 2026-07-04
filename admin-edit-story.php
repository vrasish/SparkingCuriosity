<?php
require_once __DIR__ . '/auth.php';

require_admin_login();
ensure_book_pdf_schema($pdo);

$topics = admin_science_topics();
$statuses = admin_story_statuses();
$bookId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['book_id'] ?? 0);
$message = '';
$error = '';

if ($bookId <= 0) {
    http_response_code(400);
    $error = 'Invalid story.';
    $book = null;
} else {
    $book = null;
    $storyText = '';

    try {
        $stmt = $pdo->prepare("
            SELECT
                b.book_id,
                b.title,
                b.author_name,
                b.description,
                b.cover_image_url,
                b.science_element,
                b.status,
                b.book_format,
                b.pdf_file_path,
                GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') AS categories
            FROM books b
            LEFT JOIN book_categories bc ON b.book_id = bc.book_id
            LEFT JOIN categories c ON bc.category_id = c.category_id
            WHERE b.book_id = ?
            GROUP BY b.book_id
        ");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if ($book && ($book['book_format'] ?? 'pages') !== 'pdf') {
            $stmtPages = $pdo->prepare('
                SELECT page_text
                FROM book_pages
                WHERE book_id = ?
                ORDER BY page_number ASC
            ');
            $stmtPages->execute([$bookId]);
            $storyText = implode("\n\n", $stmtPages->fetchAll(PDO::FETCH_COLUMN));
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error = 'Could not load story.';
    }

    if (!$book) {
        http_response_code(404);
        $error = 'Story not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $book) {
    $title = trim($_POST['title'] ?? '');
    $authorName = trim($_POST['author_name'] ?? '');
    $scienceTopic = trim($_POST['science_topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scienceElement = trim($_POST['science_element'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $coverImageUrl = trim($_POST['cover_image_url'] ?? '');
    if ($coverImageUrl !== '') {
        $coverFilename = cover_filename_from_url($coverImageUrl);
        if ($coverFilename !== null) {
            $coverImageUrl = cover_storage_url($coverFilename);
        }
    }
    $storyTextInput = trim($_POST['story_text'] ?? '');
    $isPdf = ($book['book_format'] ?? '') === 'pdf';

    if ($title === '' || $authorName === '' || $description === '' || $scienceElement === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($scienceTopic, $topics, true)) {
        $error = 'Please choose a valid science topic.';
    } elseif (!in_array($status, $statuses, true)) {
        $error = 'Please choose a valid status.';
    } elseif (!$isPdf && $storyTextInput === '') {
        $error = 'Story text is required for page-based stories.';
    } else {
        try {
            $stmtCat = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
            $stmtCat->execute([$scienceTopic]);
            $cat = $stmtCat->fetch();
            if ($cat) {
                $categoryId = (int) $cat['category_id'];
            } else {
                $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$scienceTopic]);
                $categoryId = (int) $pdo->lastInsertId();
            }

            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("
                UPDATE books
                SET title = :title,
                    author_name = :author_name,
                    description = :description,
                    cover_image_url = :cover_image_url,
                    science_element = :science_element,
                    status = :status
                WHERE book_id = :book_id
            ");
            $stmtUpdate->execute([
                'title' => $title,
                'author_name' => $authorName,
                'description' => $description,
                'cover_image_url' => $coverImageUrl !== '' ? $coverImageUrl : null,
                'science_element' => $scienceElement,
                'status' => $status,
                'book_id' => $bookId,
            ]);

            $pdo->prepare('DELETE FROM book_categories WHERE book_id = ?')->execute([$bookId]);
            $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)')
                ->execute([$bookId, $categoryId]);

            if (!$isPdf) {
                $paragraphs = preg_split('/\n\s*\n/', $storyTextInput);
                $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), fn($p) => $p !== ''));
                if (empty($paragraphs)) {
                    $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $storyTextInput)), fn($p) => $p !== ''));
                }
                if (empty($paragraphs)) {
                    $paragraphs = [$storyTextInput];
                }

                $pdo->prepare('DELETE FROM book_pages WHERE book_id = ?')->execute([$bookId]);
                $stmtPage = $pdo->prepare('
                    INSERT INTO book_pages (book_id, page_number, page_text, image_url)
                    VALUES (?, ?, ?, ?)
                ');
                $pageNum = 1;
                foreach ($paragraphs as $para) {
                    $stmtPage->execute([
                        $bookId,
                        $pageNum,
                        $para,
                        $pageNum === 1 && $coverImageUrl !== '' ? $coverImageUrl : null,
                    ]);
                    $pageNum++;
                }
            }

            $pdo->prepare('UPDATE submissions SET status = ? WHERE book_id = ?')
                ->execute([$status, $bookId]);

            $pdo->commit();
            $message = 'Story saved.';

            $book['title'] = $title;
            $book['author_name'] = $authorName;
            $book['description'] = $description;
            $book['science_element'] = $scienceElement;
            $book['status'] = $status;
            $book['cover_image_url'] = $coverImageUrl;
            $book['categories'] = $scienceTopic;
            $storyText = $storyTextInput;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $error = 'Save failed. Please try again.';
        }
    }
}

$currentTopic = '';
if ($book) {
    $cats = array_map('trim', explode(',', (string) ($book['categories'] ?? '')));
    $currentTopic = $cats[0] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Story | Admin | Sparking Curiosity</title>
    <?php render_stylesheet(); ?>
</head>
<body>
<?php render_fun_background(); ?>
<?php render_site_header('admin'); ?>

<main class="container page-main">
    <?php render_page_header('Edit Story', $book ? e($book['title']) : 'Story not found'); ?>

    <div class="page-section">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$book): ?>
            <p><a href="<?= e(app_url('admin-stories.php')) ?>" class="btn btn-outline">Back to All Stories</a></p>
        <?php else: ?>
            <p class="mb-2">
                <a href="<?= e(app_url('admin-stories.php')) ?>" class="btn btn-outline btn-sm">← All Stories</a>
                <a href="<?= e(app_url('book.php?id=' . (int) $bookId . '&preview=1')) ?>" class="btn btn-outline btn-sm">Preview</a>
            </p>

            <div class="form-panel">
                <form method="post" action="<?= e(app_url('admin-edit-story.php?id=' . (int) $bookId)) ?>">
                    <input type="hidden" name="book_id" value="<?= (int) $bookId ?>">

                    <div class="form-group">
                        <label for="title">Story Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required maxlength="255" value="<?= e($book['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="author_name">Author Name *</label>
                        <input type="text" id="author_name" name="author_name" class="form-control" required maxlength="255" value="<?= e($book['author_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="science_topic">Science Topic *</label>
                        <select id="science_topic" name="science_topic" class="form-control" required>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?= e($topic) ?>" <?= $currentTopic === $topic ? 'selected' : '' ?>><?= e($topic) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <?php foreach ($statuses as $statusOption): ?>
                                <option value="<?= e($statusOption) ?>" <?= ($book['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                                    <?= e(str_replace('_', ' ', $statusOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Short Description *</label>
                        <textarea id="description" name="description" class="form-control" required rows="3"><?= e($book['description']) ?></textarea>
                    </div>
                    <?php if (($book['book_format'] ?? '') !== 'pdf'): ?>
                        <div class="form-group">
                            <label for="story_text">Full Story Text *</label>
                            <textarea id="story_text" name="story_text" class="form-control" required rows="12"><?= e($storyText) ?></textarea>
                        </div>
                    <?php else: ?>
                        <p class="form-hint mb-2">This is a PDF book. Replace the PDF by uploading a new file from the creator upload page, or edit the metadata here.</p>
                        <?php if (is_safe_pdf_path($book['pdf_file_path'] ?? null)): ?>
                            <p><a href="<?= e(app_url('read-pdf.php?id=' . (int) $bookId . '&preview=1')) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Open current PDF</a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="science_element">Science Element Explanation *</label>
                        <textarea id="science_element" name="science_element" class="form-control" required rows="4"><?= e($book['science_element']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="cover_image_url">Cover Image URL</label>
                        <input type="url" id="cover_image_url" name="cover_image_url" class="form-control" value="<?= e($book['cover_image_url'] ?? '') ?>">
                        <?php if (!empty($book['cover_image_url'])): ?>
                            <p class="form-hint mt-2">
                                <img src="<?= e(cover_image_src($book['cover_image_url'], $book['title'])) ?>" alt="Cover preview" class="admin-cover-preview">
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="action-bar">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= e(app_url('admin-stories.php')) ?>" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
