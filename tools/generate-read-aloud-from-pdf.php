<?php

declare(strict_types=1);

/**
 * CLI: Generate data/read-aloud/{book_id}.json from PDF page images via OpenAI vision.
 *
 * Usage:
 *   php tools/generate-read-aloud-from-pdf.php
 *   php tools/generate-read-aloud-from-pdf.php --book=51
 *   php tools/generate-read-aloud-from-pdf.php --book=51 --force
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/ai.php';
require_once dirname(__DIR__) . '/read-aloud-lib.php';

$options = getopt('', ['book::', 'force', 'skip-tts']);
$onlyBook = isset($options['book']) ? (int) $options['book'] : 0;
$force = isset($options['force']);
$skipTts = isset($options['skip-tts']);

if (!ai_is_configured()) {
    fwrite(STDERR, "ChatGPT API key not configured.\n");
    exit(1);
}

$python = find_python_with_pymupdf();
if ($python === null) {
    fwrite(STDERR, "Python with PyMuPDF required. Run: python3 -m venv /tmp/pdfvenv && pip install pymupdf\n");
    exit(1);
}

$stmt = $pdo->query(
    "SELECT book_id, title, pdf_file_path
     FROM books
     WHERE book_format = 'pdf' AND status = 'approved'
     ORDER BY book_id"
);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($books as $book) {
    $bookId = (int) $book['book_id'];
    if ($onlyBook > 0 && $bookId !== $onlyBook) {
        continue;
    }

    $jsonPath = read_aloud_json_path($bookId);
    if (!$force && is_file($jsonPath)) {
        echo "Skip book #{$bookId} (JSON exists): {$book['title']}\n";
        maybe_prewarm_tts($bookId, $skipTts);
        continue;
    }

    $pdfPath = resolve_pdf_path($book['pdf_file_path'] ?? '');
    if ($pdfPath === null) {
        fwrite(STDERR, "Missing PDF for book #{$bookId}: {$book['title']}\n");
        continue;
    }

    echo "Processing book #{$bookId}: {$book['title']}\n";
    $workDir = sys_get_temp_dir() . '/read-aloud-import-' . $bookId;
    if (is_dir($workDir)) {
        foreach (glob($workDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($workDir);
    }
    if (!is_dir($workDir) && !mkdir($workDir, 0755, true) && !is_dir($workDir)) {
        fwrite(STDERR, "Cannot create work dir: {$workDir}\n");
        continue;
    }

    $pageImages = render_pdf_pages($python, $pdfPath, $workDir);
    if ($pageImages === []) {
        fwrite(STDERR, "No pages rendered for book #{$bookId}\n");
        continue;
    }

    $pages = [];
    foreach ($pageImages as $pageNum => $imagePath) {
        echo "  OCR page {$pageNum}...\n";
        $text = transcribe_page_image($imagePath, (int) $pageNum);
        $text = trim(preg_replace('/\s+/u', ' ', $text ?? '') ?? '');
        if ($text !== '') {
            $pages[] = ['page' => $pageNum, 'text' => $text];
        }
        usleep(1500000);
    }

    if ($pages === []) {
        fwrite(STDERR, "No text extracted for book #{$bookId}\n");
        continue;
    }

    $slug = slugify((string) $book['title']);
    $payload = [
        'story_id' => $slug,
        'book_id' => $bookId,
        'title' => (string) $book['title'],
        'pages' => $pages,
    ];

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents($jsonPath, $encoded . "\n") === false) {
        fwrite(STDERR, "Failed to write {$jsonPath}\n");
        continue;
    }

    echo "  Wrote " . count($pages) . " pages -> {$jsonPath}\n";
    invalidate_old_tts_cache($bookId);
    maybe_prewarm_tts($bookId, $skipTts);

    foreach (glob($workDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($workDir);
}

echo "Done.\n";

function find_python_with_pymupdf(): ?string
{
    $candidates = ['/tmp/pdfvenv/bin/python3', '/tmp/pdfvenv/bin/python', 'python3', 'python'];
    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' -c ' . escapeshellarg('import fitz');
        exec($cmd, $out, $code);
        if ($code === 0) {
            return $bin;
        }
    }
    return null;
}

function resolve_pdf_path(string $relative): ?string
{
    $relative = trim($relative);
    if ($relative === '') {
        return null;
    }
    $full = dirname(__DIR__) . '/' . ltrim($relative, '/');
    return is_file($full) ? $full : null;
}

function slugify(string $title): string
{
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    return trim($slug, '-');
}

/** @return array<int, string> page number => png path */
function render_pdf_pages(string $python, string $pdfPath, string $outDir): array
{
    $script = <<<'PY'
import fitz, sys, os
pdf_path, out_dir = sys.argv[1], sys.argv[2]
doc = fitz.open(pdf_path)
for i in range(doc.page_count):
    page = doc[i]
    pix = page.get_pixmap(matrix=fitz.Matrix(1.25, 1.25))
    fp = os.path.join(out_dir, f"page_{i+1}.jpg")
    pix.save(fp, jpg_quality=82)
    print(i + 1)
PY;

    $cmd = escapeshellarg($python) . ' -c ' . escapeshellarg($script) . ' '
        . escapeshellarg($pdfPath) . ' ' . escapeshellarg($outDir);
    exec($cmd, $lines, $code);
    if ($code !== 0) {
        return [];
    }

    $images = [];
    foreach ($lines as $line) {
        $pageNum = (int) trim($line);
        if ($pageNum <= 0) {
            continue;
        }
        $path = $outDir . '/page_' . $pageNum . '.jpg';
        if (is_file($path)) {
            $images[$pageNum] = $path;
        }
    }
    ksort($images);
    return $images;
}

