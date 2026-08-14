<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ai_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'chatgpt_api_key' => '',
        'chatgpt_model' => 'gpt-4o-mini',
        'chatgpt_image_model' => 'gpt-image-1',
        'master_prompt' => '',
        'read_aloud_tts_speed' => 0.9,
    ];

    $envKey = getenv('CHATGPT_API_KEY');
    if (!is_string($envKey) || $envKey === '') {
        $envKey = getenv('OPENAI_API_KEY');
    }
    if (is_string($envKey) && $envKey !== '') {
        $config = array_merge($defaults, ['chatgpt_api_key' => $envKey]);
    } else {
        $config = $defaults;
    }

    $secretPaths = [
        dirname(__DIR__, 2) . '/private/sparking-ai.config.php',
        __DIR__ . '/ai.config.php',
    ];

    foreach ($secretPaths as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $loaded = require $path;
        if (is_array($loaded)) {
            $config = ai_merge_config($config, $loaded);
        }
    }

    return ai_normalize_config($config);
}

function ai_normalize_config(array $config): array
{
    if (trim((string) ($config['chatgpt_api_key'] ?? '')) === ''
        && trim((string) ($config['openai_api_key'] ?? '')) !== '') {
        $config['chatgpt_api_key'] = $config['openai_api_key'];
    }
    if (trim((string) ($config['chatgpt_model'] ?? '')) === ''
        && trim((string) ($config['openai_model'] ?? '')) !== '') {
        $config['chatgpt_model'] = $config['openai_model'];
    }
    if (trim((string) ($config['chatgpt_image_model'] ?? '')) === ''
        && trim((string) ($config['openai_image_model'] ?? '')) !== '') {
        $config['chatgpt_image_model'] = $config['openai_image_model'];
    }

    return $config;
}

function ai_api_key_placeholders(): array
{
    return ['', 'PUT_CHATGPT_API_KEY_HERE', 'PUT_OPENAI_API_KEY_HERE', 'sk-your-key-here'];
}

function ai_api_key(): string
{
    $config = ai_config();
    foreach (['chatgpt_api_key', 'openai_api_key'] as $key) {
        $value = trim((string) ($config[$key] ?? ''));
        if ($value !== '' && !in_array($value, ai_api_key_placeholders(), true)) {
            return $value;
        }
    }

    return '';
}

function ai_chat_model(): string
{
    $config = ai_config();
    $model = trim((string) ($config['chatgpt_model'] ?? $config['openai_model'] ?? ''));

    return $model !== '' ? $model : 'gpt-4o-mini';
}

function ai_image_model(): string
{
    $config = ai_config();
    $model = trim((string) ($config['chatgpt_image_model'] ?? $config['openai_image_model'] ?? ''));

    return $model !== '' ? $model : 'gpt-image-1';
}

function ai_merge_config(array $base, array $override): array
{
    $emptyValues = ai_api_key_placeholders();

    foreach ($override as $key => $value) {
        if (is_string($value) && in_array(trim($value), $emptyValues, true)) {
            continue;
        }
        $base[$key] = $value;
    }

    return $base;
}

function ai_is_configured(): bool
{
    $key = ai_api_key();

    return $key !== '' && str_starts_with($key, 'sk-');
}

function ai_system_prompt(): string
{
    return ai_master_story_rules() . "\n\n"
        . "You are the Science Fables AI Authoring Assistant. Help creators write children's science mystery storybooks "
        . "for ages 8–15 that match the site's published PDF style: friendly, natural, realistic photo-style pages, "
        . "cream backgrounds, navy titles, and a Science Element page only at the very end.\n\n"
        . "When the user asks for a plan, outline, or full story, follow the master story rules exactly. "
        . "Do not sound like a textbook. Use fresh characters and settings each time. "
        . "Do NOT claim you already created a PDF — the site builds the PDF when the user clicks Create PDF.";
}

