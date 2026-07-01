<?php

declare(strict_types=1);

/**
 * Import pre-recorded MP3 files for read-aloud (one file per page).
 *
 * Examples:
 *   php tools/import-read-aloud-mp3.php --book=54 --file=/path/to/0001.mp3 --page=1
 *   php tools/import-read-aloud-mp3.php --book=54 --dir=/path/to/mp3s
 *
 * With --dir, looks for 0001.mp3, 0002.mp3, … (4-digit page numbers).
 */

require_once dirname(__DIR__) . '/read-aloud-lib.php';
require_once dirname(__DIR__) . '/db.php';

$options = getopt('', ['book:', 'file::', 'page::', 'dir::']);
$bookId = isset($options['book']) ? (int) $options['book'] : 0;

if ($bookId <= 0) {
    fwrite(STDERR, "Usage: php tools/import-read-aloud-mp3.php --book=ID [--file=PATH --page=N | --dir=PATH]\n");
    exit(1);
}

try {
    $data = read_aloud_load_or_init_story($pdo, $bookId);
} catch (RuntimeException $e) {
    fwrite(STDERR, "Book {$bookId} not found.\n");
    exit(1);
}

$imports = [];

if (!empty($options['dir'])) {
    $dir = rtrim((string) $options['dir'], '/');
    if (!is_dir($dir)) {
        fwrite(STDERR, "Directory not found: {$dir}\n");
        exit(1);
    }

    foreach ($data['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $page = (int) ($entry['page'] ?? 0);
        if ($page <= 0) {
            continue;
        }
        $candidate = $dir . '/' . str_pad((string) $page, 4, '0', STR_PAD_LEFT) . '.mp3';
        if (is_file($candidate)) {
            $imports[] = ['page' => $page, 'file' => $candidate];
        }
    }
} elseif (!empty($options['file']) && !empty($options['page'])) {
    $imports[] = [
        'page' => (int) $options['page'],
        'file' => (string) $options['file'],
    ];
} else {
    fwrite(STDERR, "Provide --file and --page, or --dir with numbered MP3s (0001.mp3, 0002.mp3, …).\n");
    exit(1);
}

if ($imports === []) {
    fwrite(STDERR, "No MP3 files matched for book {$bookId}.\n");
    exit(1);
}

foreach ($imports as $item) {
    try {
        read_aloud_ensure_page_in_data($data, $item['page']);
        $dest = read_aloud_import_uploaded_mp3($bookId, $item['page'], $item['file']);
        echo "Page {$item['page']}: {$dest}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Page {$item['page']} failed: {$e->getMessage()}\n");
    }
}

$data['audio_source'] = 'uploaded';
$jsonPath = read_aloud_json_path($bookId);
file_put_contents(
    $jsonPath,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);
echo "Marked book {$bookId} as audio_source=uploaded in {$jsonPath}\n";
