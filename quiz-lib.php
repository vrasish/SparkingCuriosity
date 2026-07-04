<?php

declare(strict_types=1);

require_once __DIR__ . '/read-aloud-lib.php';

function quiz_data_dir(): string
{
    $dir = __DIR__ . '/data/quiz';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function quiz_json_path(int $bookId): string
{
    return quiz_data_dir() . '/' . $bookId . '.json';
}

/** @return array<string, mixed>|null */
function quiz_story_data(int $bookId): ?array
{
    $path = quiz_json_path($bookId);
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
        return null;
    }

    return $data;
}

function quiz_has_story(int $bookId): bool
{
    return quiz_story_data($bookId) !== null;
}

/** @return list<array<string, mixed>> */
function quiz_public_questions(int $bookId): array
{
    $data = quiz_story_data($bookId);
    if ($data === null) {
        return [];
    }

    $questions = [];
    foreach ($data['questions'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $prompt = trim((string) ($entry['prompt'] ?? ''));
        $choices = $entry['choices'] ?? [];
        if ($prompt === '' || !is_array($choices) || count($choices) < 2) {
            continue;
        }

        $explanation = trim((string) ($entry['explanation'] ?? ''));
        $normalizedChoices = quiz_normalize_choices($choices, $explanation);

        if (count($normalizedChoices) < 2) {
            continue;
        }

        $correctIndex = (int) ($entry['correct_index'] ?? -1);
        if ($correctIndex < 0 || $correctIndex >= count($normalizedChoices)) {
            continue;
        }

        $questions[] = [
            'id' => (int) ($entry['id'] ?? ($index + 1)),
            'prompt' => $prompt,
            'choices' => $normalizedChoices,
            'correct_index' => $correctIndex,
            'explanation' => $explanation,
        ];
    }

    return $questions;
}

/** @param array<string, mixed> $data */
function quiz_save_story_data(int $bookId, array $data): void
{
    $data['book_id'] = $bookId;
    $path = quiz_json_path($bookId);
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
}

/** @return array{ok: bool, data?: array<string, mixed>, error?: string} */
function quiz_generate_fallback_for_book(PDO $pdo, int $bookId): array
{
    $stmt = $pdo->prepare('
        SELECT book_id, title, description, science_element
        FROM books
        WHERE book_id = ?
    ');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) {
        return ['ok' => false, 'error' => 'Book not found.'];
    }

    $title = trim((string) ($book['title'] ?? ''));
    $science = trim((string) ($book['science_element'] ?? ''));
    $storyText = read_aloud_story_full_text($bookId);

    if ($science === '') {
        $science = trim((string) ($book['description'] ?? ''));
    }

    if ($science === '') {
        return ['ok' => false, 'error' => 'No science content available.'];
    }

    $questions = quiz_build_fallback_questions($title, $science, $storyText);
    if (count($questions) !== quiz_question_count()) {
        return ['ok' => false, 'error' => 'Could not build ' . quiz_question_count() . ' fallback questions.'];
    }

    $data = [
        'book_id' => $bookId,
        'title' => $title,
        'questions' => $questions,
    ];
    quiz_save_story_data($bookId, $data);

    return ['ok' => true, 'data' => $data];
}

/** @return list<string> */
function quiz_split_sentences(string $text): array
{
    $parts = preg_split('/(?<=[.!?])\s+/', trim($text)) ?: [];
    $sentences = [];
    foreach ($parts as $part) {
        $sentence = trim($part);
        if ($sentence !== '') {
            $sentences[] = $sentence;
        }
    }

    return $sentences;
}

function quiz_question_count(): int
{
    return 4;
}

function quiz_clean_fact(string $fact): string
{
    $fact = trim(preg_replace('/\s+/', ' ', $fact) ?? '');
    $fact = preg_replace('/^(Brain Clue|Science Clue)\s+/i', '', $fact) ?? $fact;

    return trim($fact, " \t\n\r\0\x0B.,");
}

/** @return list<string> */
function quiz_facts_from_text(string $text): array
{
    $facts = [];
    $sentences = preg_split('/\.\s+/', trim($text)) ?: [];

    foreach ($sentences as $sentence) {
        $sentence = quiz_clean_fact($sentence);
        if ($sentence === '') {
            continue;
        }

        $parts = [$sentence];
        if (mb_strlen($sentence) > 70) {
            $parts = preg_split('/,\s+/', $sentence) ?: [$sentence];
        }

        foreach ($parts as $part) {
            $fact = quiz_clean_fact($part);
            if ($fact === '' || !quiz_is_good_kid_fact($fact)) {
                continue;
            }
            $key = mb_strtolower($fact);
            if (!isset($facts[$key])) {
                $facts[$key] = $fact;
            }
        }
    }

    return array_values($facts);
}

function quiz_is_good_kid_fact(string $fact): bool
{
    if (mb_strlen($fact) < 18 || mb_strlen($fact) > 90) {
        return false;
    }
    if (preg_match('/[‘’"?!]/u', $fact)) {
        return false;
    }
    if (preg_match('/\b(said|asked|replied|whispered|laughed|shouted|later in class|dr\.|ms\.|mr\.)\b/i', $fact)) {
        return false;
    }
    if (preg_match('/^(and|or|but|then|also|when|because|so|while)\b/i', $fact)) {
        return false;
    }
    if (preg_match('/\b(page|chapter|story about)\b/i', $fact) && mb_strlen($fact) < 40) {
        return false;
    }

    return true;
}

/** @return list<string> */
function quiz_extract_kid_facts(string $science, string $storyText): array
{
    $facts = quiz_facts_from_text($science);

    if (count($facts) < quiz_question_count()) {
        foreach (quiz_facts_from_text($storyText) as $fact) {
            if (count($facts) >= quiz_question_count()) {
                break;
            }
            $facts[] = $fact;
        }
    }

    $facts = array_values(array_unique($facts));

    while (count($facts) < quiz_question_count() && count($facts) > 0) {
        $facts[] = $facts[count($facts) - 1];
    }

    return $facts;
}

function quiz_short_choice(string $text): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if ($text === '') {
        return 'Something from the story';
    }

    return $text;
}