function ai_master_story_rules(): string
{
    $custom = trim((string) (ai_config()['master_prompt'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return (string) require __DIR__ . '/ai-master-prompt.php';
}

function ai_story_json_schema_instructions(): string
{
    return <<<'SCHEMA'
Return JSON only (no markdown fences) with these keys:
- title (string)
- author_name (string — use "Story Author" unless given)
- description (string, 1–2 sentences)
- science_topic (one of: Space, Human Body, Plants, Animals, Weather, Microbes, Earth Science, Engineering, Physical Science)
- setting (string — where the story takes place)
- character_1 (string — full visual description for consistent images)
- character_2 (string — full visual description for consistent images)
- adult_guide (string — full visual description for consistent images)
- pages (array of 5–8 objects, each with:
    "page_title" (short interesting title for this page),
    "text" (full story text for this page — short enough to sit below an image),
    "scene" (what happens in the realistic photo: location, action, science object, animal, or event),
    "image_prompt" (optional — detailed realistic scene prompt; if omitted, scene is used)
  )
- science_element (string — full summary text for the FINAL page only)
- science_element_scene (string — realistic scene for the Science Element page image: exhibit, diagram, poster, or nature-center sign)

Do NOT put science_element content inside story pages. The Science Element is the last page only.
SCHEMA;
}

/** @return list<array<string, string>> */
function ai_prompt_templates(): array
{
    return [
        ['id' => 'space_adventure', 'title' => 'Space', 'icon' => '🪐', 'starter' => 'Help me write a space science fiction story for kids ages 8–15.'],
        ['id' => 'body_journey', 'title' => 'Human Body', 'icon' => '🫀', 'starter' => 'Help me write a story about the human body for kids ages 8–15.'],
        ['id' => 'plant_mystery', 'title' => 'Plants', 'icon' => '🌱', 'starter' => 'Help me write a plant science story for kids ages 8–15.'],
        ['id' => 'animal_adventure', 'title' => 'Animals', 'icon' => '🐾', 'starter' => 'Help me write an animal science story for kids ages 8–15.'],
        ['id' => 'weather_watch', 'title' => 'Weather', 'icon' => '🌦️', 'starter' => 'Help me write a weather science story for kids ages 8–15.'],
        ['id' => 'germ_detectives', 'title' => 'Microbes', 'icon' => '🦠', 'starter' => 'Help me write a kid-friendly microbes or bacteria story for ages 8–15.'],
        ['id' => 'earth_science', 'title' => 'Earth Science', 'icon' => '🌍', 'starter' => 'Help me write an earth science story about oceans, weather, or the atmosphere for kids ages 8–15.'],
        ['id' => 'engineering_build', 'title' => 'Engineering', 'icon' => '🔧', 'starter' => 'Help me write an engineering story for kids ages 8–15.'],
        ['id' => 'physical_science', 'title' => 'Physical Science', 'icon' => '⚛️', 'starter' => 'Help me write a physical science story about forces, energy, or matter for kids ages 8–15.'],
    ];
}

function ai_init_session(): void
{
    if (!isset($_SESSION['ai_chat']) || !is_array($_SESSION['ai_chat'])) {
        $_SESSION['ai_chat'] = [
            'messages' => [],
            'template_id' => null,
            'meta' => [],
            'workflow' => ai_default_workflow(),
        ];
    }

    if (!isset($_SESSION['ai_chat']['workflow']) || !is_array($_SESSION['ai_chat']['workflow'])) {
        $_SESSION['ai_chat']['workflow'] = ai_default_workflow();
    }
}

/** @return array{step: string, idea: string, plan: string, outline: string, story: array<string, mixed>|null} */
function ai_default_workflow(): array
{
    return [
        'step' => 'idea',
        'idea' => '',
        'plan' => '',
        'outline' => '',
        'story' => null,
    ];
}

/** @return array{step: string, idea: string, plan: string, outline: string, story: array<string, mixed>|null} */
function ai_workflow(): array
{
    ai_init_session();
    $workflow = $_SESSION['ai_chat']['workflow'] ?? ai_default_workflow();
    if (!is_array($workflow)) {
        $workflow = ai_default_workflow();
    }

    return array_merge(ai_default_workflow(), $workflow);
}

function ai_workflow_step(): string
{
    return ai_workflow()['step'] ?? 'idea';
}

/** @param array<string, mixed> $updates */
function ai_set_workflow(array $updates): void
{
    ai_init_session();
    $_SESSION['ai_chat']['workflow'] = array_merge(ai_workflow(), $updates);
}

function ai_reset_workflow(): void
{
    ai_set_workflow(ai_default_workflow());
}

/** @return list<array{role: string, content: string}> */
function ai_session_messages(): array
{
    ai_init_session();
    return $_SESSION['ai_chat']['messages'] ?? [];
}

function ai_reset_session(): void
{
    $_SESSION['ai_chat'] = [
        'messages' => [],
        'template_id' => null,
        'meta' => [],
        'workflow' => ai_default_workflow(),
    ];
}

function ai_append_message(string $role, string $content): void
{
    ai_init_session();
    $_SESSION['ai_chat']['messages'][] = [
        'role' => $role,
        'content' => $content,
    ];
}

/**
 * @param list<array{role: string, content: string}> $messages
 * @return array{ok: bool, content: string, error: string}
 */
function ai_call_openai(array $messages): array
{
    if (!ai_is_configured()) {
        return [
            'ok' => false,
            'content' => '',
            'error' => 'ChatGPT API key not configured. Add chatgpt_api_key to sparking-ai.config.php.',
        ];
    }

    $config = ai_config();
    $payload = [
        'model' => ai_chat_model(),
        'messages' => array_merge(
            [['role' => 'system', 'content' => ai_system_prompt()]],
            $messages
        ),
        'temperature' => 0.75,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ai_api_key(),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'content' => '', 'error' => 'AI request failed: ' . $curlError];
    }

    $data = json_decode($raw, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        return ['ok' => false, 'content' => '', 'error' => 'OpenAI error: ' . $msg];
    }

    $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
    if ($content === '') {
        return ['ok' => false, 'content' => '', 'error' => 'Empty response from AI.'];
    }

    return ['ok' => true, 'content' => $content, 'error' => ''];
}

function ai_parse_story_json(string $jsonText): ?array
{
    $jsonText = trim($jsonText);
    $jsonText = preg_replace('/^```json\s*|\s*```$/', '', $jsonText) ?? $jsonText;
    $story = json_decode($jsonText, true);

    if (!is_array($story) || empty($story['pages']) || !is_array($story['pages'])) {
        return null;
    }

    return $story;
}

/** @param array<string, mixed> $story */
function ai_outline_display_from_story(array $story): string
{
    $lines = [];
    $title = trim((string) ($story['title'] ?? 'Untitled Story'));
    $lines[] = '# ' . $title;
    $lines[] = '';
    $lines[] = '**Science topic:** ' . trim((string) ($story['science_topic'] ?? 'Space'));
    $lines[] = '**Description:** ' . trim((string) ($story['description'] ?? ''));
    $lines[] = '';
    $lines[] = '**Science element (for kids):**';
    $lines[] = trim((string) ($story['science_element'] ?? ''));
    $lines[] = '';

    foreach ($story['pages'] as $i => $page) {
        if (!is_array($page)) {
            continue;
        }
        $n = $i + 1;
        $pageTitle = trim((string) ($page['page_title'] ?? $page['image_caption'] ?? 'Page ' . $n));
        $lines[] = '## Page ' . $n . ': ' . $pageTitle;
        $lines[] = trim((string) ($page['text'] ?? ''));
        $scene = trim((string) ($page['scene'] ?? ''));
        if ($scene !== '') {
            $lines[] = '';
            $lines[] = '_Scene: ' . $scene . '_';
        }
        $prompt = trim((string) ($page['image_prompt'] ?? ''));
        if ($prompt !== '') {
            $lines[] = '_Image: ' . $prompt . '_';
        }
        $lines[] = '';
    }

    $lines[] = '## Science Element (final page only)';
    $lines[] = trim((string) ($story['science_element'] ?? ''));
    $scienceScene = trim((string) ($story['science_element_scene'] ?? ''));
    if ($scienceScene !== '') {
        $lines[] = '';
        $lines[] = '_Scene: ' . $scienceScene . '_';
    }

    return trim(implode("\n", $lines));
}

/**
 * @return array{ok: bool, plan: string, error: string}
 */
function ai_generate_plan(string $idea): array
{
    $idea = trim($idea);
    if ($idea === '') {
        return ['ok' => false, 'plan' => '', 'error' => 'Describe your story idea first.'];
    }

    $messages = [
        [
            'role' => 'user',
            'content' => "Create a step-by-step PLAN for a children's science mystery storybook (ages 8–15) based on this idea:\n\n"
                . $idea . "\n\n"
                . ai_master_story_rules() . "\n\n"
                . "Write a clear, editable plan the author can review before drafting. Use markdown with these sections:\n"
                . "1. **Working title** — kid-friendly title\n"
                . "2. **Science topic & concept** — one site topic and one clear science idea\n"
                . "3. **Characters & setting** — character_1, character_2, adult_guide (with clothing/visual details), and a fresh setting\n"
                . "4. **Story arc** — mystery, discovery, resolution through fiction (not lecture)\n"
                . "5. **Page breakdown** — 5–8 story pages; each bullet: page_title, what happens, what the reader learns\n"
                . "6. **Science Element page** — what the final summary page will cover (vocabulary, takeaway)\n"
                . "7. **Realistic photo scenes** — note that pages will use cinematic realistic photos, cream storybook layout, navy titles\n\n"
                . "Do NOT write the full story yet — only the plan.",
        ],
    ];

    $result = ai_call_openai($messages);
    if (!$result['ok']) {
        return ['ok' => false, 'plan' => '', 'error' => $result['error']];
    }

    return ['ok' => true, 'plan' => $result['content'], 'error' => ''];
}

/**
 * @return array{ok: bool, outline: string, story: array<string, mixed>|null, error: string}
 */
function ai_generate_outline(string $idea, string $plan): array
{
    $idea = trim($idea);
    $plan = trim($plan);
    if ($idea === '') {
        return ['ok' => false, 'outline' => '', 'story' => null, 'error' => 'Story idea is missing.'];
    }
    if ($plan === '') {
        return ['ok' => false, 'outline' => '', 'story' => null, 'error' => 'Approve or write a plan first.'];
    }

    $messages = [
        [
            'role' => 'user',
            'content' => "Using this story idea and approved plan, produce a finished children's science mystery storybook outline for ages 8–15.\n\n"
                . ai_master_story_rules() . "\n\n"
                . ai_story_json_schema_instructions() . "\n\n"
                . "Follow the plan closely. Invent fresh characters and a fresh setting if the plan is vague.\n\n"
                . "Story idea:\n" . $idea . "\n\n"
                . "Approved plan:\n" . $plan,
        ],
    ];

    $result = ai_call_openai($messages);
    if (!$result['ok']) {
        return ['ok' => false, 'outline' => '', 'story' => null, 'error' => $result['error']];
    }

    $story = ai_parse_story_json($result['content']);
    if ($story === null) {
        return ['ok' => false, 'outline' => '', 'story' => null, 'error' => 'Could not build the story outline. Try again.'];
    }

    return [
        'ok' => true,
        'outline' => ai_outline_display_from_story($story),
        'story' => $story,
        'error' => '',
    ];
}

/**
 * @return array{ok: bool, story: array<string, mixed>|null, error: string}
 */
function ai_story_from_outline(string $idea, string $plan, string $outline): array
{
    $idea = trim($idea);
    $plan = trim($plan);
    $outline = trim($outline);
    if ($outline === '') {
        return ['ok' => false, 'story' => null, 'error' => 'Story outline is empty.'];
    }

    $messages = [
        [
            'role' => 'user',
            'content' => "Convert this approved story outline into JSON for PDF generation (ages 8–15). Return JSON only (no markdown fences).\n\n"
                . ai_story_json_schema_instructions() . "\n\n"
                . "Respect any edits the author made to the outline. Keep one clear science concept. "
                . "Display story text exactly as written — do not rewrite, shorten, duplicate, or add lines.\n\n"
                . "Story idea:\n" . $idea . "\n\n"
                . "Plan:\n" . $plan . "\n\n"
                . "Story outline:\n" . $outline,
        ],
    ];

    $result = ai_call_openai($messages);
    if (!$result['ok']) {
        return ['ok' => false, 'story' => null, 'error' => $result['error']];
    }

    $story = ai_parse_story_json($result['content']);
    if ($story === null) {
        return ['ok' => false, 'story' => null, 'error' => 'Could not parse the outline for PDF export. Check the outline and try again.'];
    }

    return ['ok' => true, 'story' => $story, 'error' => ''];
}

/**
 * @return array{ok: bool, story: array<string, mixed>|null, error: string}
 */
function ai_story_for_export(): array
{
    $workflow = ai_workflow();
    $idea = trim((string) ($workflow['idea'] ?? ''));
    $plan = trim((string) ($workflow['plan'] ?? ''));
    $outline = trim((string) ($workflow['outline'] ?? ''));

    if ($outline !== '') {
        return ai_story_from_outline($idea, $plan, $outline);
    }

    $messages = ai_session_messages();
    if (ai_chat_has_user_messages($messages)) {
        return ai_extract_story_for_pdf($messages);
    }

    return ['ok' => false, 'story' => null, 'error' => 'Complete the plan and story outline before creating a PDF.'];
}

/** Writable folder for AI images (Apache cannot write to macOS user /tmp). */
function ai_temp_dir(): string
{
    $dir = __DIR__ . '/uploads/ai-temp';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
    }
    return $dir;
}

function ai_write_temp_file(string $prefix, string $binary, string $ext): ?string
{
    $path = ai_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
    if (file_put_contents($path, $binary) === false) {
        error_log('AI temp write failed: ' . $path);
        return null;
    }
    return $path;
}

function ai_fetch_image_to_temp(string $url): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'ScienceFables/1.0',
    ]);
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($data === false || $code >= 400 || strlen($data) < 100) {
        return null;
    }

    $ext = 'jpg';
    if (str_contains($contentType, 'png')) {
        $ext = 'png';
    } elseif (str_contains($contentType, 'webp')) {
        $ext = 'webp';
    }

    $path = ai_write_temp_file('sc_ai_', $data, $ext);
    if ($path === null) {
        return null;
    }

    return $path;
}

