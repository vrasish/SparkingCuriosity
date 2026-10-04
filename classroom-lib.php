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
            visitor_token VARCHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id),
            KEY idx_classroom_events_class (classroom_id, event_type),
            KEY idx_classroom_events_book (classroom_id, book_id),
            KEY idx_classroom_events_visitor (classroom_id, visitor_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Older installs may already have classroom_events without visitor_token.
    try {
        $col = $pdo->query("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'classroom_events'
              AND COLUMN_NAME = 'visitor_token'
        ")->fetchColumn();
        if (!$col) {
            $pdo->exec('ALTER TABLE classroom_events ADD COLUMN visitor_token VARCHAR(64) NULL AFTER total');
            $pdo->exec('ALTER TABLE classroom_events ADD KEY idx_classroom_events_visitor (classroom_id, visitor_token)');
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
    }

    $checked = true;
}

function classroom_seed_ms_kim(PDO $pdo): void
{
    static $seeded = false;
    if ($seeded) {
        return;
    }
    ensure_classrooms_schema($pdo);

    $teacherId = null;
    foreach (['akim@lasdk8.org', 'akim@lasdschools.org'] as $email) {
        $teacherStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $teacherStmt->execute([$email]);
        $teacherId = $teacherStmt->fetchColumn();
        if ($teacherId) {
            break;
        }
    }
    if (!$teacherId) {
        $seeded = true;
        return;
    }

    $assignedBookId = null;
    $bookStmt = $pdo->prepare("
        SELECT book_id FROM books
        WHERE status = 'approved'
          AND (title LIKE ? OR story_topic LIKE ?)
        ORDER BY book_id
        LIMIT 1
    ");
    $bookStmt->execute(['%Good Bacteria Club%', '%Good bacteria%']);
    $assignedBookId = $bookStmt->fetchColumn();
    if ($assignedBookId === false) {
        $assignedBookId = null;
    } else {
        $assignedBookId = (int) $assignedBookId;
    }

    $exists = $pdo->prepare('SELECT classroom_id FROM classrooms WHERE slug = ? LIMIT 1');
    $exists->execute(['MsKim']);
    $existingId = $exists->fetchColumn();
    if ($existingId) {
        // Keep MsKim on the preferred login account; do not overwrite teacher-chosen assignment.
        $pdo->prepare('UPDATE classrooms SET teacher_user_id = ? WHERE classroom_id = ?')
            ->execute([(int) $teacherId, (int) $existingId]);
        classroom_seed_ms_kim_seed_story_done($pdo, (int) $existingId);
        $seeded = true;
        return;
    }

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
        'Good bacteria (Microbiome)',
        '🦠',
        $assignedBookId,
    ]);

    $newId = (int) $pdo->lastInsertId();
    if ($newId > 0) {
        classroom_seed_ms_kim_seed_story_done($pdo, $newId);
    }

    $seeded = true;
}

/**
 * Demo: mark The Seed That Slept Underground as a completed story (24 students, 100%).
 * Current assigned story remains Good Bacteria Club. Skips if seed quiz events already exist.
 */
function classroom_seed_ms_kim_seed_story_done(PDO $pdo, int $classroomId): void
{
    if ($classroomId <= 0) {
        return;
    }

    $seedBookStmt = $pdo->prepare("
        SELECT book_id FROM books
        WHERE status = 'approved'
          AND (title LIKE ? OR story_topic = 'Germination')
        ORDER BY book_id
        LIMIT 1
    ");
    $seedBookStmt->execute(['%Seed That Slept%']);
    $seedBookId = $seedBookStmt->fetchColumn();
    if ($seedBookId === false) {
        return;
    }
    $seedBookId = (int) $seedBookId;

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM classroom_events
        WHERE classroom_id = ? AND book_id = ? AND event_type = 'quiz_complete'
    ");
    $countStmt->execute([$classroomId, $seedBookId]);
    if ((int) $countStmt->fetchColumn() > 0) {
        return;
    }

    $classSize = 24;
    $ins = $pdo->prepare("
        INSERT INTO classroom_events (classroom_id, event_type, book_id, score, total, visitor_token)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    for ($i = 1; $i <= $classSize; $i++) {
        $token = hash('sha256', 'mskim-demo-student-' . $i);
        $ins->execute([$classroomId, 'story_open', $seedBookId, null, null, $token]);
        $ins->execute([$classroomId, 'quiz_complete', $seedBookId, 5, 5, $token]);
    }
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

function classroom_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/** Anonymous browser token for class-level unique counts (not tied to a student identity). */
function classroom_visitor_token(): string
{
    $existing = trim((string) ($_COOKIE['scifables_class_visitor'] ?? ''));
    if ($existing !== '' && preg_match('/^[a-f0-9]{32,64}$/', $existing)) {
        return $existing;
    }

    try {
        $token = bin2hex(random_bytes(16));
    } catch (Throwable $ex) {
        $token = hash('sha256', uniqid('class', true));
    }

    setcookie('scifables_class_visitor', $token, classroom_cookie_options(time() + 60 * 60 * 24 * 180));
    $_COOKIE['scifables_class_visitor'] = $token;

    return $token;
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
    setcookie('scifables_classroom', $cookieValue, classroom_cookie_options(time() + 60 * 60 * 24 * 90));
    classroom_visitor_token();
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

function classroom_should_exclude_activity(): bool
{
    // SciFables admin testing must not pollute teacher classroom analytics.
    return function_exists('is_admin_user') && is_admin_user();
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
    if (classroom_should_exclude_activity()) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO classroom_events (classroom_id, event_type, book_id, score, total, visitor_token)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $classroomId,
            $eventType,
            $bookId,
            $score,
            $total,
            classroom_visitor_token(),
        ]);

        return true;
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return false;
    }
}

/**
 * Wipe all activity events for a classroom (admin/test reset). Keeps class settings.
 *
 * @return array{ok: bool, error?: string, deleted?: int}
 */
function classroom_reset_analytics(PDO $pdo, int $classroomId): array
{
    ensure_classrooms_schema($pdo);
    if ($classroomId <= 0) {
        return ['ok' => false, 'error' => 'Classroom not found.'];
    }

    $classroom = classroom_by_id($pdo, $classroomId);
    if (!$classroom) {
        return ['ok' => false, 'error' => 'Classroom not found.'];
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM classroom_events WHERE classroom_id = ?');
        $stmt->execute([$classroomId]);

        return ['ok' => true, 'deleted' => $stmt->rowCount()];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return ['ok' => false, 'error' => 'Could not reset classroom analytics.'];
    }
}

/**
 * @return array{
 *   stories_read: int,
 *   quizzes_completed: int,
 *   average_quiz_score: float|null,
 *   additional_stories_explored: int,
 *   unique_stories: int,
 *   students_read_assigned: int,
 *   students_completed_quiz: int,
 *   assigned_story_opens: int,
 *   assigned_quizzes_completed: int,
 *   assigned_average_quiz_score: float|null,
 *   students_completed_assigned_quiz: int
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
        'students_read_assigned' => 0,
        'students_completed_quiz' => 0,
        'assigned_story_opens' => 0,
        'assigned_quizzes_completed' => 0,
        'assigned_average_quiz_score' => null,
        'students_completed_assigned_quiz' => 0,
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

    // Extra exploration = opened books that are not the current assignment and not already "story completed".
    $additional = 0;
    if ($assignedBookId > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.book_id)
            FROM classroom_events o
            WHERE o.classroom_id = ?
              AND o.event_type = 'story_open'
              AND o.book_id <> ?
              AND o.book_id NOT IN (
                  SELECT q.book_id FROM classroom_events q
                  WHERE q.classroom_id = o.classroom_id
                    AND q.event_type = 'quiz_complete'
              )
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $additional = (int) $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.book_id)
            FROM classroom_events o
            WHERE o.classroom_id = ?
              AND o.event_type = 'story_open'
              AND o.book_id NOT IN (
                  SELECT q.book_id FROM classroom_events q
                  WHERE q.classroom_id = o.classroom_id
                    AND q.event_type = 'quiz_complete'
              )
        ");
        $stmt->execute([$classroomId]);
        $additional = (int) $stmt->fetchColumn();
    }

    $studentsReadAssigned = 0;
    $assignedStoryOpens = 0;
    $assignedQuizzes = 0;
    $assignedAvg = null;
    $studentsAssignedQuiz = 0;
    if ($assignedBookId > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT visitor_token) FROM classroom_events
            WHERE classroom_id = ?
              AND event_type = 'story_open'
              AND book_id = ?
              AND visitor_token IS NOT NULL
              AND visitor_token <> ''
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $studentsReadAssigned = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM classroom_events
            WHERE classroom_id = ? AND event_type = 'story_open' AND book_id = ?
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $assignedStoryOpens = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM classroom_events
            WHERE classroom_id = ? AND event_type = 'quiz_complete' AND book_id = ?
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $assignedQuizzes = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT AVG(CASE WHEN total > 0 THEN (score * 100.0) / total ELSE NULL END)
            FROM classroom_events
            WHERE classroom_id = ?
              AND event_type = 'quiz_complete'
              AND book_id = ?
              AND total IS NOT NULL
              AND total > 0
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $assignedAvgRaw = $stmt->fetchColumn();
        if ($assignedAvgRaw !== false && $assignedAvgRaw !== null) {
            $assignedAvg = round((float) $assignedAvgRaw);
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT visitor_token) FROM classroom_events
            WHERE classroom_id = ?
              AND event_type = 'quiz_complete'
              AND book_id = ?
              AND visitor_token IS NOT NULL
              AND visitor_token <> ''
        ");
        $stmt->execute([$classroomId, $assignedBookId]);
        $studentsAssignedQuiz = (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT visitor_token) FROM classroom_events
        WHERE classroom_id = ?
          AND event_type = 'quiz_complete'
          AND visitor_token IS NOT NULL
          AND visitor_token <> ''
    ");
    $stmt->execute([$classroomId]);
    $studentsQuizzed = (int) $stmt->fetchColumn();

    return [
        'stories_read' => $storiesRead,
        'quizzes_completed' => $quizzes,
        'average_quiz_score' => $avg,
        'additional_stories_explored' => $additional,
        'unique_stories' => $uniqueStories,
        'students_read_assigned' => $studentsReadAssigned,
        'students_completed_quiz' => $studentsQuizzed,
        'assigned_story_opens' => $assignedStoryOpens,
        'assigned_quizzes_completed' => $assignedQuizzes,
        'assigned_average_quiz_score' => $assignedAvg,
        'students_completed_assigned_quiz' => $studentsAssignedQuiz,
    ];
}

/**
 * Stories students finished (quiz complete), separate from the currently assigned story.
 *
 * @return list<array{
 *   book_id: int,
 *   title: string,
 *   story_topic: string,
 *   students_completed: int,
 *   quizzes_completed: int,
 *   average_quiz_score: float|null
 * }>
 */
function classroom_stories_completed(PDO $pdo, array $classroom): array
{
    ensure_classrooms_schema($pdo);
    $classroomId = (int) ($classroom['classroom_id'] ?? 0);
    if ($classroomId <= 0) {
        return [];
    }

    $assignedBookId = (int) ($classroom['assigned_book_id'] ?? 0);

    try {
        // Past completed stories only — current assignment is shown separately.
        $sql = "
            SELECT
                e.book_id,
                COALESCE(b.title, CONCAT('Story #', e.book_id)) AS title,
                COALESCE(b.story_topic, '') AS story_topic,
                COUNT(*) AS quizzes_completed,
                COUNT(DISTINCT NULLIF(e.visitor_token, '')) AS students_completed,
                AVG(CASE WHEN e.total > 0 THEN (e.score * 100.0) / e.total ELSE NULL END) AS average_quiz_score
            FROM classroom_events e
            LEFT JOIN books b ON b.book_id = e.book_id
            WHERE e.classroom_id = ?
              AND e.event_type = 'quiz_complete'
        ";
        $params = [$classroomId];
        if ($assignedBookId > 0) {
            $sql .= ' AND e.book_id <> ?';
            $params[] = $assignedBookId;
        }
        $sql .= '
            GROUP BY e.book_id, b.title, b.story_topic
            ORDER BY students_completed DESC, title ASC
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $avgRaw = $row['average_quiz_score'] ?? null;
            $out[] = [
                'book_id' => (int) ($row['book_id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'story_topic' => (string) ($row['story_topic'] ?? ''),
                'students_completed' => (int) ($row['students_completed'] ?? 0),
                'quizzes_completed' => (int) ($row['quizzes_completed'] ?? 0),
                'average_quiz_score' => ($avgRaw === null || $avgRaw === '') ? null : round((float) $avgRaw),
            ];
        }

        return $out;
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return [];
    }
}

/** @return list<array{book_id:int,title:string,story_topic:?string}> */
function classroom_assignable_books(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT book_id, title, story_topic
            FROM books
            WHERE status = 'approved'
            ORDER BY title ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return [];
    }
}

/**
 * @return array{ok: bool, error?: string}
 */
function classroom_update_assignment(
    PDO $pdo,
    int $classroomId,
    int $teacherUserId,
    int $bookId,
    string $assignedTopic,
    string $assignedTopicIcon,
    int $classSize
): array {
    ensure_classrooms_schema($pdo);
    $classroom = classroom_by_id($pdo, $classroomId);
    if (!$classroom || (int) ($classroom['teacher_user_id'] ?? 0) !== $teacherUserId) {
        return ['ok' => false, 'error' => 'Classroom not found.'];
    }

    $assignedTopic = trim($assignedTopic);
    $assignedTopicIcon = trim($assignedTopicIcon);
    if ($bookId <= 0) {
        return ['ok' => false, 'error' => 'Choose a story to assign.'];
    }
    if ($assignedTopic === '') {
        return ['ok' => false, 'error' => 'Assigned topic is required.'];
    }
    if ($classSize < 1) {
        $classSize = 1;
    }

    $bookStmt = $pdo->prepare("SELECT book_id, story_topic FROM books WHERE book_id = ? AND status = 'approved' LIMIT 1");
    $bookStmt->execute([$bookId]);
    $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) {
        return ['ok' => false, 'error' => 'That story is not available.'];
    }

    if ($assignedTopicIcon === '') {
        $assignedTopicIcon = '🌱';
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE classrooms
            SET assigned_book_id = ?, assigned_topic = ?, assigned_topic_icon = ?, class_size = ?
            WHERE classroom_id = ? AND teacher_user_id = ?
        ");
        $stmt->execute([
            $bookId,
            $assignedTopic,
            $assignedTopicIcon,
            $classSize,
            $classroomId,
            $teacherUserId,
        ]);

        return ['ok' => true];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return ['ok' => false, 'error' => 'Could not update the assignment.'];
    }
}

function teacher_has_classroom(PDO $pdo, int $userId): bool
{
    return classrooms_for_teacher($pdo, $userId) !== [];
}

/**
 * All classrooms for SciFables admin (no student PII).
 *
 * @return list<array<string, mixed>>
 */
function classrooms_all_for_admin(PDO $pdo): array
{
    ensure_classrooms_schema($pdo);
    classroom_seed_ms_kim($pdo);

    try {
        $stmt = $pdo->query("
            SELECT
                c.*,
                u.full_name AS teacher_account_name,
                u.email AS teacher_email,
                b.title AS assigned_book_title
            FROM classrooms c
            LEFT JOIN users u ON u.user_id = c.teacher_user_id
            LEFT JOIN books b ON b.book_id = c.assigned_book_id
            ORDER BY c.school_name ASC, c.teacher_display_name ASC, c.classroom_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return [];
    }
}

/**
 * Platform-wide classroom impact totals (aggregate only).
 *
 * @return array{
 *   schools: int,
 *   classrooms: int,
 *   students_reached: int,
 *   stories_opened: int,
 *   quizzes_completed: int,
 *   average_quiz_score: float|null,
 *   additional_stories_explored: int
 * }
 */
function classroom_platform_impact(PDO $pdo): array
{
    ensure_classrooms_schema($pdo);
    classroom_seed_ms_kim($pdo);

    $empty = [
        'schools' => 0,
        'classrooms' => 0,
        'students_reached' => 0,
        'stories_opened' => 0,
        'quizzes_completed' => 0,
        'average_quiz_score' => null,
        'additional_stories_explored' => 0,
    ];

    try {
        $row = $pdo->query("
            SELECT
                COUNT(*) AS classrooms,
                COUNT(DISTINCT NULLIF(TRIM(school_name), '')) AS schools,
                COALESCE(SUM(class_size), 0) AS students_reached
            FROM classrooms
        ")->fetch(PDO::FETCH_ASSOC);

        $storiesOpened = (int) $pdo->query("
            SELECT COUNT(*) FROM classroom_events WHERE event_type = 'story_open'
        ")->fetchColumn();

        $quizzesCompleted = (int) $pdo->query("
            SELECT COUNT(*) FROM classroom_events WHERE event_type = 'quiz_complete'
        ")->fetchColumn();

        $avgRaw = $pdo->query("
            SELECT AVG(CASE WHEN total > 0 THEN (score * 100.0) / total ELSE NULL END)
            FROM classroom_events
            WHERE event_type = 'quiz_complete' AND total IS NOT NULL AND total > 0
        ")->fetchColumn();
        $avg = ($avgRaw !== false && $avgRaw !== null) ? round((float) $avgRaw) : null;

        // Sum per-classroom "extra stories" (distinct books opened besides assigned).
        $additional = 0;
        $classrooms = $pdo->query('SELECT classroom_id, assigned_book_id FROM classrooms')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($classrooms as $classroom) {
            $stats = classroom_stats($pdo, $classroom);
            $additional += (int) ($stats['additional_stories_explored'] ?? 0);
        }

        return [
            'schools' => (int) ($row['schools'] ?? 0),
            'classrooms' => (int) ($row['classrooms'] ?? 0),
            'students_reached' => (int) ($row['students_reached'] ?? 0),
            'stories_opened' => $storiesOpened,
            'quizzes_completed' => $quizzesCompleted,
            'average_quiz_score' => $avg,
            'additional_stories_explored' => $additional,
        ];
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        return $empty;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function classroom_admin_rows_with_stats(PDO $pdo): array
{
    $rows = [];
    foreach (classrooms_all_for_admin($pdo) as $classroom) {
        $stats = classroom_stats($pdo, $classroom);
        $completed = classroom_stories_completed($pdo, $classroom);
        $rows[] = array_merge($classroom, [
            'stats' => $stats,
            'completed_stories' => $completed,
        ]);
    }

    return $rows;
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