function quiz_expand_truncated_choice(string $choice, string $explanation): string
{
    if (!str_ends_with($choice, '...')) {
        return $choice;
    }

    $prefix = rtrim(mb_substr($choice, 0, mb_strlen($choice) - 3));
    if ($explanation !== '' && mb_strpos($explanation, $prefix) === 0) {
        return $explanation;
    }

    return $prefix;
}

/** @param list<mixed> $choices @return list<string> */
function quiz_normalize_choices(array $choices, string $explanation): array
{
    $normalizedChoices = [];
    foreach ($choices as $choice) {
        $text = trim((string) $choice);
        if ($text !== '') {
            $normalizedChoices[] = quiz_expand_truncated_choice($text, $explanation);
        }
    }

    return $normalizedChoices;
}

/** @return list<array<string, mixed>> */
function quiz_build_fallback_questions(string $title, string $science, string $storyText): array
{
    $facts = quiz_extract_kid_facts($science, $storyText);
    if ($facts === []) {
        $facts = [quiz_short_choice($science)];
    }

    while (count($facts) < quiz_question_count()) {
        $facts[] = $facts[count($facts) - 1];
    }

    $distractors = [
        'The Moon makes its own light.',
        'Plants eat food from soil only.',
        'Your heart pumps air through your body.',
        'Sound is fastest in outer space.',
        'All germs are bad for you.',
        'The brain does not help you move.',
        'Rocks never change at all.',
        'Fish breathe air like people do.',
    ];

    $templates = [
        static fn (string $title): string => 'What is "' . $title . '" mostly about?',
        static fn (): string => 'Which answer is true?',
        static fn (): string => 'What did you learn from this story?',
        static fn (): string => 'Pick the best answer:',
    ];

    $questions = [];
    for ($q = 0; $q < quiz_question_count(); $q += 1) {
        $fact = $facts[$q];
        $correct = quiz_short_choice($fact);
        $shortDistractors = array_map(
            static fn (string $d): string => quiz_short_choice($d),
            $distractors
        );
        $choiceSet = quiz_make_choice_set($correct, $shortDistractors);
        $prompt = $q === 0 ? $templates[0]($title) : $templates[$q]();
        $questions[] = [
            'id' => $q + 1,
            'prompt' => $prompt,
            'choices' => $choiceSet['choices'],
            'correct_index' => $choiceSet['correct_index'],
            'explanation' => quiz_short_choice($fact),
        ];
    }

    return quiz_public_questions_from_raw($questions);
}

/** @param list<string> $distractors @return array{choices: list<string>, correct_index: int} */
function quiz_make_choice_set(string $correct, array $distractors): array
{
    $correct = trim($correct);
    $choices = [$correct];
    shuffle($distractors);

    foreach ($distractors as $distractor) {
        if (count($choices) >= 4) {
            break;
        }
        $distractor = trim($distractor);
        if ($distractor === '' || in_array($distractor, $choices, true)) {
            continue;
        }
        if (quiz_strings_similar($distractor, $correct)) {
            continue;
        }
        $choices[] = $distractor;
    }

    while (count($choices) < 4) {
        $choices[] = 'None of these answers is correct.';
    }

    $correctIndex = 0;
    shuffle($choices);
    $correctIndex = array_search($correct, $choices, true);
    if ($correctIndex === false) {
        $choices[0] = $correct;
        $correctIndex = 0;
    }

    return [
        'choices' => array_slice($choices, 0, 4),
        'correct_index' => (int) $correctIndex,
    ];
}

