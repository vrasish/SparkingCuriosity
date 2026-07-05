(function () {
    const ideaInput = document.getElementById('ai-idea-input');
    const planInput = document.getElementById('ai-plan-input');
    const outlineInput = document.getElementById('ai-outline-input');
    const statusEl = document.getElementById('ai-status');
    const progressEl = document.getElementById('ai-export-progress');
    const progressBarEl = document.getElementById('ai-export-progress-bar');
    const resetBtn = document.getElementById('ai-reset-btn');
    const exportBtn = document.getElementById('ai-export-btn');
    const exportOutlineBtn = document.getElementById('ai-export-outline-btn');
    const generatePlanBtn = document.getElementById('ai-generate-plan-btn');
    const regeneratePlanBtn = document.getElementById('ai-regenerate-plan-btn');
    const generateOutlineBtn = document.getElementById('ai-generate-outline-btn');
    const regenerateOutlineBtn = document.getElementById('ai-regenerate-outline-btn');
    const stagePlan = document.getElementById('ai-stage-plan');
    const stageOutline = document.getElementById('ai-stage-outline');
    const starterChips = document.querySelectorAll('.ai-starter-chip');
    const stepItems = document.querySelectorAll('.ai-step-item');

    const API_URL = window.AI_API_URL || 'ai-chat-api';

    let isBusy = false;
    let currentStep = window.AI_WORKFLOW_STEP || 'idea';

    function setStatus(text, isError) {
        statusEl.textContent = text || '';
        statusEl.classList.toggle('ai-status-error', !!isError);
    }

    function setProgress(visible, percent, label) {
        if (!progressEl) return;
        progressEl.hidden = !visible;
        if (progressBarEl) {
            progressBarEl.style.width = Math.min(100, Math.max(0, percent)) + '%';
        }
        if (label) {
            setStatus(label, false);
        }
    }

    function setBusy(busy) {
        isBusy = busy;
        const controls = [
            ideaInput,
            planInput,
            outlineInput,
            resetBtn,
            exportBtn,
            exportOutlineBtn,
            generatePlanBtn,
            regeneratePlanBtn,
            generateOutlineBtn,
            regenerateOutlineBtn,
        ];
        controls.forEach(function (el) {
            if (el) el.disabled = busy;
        });
    }

    function updateStepNav(step) {
        currentStep = step;
        const order = ['idea', 'plan', 'outline', 'ready'];
        const stepIndex = Math.max(order.indexOf(step === 'idea' ? 'plan' : step), 0);

        stepItems.forEach(function (item) {
            const itemStep = item.getAttribute('data-step');
            let itemIndex = order.indexOf(itemStep);
            if (itemStep === 'plan') itemIndex = 1;
            if (itemStep === 'outline') itemIndex = 2;
            if (itemStep === 'ready') itemIndex = 3;

            const active =
                (step === 'idea' && itemStep === 'plan') ||
                (step === 'plan' && itemStep === 'plan') ||
                (step === 'outline' && itemStep === 'outline') ||
                (step === 'ready' && itemStep === 'ready');

            const done =
                (itemStep === 'plan' && ['outline', 'ready'].includes(step)) ||
                (itemStep === 'outline' && step === 'ready') ||
                (itemStep === 'ready' && step === 'ready');

            item.classList.toggle('ai-step-active', active);
            item.classList.toggle('ai-step-done', done && !active);
        });
    }

    function showStage(stage) {
        if (stagePlan) {
            stagePlan.classList.toggle('ai-stage-hidden', !['plan', 'outline', 'ready'].includes(stage) || !planInput.value.trim());
        }
        if (stageOutline) {
            stageOutline.classList.toggle('ai-stage-hidden', !['outline', 'ready'].includes(stage) || !outlineInput.value.trim());
        }
    }

    function updateExportButtons() {
        const hasOutline = outlineInput.value.trim().length > 0;
        exportBtn.disabled = isBusy || !hasOutline;
        exportOutlineBtn.disabled = isBusy || !hasOutline;
    }

    async function postJson(payload) {
        const res = await fetch(API_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        const contentType = res.headers.get('content-type') || '';
        const raw = await res.text();

        if (!contentType.includes('application/json')) {
            if (res.redirected || raw.includes('login.php')) {
                throw new Error('Your session expired. Please log in again and retry.');
            }
            throw new Error('Unexpected server response. Try again in a moment.');
        }

        let data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            throw new Error('Could not read server response. Try again.');
        }

        if (!data.ok) {
            throw new Error(data.error || 'Request failed');
        }

        return data;
    }

    function requireConfigured() {
        if (!window.AI_CONFIGURED) {
            setStatus(
                'Add your OpenAI API key in /Applications/XAMPP/xamppfiles/private/sparking-ai.config.php, reload, then try again.',
                true
            );
            return false;
        }
        return true;
    }

    async function generatePlan() {
        if (!requireConfigured() || isBusy) return;

        const idea = ideaInput.value.trim();
        if (!idea) {
            setStatus('Describe your story idea first.', true);
            ideaInput.focus();
            return;
        }

        setBusy(true);
        setStatus('Drafting your plan…');

        try {
            const data = await postJson({ action: 'generate_plan', idea: idea });
            planInput.value = data.plan || '';
            updateStepNav('plan');
            showStage('plan');
            planInput.focus();
            setStatus('Plan ready — edit it if you like, then create the story outline.');
        } catch (err) {
            setStatus(err.message, true);
        } finally {
            setBusy(false);
            updateExportButtons();
        }
    }

    async function generateOutline() {
        if (!requireConfigured() || isBusy) return;

        const idea = ideaInput.value.trim();
        const plan = planInput.value.trim();
        if (!idea) {
            setStatus('Story idea is missing.', true);
            return;
        }
        if (!plan) {
            setStatus('Write or generate a plan first.', true);
            return;
        }

        setBusy(true);
        setStatus('Writing your story outline…');

        try {
            await postJson({ action: 'save_plan', idea: idea, plan: plan });
            const data = await postJson({ action: 'generate_outline', idea: idea, plan: plan });
            outlineInput.value = data.outline || '';
            updateStepNav('outline');
            showStage('outline');
            outlineInput.focus();
            setStatus('Story outline ready — edit it if you like, then create your PDF.');
        } catch (err) {
            setStatus(err.message, true);
        } finally {
            setBusy(false);
            updateExportButtons();
        }
    }

    async function exportPdf() {
        if (!requireConfigured() || isBusy) return;

        const outline = outlineInput.value.trim();
        if (!outline) {
            setStatus('Generate a story outline before creating a PDF.', true);
            return;
        }

        if (
            !confirm(
                'Create a PDF from this outline?\n\nChatGPT will write realistic photo-style pages matching your published storybooks, plus a Science Element page at the end. This takes a few minutes.'
            )
        ) {
            return;
        }

        setBusy(true);
        setProgress(true, 5, 'Step 1/3 — Preparing your story…');

        try {
            const prep = await postJson({
                action: 'export_prepare',
                plan: planInput.value.trim(),
                outline: outline,
            });
            const pageCount = prep.page_count || 0;
            const totalImages = prep.total_images || pageCount + 2;
            let doneImages = 0;

            setProgress(true, 15, 'Step 2/3 — Creating library cover photo…');
            await postJson({ action: 'export_image', kind: 'cover' });
            doneImages += 1;
            setProgress(true, 15 + (doneImages / totalImages) * 70, 'Step 2/3 — Cover photo ready.');

            for (let i = 0; i < pageCount; i += 1) {
                setProgress(
                    true,
                    15 + (doneImages / totalImages) * 70,
                    'Step 2/3 — Creating realistic photo ' + (i + 1) + ' of ' + pageCount + '…'
                );
                await postJson({ action: 'export_image', kind: 'page', page_index: i });
                doneImages += 1;
                setProgress(
                    true,
                    15 + (doneImages / totalImages) * 70,
                    'Step 2/3 — Page photo ' + (i + 1) + ' of ' + pageCount + ' done.'
                );
            }

            setProgress(
                true,
                15 + (doneImages / totalImages) * 70,
                'Step 2/3 — Creating Science Element page photo…'
            );
            await postJson({ action: 'export_image', kind: 'science_element' });
            doneImages += 1;
            setProgress(true, 15 + (doneImages / totalImages) * 70, 'Step 2/3 — Science Element photo ready.');

            setProgress(true, 92, 'Step 3/3 — Building your PDF…');
            const done = await postJson({ action: 'export_finalize' });

            setProgress(true, 100, done.message || 'Done!');
            updateStepNav('ready');
            if (done.redirect) {
                window.location.href = done.redirect;
            }
        } catch (err) {
            setProgress(false, 0, '');
            setStatus(err.message, true);
        } finally {
            setBusy(false);
            updateExportButtons();
        }
    }

    async function resetWorkflow() {
        if (isBusy) return;
        if (!confirm('Start over? This clears your idea, plan, and outline.')) return;

        setBusy(true);
        try {
            await postJson({ action: 'reset' });
            ideaInput.value = '';
            planInput.value = '';
            outlineInput.value = '';
            updateStepNav('idea');
            showStage('idea');
            setProgress(false, 0, '');
            setStatus('');
            ideaInput.focus();
        } catch (err) {
            setStatus(err.message, true);
        } finally {
            setBusy(false);
            updateExportButtons();
        }
    }

    generatePlanBtn.addEventListener('click', generatePlan);
    regeneratePlanBtn.addEventListener('click', generatePlan);
    generateOutlineBtn.addEventListener('click', generateOutline);
    regenerateOutlineBtn.addEventListener('click', generateOutline);
    exportBtn.addEventListener('click', exportPdf);
    exportOutlineBtn.addEventListener('click', exportPdf);
    resetBtn.addEventListener('click', resetWorkflow);

    outlineInput.addEventListener('input', updateExportButtons);

    starterChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            const starter = chip.getAttribute('data-starter') || '';
            ideaInput.value = starter;
            ideaInput.focus();
        });
    });

    updateStepNav(currentStep);
    showStage(currentStep);
    updateExportButtons();
    ideaInput.focus();
})();