function ai_pdf_add_image(TCPDF $pdf, ?string $path, float $x, float $y, float $w, float $h = 0): bool
{
    if ($path === null || !is_file($path) || @getimagesize($path) === false) {
        return false;
    }

    try {
        $pdf->Image($path, $x, $y, $w, $h, '', '', '', false, 300);
        return true;
    } catch (Throwable $e) {
        error_log('PDF image skipped: ' . $e->getMessage());
        return false;
    }
}

/**
 * Place image in a box without squishing — keeps aspect ratio, centered horizontally.
 * @return float|null Bottom Y position after the image
 */
function ai_pdf_add_image_fit(TCPDF $pdf, string $path, float $x, float $y, float $maxW, float $maxH): ?float
{
    $size = @getimagesize($path);
    if ($size === false) {
        return null;
    }

    $pxW = (int) ($size[0] ?? 0);
    $pxH = (int) ($size[1] ?? 0);
    if ($pxW <= 0 || $pxH <= 0) {
        return null;
    }

    $ratio = $pxW / $pxH;
    $w = $maxW;
    $h = $maxW / $ratio;
    if ($h > $maxH) {
        $h = $maxH;
        $w = $maxH * $ratio;
    }

    $fitX = $x + ($maxW - $w) / 2;

    try {
        $pdf->Image($path, $fitX, $y, $w, $h, '', '', '', false, 300);
        return $y + $h;
    } catch (Throwable $e) {
        error_log('PDF image skipped: ' . $e->getMessage());
        return null;
    }
}