function quiz_strings_similar(string $a, string $b): bool
{
    similar_text(mb_strtolower($a), mb_strtolower($b), $percent);

    return $percent > 70;
}

/** @return array{ok: bool, data?: array<string, mixed>, error?: string} */
function quiz_generate_for_book(PDO $pdo, int $bookId): array
{
    require_once __DIR__ . '/ai.php';

    $stmt = $pdo->prepare('
        SELECT book_id, title, description, science_element
        FROM books
        WHERE book_id = ?
    ');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) {
        return ['ok' => false, 'error' => 'Book not found.'];
    }

    $storyText = read_aloud_story_full_text($bookId);
    if ($storyText === '') {
        $storyText = trim((string) ($book['description'] ?? ''));
    }

    $science = trim((string) ($book['science_element'] ?? ''));
    $title = trim((string) ($book['title'] ?? ''));

    if (!ai_is_configured()) {
        return ['ok' => false, 'error' => 'OpenAI API key not configured.'];
    }

    $system = <<<'PROMPT'
You write simple reading quizzes for children ages 8–12.

Return ONLY valid JSON with this shape:
{
  "questions": [
    {
      "id": 1,
      "prompt": "Short question?",
      "choices": ["Short A", "Short B", "Short C", "Short D"],
      "correct_index": 0,
      "explanation": "One short friendly sentence."
    }
  ]
}

Rules:
- Exactly 4 questions.
- Each question has exactly 4 answer choices written as complete, readable sentences.
- Use easy words a 3rd–6th grader can read.
- Never truncate choices with ellipsis; every choice must be fully readable.
- Ask about 4 different facts from the story. Do not repeat the same idea.
- Keep questions simple, like "What does the brain do?" not long formal sentences.
- Keep explanations to one short sentence.
- correct_index is 0-based.
PROMPT;

    $user = "Story title: {$title}\n\nScience summary:\n{$science}\n\nStory text:\n"
        . mb_substr($storyText, 0, 12000);

    $config = ai_config();
    $payload = [
        'model' => $config['openai_model'] ?? 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
        'temperature' => 0.4,
        'response_format' => ['type' => 'json_object'],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['openai_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpCode >= 400) {
        if ($httpCode === 429) {
            return quiz_generate_fallback_for_book($pdo, $bookId);
        }

        return ['ok' => false, 'error' => 'OpenAI request failed (HTTP ' . $httpCode . ').'];
    }

    $response = json_decode($raw, true);
    $content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['questions']) || !is_array($parsed['questions'])) {
        return ['ok' => false, 'error' => 'Could not parse quiz JSON from AI.'];
    }

    $questions = quiz_public_questions_from_raw($parsed['questions']);
    if (count($questions) !== quiz_question_count()) {
        return ['ok' => false, 'error' => 'Expected ' . quiz_question_count() . ' valid questions, got ' . count($questions) . '.'];
    }

    $data = [
        'book_id' => $bookId,
        'title' => $title,
        'questions' => $questions,
    ];
    quiz_save_story_data($bookId, $data);

    return ['ok' => true, 'data' => $data];
}

/** @param list<mixed> $rawQuestions @return list<array<string, mixed>> */
function quiz_public_questions_from_raw(array $rawQuestions): array
{
    $questions = [];
    foreach ($rawQuestions as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $prompt = trim((string) ($entry['prompt'] ?? ''));
        $choices = $entry['choices'] ?? [];
        if ($prompt === '' || !is_array($choices)) {
            continue;
        }

        $explanation = trim((string) ($entry['explanation'] ?? ''));
        $normalizedChoices = quiz_normalize_choices($choices, $explanation);

        if (count($normalizedChoices) < 2) {
            continue;
        }

        $correctIndex = (int) ($entry['correct_index'] ?? -1);
        if ($correctIndex < 0 || $correctIndex >= count($normalizedChoices)) {
            continue;
        }

        $questions[] = [
            'id' => (int) ($entry['id'] ?? ($index + 1)),
            'prompt' => $prompt,
            'choices' => array_slice($normalizedChoices, 0, 4),
            'correct_index' => $correctIndex,
            'explanation' => $explanation,
        ];
    }

    return array_slice($questions, 0, quiz_question_count());
}
