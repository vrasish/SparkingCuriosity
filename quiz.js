(function (global) {
    'use strict';

    var state = {
        bookId: null,
        apiUrl: '',
        container: null,
        mountTarget: null,
        sidebarEl: null,
        ctaEl: null,
        questions: [],
        quizIntro: '',
        currentIndex: 0,
        score: 0,
        answered: false,
        revealed: false,
        promptVisible: false,
    };

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderEmpty(message) {
        if (!state.mountTarget) {
            return;
        }
        state.mountTarget.innerHTML =
            '<section class="story-quiz story-quiz-empty" aria-live="polite">' +
            '<p>' + escapeHtml(message) + '</p>' +
            '</section>';
    }

    function celebrateCorrect() {
        launchConfetti();
        launchBalloons();
    }

    function launchConfetti() {
        var canvas = document.createElement('canvas');
        canvas.className = 'story-quiz-confetti-canvas';
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        document.body.appendChild(canvas);

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            canvas.remove();
            return;
        }

        var colors = ['#7c3aed', '#06b6d4', '#facc15', '#f472b6', '#34d399', '#fb923c'];
        var pieces = [];
        var count = 70;

        for (var i = 0; i < count; i += 1) {
            pieces.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height * 0.4 - canvas.height * 0.2,
                vx: (Math.random() - 0.5) * 4,
                vy: Math.random() * 3 + 2,
                size: Math.random() * 7 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                rot: Math.random() * Math.PI,
                spin: (Math.random() - 0.5) * 0.2,
            });
        }

        var start = window.performance.now();
        function frame(now) {
            var elapsed = now - start;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            pieces.forEach(function (piece) {
                piece.x += piece.vx;
                piece.y += piece.vy;
                piece.vy += 0.08;
                piece.rot += piece.spin;
                ctx.save();
                ctx.translate(piece.x, piece.y);
                ctx.rotate(piece.rot);
                ctx.fillStyle = piece.color;
                ctx.fillRect(-piece.size / 2, -piece.size / 2, piece.size, piece.size * 0.6);
                ctx.restore();
            });

            if (elapsed < 1800) {
                window.requestAnimationFrame(frame);
            } else {
                canvas.remove();
            }
        }

        window.requestAnimationFrame(frame);
    }

    function launchBalloons() {
        var layer = document.createElement('div');
        layer.className = 'story-quiz-balloons';
        layer.setAttribute('aria-hidden', 'true');

        for (var i = 0; i < 7; i += 1) {
            var balloon = document.createElement('span');
            balloon.className = 'story-quiz-balloon';
            balloon.textContent = '🎈';
            balloon.style.left = 8 + Math.random() * 84 + '%';
            balloon.style.animationDelay = i * 0.12 + 's';
            balloon.style.fontSize = 24 + Math.floor(Math.random() * 18) + 'px';
            layer.appendChild(balloon);
        }

        document.body.appendChild(layer);
        window.setTimeout(function () {
            layer.remove();
        }, 4200);
    }

    function renderQuestion() {
        if (!state.mountTarget || !state.questions.length) {
            return;
        }

        var question = state.questions[state.currentIndex];
        var total = state.questions.length;
        var html =
            '<section class="story-quiz" aria-labelledby="story-quiz-title">' +
            '  <div class="story-quiz-header">' +
            '    <h2 id="story-quiz-title">📝 Story Quiz</h2>' +
            (state.quizIntro
                ? '    <p class="story-quiz-intro">' + escapeHtml(state.quizIntro) + '</p>'
                : '') +
            '    <p class="story-quiz-lead">Answer all ' + total + ' fun questions!</p>' +
            '    <div class="story-quiz-progress" aria-hidden="true">' +
            '      <span class="story-quiz-progress-fill" style="width:' +
            Math.round((state.currentIndex / total) * 100) +
            '%"></span>' +
            '    </div>' +
            '    <p class="story-quiz-meta">Question ' +
            (state.currentIndex + 1) +
            ' of ' +
            total +
            '</p>' +
            '  </div>' +
            '  <fieldset class="story-quiz-question">' +
            '    <legend class="story-quiz-prompt">' +
            escapeHtml(question.prompt) +
            '</legend>' +
            '    <div class="story-quiz-choices">';

        question.choices.forEach(function (choice, index) {
            html +=
                '<label class="story-quiz-choice">' +
                '<input type="radio" name="story-quiz-choice" value="' +
                index +
                '">' +
                '<span class="story-quiz-choice-text">' +
                escapeHtml(choice) +
                '</span>' +
                '</label>';
        });

        html +=
            '    </div>' +
            '  </fieldset>' +
            '  <div class="story-quiz-feedback" hidden aria-live="polite"></div>' +
            '  <div class="story-quiz-actions">' +
            '    <button type="button" class="btn btn-primary btn-sm story-quiz-check" disabled>Check Answer</button>' +
            '  </div>' +
            '</section>';

        state.mountTarget.innerHTML = html;
        state.answered = false;

        var checkBtn = $('.story-quiz-check', state.mountTarget);
        var feedbackEl = $('.story-quiz-feedback', state.mountTarget);
        var choices = state.mountTarget.querySelectorAll('.story-quiz-choice');

        choices.forEach(function (label) {
            label.addEventListener('click', function () {
                if (state.answered) {
                    return;
                }
                checkBtn.disabled = !$('input:checked', state.mountTarget);
            });
        });

        checkBtn.addEventListener('click', function () {
            if (state.answered) {
                goNext();
                return;
            }

            var selected = $('input[name="story-quiz-choice"]:checked', state.mountTarget);
            if (!selected) {
                return;
            }

            state.answered = true;
            var choiceIndex = parseInt(selected.value, 10);
            var isCorrect = choiceIndex === question.correct_index;
            if (isCorrect) {
                state.score += 1;
                celebrateCorrect();
            }

            choices.forEach(function (label, index) {
                label.classList.remove('is-selected', 'is-correct', 'is-incorrect');
                var input = $('input', label);
                if (input && input.checked) {
                    label.classList.add('is-selected');
                }
                if (isCorrect && input && input.checked) {
                    label.classList.add('is-correct');
                } else if (!isCorrect && input && input.checked) {
                    label.classList.add('is-incorrect');
                }
                if (input) {
                    input.disabled = true;
                }
                label.style.pointerEvents = 'none';
            });

            feedbackEl.hidden = false;
            feedbackEl.className =
                'story-quiz-feedback ' + (isCorrect ? 'is-correct' : 'is-incorrect');
            feedbackEl.innerHTML = isCorrect
                ? '<strong>Great job!</strong> <span class="story-quiz-tick" aria-hidden="true">✓</span>'
                : '<strong>Nice try!</strong> Keep going.';

            checkBtn.textContent =
                state.currentIndex + 1 >= state.questions.length ? 'See Results' : 'Next Question';
            checkBtn.disabled = false;
        });
    }


    var QUIZ_DONE_STORAGE_KEY = 'scifables_quiz_done';

    function getQuizDoneMap() {
        try {
            var raw = global.localStorage.getItem(QUIZ_DONE_STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : {};
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (err) {
            return {};
        }
    }

    function markQuizDoneLocally(bookId) {
        if (!bookId) {
            return;
        }
        var map = getQuizDoneMap();
        map[String(bookId)] = true;
        try {
            global.localStorage.setItem(QUIZ_DONE_STORAGE_KEY, JSON.stringify(map));
        } catch (err) {
            // ignore quota / private mode
        }
        if (global.SciFablesQuizDone && typeof global.SciFablesQuizDone.applyBadges === 'function') {
            global.SciFablesQuizDone.applyBadges();
        } else {
            applyQuizDoneBadges(map);
        }
    }

    function applyQuizDoneBadges(map) {
        map = map || getQuizDoneMap();
        document.querySelectorAll('.topic-tags[data-book-id], .story-card-bubbles[data-book-id]').forEach(function (el) {
            var id = el.getAttribute('data-book-id');
            if (!id || !map[String(id)] || el.querySelector('.quiz-done-tag')) {
                return;
            }
            var span = document.createElement('span');
            span.className = 'topic-tag quiz-done-tag' + (el.classList.contains('story-card-bubbles-home') ? ' home-story-tag' : '');
            span.title = 'Quiz completed';
            span.textContent = 'Quiz done ✓';
            el.appendChild(span);
        });
    }

    function persistQuizCompletion() {
        if (!state.bookId) {
            return;
        }
        markQuizDoneLocally(state.bookId);

        if (!state.apiUrl) {
            return;
        }

        var payload = {
            book_id: state.bookId,
            score: state.score,
            total: state.questions.length,
        };

        fetch(state.apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).catch(function () {
            // local badge already saved
        });
    }

    function renderResults() {
        if (!state.mountTarget) {
            return;
        }

        if (global.posthog) {
            global.posthog.capture('quiz_completed', {
                story_id: state.bookId,
                score: state.score,
                total: state.questions.length,
                percent: Math.round((state.score / state.questions.length) * 100),
            });
        }

        persistQuizCompletion();

        var total = state.questions.length;
        var percent = Math.round((state.score / total) * 100);
        var message = 'Great job exploring this story!';
        if (percent === 100) {
            message = 'Wow! You got them all right!';
        } else if (percent >= 75) {
            message = 'Awesome! You learned a lot from this story.';
        } else if (percent >= 50) {
            message = 'Good job! Read the story again to learn even more.';
        } else {
            message = 'Keep going! Try the story one more time.';
        }

        state.mountTarget.innerHTML =
            '<section class="story-quiz story-quiz-results" aria-live="polite">' +
            '<div class="story-quiz-header">' +
            '<h2>🎉 Quiz Complete!</h2>' +
            '<p class="story-quiz-score">' +
            state.score +
            ' / ' +
            total +
            ' correct</p>' +
            '<p class="story-quiz-lead">' +
            escapeHtml(message) +
            '</p>' +
            '</div>' +
            '<div class="story-quiz-actions">' +
            '<button type="button" class="btn btn-outline btn-sm story-quiz-retry">Try Again</button>' +
            '</div>' +
            '</section>';

        $('.story-quiz-retry', state.mountTarget).addEventListener('click', function () {
            state.currentIndex = 0;
            state.score = 0;
            renderQuestion();
        });
    }

    function goNext() {
        if (state.currentIndex + 1 >= state.questions.length) {
            renderResults();
            return;
        }
        state.currentIndex += 1;
        renderQuestion();
    }

    function ensureTakeQuizButton() {
        if (state.ctaEl || !state.container) {
            return;
        }

        state.ctaEl = document.createElement('div');
        state.ctaEl.className = 'story-quiz-cta';
        state.ctaEl.hidden = true;
        state.ctaEl.innerHTML =
            '<button type="button" class="btn btn-primary story-quiz-start">Take the Quiz</button>';

        state.ctaEl.querySelector('.story-quiz-start').addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            revealQuiz();
        });

        if (state.sidebarEl) {
            state.sidebarEl.appendChild(state.ctaEl);
            return;
        }

        if (state.container.id === 'pdf-reader') {
            state.sidebarEl = document.getElementById('pdf-quiz-sidebar');
            if (state.sidebarEl) {
                state.sidebarEl.appendChild(state.ctaEl);
            }
        }
    }

    function placeQuizCtaOnPage(pageEl) {
        if (!state.ctaEl || !pageEl || state.sidebarEl) {
            return;
        }

        if (pageEl.classList.contains('pdf-page-frame')) {
            return;
        }

        if (state.ctaEl.parentElement !== pageEl) {
            pageEl.appendChild(state.ctaEl);
        }

        pageEl.classList.add('has-story-quiz-cta');
    }

    function showTakeQuizPrompt() {
        if (state.promptVisible || state.revealed || !state.questions.length) {
            return;
        }
        ensureTakeQuizButton();
        if (!state.ctaEl) {
            return;
        }
        state.promptVisible = true;
        state.ctaEl.hidden = false;
        if (state.sidebarEl) {
            state.sidebarEl.classList.add('has-quiz-cta');
        }
    }

    function revealQuiz() {
        if (state.revealed || !state.mountTarget) {
            return;
        }
        if (global.ReadAloud && typeof global.ReadAloud.stopCurrentAudio === 'function') {
            global.ReadAloud.stopCurrentAudio();
        }
        state.revealed = true;
        if (global.posthog) {
            global.posthog.capture('quiz_started', {
                story_id: state.bookId,
                question_count: state.questions.length,
            });
        }
        state.mountTarget.hidden = false;
        if (state.ctaEl) {
            state.ctaEl.hidden = true;
        }
        if (state.sidebarEl) {
            state.sidebarEl.classList.remove('has-quiz-cta');
        }
        state.mountTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function mountStoryQuiz(container, options) {
        if (!container || !options || !options.apiUrl || !options.bookId) {
            return;
        }

        state.container = container;
        state.apiUrl = options.apiUrl;
        state.bookId = options.bookId;
        state.mountTarget = options.mountEl || null;
        state.sidebarEl = options.sidebarEl || null;

        if (!state.sidebarEl && options.mode === 'pdf') {
            state.sidebarEl = document.getElementById('pdf-quiz-sidebar');
        }

        if (!state.mountTarget) {
            state.mountTarget = document.createElement('div');
            state.mountTarget.className = 'story-quiz-mount';
            state.mountTarget.hidden = true;

            if (options.mode === 'pdf') {
                container.appendChild(state.mountTarget);
            } else {
                var content = container.closest('.book-content') || container.parentElement;
                if (content) {
                    content.appendChild(state.mountTarget);
                }
            }
        }

        ensureTakeQuizButton();

        fetch(
            state.apiUrl +
                (state.apiUrl.indexOf('?') === -1 ? '?' : '&') +
                'id=' +
                encodeURIComponent(String(state.bookId)),
            { credentials: 'same-origin' }
        )
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('not_found');
                }
                return response.json();
            })
            .then(function (data) {
                state.questions = Array.isArray(data.questions) ? data.questions : [];
                state.quizIntro = typeof data.intro === 'string' ? data.intro.trim() : '';
                if (!state.questions.length) {
                    renderEmpty('Quiz coming soon for this story.');
                    return;
                }
                renderQuestion();
                setupRevealTriggers();
            })
            .catch(function () {
                renderEmpty('Quiz coming soon for this story.');
            });
    }

    function setupRevealTriggers() {
        if (!state.container) {
            return;
        }

        if (state.container.id === 'pdf-reader') {
            state.container.addEventListener('pdf-reader:ready', function (event) {
                var numPages = event.detail && event.detail.numPages ? event.detail.numPages : 0;
                var lastFrame = state.container.querySelector(
                    '.pdf-page-frame[data-page-number="' + numPages + '"]'
                );

                if (!lastFrame || typeof IntersectionObserver === 'undefined') {
                    showTakeQuizPrompt();
                    return;
                }

                var observer = new IntersectionObserver(
                    function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                showTakeQuizPrompt();
                                observer.disconnect();
                            }
                        });
                    },
                    { threshold: 0.35 }
                );
                observer.observe(lastFrame);
            });

            state.container.addEventListener('story-quiz:reveal', showTakeQuizPrompt);
            return;
        }

        var lastStoryPage = state.container.querySelector('.story-page:last-of-type');
        if (lastStoryPage) {
            placeQuizCtaOnPage(lastStoryPage);
        }

        if (!lastStoryPage || typeof IntersectionObserver === 'undefined') {
            showTakeQuizPrompt();
            return;
        }

        var textObserver = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        showTakeQuizPrompt();
                        textObserver.disconnect();
                    }
                });
            },
            { threshold: 0.35 }
        );
        textObserver.observe(lastStoryPage);
    }

    function initPdfStoryQuiz(pdfReaderEl) {
        if (!pdfReaderEl || pdfReaderEl.dataset.quizInit === '1') {
            return;
        }
        pdfReaderEl.dataset.quizInit = '1';

        var bookId = parseInt(pdfReaderEl.getAttribute('data-story-id') || '0', 10);
        var apiUrl = pdfReaderEl.getAttribute('data-quiz-api') || '';
        if (!bookId || !apiUrl) {
            return;
        }

        mountStoryQuiz(pdfReaderEl, {
            bookId: bookId,
            apiUrl: apiUrl,
            mode: 'pdf',
            sidebarEl: document.getElementById('pdf-quiz-sidebar'),
        });
    }

    function initTextStoryQuiz(bookContentEl) {
        if (!bookContentEl || bookContentEl.dataset.quizInit === '1') {
            return;
        }
        bookContentEl.dataset.quizInit = '1';

        var bookId = parseInt(bookContentEl.getAttribute('data-book-id') || '0', 10);
        var apiUrl = bookContentEl.getAttribute('data-quiz-api') || '';
        if (!bookId || !apiUrl) {
            return;
        }

        var mountEl = document.createElement('div');
        mountEl.className = 'story-quiz-mount';
        mountEl.hidden = true;
        bookContentEl.appendChild(mountEl);

        mountStoryQuiz(bookContentEl, {
            bookId: bookId,
            apiUrl: apiUrl,
            mode: 'text',
            mountEl: mountEl,
        });
    }

    global.StoryQuiz = {
        mountStoryQuiz: mountStoryQuiz,
        initPdfStoryQuiz: initPdfStoryQuiz,
        initTextStoryQuiz: initTextStoryQuiz,
        revealQuiz: revealQuiz,
        showTakeQuizPrompt: showTakeQuizPrompt,
    };

    document.addEventListener('DOMContentLoaded', function () {
        var pdfReader = document.getElementById('pdf-reader');
        if (pdfReader) {
            initPdfStoryQuiz(pdfReader);
        }

        var textContent = document.querySelector('.book-content-text');
        if (textContent) {
            initTextStoryQuiz(textContent);
        }
    });
})(window);
