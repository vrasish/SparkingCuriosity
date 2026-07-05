<?php
require_once __DIR__ . '/auth.php';
require_creator_login();

$topics = ['Space', 'Body', 'Plants', 'Animals', 'Weather', 'Germs', 'Earth Science', 'Engineering', 'Physical Science'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author_name = trim($_POST['author_name'] ?? '');
    $science_topic = trim($_POST['science_topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $story_text = trim($_POST['story_text'] ?? '');
    $science_element = trim($_POST['science_element'] ?? '');
    $cover_image_url = trim($_POST['cover_image_url'] ?? '');

    if ($title === '' || $author_name === '' || $science_topic === '' || $description === '' || $story_text === '' || $science_element === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($science_topic, $topics, true)) {
        $error = 'Please choose a valid science topic.';
    } else {
        try {
            $stmtCat = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
            $stmtCat->execute([$science_topic]);
            $cat = $stmtCat->fetch();

            if ($cat) {
                $category_id = (int) $cat['category_id'];
            } else {
                $stmtNewCat = $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)');
                $stmtNewCat->execute([$science_topic]);
                $category_id = (int) $pdo->lastInsertId();
            }

            $paragraphs = preg_split('/\n\s*\n/', $story_text);
            $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), fn($p) => $p !== ''));
            if (empty($paragraphs)) {
                $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $story_text)), fn($p) => $p !== ''));
            }
            if (empty($paragraphs)) {
                $paragraphs = [$story_text];
            }

            $pdo->beginTransaction();

            $userId = (int) (current_user()['user_id'] ?? 0);

            $stmtBook = $pdo->prepare("
                INSERT INTO books (title, author_name, description, cover_image_url, age_group, science_element, status, created_by)
                VALUES (:title, :author_name, :description, :cover_image_url, '8-12', :science_element, 'under_review', :created_by)
            ");
            $stmtBook->execute([
                'title' => $title,
                'author_name' => $author_name,
                'description' => $description,
                'cover_image_url' => $cover_image_url !== '' ? $cover_image_url : null,
                'science_element' => $science_element,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            $book_id = (int) $pdo->lastInsertId();

            $stmtBookCat = $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)');
            $stmtBookCat->execute([$book_id, $category_id]);

            $stmtPage = $pdo->prepare('
                INSERT INTO book_pages (book_id, page_number, page_text, image_url)
                VALUES (?, ?, ?, ?)
            ');
            $pageNum = 1;
            foreach ($paragraphs as $para) {
                $stmtPage->execute([
                    $book_id,
                    $pageNum,
                    $para,
                    $pageNum === 1 && $cover_image_url !== '' ? $cover_image_url : null,
                ]);
                $pageNum++;
            }

            $stmtSubmission = $pdo->prepare("
                INSERT INTO submissions (book_id, submitted_by, status)
                VALUES (?, ?, 'under_review')
            ");
            $stmtSubmission->execute([$book_id, $userId > 0 ? $userId : null]);

            $pdo->commit();
            $message = 'Your story has been submitted for review.';
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $error = 'Submission failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Submit a Story')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <?php render_page_header(
        'Submit Your Science Story',
        'Share a fictional story for ages 8–12 that teaches one clear science idea. All stories are reviewed before publishing.'
    ); ?>

    <div class="page-section">
    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
        <p><a href="<?= e(app_url('creator-dashboard.php')) ?>" class="btn btn-primary">View Creator Dashboard</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="form-panel">
            <form method="post" action="<?= e(app_url('submit-story.php')) ?>">
                <div class="form-group">
                    <label for="title">Story Title *</label>
                    <input type="text" id="title" name="title" class="form-control" required maxlength="255" value="<?= e($_POST['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="author_name">Author Name *</label>
                    <input type="text" id="author_name" name="author_name" class="form-control" required maxlength="255" value="<?= e($_POST['author_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="science_topic">Science Topic *</label>
                    <select id="science_topic" name="science_topic" class="form-control" required>
                        <option value="">Choose a topic</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= e($topic) ?>" <?= (($_POST['science_topic'] ?? '') === $topic) ? 'selected' : '' ?>><?= e($topic) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Short Description *</label>
                    <textarea id="description" name="description" class="form-control" required rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="story_text">Full Story Text *</label>
                    <textarea id="story_text" name="story_text" class="form-control" required rows="12" placeholder="Separate paragraphs with a blank line. Each paragraph becomes one story page."><?= e($_POST['story_text'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="science_element">Science Element Explanation *</label>
                    <textarea id="science_element" name="science_element" class="form-control" required rows="4" placeholder="Explain the one science idea kids learn from this story."><?= e($_POST['science_element'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="cover_image_url">Cover Image URL</label>
                    <input type="url" id="cover_image_url" name="cover_image_url" class="form-control" placeholder="https://example.com/cover.jpg" value="<?= e($_POST['cover_image_url'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Submit for Review</button>
            </form>
        </div>
    <?php endif; ?>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
