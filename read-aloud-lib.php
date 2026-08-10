<?php

declare(strict_types=1);

require_once __DIR__ . '/ai.php';

function read_aloud_tts_provider(): string
{
    if (ai_is_configured()) {
        return 'openai';
    }

    return 'browser';
}

/** @return array{provider: string, model: string, voice: string, instructions: string, speed: float, cache_version: string} */
function read_aloud_tts_config(): array
{
    $speed = read_aloud_tts_speed();

    return [
        'provider' => 'openai',
        'model' => 'gpt-4o-mini-tts',
        'voice' => 'shimmer',
        'instructions' => 'You are reading a children\'s science story aloud to kids ages 8–12. '
            . 'Use a warm, natural female storyteller voice with lively intonation and emotional variety. '
            . 'Slow down slightly on important science words, lift your tone gently on questions, '
            . 'and sound curious or surprised when the characters wonder about something. '
            . 'Pause briefly between sentences. Sound like a real person telling a story, not a flat robot.',
        'speed' => $speed,
        'cache_version' => 'v5-slower',
    ];
}

function read_aloud_tts_speed(): float
{
    $raw = ai_config()['read_aloud_tts_speed'] ?? 0.9;
    $speed = is_numeric($raw) ? (float) $raw : 0.9;

    return max(0.7, min(1.5, $speed));
}

function read_aloud_insert_heading_breaks(string $text): string
{
    $pattern = '/^(.+?)\s+(?=(?:[A-Z][a-z]+\s+and\s+[A-Z][a-z]+|[A-Z][a-z]+\s+(?:then|said|noticed|wondered|looked|walked|smiled|placed|points|asked|thought|found|saw|realized|discovered|explained|whispered|shouted|called|replied|gasped|knelt|opened|watched|waited|began|started|continued|showed|touched|felt|heard|remembered|decided|tried|helped|followed|brought|carried|stood|sat|turned|waved|blinked|spotted|pointed|hugged|ran|jumped|climbed|cheered|hopped|twitched|lifted|bounced|sighed|laughed|giggled|nodded|shook|reached|grabbed|dropped|fell|slept|woke|ate|drank|grew|moved|stopped|stayed|left|came|went|returned|arrived|entered|peeked|stared|glanced|raised|lowered))\b)/u';

    if (!preg_match($pattern, $text, $matches)) {
        return $text;
    }

    $heading = trim($matches[1]);
    if ($heading === '' || preg_match('/[.!]/', $heading) || str_word_count($heading) > 12) {
        return $text;
    }

    $updated = preg_replace($pattern, '$1' . "\n\n", $text, 1);

    return is_string($updated) ? $updated : $text;
}

function read_aloud_prepare_text_for_tts(string $text): string
{
    $text = trim(preg_replace('/[ \t\r\f\v]+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return '';
    }

    $text = read_aloud_insert_heading_breaks($text);

    $chunks = preg_split('/(?:\n\n+|(?<=[.!?])\s+)/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chunks) || $chunks === []) {
        return $text;
    }

    $chunks = array_values(array_filter(array_map(
        static fn (string $chunk): string => trim($chunk),
        $chunks
    ), static fn (string $chunk): bool => $chunk !== ''));

    if (count($chunks) <= 1) {
        return $chunks[0] ?? $text;
    }

    return implode("\n\n", $chunks);
}

function read_aloud_neural_available(?int $bookId = null): bool
{
    if ($bookId !== null && read_aloud_book_has_uploaded_audio($bookId)) {
        return true;
    }

    return read_aloud_tts_provider() !== 'browser';
}

function read_aloud_json_path(int $bookId): string
{
    return __DIR__ . '/data/read-aloud/' . $bookId . '.json';
}

/** @return array<string, mixed>|null */
function read_aloud_story_data(int $bookId): ?array
{
    $jsonPath = read_aloud_json_path($bookId);
    if (!is_file($jsonPath)) {
        return null;
    }

    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['pages']) || !is_array($data['pages'])) {
        return null;
    }

    return $data;
}

function read_aloud_story_id_from_title(string $title): string
{
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-');
}

/** @return array<string, mixed> */
function read_aloud_load_or_init_story(PDO $pdo, int $bookId): array
{
    $data = read_aloud_story_data($bookId);
    if ($data !== null) {
        return $data;
    }

    $stmt = $pdo->prepare('SELECT book_id, title FROM books WHERE book_id = ? LIMIT 1');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
    if (!$book) {
        throw new RuntimeException('book_not_found');
    }

    $title = (string) $book['title'];

    return [
        'story_id' => read_aloud_story_id_from_title($title),
        'book_id' => $bookId,
        'title' => $title,
        'audio_source' => 'uploaded',
        'pages' => [],
    ];
}