function ai_pdf_content_width(TCPDF $pdf): float
{
    return $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
}

function ai_pdf_left_margin(TCPDF $pdf): float
{
    return $pdf->getMargins()['left'];
}

function ai_pdf_fill_page(TCPDF $pdf, int $r, int $g, int $b): void
{
    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');
}

function ai_pdf_navy_text(TCPDF $pdf): void
{
    $pdf->SetTextColor(26, 35, 126);
}

function ai_pdf_body_text(TCPDF $pdf): void
{
    $pdf->SetTextColor(20, 20, 20);
}

function ai_pdf_draw_heart_footer(TCPDF $pdf, float $contentX, float $contentW, float $y): void
{
    $pdf->SetDrawColor(37, 99, 235);
    $pdf->SetLineWidth(0.4);
    $midX = $contentX + ($contentW / 2);
    $heartW = 4.5;
    $pdf->Line($contentX, $y, $midX - $heartW, $y);
    $pdf->Line($midX + $heartW, $y, $contentX + $contentW, $y);
    $pdf->SetFillColor(239, 68, 68);
    $pdf->Circle($midX, $y, 1.2, 0, 360, 'F');
}

function ai_pdf_render_story_page(
    TCPDF $pdf,
    string $pageTitle,
    string $text,
    ?string $imgPath,
    float $contentX,
    float $contentW
): void {
    ai_pdf_fill_page($pdf, 255, 249, 240);

    $pdf->SetY(18);
    ai_pdf_navy_text($pdf);
    $pdf->SetFont('times', 'B', 22);
    $pdf->MultiCell($contentW, 10, $pageTitle, 0, 'C');
    $pdf->Ln(4);

    $imageTop = $pdf->GetY();
    $footerY = $pdf->getPageHeight() - 18;
    $available = $footerY - $imageTop - 28;
    $imageMaxH = min($available * 0.58, 118);

    $textTop = $imageTop;
    if ($imgPath !== null && is_file($imgPath)) {
        $imageBottom = ai_pdf_add_image_fit($pdf, $imgPath, $contentX, $imageTop, $contentW, $imageMaxH);
        if ($imageBottom !== null) {
            $textTop = $imageBottom + 5;
        }
    }

    ai_pdf_body_text($pdf);
    $pdf->SetXY($contentX, $textTop);
    $pdf->SetFont('times', '', 13);
    $textHeight = max(24, $footerY - $textTop - 8);
    $pdf->MultiCell($contentW, 6.5, $text, 0, 'L', false, 1, $contentX, $textTop, true, 0, false, true, $textHeight, 'T');

    ai_pdf_draw_heart_footer($pdf, $contentX, $contentW, $footerY);
}

