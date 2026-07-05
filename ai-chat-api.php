<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai.php';

require_creator_login();
ai_init_session();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_json_response(['ok' => false, 'error' => 'POST required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    ai_json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$action = (string) ($input['action'] ?? '');

if ($action === 'reset') {
    ai_reset_session();
    ai_export_clear_session();
    ai_json_response(['ok' => true, 'workflow' => ai_workflow()]);
}

if ($action === 'generate_plan') {
    set_time_limit(120);

    $idea = trim((string) ($input['idea'] ?? ''));
    if ($idea === '') {
        ai_json_response(['ok' => false, 'error' => 'Describe your story idea first.'], 400);
    }

    $result = ai_generate_plan($idea);
    if (!$result['ok']) {
        ai_json_response(['ok' => false, 'error' => $result['error']], 502);
    }

    ai_set_workflow([
        'step' => 'plan',
        'idea' => $idea,
        'plan' => $result['plan'],
        'outline' => '',
        'story' => null,
    ]);

    ai_json_response([
        'ok' => true,
        'plan' => $result['plan'],
        'workflow' => ai_workflow(),
    ]);
}

if ($action === 'save_plan') {
    $plan = trim((string) ($input['plan'] ?? ''));
    if ($plan === '') {
        ai_json_response(['ok' => false, 'error' => 'Plan cannot be empty.'], 400);
    }

    $updates = ['plan' => $plan, 'step' => 'plan'];
    if (isset($input['idea'])) {
        $updates['idea'] = trim((string) $input['idea']);
    }

    ai_set_workflow($updates);

    ai_json_response([
        'ok' => true,
        'workflow' => ai_workflow(),
    ]);
}

if ($action === 'generate_outline') {
    set_time_limit(180);

    $workflow = ai_workflow();
    $idea = trim((string) ($input['idea'] ?? $workflow['idea'] ?? ''));
    $plan = trim((string) ($input['plan'] ?? $workflow['plan'] ?? ''));

    if ($idea === '') {
        ai_json_response(['ok' => false, 'error' => 'Story idea is missing.'], 400);
    }
    if ($plan === '') {
        ai_json_response(['ok' => false, 'error' => 'Write or approve a plan first.'], 400);
    }

    $result = ai_generate_outline($idea, $plan);
    if (!$result['ok'] || !is_array($result['story'])) {
        ai_json_response(['ok' => false, 'error' => $result['error']], 502);
    }

    ai_set_workflow([
        'step' => 'outline',
        'idea' => $idea,
        'plan' => $plan,
        'outline' => $result['outline'],
        'story' => $result['story'],
    ]);

    ai_json_response([
        'ok' => true,
        'outline' => $result['outline'],
        'workflow' => ai_workflow(),
    ]);
}

if ($action === 'save_outline') {
    $outline = trim((string) ($input['outline'] ?? ''));
    if ($outline === '') {
        ai_json_response(['ok' => false, 'error' => 'Story outline cannot be empty.'], 400);
    }

    $updates = [
        'outline' => $outline,
        'step' => 'outline',
        'story' => null,
    ];
    if (isset($input['plan'])) {
        $updates['plan'] = trim((string) $input['plan']);
    }

    ai_set_workflow($updates);

    ai_json_response([
        'ok' => true,
        'workflow' => ai_workflow(),
    ]);
}

if ($action === 'chat') {
    $message = trim((string) ($input['message'] ?? ''));
    if ($message === '') {
        ai_json_response(['ok' => false, 'error' => 'Message is empty.'], 400);
    }

    ai_append_message('user', $message);
    $result = ai_call_openai(ai_session_messages());
    if (!$result['ok']) {
        array_pop($_SESSION['ai_chat']['messages']);
        ai_json_response(['ok' => false, 'error' => $result['error']], 502);
    }

    ai_append_message('assistant', $result['content']);
    ai_json_response([
        'ok' => true,
        'reply' => $result['content'],
        'messages' => ai_session_messages(),
    ]);
}

if ($action === 'export_prepare') {
    set_time_limit(180);

    $workflow = ai_workflow();
    if (trim((string) ($workflow['outline'] ?? '')) === '') {
        ai_json_response(['ok' => false, 'error' => 'Generate and review your story outline before creating a PDF.'], 400);
    }

    if (isset($input['outline'])) {
        ai_set_workflow([
            'outline' => trim((string) $input['outline']),
            'story' => null,
        ]);
    }
    if (isset($input['plan'])) {
        ai_set_workflow(['plan' => trim((string) $input['plan'])]);
    }

    ai_export_clear_session();
    ai_export_init_session();

    $extract = ai_story_for_export();
    if (!$extract['ok'] || !is_array($extract['story'])) {
        ai_export_clear_session();
        ai_json_response(['ok' => false, 'error' => $extract['error']], 502);
    }

    $story = $extract['story'];
    $user = current_user();
    $authorName = trim((string) ($user['full_name'] ?? ''));
    if ($authorName !== '' && (($story['author_name'] ?? '') === 'Story Author' || ($story['author_name'] ?? '') === '')) {
        $story['author_name'] = $authorName;
    }

    ai_export_set_story($story);
    ai_set_workflow(['step' => 'ready', 'story' => $story]);

    $pageCount = count($story['pages'] ?? []);
    $hasScienceElement = trim((string) ($story['science_element'] ?? '')) !== '';
    ai_json_response([
        'ok' => true,
        'title' => (string) ($story['title'] ?? 'Untitled Story'),
        'page_count' => $pageCount,
        'total_images' => $pageCount + 1 + ($hasScienceElement ? 1 : 0),
    ]);
}

if ($action === 'export_image') {
    set_time_limit(240);

    $export = ai_export_state();
    $story = is_array($export['story'] ?? null) ? $export['story'] : null;
    if ($story === null) {
        ai_json_response(['ok' => false, 'error' => 'Export session expired. Click Create PDF again.'], 400);
    }

    $kind = (string) ($input['kind'] ?? 'page');
    $title = (string) ($story['title'] ?? 'Story');

    if ($kind === 'cover') {
        $prompt = ai_cover_image_prompt($story);
        $image = ai_generate_illustration($prompt);
        if (!$image['ok']) {
            ai_json_response(['ok' => false, 'error' => $image['error']], 502);
        }
        ai_export_set_cover($image['path']);
        ai_json_response(['ok' => true, 'kind' => 'cover']);
    }

    if ($kind === 'science_element') {
        $prompt = ai_science_element_image_prompt($story);
        $image = ai_generate_illustration($prompt);
        if (!$image['ok']) {
            ai_json_response(['ok' => false, 'error' => $image['error']], 502);
        }
        ai_export_set_science_element($image['path']);
        ai_json_response(['ok' => true, 'kind' => 'science_element']);
    }

    $pageIndex = (int) ($input['page_index'] ?? -1);
    $pages = $story['pages'] ?? [];
    if ($pageIndex < 0 || $pageIndex >= count($pages) || !is_array($pages[$pageIndex])) {
        ai_json_response(['ok' => false, 'error' => 'Invalid page index.'], 400);
    }

    $prompt = ai_page_image_prompt($pages[$pageIndex], $title, $story);
    $image = ai_generate_illustration($prompt);
    if (!$image['ok']) {
        ai_json_response(['ok' => false, 'error' => $image['error']], 502);
    }

    ai_export_set_page_image($pageIndex, $image['path']);
    ai_json_response([
        'ok' => true,
        'kind' => 'page',
        'page_index' => $pageIndex,
    ]);
}

if ($action === 'export_finalize') {
    set_time_limit(120);

    $export = ai_export_state();
    $story = is_array($export['story'] ?? null) ? $export['story'] : null;
    if ($story === null) {
        ai_json_response(['ok' => false, 'error' => 'Export session expired. Click Create PDF again.'], 400);
    }

    $pdf = ai_generate_story_pdf($story, [
        'cover_path' => $export['cover_path'] ?? null,
        'page_paths' => $export['page_paths'] ?? [],
        'science_element_path' => $export['science_element_path'] ?? null,
    ]);
    if (!$pdf['ok']) {
        ai_json_response(['ok' => false, 'error' => $pdf['error']], 500);
    }

    $user = current_user();
    $saved = ai_save_book_from_story(
        $pdo,
        $story,
        $pdf['path'],
        (int) ($user['user_id'] ?? 0),
        is_string($export['cover_path'] ?? null) ? $export['cover_path'] : null
    );

    ai_export_clear_session();

    if (!$saved['ok']) {
        ai_json_response(['ok' => false, 'error' => $saved['error']], 500);
    }

    ai_json_response([
        'ok' => true,
        'book_id' => $saved['book_id'],
        'redirect' => 'creator-dashboard.php',
        'message' => 'PDF created with AI illustrations and submitted for review!',
    ]);
}

if ($action === 'export_pdf') {
    set_time_limit(600);

    $workflow = ai_workflow();
    if (trim((string) ($workflow['outline'] ?? '')) === '' && !ai_chat_has_user_messages(ai_session_messages())) {
        ai_json_response(['ok' => false, 'error' => 'Complete the story outline before exporting.'], 400);
    }

    ai_export_clear_session();
    ai_export_init_session();

    $extract = ai_story_for_export();
    if (!$extract['ok'] || !is_array($extract['story'])) {
        ai_export_clear_session();
        ai_json_response(['ok' => false, 'error' => $extract['error']], 502);
    }

    $story = $extract['story'];
    $user = current_user();
    $authorName = trim((string) ($user['full_name'] ?? ''));
    if ($authorName !== '' && (($story['author_name'] ?? '') === 'Story Author' || ($story['author_name'] ?? '') === '')) {
        $story['author_name'] = $authorName;
    }

    $cover = ai_generate_illustration(ai_cover_image_prompt($story));
    if ($cover['ok']) {
        ai_export_set_cover($cover['path']);
    }

    foreach ($story['pages'] ?? [] as $i => $page) {
        if (!is_array($page)) {
            continue;
        }
        $img = ai_generate_illustration(ai_page_image_prompt($page, (string) ($story['title'] ?? 'Story'), $story));
        if ($img['ok']) {
            ai_export_set_page_image((int) $i, $img['path']);
        }
    }

    $scienceImg = ai_generate_illustration(ai_science_element_image_prompt($story));
    if ($scienceImg['ok']) {
        ai_export_set_science_element($scienceImg['path']);
    }

    $export = ai_export_state() ?? [];
    $pdf = ai_generate_story_pdf($story, [
        'cover_path' => $export['cover_path'] ?? null,
        'page_paths' => $export['page_paths'] ?? [],
        'science_element_path' => $export['science_element_path'] ?? null,
    ]);
    if (!$pdf['ok']) {
        ai_export_clear_session();
        ai_json_response(['ok' => false, 'error' => $pdf['error']], 500);
    }

    $saved = ai_save_book_from_story(
        $pdo,
        $story,
        $pdf['path'],
        (int) ($user['user_id'] ?? 0),
        is_string($export['cover_path'] ?? null) ? $export['cover_path'] : null
    );
    ai_export_clear_session();

    if (!$saved['ok']) {
        ai_json_response(['ok' => false, 'error' => $saved['error']], 500);
    }

    ai_json_response([
        'ok' => true,
        'book_id' => $saved['book_id'],
        'redirect' => 'creator-dashboard.php',
        'message' => 'PDF created and submitted for review!',
    ]);
}

ai_json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
