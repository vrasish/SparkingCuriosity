<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai.php';

require_creator_login();
ai_init_session();

$user = current_user();
$starters = ai_prompt_templates();
$configured = ai_is_configured();
$workflow = ai_workflow();
$step = $workflow['step'] ?? 'idea';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(site_page_title('AI Authoring Tool')) ?></title>
    <?php render_stylesheet(); ?>
</head>
<body class="<?= body_class('ai-authoring-body') ?>">
<?php render_fun_background(); ?>
<?php render_site_header('public'); ?>

<main class="container page-main page-main-compact ai-authoring-page">
    <header class="ai-page-header">
        <h1 class="ai-page-title">AI Authoring Tool</h1>
        <p class="ai-page-lead">Describe your science topic and the AI will draft a mystery storybook like your published PDFs — realistic photo pages, cream layout, navy titles, and a Science Element page at the end.</p>
    </header>

    <?php if (!$configured): ?>
        <div class="alert alert-error ai-setup-alert">
            <strong>One more step to enable the AI:</strong>
            Add your OpenAI API key to
            <code>/Applications/XAMPP/xamppfiles/private/sparking-ai.config.php</code>
            (outside the website folder, not reachable by browser).
            <br><small>Get a key at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a> — then reload this page.</small>
        </div>
    <?php endif; ?>

    <section class="ai-workflow-panel">
        <nav class="ai-step-nav" aria-label="Story creation steps">
            <div class="ai-step-item<?= in_array($step, ['idea', 'plan', 'outline', 'ready'], true) ? ' ai-step-active' : '' ?><?= in_array($step, ['plan', 'outline', 'ready'], true) ? ' ai-step-done' : '' ?>" data-step="plan">
                <span class="ai-step-num">1</span>
                <span class="ai-step-label">Plan</span>
            </div>
            <div class="ai-step-connector" aria-hidden="true"></div>
            <div class="ai-step-item<?= in_array($step, ['outline', 'ready'], true) ? ' ai-step-active' : '' ?><?= $step === 'ready' ? ' ai-step-done' : '' ?>" data-step="outline">
                <span class="ai-step-num">2</span>
                <span class="ai-step-label">Story outline</span>
            </div>
            <div class="ai-step-connector" aria-hidden="true"></div>
            <div class="ai-step-item<?= $step === 'ready' ? ' ai-step-active ai-step-done' : '' ?>" data-step="ready">
                <span class="ai-step-num">3</span>
                <span class="ai-step-label">Create PDF</span>
            </div>
        </nav>

        <div class="ai-workflow-toolbar">
            <button type="button" class="btn btn-outline btn-sm" id="ai-reset-btn">Start over</button>
            <button type="button" class="btn btn-primary btn-sm" id="ai-export-btn"<?= trim((string) ($workflow['outline'] ?? '')) === '' ? ' disabled' : '' ?>>Create PDF &amp; submit</button>
        </div>

        <!-- Step 1: Idea + Plan -->
        <section class="ai-workflow-stage" id="ai-stage-idea">
            <h2 class="ai-panel-title">Your story idea</h2>
            <p class="ai-panel-hint">One sentence is enough — e.g. a science topic or mystery setting. The AI will draft a 5–8 page storybook plan with fresh characters.</p>
            <textarea id="ai-idea-input" class="form-control ai-workflow-textarea ai-idea-input" rows="3" placeholder="Example: A girl finds a glowing seed that only grows in moonlight…"><?= e($workflow['idea'] ?? '') ?></textarea>

            <details class="ai-starters-details">
                <summary>Optional topic starters</summary>
                <div class="ai-starter-chips">
                    <?php foreach ($starters as $starter): ?>
                        <button type="button" class="ai-starter-chip" data-starter="<?= e($starter['starter']) ?>">
                            <?= e($starter['icon']) ?> <?= e($starter['title']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </details>

            <div class="ai-stage-actions">
                <button type="button" class="btn btn-primary" id="ai-generate-plan-btn">Generate plan</button>
            </div>
        </section>

        <section class="ai-workflow-stage<?= trim((string) ($workflow['plan'] ?? '')) === '' ? ' ai-stage-hidden' : '' ?>" id="ai-stage-plan">
            <h2 class="ai-panel-title">Plan</h2>
            <p class="ai-panel-hint">Review and edit what the AI will do. Change anything you like, then continue to the story outline.</p>
            <textarea id="ai-plan-input" class="form-control ai-workflow-textarea" rows="14" placeholder="Your plan will appear here…"><?= e($workflow['plan'] ?? '') ?></textarea>
            <div class="ai-stage-actions">
                <button type="button" class="btn btn-outline" id="ai-regenerate-plan-btn">Regenerate plan</button>
                <button type="button" class="btn btn-primary" id="ai-generate-outline-btn">Approve plan &amp; create outline</button>
            </div>
        </section>

        <!-- Step 2: Story outline -->
        <section class="ai-workflow-stage<?= trim((string) ($workflow['outline'] ?? '')) === '' ? ' ai-stage-hidden' : '' ?>" id="ai-stage-outline">
            <h2 class="ai-panel-title">Story outline</h2>
            <p class="ai-panel-hint">Page-by-page story with titles, scenes, and text. Edit anything, then create your realistic photo PDF.</p>
            <textarea id="ai-outline-input" class="form-control ai-workflow-textarea" rows="18" placeholder="Your story outline will appear here…"><?= e($workflow['outline'] ?? '') ?></textarea>
            <div class="ai-stage-actions">
                <button type="button" class="btn btn-outline" id="ai-regenerate-outline-btn">Regenerate outline</button>
                <button type="button" class="btn btn-primary" id="ai-export-outline-btn"<?= trim((string) ($workflow['outline'] ?? '')) === '' ? ' disabled' : '' ?>>Create PDF &amp; submit</button>
            </div>
        </section>

        <div id="ai-export-progress" class="ai-export-progress" hidden>
            <div class="ai-export-progress-track">
                <div id="ai-export-progress-bar" class="ai-export-progress-bar"></div>
            </div>
        </div>
        <p id="ai-status" class="ai-status" role="status"></p>
    </section>
</main>

<?php render_site_footer(); ?>
<script>
window.AI_CONFIGURED = <?= $configured ? 'true' : 'false' ?>;
window.AI_WORKFLOW_STEP = <?= json_encode($step) ?>;
window.AI_API_URL = <?= json_encode(app_url('ai-chat-api.php')) ?>;
</script>
<script src="<?= e(asset_url('ai-authoring.js')) ?>"></script>
</body>
</html>
