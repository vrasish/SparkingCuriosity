<?php

declare(strict_types=1);

/**
 * @return array<string, array<string, mixed>>
 */
function impact_stories(): array
{
    return [
        'art-for-hope-dzaleka-malawi' => [
            'slug' => 'art-for-hope-dzaleka-malawi',
            'folder_title' => 'Art for Hope — Dzaleka Refugee Camp, Malawi',
            'partner' => 'Art for Hope',
            'partner_website' => 'https://artfohope.org/',
            'location' => 'Dzaleka Refugee Camp, Malawi',
            'date_label' => 'August 2026',
            'children_reached' => 350,
            'schools' => 1,
            'summary' => '350 students explored seed germination and states of matter through stories, quizzes, and hands-on science.',
            'cover' => 'assets/impact/dzaleka-2026/session-1.png',
            'media_base' => 'assets/impact/dzaleka-2026',
            'sessions' => [
                'seed-germination' => [
                    'slug' => 'seed-germination',
                    'title' => 'The Seed That Slept Underground',
                    'subtitle' => 'A story about germination',
                    'summary' => 'Students learned how seeds germinate, completed a quiz, and planted seeds to observe at home.',
                    'cover' => 'assets/impact/dzaleka-2026/session-1.png',
                ],
                'matter-mystery' => [
                    'slug' => 'matter-mystery',
                    'title' => 'The Matter Mystery',
                    'subtitle' => 'A story about solids, liquids, and gases',
                    'summary' => 'Students explored the three states of matter, observed ice in a bottle, and completed the story quiz.',
                    'cover' => 'assets/impact/dzaleka-2026/matter-2.png',
                ],
                'online-states-of-matter' => [
                    'slug' => 'online-states-of-matter',
                    'title' => 'Online States of Matter Session',
                    'subtitle' => 'October 11, 2026 · Solids, liquids, and gases',
                    'summary' => 'Vaishnavi conducted an online SciFables science session with Art for Hope students, explaining the three states of matter using simple examples.',
                    'cover' => 'assets/impact/dzaleka-2026/online-matter-session.jpg',
                ],
            ],
        ],
        'risa-malawi' => [
            'slug' => 'risa-malawi',
            'folder_title' => 'RISA — Refugee Independent Schools Association of Malawi',
            'partner' => 'RISA - Refugee Independent Schools Association of Malawi',
            'partner_website' => 'https://risamalawi.org/',
            'location' => 'Dzaleka Refugee Camp & host communities, Malawi',
            'date_label' => 'September 2026',
            'children_reached' => 27000,
            'schools' => 61,
            'summary' => 'Bringing SciFables to 27,000 students across 61 member schools.',
            'cover' => 'assets/impact/risa-malawi/teacher-training-2026-09-25.png',
            'media_base' => 'assets/impact/risa-malawi',
            'member_schools' => '61',
            'highlights' => [
                [
                    'image' => 'assets/impact/risa-malawi/teacher-training-2026-09-25.png',
                    'caption' => 'Teacher Training & Program Launch — September 25, 2026',
                ],
            ],
            'sessions' => [],
        ],
        'loyola-elementary-los-altos' => [
            'slug' => 'loyola-elementary-los-altos',
            'folder_title' => 'Loyola Elementary School — Los Altos, California',
            'partner' => 'Loyola Elementary School',
            'partner_website' => '',
            'location' => 'Los Altos, California',
            'date_label' => 'October 2, 2026',
            'children_reached' => 24,
            'schools' => 1,
            'summary' => 'SciFables Classroom STEM Session with 24 students exploring seed germination through storytelling, discussion, quiz, and planting.',
            'cover' => 'assets/impact/loyola-2026/session.jpg',
            'media_base' => 'assets/impact/loyola-2026',
            'sessions' => [
                'stem-seed-session' => [
                    'slug' => 'stem-seed-session',
                    'title' => 'SciFables Classroom STEM Session',
                    'subtitle' => 'October 2, 2026 · Seed Germination',
                    'summary' => '24 students explored The Seed That Slept Underground, completed a quiz, and received bean seeds to plant and observe.',
                    'cover' => 'assets/impact/loyola-2026/session.jpg',
                ],
            ],
        ],
    ];
}

/** @return array<string, mixed>|null */
function impact_story_by_slug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    $stories = impact_stories();

    return $stories[$slug] ?? null;
}

function impact_story_url(string $slug): string
{
    return app_url('impact-story.php?id=' . rawurlencode($slug));
}

/** @return array<string, mixed>|null */
function impact_session_by_slug(array $story, string $sessionSlug): ?array
{
    $sessions = $story['sessions'] ?? [];
    if (!is_array($sessions)) {
        return null;
    }

    $session = $sessions[$sessionSlug] ?? null;

    return is_array($session) ? $session : null;
}

function impact_session_url(string $storySlug, string $sessionSlug): string
{
    return app_url(
        'impact-story.php?id=' . rawurlencode($storySlug)
        . '&session=' . rawurlencode($sessionSlug)
    );
}

function impact_total_children_reached(): int
{
    $total = 0;
    foreach (impact_stories() as $story) {
        $total += (int) ($story['children_reached'] ?? 0);
    }

    return $total;
}

function impact_total_schools(): int
{
    $total = 0;
    foreach (impact_stories() as $story) {
        $total += (int) ($story['schools'] ?? 0);
    }

    return $total;
}

function impact_session_count(): int
{
    return count(impact_stories());
}