function transcribe_page_image(string $imagePath, int $pageNum): string
{
    $bytes = file_get_contents($imagePath);
    if ($bytes === false) {
        return '';
    }

    $b64 = base64_encode($bytes);
    $config = ai_config();
    $prompt = $pageNum === 1
        ? 'This is the cover of a children\'s science storybook. Transcribe the title and subtitle exactly as shown, as one short line to read aloud. Return only that text.'
        : 'Transcribe ALL story text on this children\'s book page in exact reading order. Include narration and dialogue. If the page has a large title or section heading above the story text, put a blank line between the heading and the body. Return only the words to read aloud — no image descriptions, labels, or page numbers.';

    $payload = [
        'model' => ai_chat_model(),
        'temperature' => 0,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . $b64,
                            'detail' => 'high',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $encoded = json_encode($payload);
    if ($encoded === false) {
        return '';
    }

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . ai_api_key(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw !== false && $httpCode === 200) {
            $data = json_decode($raw, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            return is_string($content) ? trim($content) : '';
        }

        $waitSeconds = min(30, (int) pow(2, $attempt));
        fwrite(STDERR, "    Vision API page {$pageNum} HTTP {$httpCode}, retry in {$waitSeconds}s...\n");
        sleep($waitSeconds);
    }

    fwrite(STDERR, "    Vision API failed for page {$pageNum} after retries\n");
    return '';
}

function invalidate_old_tts_cache(int $bookId): void
{
    $dir = read_aloud_audio_dir($bookId);
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/page-*.mp3') ?: [] as $file) {
        @unlink($file);
    }
}

function maybe_prewarm_tts(int $bookId, bool $skipTts): void
{
    if ($skipTts) {
        return;
    }

    $data = read_aloud_story_data($bookId);
    if ($data === null) {
        return;
    }

    foreach ($data['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $page = (int) ($entry['page'] ?? 0);
        if ($page <= 0) {
            continue;
        }
        if (read_aloud_cached_audio_path($bookId, $page) !== null) {
            continue;
        }
        try {
            read_aloud_generate_audio($bookId, $page);
            echo "  TTS page {$page} cached\n";
            sleep(2);
        } catch (Throwable $e) {
            fwrite(STDERR, "  TTS page {$page} failed: {$e->getMessage()}\n");
        }
    }
}
