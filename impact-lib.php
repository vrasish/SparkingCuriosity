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
            'summary' => '350 children explored seed germination through story, quiz, and hands-on planting.',
            'cover' => 'assets/impact/dzaleka-2026/session-1.png',
            'media_base' => 'assets/impact/dzaleka-2026',
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

function impact_total_children_reached(): int
{
    $total = 0;
    foreach (impact_stories() as $story) {
        $total += (int) ($story['children_reached'] ?? 0);
    }

    return $total;
}

function impact_session_count(): int
{
    return count(impact_stories());
}