function ai_illustration_style(): string
{
    return 'Realistic, cinematic, high-quality photograph for a vertical children\'s science storybook page. '
        . 'Natural lighting, believable people, animals, and environments — NOT cartoon, NOT illustrated, NOT anime. '
        . 'No text, letters, speech bubbles, comic panels, watermarks, logos, or page numbers in the image.';
}

function ai_character_block(array $story): string
{
    $parts = [];
    foreach (['character_1', 'character_2', 'adult_guide'] as $key) {
        $val = trim((string) ($story[$key] ?? ''));
        if ($val !== '') {
            $parts[] = $val;
        }
    }

    return $parts === [] ? '' : 'Keep these characters consistent: ' . implode('; ', $parts) . '. ';
}

/**
 * @return array{ok: bool, path: string, error: string}
 */
function ai_generate_illustration(string $scenePrompt, string $size = '1024x1536'): array
{
    if (!ai_is_configured()) {
        return ['ok' => false, 'path' => '', 'error' => 'ChatGPT API key not configured.'];
    }

    $prompt = ai_illustration_style() . ' Scene: ' . trim($scenePrompt);

    $payload = [
        'model' => ai_image_model(),
        'prompt' => $prompt,
        'n' => 1,
        'size' => $size,
    ];

    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ai_api_key(),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 180,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'path' => '', 'error' => 'Image request failed: ' . $curlError];
    }

    $data = json_decode($raw, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        return ['ok' => false, 'path' => '', 'error' => 'Image generation error: ' . $msg];
    }

    $b64 = $data['data'][0]['b64_json'] ?? '';
    if (!is_string($b64) || $b64 === '') {
        return ['ok' => false, 'path' => '', 'error' => 'No image data returned from OpenAI.'];
    }

    $binary = base64_decode($b64, true);
    if ($binary === false || strlen($binary) < 500) {
        return ['ok' => false, 'path' => '', 'error' => 'Invalid image data from OpenAI.'];
    }

    $path = ai_write_temp_file('sc_ai_img_', $binary, 'png');
    if ($path === null) {
        return ['ok' => false, 'path' => '', 'error' => 'Could not save generated image. Check that uploads/ai-temp is writable.'];
    }

    return ['ok' => true, 'path' => $path, 'error' => ''];
}

