<?php
/**
 * Secrets should NOT live in htdocs if you can avoid it.
 *
 * Recommended (outside web root):
 *   /Applications/XAMPP/xamppfiles/private/sparking-ai.config.php
 *
 * Copy from: private/sparking-ai.config.example.php (in XAMPP private folder)
 *
 * XAMPP: chmod 755 the private folder and chmod 644 the config file so Apache (user "daemon") can read it.
 *
 * Or set server env: OPENAI_API_KEY=sk-...
 *
 * Optional fallback: copy this file to ai.config.php (gitignored).
 */
return [
    'openai_api_key' => 'PUT_OPENAI_API_KEY_HERE',
    'openai_model' => 'gpt-4o-mini',
    'openai_image_model' => 'gpt-image-1',
    'read_aloud_tts_speed' => 0.9,
];