/** @param array<string, mixed> $data */
function read_aloud_ensure_page_in_data(array &$data, int $page, string $text = ''): void
{
    if ($page <= 0) {
        throw new InvalidArgumentException('invalid_page');
    }

    if (!isset($data['pages']) || !is_array($data['pages'])) {
        $data['pages'] = [];
    }

    foreach ($data['pages'] as $entry) {
        if (is_array($entry) && (int) ($entry['page'] ?? 0) === $page) {
            return;
        }
    }

    if ($text === '') {
        $text = $page === 1 && isset($data['title'])
            ? (string) $data['title']
            : 'Page ' . $page;
    }

    $data['pages'][] = [
        'page' => $page,
        'text' => $text,
    ];

    usort(
        $data['pages'],
        static fn (array $a, array $b): int => (int) ($a['page'] ?? 0) <=> (int) ($b['page'] ?? 0)
    );
}

function read_aloud_book_prefers_uploaded_audio(int $bookId): bool
{
    $data = read_aloud_story_data($bookId);
    if ($data === null) {
        return false;
    }

    return (($data['audio_source'] ?? '') === 'uploaded');
}

function read_aloud_book_has_uploaded_audio(int $bookId): bool
{
    $data = read_aloud_story_data($bookId);
    if ($data === null) {
        return false;
    }

    foreach ($data['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $page = (int) ($entry['page'] ?? 0);
        if ($page <= 0) {
            continue;
        }
        if (read_aloud_uploaded_audio_path($bookId, $page) !== null) {
            return true;
        }
    }

    return false;
}

function read_aloud_story_full_text(int $bookId): string
{
    $data = read_aloud_story_data($bookId);
    if ($data === null) {
        return '';
    }

    $parts = [];
    foreach ($data['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $text = isset($entry['text']) ? trim((string) $entry['text']) : '';
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return implode(' ', $parts);
}

function read_aloud_page_text(int $bookId, int $page): ?string
{
    $data = read_aloud_story_data($bookId);
    if ($data === null) {
        return null;
    }

    foreach ($data['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pageNum = isset($entry['page']) ? (int) $entry['page'] : 0;
        if ($pageNum !== $page) {
            continue;
        }
        $text = isset($entry['text']) ? trim((string) $entry['text']) : '';
        return $text !== '' ? $text : null;
    }

    return null;
}

function read_aloud_audio_dir(int $bookId): string
{
    return __DIR__ . '/data/read-aloud/audio/' . $bookId;
}

function read_aloud_uploaded_audio_path(int $bookId, int $page): ?string
{
    $path = read_aloud_audio_dir($bookId) . '/page-' . $page . '-uploaded.mp3';
    return is_file($path) ? $path : null;
}

function read_aloud_cached_audio_path(int $bookId, int $page): ?string
{
    $uploaded = read_aloud_uploaded_audio_path($bookId, $page);
    if ($uploaded !== null) {
        return $uploaded;
    }

    $tts = read_aloud_tts_config();
    $path = read_aloud_audio_dir($bookId) . '/page-' . $page . '-' . $tts['cache_version'] . '.mp3';
    if (is_file($path)) {
        return $path;
    }

    $dir = read_aloud_audio_dir($bookId);
    if (!is_dir($dir)) {
        return null;
    }

    $matches = glob($dir . '/page-' . $page . '-*.mp3');
    if (!is_array($matches)) {
        return null;
    }

    foreach ($matches as $candidate) {
        if (is_file($candidate) && !str_ends_with($candidate, '-uploaded.mp3')) {
            return $candidate;
        }
    }

    return null;
}

/** @return list<int> */
function read_aloud_playable_pages(int $bookId): array
{
    $data = read_aloud_story_data($bookId);
    if ($data === null) {
        return [];
    }

    $pages = [];
    foreach ($data['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $page = (int) ($entry['page'] ?? 0);
        if ($page <= 0) {
            continue;
        }
        $text = isset($entry['text']) ? trim((string) $entry['text']) : '';
        if ($text === '') {
            continue;
        }
        if (read_aloud_book_prefers_uploaded_audio($bookId)) {
            if (read_aloud_uploaded_audio_path($bookId, $page) === null) {
                continue;
            }
        }
        $pages[] = $page;
    }

    sort($pages);

    return $pages;
}

function read_aloud_generate_audio(int $bookId, int $page): string
{
    $cached = read_aloud_cached_audio_path($bookId, $page);
    if ($cached !== null) {
        return $cached;
    }

    if (read_aloud_book_prefers_uploaded_audio($bookId)) {
        throw new RuntimeException('uploaded_audio_missing');
    }

    if (!read_aloud_neural_available($bookId)) {
        throw new RuntimeException('neural_tts_unavailable');
    }

    $text = read_aloud_page_text($bookId, $page);
    if ($text === null) {
        throw new RuntimeException('page_text_missing');
    }

    $preparedText = read_aloud_prepare_text_for_tts($text);

    return read_aloud_generate_audio_openai($bookId, $page, $preparedText);
}

function read_aloud_generate_audio_openai(int $bookId, int $page, string $preparedText): string
{
    $config = ai_config();
    $tts = read_aloud_tts_config();
    $payloadData = [
        'model' => $tts['model'],
        'input' => $preparedText,
        'voice' => $tts['voice'],
        'response_format' => 'mp3',
        'instructions' => $tts['instructions'],
        'speed' => $tts['speed'],
    ];
    $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new RuntimeException('tts_encode_failed');
    }

    $lastError = 'unknown';
    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        if ($ch === false) {
            throw new RuntimeException('curl_init_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . ai_api_key(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $audio = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($audio !== false && $httpCode === 200) {
            return read_aloud_save_audio_file($bookId, $page, $audio, $tts['cache_version']);
        }

        $waitSeconds = min(45, (int) pow(2, $attempt));
        sleep($waitSeconds);
        $lastError = $curlError !== '' ? $curlError : 'HTTP ' . $httpCode;
    }

    throw new RuntimeException('tts_request_failed: ' . $lastError);
}

function read_aloud_save_audio_file(int $bookId, int $page, string $audio, string $cacheVersion): string
{
    $dir = read_aloud_audio_dir($bookId);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('audio_dir_create_failed');
    }
    @chmod($dir, 0777);

    $path = $dir . '/page-' . $page . '-' . $cacheVersion . '.mp3';
    if (file_put_contents($path, $audio) === false) {
        throw new RuntimeException('audio_write_failed');
    }
    @chmod($path, 0666);

    return $path;
}

function read_aloud_import_uploaded_mp3(int $bookId, int $page, string $sourcePath): string
{
    if ($page <= 0) {
        throw new InvalidArgumentException('invalid_page');
    }
    if (!is_file($sourcePath)) {
        throw new InvalidArgumentException('source_missing');
    }

    $dir = read_aloud_audio_dir($bookId);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('audio_dir_create_failed');
    }
    @chmod($dir, 0777);

    $dest = $dir . '/page-' . $page . '-uploaded.mp3';
    if (!copy($sourcePath, $dest)) {
        throw new RuntimeException('audio_copy_failed');
    }
    @chmod($dest, 0666);
    read_aloud_invalidate_full_story_audio($bookId);

    return $dest;
}

/** @return list<string> Absolute paths to cached page MP3s in story order, or [] if incomplete. */
function read_aloud_full_story_source_paths(int $bookId): array
{
    $pages = read_aloud_playable_pages($bookId);
    if ($pages === []) {
        return [];
    }

    $paths = [];
    foreach ($pages as $page) {
        $path = read_aloud_cached_audio_path($bookId, $page);
        if ($path === null || !is_file($path)) {
            return [];
        }
        $paths[] = $path;
    }

    return $paths;
}

function read_aloud_full_story_audio_available(int $bookId): bool
{
    return read_aloud_full_story_source_paths($bookId) !== [];
}

function read_aloud_full_story_audio_path(int $bookId): string
{
    return read_aloud_audio_dir($bookId) . '/story-full.mp3';
}

function read_aloud_invalidate_full_story_audio(int $bookId): void
{
    $path = read_aloud_full_story_audio_path($bookId);
    if (is_file($path)) {
        @unlink($path);
    }
    $tmp = $path . '.tmp';
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

/**
 * Build (or reuse) a single MP3 of all playable page audio for download.
 * Uses binary concatenation, which works for sequential MPEG frame streams.
 */
function read_aloud_ensure_full_story_audio(int $bookId): ?string
{
    $sources = read_aloud_full_story_source_paths($bookId);
    if ($sources === []) {
        return null;
    }

    $out = read_aloud_full_story_audio_path($bookId);
    $needsRebuild = !is_file($out) || filesize($out) < 1;
    if (!$needsRebuild) {
        $outMtime = (int) filemtime($out);
        foreach ($sources as $src) {
            if ((int) filemtime($src) > $outMtime) {
                $needsRebuild = true;
                break;
            }
        }
    }

    if (!$needsRebuild) {
        return $out;
    }

    $dir = read_aloud_audio_dir($bookId);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return null;
    }

    $tmp = $out . '.tmp';
    $fh = fopen($tmp, 'wb');
    if ($fh === false) {
        return null;
    }

    try {
        foreach ($sources as $src) {
            $in = fopen($src, 'rb');
            if ($in === false) {
                throw new RuntimeException('source_open_failed');
            }
            stream_copy_to_stream($in, $fh);
            fclose($in);
        }
    } catch (Throwable $e) {
        fclose($fh);
        @unlink($tmp);
        return null;
    }

    fclose($fh);
    @chmod($tmp, 0666);

    if (!rename($tmp, $out)) {
        @unlink($tmp);
        return null;
    }

    return $out;
}

function read_aloud_download_url(int $bookId, bool $preview = false): string
{
    $url = app_url('read-aloud-download.php?id=' . $bookId);
    if ($preview) {
        $url .= '&preview=1';
    }

    return $url;
}