function ai_export_init_session(): void
{
    ai_init_session();
    $_SESSION['ai_export'] = [
        'story' => null,
        'cover_path' => null,
        'page_paths' => [],
        'science_element_path' => null,
        'temp_files' => [],
    ];
}

function ai_export_clear_session(): void
{
    ai_init_session();
    $export = $_SESSION['ai_export'] ?? null;
    if (is_array($export)) {
        foreach ($export['temp_files'] ?? [] as $tmp) {
            if (is_string($tmp) && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
    unset($_SESSION['ai_export']);
}

/** @return array<string, mixed>|null */
function ai_export_state(): ?array
{
    ai_init_session();
    $export = $_SESSION['ai_export'] ?? null;
    return is_array($export) ? $export : null;
}

function ai_export_track_temp(string $path): void
{
    ai_init_session();
    if (!isset($_SESSION['ai_export']) || !is_array($_SESSION['ai_export'])) {
        ai_export_init_session();
    }
    $_SESSION['ai_export']['temp_files'][] = $path;
}

function ai_export_set_story(array $story): void
{
    ai_init_session();
    if (!isset($_SESSION['ai_export']) || !is_array($_SESSION['ai_export'])) {
        ai_export_init_session();
    }
    $_SESSION['ai_export']['story'] = $story;
}

function ai_export_set_cover(string $path): void
{
    ai_init_session();
    $_SESSION['ai_export']['cover_path'] = $path;
    ai_export_track_temp($path);
}

function ai_export_set_page_image(int $index, string $path): void
{
    ai_init_session();
    $_SESSION['ai_export']['page_paths'][$index] = $path;
    ai_export_track_temp($path);
}

function ai_export_set_science_element(string $path): void
{
    ai_init_session();
    $_SESSION['ai_export']['science_element_path'] = $path;
    ai_export_track_temp($path);
}

/**
 * @param list<array{role: string, content: string}> $messages
 * @return array{ok: bool, story: array<string, mixed>|null, error: string}
 */
function ai_extract_story_for_pdf(array $messages): array
{
    $transcript = '';
    foreach ($messages as $msg) {
        $label = $msg['role'] === 'assistant' ? 'Assistant' : 'Author';
        $transcript .= $label . ":\n" . $msg['content'] . "\n\n";
    }

    $extractMessages = [
        [
            'role' => 'user',
            'content' => "From this writing session, produce a finished children's science mystery storybook for ages 8–15 as JSON only (no markdown fences).\n\n"
                . ai_master_story_rules() . "\n\n"
                . ai_story_json_schema_instructions() . "\n\n"
                . "If the conversation is brief, invent a complete story with fresh characters and setting.\n"
                . "Display story text exactly as written — do not rewrite, shorten, duplicate, or add lines.\n\n"
                . "Conversation:\n" . $transcript,
        ],
    ];

    $result = ai_call_openai($extractMessages);
    if (!$result['ok']) {
        return ['ok' => false, 'story' => null, 'error' => $result['error']];
    }

    $jsonText = trim($result['content']);
    $jsonText = preg_replace('/^```json\s*|\s*```$/', '', $jsonText) ?? $jsonText;
    $story = json_decode($jsonText, true);

    if (!is_array($story) || empty($story['pages']) || !is_array($story['pages'])) {
        return ['ok' => false, 'story' => null, 'error' => 'Could not structure the story for PDF. Try asking the AI to write a full draft first, then export again.'];
    }

    return ['ok' => true, 'story' => $story, 'error' => ''];
}

/**
 * @param array<string, mixed> $story
 * @param array{cover_path?: string|null, page_paths?: array<int, string>, science_element_path?: string|null} $images
 */
function ai_generate_story_pdf(array $story, array $images = []): array
{
    if (!defined('K_TCPDF_THROW_EXCEPTION')) {
        define('K_TCPDF_THROW_EXCEPTION', true);
    }

    require_once __DIR__ . '/lib/tcpdf/tcpdf.php';
    require_once __DIR__ . '/pdf-branding-lib.php';

    $author = trim((string) ($story['author_name'] ?? 'Story Author'));
    $title = trim((string) ($story['title'] ?? 'Untitled Story'));
    $scienceElement = trim((string) ($story['science_element'] ?? ''));
    $pages = $story['pages'] ?? [];
    $pagePaths = $images['page_paths'] ?? [];
    $scienceElementPath = $images['science_element_path'] ?? null;

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(site_brand_name());
    $pdf->SetAuthor($author);
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 16, 20);
    $pdf->SetAutoPageBreak(false);

    $contentW = ai_pdf_content_width($pdf);
    $contentX = ai_pdf_left_margin($pdf);

    foreach ($pages as $i => $page) {
        if (!is_array($page)) {
            continue;
        }
        $text = trim((string) ($page['text'] ?? ''));
        if ($text === '') {
            continue;
        }

        $pageTitle = trim((string) ($page['page_title'] ?? $page['image_caption'] ?? 'Page ' . ($i + 1)));
        $imgPath = $pagePaths[$i] ?? ($page['image_path'] ?? null);
        $imgPath = is_string($imgPath) && is_file($imgPath) ? $imgPath : null;

        $pdf->AddPage();
        ai_pdf_render_story_page($pdf, $pageTitle, $text, $imgPath, $contentX, $contentW);
    }

    if ($scienceElement !== '') {
        $pdf->AddPage();
        $scienceImg = is_string($scienceElementPath) && is_file($scienceElementPath) ? $scienceElementPath : null;
        ai_pdf_render_story_page($pdf, 'Science Element', $scienceElement, $scienceImg, $contentX, $contentW);
    }

    brand_tcpdf_document($pdf);

    $uploadDir = books_upload_dir();
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $safeName = 'ai_book_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $relativePath = 'uploads/books/' . $safeName;
    $fullPath = __DIR__ . '/' . $relativePath;

    $pdf->Output($fullPath, 'F');

    if (!is_file($fullPath)) {
        return ['ok' => false, 'path' => '', 'error' => 'PDF file could not be saved.'];
    }

    return ['ok' => true, 'path' => $relativePath, 'error' => ''];
}

/**
 * @param array<string, mixed> $story
 */
function ai_save_book_from_story(PDO $pdo, array $story, string $pdfPath, int $userId, ?string $coverImagePath = null): array
{
    ensure_book_pdf_schema($pdo);

    $title = trim((string) ($story['title'] ?? 'Untitled Story'));
    $author = trim((string) ($story['author_name'] ?? 'Story Author'));
    $description = trim((string) ($story['description'] ?? ''));
    $scienceElement = trim((string) ($story['science_element'] ?? ''));
    $topic = trim((string) ($story['science_topic'] ?? 'Space'));
    $allowedTopics = ['Space', 'Human Body', 'Plants', 'Animals', 'Weather', 'Microbes', 'Earth Science', 'Engineering', 'Physical Science'];
    if (!in_array($topic, $allowedTopics, true)) {
        $topic = 'Space';
    }

    $coverBrowser = null;
    if ($coverImagePath !== null && is_file($coverImagePath)) {
        $coverFile = 'ai_' . bin2hex(random_bytes(6)) . '.png';
        $coversDir = books_covers_dir();
        if (!is_dir($coversDir)) {
            mkdir($coversDir, 0777, true);
        }
        $coverDisk = $coversDir . '/' . $coverFile;
        if (copy($coverImagePath, $coverDisk)) {
            $coverBrowser = app_base_path() . '/uploads/covers/' . $coverFile;
        }
    }

    try {
        $stmtCat = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
        $stmtCat->execute([$topic]);
        $cat = $stmtCat->fetch();
        if ($cat) {
            $categoryId = (int) $cat['category_id'];
        } else {
            $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$topic]);
            $categoryId = (int) $pdo->lastInsertId();
        }

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
            'author_name' => $author,
            'description' => $description !== '' ? $description : 'An AI-assisted science story for ages 8–15.',
            'cover_image_url' => $coverBrowser,
            'science_element' => $scienceElement,
            'pdf_file_path' => $pdfPath,
            'created_by' => $userId > 0 ? $userId : null,
        ]);
        $bookId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)')
            ->execute([$bookId, $categoryId]);

        $pdo->prepare("INSERT INTO submissions (book_id, submitted_by, status) VALUES (?, ?, 'under_review')")
            ->execute([$bookId, $userId > 0 ? $userId : null]);

        $pdo->commit();

        return ['ok' => true, 'book_id' => $bookId, 'error' => ''];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return ['ok' => false, 'book_id' => 0, 'error' => 'Could not save story to the library.'];
    }
}

