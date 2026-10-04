<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_classrooms_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classrooms (
            classroom_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_user_id INT UNSIGNED NOT NULL,
            slug VARCHAR(80) NOT NULL,
            teacher_display_name VARCHAR(120) NOT NULL,
            school_name VARCHAR(160) NULL,
            class_size INT UNSIGNED NOT NULL DEFAULT 0,
            assigned_topic VARCHAR(120) NOT NULL DEFAULT '',
            assigned_topic_icon VARCHAR(16) NOT NULL DEFAULT '🌱',
            assigned_book_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (classroom_id),
            UNIQUE KEY uq_classrooms_slug (slug),
            KEY idx_classrooms_teacher (teacher_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classroom_events (
            event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            classroom_id INT UNSIGNED NOT NULL,
            event_type ENUM('story_open', 'quiz_complete') NOT NULL,
            book_id INT UNSIGNED NOT NULL,
            score INT NULL,
            total INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id),
            KEY idx_classroom_events_class (classroom_id, event_type),
            KEY idx_classroom_events_book (classroom_id, book_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $checked = true;
}

function classroom_seed_ms_kim(PDO $pdo): void
{
    static $seeded = false;
    if ($seeded) {
        return;
    }
    ensure_classrooms_schema($pdo);

    $exists = $pdo->prepare('SELECT classroom_id FROM classrooms WHERE slug = ? LIMIT 1');
    $exists->execute(['MsKim']);
    if ($exists->fetchColumn()) {
        $seeded = true;
        return;
    }

    $teacherId = null;
    $teacherStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $teacherStmt->execute(['akim@lasdschools.org']);
    $teacherId = $teacherStmt->fetchColumn();
    if (!$teacherId) {
        $seeded = true;
        return;
    }

    $bookId = null;
    $bookStmt = $pdo->prepare("
        SELECT book_id FROM books
        WHERE status = 'approved'
          AND (story_topic = 'Germination' OR title LIKE ?)
        ORDER BY book_id
        LIMIT 1
    ");
    $bookStmt->execute(['%Seed That Slept%']);
    $bookId = $bookStmt->fetchColumn();

    $insert = $pdo->prepare("
        INSERT INTO classrooms (
            teacher_user_id, slug, teacher_display_name, school_name,
            class_size, assigned_topic, assigned_topic_icon, assigned_book_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        (int) $teacherId,
        'MsKim',
        'Ms. Kim',
        'Loyola Elementary',
        24,
        'Germination',
        '🌱',
        $bookId !== false ? (int) $bookId : null,
    ]);

    $seeded = true;
}

/** @return array<string, mixed>|null */
function classroom_by_slug(PDO $pdo, string $slug): ?array
{
    ensure_classrooms_schema($pdo);
    classroom_seed_ms_kim($pdo);

    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM classrooms WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        // Case-insensitive fallback
        $stmt = $pdo->prepare('SELECT * FROM classrooms WHERE LOWER(slug) = LOWER(?) LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function classroom_by_id(PDO $pdo, int $classroomId): ?array
{
    ensure_classrooms_schema($pdo);
    if ($classroomId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM classrooms WHERE classroom_id = ? LIMIT 1');
    $stmt->execute([$classroomId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function classrooms_for_teacher(PDO $pdo, int $teacherUserId): array
{
    ensure_classrooms_schema($pdo);
    classroom_seed_ms_kim($pdo);
    if ($teacherUserId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM classrooms WHERE teacher_user_id = ? ORDER BY created_at ASC');
    $stmt->execute([$teacherUserId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function classroom_public_url(string $slug): string
{
    $base = rtrim(app_base_path(), '/');
    $path = ($base === '' ? '' : $base) . '/class/' . rawurlencode($slug);

    return $path;
}

function classroom_absolute_url(string $slug): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'scifables.com');
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host . classroom_public_url($slug);
}

function classroom_enter_session(array $classroom): void
{
    stories_open_writable_session();
    $_SESSION['classroom_id'] = (int) ($classroom['classroom_id'] ?? 0);
    $_SESSION['classroom_slug'] = (string) ($classroom['slug'] ?? '');
    $_SESSION['classroom_teacher'] = (string) ($classroom['teacher_display_name'] ?? '');
    $_SESSION['classroom_assigned_topic'] = (string) ($classroom['assigned_topic'] ?? '');
    $_SESSION['classroom_assigned_icon'] = (string) ($classroom['assigned_topic_icon'] ?? '🌱');
    $_SESSION['classroom_assigned_book_id'] = (int) ($classroom['assigned_book_id'] ?? 0);

    $cookieValue = (string) ((int) ($classroom['classroom_id'] ?? 0));
    setcookie('scifables_classroom', $cookieValue, [
        'expires' => time() + 60 * 60 * 24 * 90,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function classroom_current_id(): int
{
    $fromSession = (int) ($_SESSION['classroom_id'] ?? 0);
    if ($fromSession > 0) {
        return $fromSession;
    }

    $fromCookie = (int) ($_COOKIE['scifables_classroom'] ?? 0);
    if ($fromCookie > 0) {
        return $fromCookie;
    }

    return 0;
}

function is_classroom_guest(): bool
{
    return !is_logged_in() && classroom_current_id() > 0;
}

/** @return array<string, mixed>|null */
function classroom_current(PDO $pdo): ?array
{
    $id = classroom_current_id();
    if ($id <= 0) {
        return null;
    }

    $classroom = classroom_by_id($pdo, $id);
    if ($classroom && empty($_SESSION['classroom_id'])) {
        classroom_enter_session($classroom);
    }

    return $classroom;
}

function classroom_record_event(
    PDO $pdo,
    int $classroomId,
    string $eventType,
    int $bookId,
    ?int $score = null,
    ?int $total = null
): bool {
    ensure_classrooms_schema($pdo);
    if ($classroomId <= 0 || $bookId <= 0) {
        return false;
    }
    if (!in_array($eventType, ['story_open', 'quiz_complete'], true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO classroom_events (classroom_id, event_type, book_id, score, total)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$classroomId, $eventType, $bookId, $score, $total]);

        return true;
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return false;
    }
}

/**
 * @return array{
 *   stories_read: int,
 *   quizzes_completed: int,
 *   average_quiz_score: float|null,
 *   additional_stories_explored: int,
 *   unique_stories: int
 * }
 */
function classroom_stats(PDO $pdo, array $classroom): array
{
    ensure_classrooms_schema($pdo);
    $classroomId = (int) ($classroom['classroom_id'] ?? 0);
    $assignedBookId = (int) ($classroom['assigned_book_id'] ?? 0);

    $empty = [
        'stories_read' => 0,
        'quizzes_completed' => 0,
        'average_quiz_score' => null,
        'additional_stories_explored' => 0,
        'unique_stories' => 0,
    ];
    if ($classroomId <= 0) {
        return $empty;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM classroom_events
        WHERE classroom_id = ? AND event_type = 'story_open'
    ");
    $stmt->execute([$classroomId]);
    $storiesRead = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM classroom_events
        WHERE classroom_id = ? AND event_type = 'quiz_complete'
    ");
    $stmt->execute([$classroomId]);
    $quizzes = (int) $stmt->fetchColumn();

    $avg = null;
    $stmt = $pdo->prepare("
        SELECT AVG(CASE WHEN total > 0 THEN (score * 100.0) / total ELSE NULL END)
        FROM classroom_events
        WHERE classroom_id = ? AND event_type = 'quiz_complete' AND total IS NOT NULL AND total > 0
    ");
    $stmt->execute([$classroomId]);
    $avgRaw = $stmt->fetchColumn();
    if ($avgRaw !== false && $avgRaw !== null) {
        $avg = round((float) $avgRaw);
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT book_id) FROM classroom_events
        WHERE classroom_id = ? AND event_type = 'story_open'
    ");
    $stmt->execute([$classroomId]);
    $uniqueStories = (int) $stmt->fetchColumn();

    $additional = 0;
    if ($assignedBookId > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT book_id) FROM classroom_events
            WHERE classroom_id = ? AND event_type = 'story_open' AND book_id <> ?
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $additional = (int) $stmt->fetchColumn();
    } else {
        $additional = $uniqueStories;
    }

    return [
        'stories_read' => $storiesRead,
        'quizzes_completed' => $quizzes,
        'average_quiz_score' => $avg,
        'additional_stories_explored' => $additional,
        'unique_stories' => $uniqueStories,
    ];
}

function render_classroom_banner(?array $classroom): void
{
    if (!$classroom) {
        return;
    }

    $teacher = (string) ($classroom['teacher_display_name'] ?? 'Your teacher');
    $topic = (string) ($classroom['assigned_topic'] ?? '');
    $icon = (string) ($classroom['assigned_topic_icon'] ?? '🌱');

    echo '<div class="class-banner" role="status">';
    echo '<div class="class-banner-inner">';
    echo '<p class="class-banner-assigned">Assigned by <strong>' . e($teacher) . '</strong></p>';
    if ($topic !== '') {
        echo '<p class="class-banner-topic"><span aria-hidden="true">' . e($icon) . '</span> ' . e($topic) . '</p>';
    }
    echo '<p class="class-banner-note">Classroom mode — all stories open, no signup needed.</p>';
    echo '</div>';
    echo '</div>';
}

function classroom_slug_available(PDO $pdo, string $slug, ?int $exceptId = null): bool
{
    ensure_classrooms_schema($pdo);
    $slug = trim($slug);
    if ($slug === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,79}$/', $slug)) {
        return false;
    }
    if ($exceptId) {
        $stmt = $pdo->prepare('SELECT classroom_id FROM classrooms WHERE LOWER(slug) = LOWER(?) AND classroom_id <> ? LIMIT 1');
        $stmt->execute([$slug, $exceptId]);
    } else {
        $stmt = $pdo->prepare('SELECT classroom_id FROM classrooms WHERE LOWER(slug) = LOWER(?) LIMIT 1');
        $stmt->execute([$slug]);
    }

    return !$stmt->fetchColumn();
}

/**
 * @return array{ok: bool, error?: string, classroom?: array<string, mixed>}
 */
function classroom_create(
    PDO $pdo,
    int $teacherUserId,
    string $slug,
    string $teacherDisplayName,
    string $schoolName,
    int $classSize,
    string $assignedTopic,
    string $assignedTopicIcon = '🌱',
    ?int $assignedBookId = null
): array {
    ensure_classrooms_schema($pdo);
    $slug = trim($slug);
    $teacherDisplayName = trim($teacherDisplayName);
    $schoolName = trim($schoolName);
    $assignedTopic = trim($assignedTopic);

    if ($teacherUserId <= 0) {
        return ['ok' => false, 'error' => 'Please log in to create a classroom.'];
    }
    if ($teacherDisplayName === '') {
        return ['ok' => false, 'error' => 'Teacher display name is required.'];
    }
    if (!classroom_slug_available($pdo, $slug)) {
        return ['ok' => false, 'error' => 'That classroom link is unavailable. Try another name (letters and numbers only).'];
    }
    if ($classSize < 1) {
        $classSize = 1;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO classrooms (
                teacher_user_id, slug, teacher_display_name, school_name,
                class_size, assigned_topic, assigned_topic_icon, assigned_book_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $teacherUserId,
            $slug,
            $teacherDisplayName,
            $schoolName !== '' ? $schoolName : null,
            $classSize,
            $assignedTopic,
            $assignedTopicIcon !== '' ? $assignedTopicIcon : '🌱',
            $assignedBookId,
        ]);
        $id = (int) $pdo->lastInsertId();
        $classroom = classroom_by_id($pdo, $id);

        return $classroom
            ? ['ok' => true, 'classroom' => $classroom]
            : ['ok' => false, 'error' => 'Classroom was created but could not be loaded.'];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return ['ok' => false, 'error' => 'Could not create classroom right now.'];
    }
}
