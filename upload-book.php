<?php
require_once __DIR__ . '/auth.php';
require_creator_login();

ensure_book_pdf_schema($pdo);

$topics = ['Space', 'Human Body', 'Plants', 'Animals', 'Weather', 'Microbes', 'Earth Science', 'Engineering', 'Physical Science'];
$message = '';
$error = '';
$uploadDir = books_upload_dir();
$coversDir = __DIR__ . '/uploads/covers';

foreach ([$uploadDir, $coversDir, dirname($uploadDir)] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author_name = trim($_POST['author_name'] ?? '');
    $science_topic = trim($_POST['science_topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $science_element = trim($_POST['science_element'] ?? '');
    $cover_image_url = trim($_POST['cover_image_url'] ?? '');

    if ($title === '' || $author_name === '' || $science_topic === '' || $description === '' || $science_element === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($science_topic, $topics, true)) {
        $error = 'Please choose a valid science topic.';
    } elseif (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErr = $_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = match ($uploadErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'PDF is too large. XAMPP allows up to ' . ini_get('upload_max_filesize') . ' per file.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Please upload a PDF file.',
            default => 'Upload failed (error code ' . $uploadErr . ').',
        };
    } else {
        $file = $_FILES['pdf_file'];
        $tmpPath = $file['tmp_name'];
        $originalName = $file['name'] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            $error = 'Only PDF files are allowed.';
        } elseif ($file['size'] > 128 * 1024 * 1024) {
            $error = 'PDF file is too large (max 128 MB).';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmpPath) : '';
            if ($finfo) {
                finfo_close($finfo);
            }

            $handle = fopen($tmpPath, 'rb');
            $header = $handle ? fread($handle, 4) : '';
            if ($handle) {
                fclose($handle);
            }

            if ($mime !== 'application/pdf' || $header !== '%PDF') {
                $error = 'Invalid file. Only valid PDF uploads are accepted.';
            } else {
                $savedCoverUrl = $cover_image_url !== '' ? $cover_image_url : null;
                if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                    $coverFile = $_FILES['cover_file'];
                    $coverExt = strtolower(pathinfo($coverFile['name'] ?? '', PATHINFO_EXTENSION));
                    $allowedCover = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    $coverMime = '';
                    $cfinfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($cfinfo) {
                        $coverMime = finfo_file($cfinfo, $coverFile['tmp_name']);
                        finfo_close($cfinfo);
                    }
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    if (in_array($coverExt, $allowedCover, true) && in_array($coverMime, $allowedMimes, true)) {
                        $coverSafe = 'cover_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . ($coverExt === 'jpeg' ? 'jpg' : $coverExt);
                        $coverRel = 'uploads/covers/' . $coverSafe;
                        $coverFull = __DIR__ . '/' . $coverRel;
                        if (move_uploaded_file($coverFile['tmp_name'], $coverFull)) {
                            $savedCoverUrl = $coverRel;
                        }
                    }
                }

                $safeName = 'book_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
                $relativePath = 'uploads/books/' . $safeName;
                $fullPath = __DIR__ . '/' . $relativePath;

                if (!is_writable($uploadDir)) {
                    $error = 'Could not save the PDF file: uploads/books is not writable by the web server. '
                        . 'In Terminal run: chmod -R 777 /Applications/XAMPP/xamppfiles/htdocs/stories/uploads';
                } elseif (!move_uploaded_file($tmpPath, $fullPath)) {
                    $last = error_get_last();
                    $detail = $last['message'] ?? 'unknown error';
                    $error = 'Could not save the PDF file. ' . $detail;
                } else {
                    require_once __DIR__ . '/pdf-branding-lib.php';
                    brand_pdf_file($fullPath);

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

                        $userId = (int) (current_user()['user_id'] ?? 0);

                        $pdo->beginTransaction();

                        $stmtBook = $pdo->prepare("
                            INSERT INTO books (
                                title, author_name, description, cover_image_url,
                                age_group, science_element, status, book_format, pdf_file_path, created_by
                            ) VALUES (
                                :title, :author_name, :description, :cover_image_url,
                                '8-15', :science_element, 'under_review', 'pdf', :pdf_file_path, :created_by
                            )
                        ");
                        $stmtBook->execute([
                            'title' => $title,
                            'author_name' => $author_name,
                            'description' => $description,
                            'cover_image_url' => $savedCoverUrl,
                            'science_element' => $science_element,
                            'pdf_file_path' => $relativePath,
                            'created_by' => $userId > 0 ? $userId : null,
                        ]);
                        $book_id = (int) $pdo->lastInsertId();

                        $stmtBookCat = $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)');
                        $stmtBookCat->execute([$book_id, $category_id]);

                        $stmtSubmission = $pdo->prepare("
                            INSERT INTO submissions (book_id, submitted_by, status)
                            VALUES (?, ?, 'under_review')
                        ");
                        $stmtSubmission->execute([$book_id, $userId > 0 ? $userId : null]);

                        $pdo->commit();

                        $message = 'PDF book submitted for review.';
                        header('Location: ' . app_url('creator-dashboard.php'));
                        exit;
                    } catch (PDOException $ex) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if (is_file($fullPath)) {
                            unlink($fullPath);
                        }
                        error_log($ex->getMessage());
                        $error = 'Database error while saving the book.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('Upload PDF Book')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class() ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact">
    <?php render_page_header(
        'Upload PDF Book',
        'Upload a finished PDF story. It will be reviewed by an admin before it appears in the library.'
    ); ?>

    <div class="page-section">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="form-panel">
        <form method="post" action="<?= e(app_url('upload-book.php')) ?>" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" class="form-control" required maxlength="255"
                    value="<?= e($_POST['title'] ?? 'Grandpa Green and Nora') ?>">
            </div>
            <div class="form-group">
                <label for="author_name">Author Name *</label>
                <input type="text" id="author_name" name="author_name" class="form-control" required maxlength="255"
                    value="<?= e($_POST['author_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="science_topic">Science Topic *</label>
                <select id="science_topic" name="science_topic" class="form-control" required>
                    <option value="">Choose a topic</option>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= e($topic) ?>" <?= (($_POST['science_topic'] ?? 'Plants') === $topic) ? 'selected' : '' ?>><?= e($topic) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="description">Short Description *</label>
                <textarea id="description" name="description" class="form-control" required rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="science_element">Science Element *</label>
                <textarea id="science_element" name="science_element" class="form-control" required rows="4"><?= e($_POST['science_element'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="cover_file">Cover Image (optional)</label>
                <input type="file" id="cover_file" name="cover_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif">
                <p style="font-size:0.9rem;color:var(--color-text-muted);margin-top:8px;">JPG, PNG, WebP, or GIF</p>
            </div>
            <div class="form-group">
                <label for="cover_image_url">Or Cover Image URL</label>
                <input type="url" id="cover_image_url" name="cover_image_url" class="form-control"
                    placeholder="https://example.com/cover.jpg" value="<?= e($_POST['cover_image_url'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="pdf_file">PDF File *</label>
                <input type="file" id="pdf_file" name="pdf_file" class="form-control" accept="application/pdf,.pdf" required>
                <p style="font-size:0.9rem;color:var(--color-text-muted);margin-top:8px;">PDF only · max 128 MB</p>
            </div>
            <button type="submit" class="btn btn-primary">Upload Book</button>
        </form>
    </div>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