function ai_page_image_prompt(array $page, string $storyTitle, array $story = []): string
{
    $custom = trim((string) ($page['image_prompt'] ?? ''));
    $scene = trim((string) ($page['scene'] ?? ''));
    if ($custom !== '') {
        $scene = $custom;
    }
    if ($scene === '') {
        $scene = trim((string) ($page['image_caption'] ?? 'Story scene'));
    }

    $pageTitle = trim((string) ($page['page_title'] ?? ''));
    $setting = trim((string) ($story['setting'] ?? ''));

    return ai_character_block($story)
        . ($setting !== '' ? 'Setting: ' . $setting . '. ' : '')
        . ($pageTitle !== '' ? 'Page titled "' . $pageTitle . '". ' : '')
        . 'Story: "' . $storyTitle . '". Scene: ' . $scene;
}

function ai_science_element_image_prompt(array $story): string
{
    $topic = trim((string) ($story['science_topic'] ?? 'science'));
    $scene = trim((string) ($story['science_element_scene'] ?? ''));
    if ($scene === '') {
        $scene = 'The same characters learning from a realistic science display, diagram, model, exhibit, poster, or nature-center sign about '
            . $topic . '.';
    }

    return ai_character_block($story) . $scene;
}

function ai_cover_image_prompt(array $story): string
{
    $title = trim((string) ($story['title'] ?? 'Science Story'));
    $topic = trim((string) ($story['science_topic'] ?? 'science'));
    $setting = trim((string) ($story['setting'] ?? ''));
    $firstPage = is_array($story['pages'][0] ?? null) ? $story['pages'][0] : [];
    $scene = trim((string) ($firstPage['scene'] ?? ''));
    if ($scene === '') {
        $scene = 'An exciting realistic scene introducing a kids science mystery about ' . $topic;
    }

    return ai_character_block($story)
        . ($setting !== '' ? 'Setting: ' . $setting . '. ' : '')
        . 'Book cover photo for "' . $title . '". ' . $scene;
}

function ai_chat_has_user_messages(array $messages): bool
{
    foreach ($messages as $msg) {
        if (($msg['role'] ?? '') === 'user' && trim((string) ($msg['content'] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function ai_json_response(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}
