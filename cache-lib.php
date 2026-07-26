<?php

declare(strict_types=1);

function stories_cache_dir(): string
{
    $dir = __DIR__ . '/data/cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function stories_cache_get(string $key, int $ttlSeconds = 600): ?array
{
    if ($ttlSeconds <= 0) {
        return null;
    }

    $path = stories_cache_dir() . '/' . $key . '.json';
    if (!is_file($path)) {
        return null;
    }

    if ((time() - (int) filemtime($path)) > $ttlSeconds) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

function stories_cache_set(string $key, array $data): void
{
    $dir = stories_cache_dir();
    if (!is_writable($dir)) {
        return;
    }

    $path = $dir . '/' . $key . '.json';
    @file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function explore_cache_key(string $topicFilter, string $searchQuery): string
{
    return 'explore-v3-' . md5($topicFilter . '|' . $searchQuery);
}

/** @return list<string> */
function explore_search_words(string $searchQuery): array
{
    $searchQuery = trim($searchQuery);
    if ($searchQuery === '') {
        return [];
    }

    $parts = preg_split('/\s+/u', $searchQuery, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return [];
    }

    $words = [];
    foreach ($parts as $part) {
        $word = strtolower(trim($part));
        if ($word !== '') {
            $words[] = $word;
        }
    }

    return $words;
}

/** @param list<array<string, mixed>> $books */
function explore_books_filter_by_search(array $books, string $searchQuery): array
{
    $words = explore_search_words($searchQuery);
    if ($words === []) {
        return $books;
    }

    require_once __DIR__ . '/read-aloud-lib.php';

    $filtered = [];
    foreach ($books as $book) {
        if (!is_array($book)) {
            continue;
        }

        $haystack = strtolower(implode(' ', [
            (string) ($book['title'] ?? ''),
            (string) ($book['description'] ?? ''),
            (string) ($book['author_name'] ?? ''),
            (string) ($book['categories'] ?? ''),
            (string) ($book['story_topic'] ?? ''),
            (string) ($book['science_element'] ?? ''),
            read_aloud_story_full_text((int) ($book['book_id'] ?? 0)),
        ]));

        $matches = true;
        foreach ($words as $word) {
            if (!str_contains($haystack, $word)) {
                $matches = false;
                break;
            }
        }

        if ($matches) {
            $filtered[] = $book;
        }
    }

    return $filtered;
}

/** @return array{books: list<array<string, mixed>>, ratings: array<int, array<string, mixed>>}|null */
function explore_page_cache_get(string $topicFilter, string $searchQuery, int $ttlSeconds = 600): ?array
{
    $cached = stories_cache_get(explore_cache_key($topicFilter, $searchQuery), $ttlSeconds);
    if ($cached === null || !isset($cached['books'], $cached['ratings']) || !is_array($cached['books']) || !is_array($cached['ratings'])) {
        return null;
    }

    return [
        'books' => $cached['books'],
        'ratings' => $cached['ratings'],
    ];
}

/** @param list<array<string, mixed>> $books */
function explore_page_cache_set(string $topicFilter, string $searchQuery, array $books, array $ratings): void
{
    stories_cache_set(explore_cache_key($topicFilter, $searchQuery), [
        'books' => $books,
        'ratings' => $ratings,
    ]);
}

/** @return array{books: list<array<string, mixed>>, top_picks: list<array<string, mixed>>, ratings: array<int, array<string, mixed>>}|null */
function home_page_cache_get(int $ttlSeconds = 600): ?array
{
    $cached = stories_cache_get('home-featured', $ttlSeconds);
    if ($cached === null || !isset($cached['books'], $cached['ratings']) || !is_array($cached['books']) || !is_array($cached['ratings'])) {
        return null;
    }

    return [
        'books' => $cached['books'],
        'top_picks' => is_array($cached['top_picks'] ?? null) ? $cached['top_picks'] : [],
        'ratings' => $cached['ratings'],
    ];
}

/** @param list<array<string, mixed>> $books */
/** @param list<array<string, mixed>> $topPicks */
function home_page_cache_set(array $books, array $ratings, array $topPicks = []): void
{
    stories_cache_set('home-featured', [
        'books' => $books,
        'top_picks' => $topPicks,
        'ratings' => $ratings,
    ]);
}

function home_page_cache_clear(): void
{
    $path = stories_cache_dir() . '/home-featured.json';
    if (is_file($path)) {
        @unlink($path);
    }
}

function explore_page_cache_clear(): void
{
    $dir = stories_cache_dir();
    foreach (glob($dir . '/explore-*.json') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function stories_page_cache_clear(): void
{
    home_page_cache_clear();
    explore_page_cache_clear();
}
