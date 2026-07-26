(function (global) {
    'use strict';

    var state = {
        container: null,
        promptEl: null,
        bookId: 0,
        apiUrl: '',
        canSubmit: false,
        loginUrl: '',
        existingRating: 0,
        promptVisible: false,
        submitting: false,
        selectedRating: 0,
    };

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getSidebarEl() {
        return document.getElementById('pdf-quiz-sidebar');
    }

    function ensurePrompt() {
        if (state.promptEl) {
            return;
        }

        state.promptEl = document.createElement('div');
        state.promptEl.className = 'story-rating-prompt';
        state.promptEl.hidden = true;
        state.promptEl.setAttribute('role', 'region');
        state.promptEl.setAttribute('aria-label', 'Rate this story');
        state.promptEl.innerHTML =
            '<div class="story-rating-prompt-card">' +
            '  <h3 class="story-rating-prompt-title">How did you like this story?</h3>' +
            '  <p class="story-rating-prompt-lead">Tap a star to rate it.</p>' +
            '  <div class="story-rating-stars-input" role="radiogroup" aria-label="Story rating"></div>' +
            '  <p class="story-rating-prompt-message" hidden aria-live="polite"></p>' +
            '  <p class="story-rating-prompt-login" hidden><a href="#" class="story-rating-login-link">Log in</a> to rate this story.</p>' +
            '</div>';

        var starsWrap = state.promptEl.querySelector('.story-rating-stars-input');
        for (var i = 1; i <= 5; i += 1) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'story-rating-star-btn';
            btn.setAttribute('data-rating', String(i));
            btn.setAttribute('aria-label', i + ' star' + (i === 1 ? '' : 's'));
            btn.innerHTML = '<span class="story-rating-star-icon" aria-hidden="true">★</span>';
            btn.addEventListener('click', function (event) {
                var rating = parseInt(event.currentTarget.getAttribute('data-rating') || '0', 10);
                if (rating > 0) {
                    submitRating(rating);
                }
            });
            btn.addEventListener('mouseenter', function (event) {
                if (state.submitting) {
                    return;
                }
                var hoverRating = parseInt(event.currentTarget.getAttribute('data-rating') || '0', 10);
                paintStars(hoverRating, true);
            });
            btn.addEventListener('mouseleave', function () {
                if (state.submitting) {
                    return;
                }
                paintStars(state.selectedRating || state.existingRating, false);
            });
            starsWrap.appendChild(btn);
        }

        var loginLink = state.promptEl.querySelector('.story-rating-login-link');
        if (loginLink && state.loginUrl) {
            loginLink.href = state.loginUrl;
        }

        var sidebarEl = getSidebarEl();
        if (sidebarEl && state.container && state.container.id === 'pdf-reader') {
            sidebarEl.appendChild(state.promptEl);
            return;
        }

        if (state.container) {
            state.container.appendChild(state.promptEl);
        }
    }

    function paintStars(rating, isHover) {
        if (!state.promptEl) {
            return;
        }

        var buttons = state.promptEl.querySelectorAll('.story-rating-star-btn');
        buttons.forEach(function (btn) {
            var value = parseInt(btn.getAttribute('data-rating') || '0', 10);
            btn.classList.toggle('is-active', value <= rating);
            btn.classList.toggle('is-hover', isHover && value <= rating);
        });
    }

    function setMessage(text, isError) {
        if (!state.promptEl) {
            return;
        }

        var messageEl = state.promptEl.querySelector('.story-rating-prompt-message');
        if (!messageEl) {
            return;
        }

        if (!text) {
            messageEl.hidden = true;
            messageEl.textContent = '';
            messageEl.classList.remove('is-error');
            return;
        }

        messageEl.hidden = false;
        messageEl.textContent = text;
        messageEl.classList.toggle('is-error', !!isError);
    }

    function showPrompt() {
        if (state.promptVisible || !state.promptEl) {
            return;
        }

        ensurePrompt();
        state.promptVisible = true;
        state.promptEl.hidden = false;

        var sidebarEl = getSidebarEl();
        if (sidebarEl && state.container && state.container.id === 'pdf-reader') {
            sidebarEl.classList.add('has-rating-prompt');
        }

        if (!state.canSubmit) {
            var loginEl = state.promptEl.querySelector('.story-rating-prompt-login');
            var leadEl = state.promptEl.querySelector('.story-rating-prompt-lead');
            var starsEl = state.promptEl.querySelector('.story-rating-stars-input');
            if (loginEl) {
                loginEl.hidden = false;
            }
            if (leadEl) {
                leadEl.textContent = 'Sign in to share your rating.';
            }
            if (starsEl) {
                starsEl.setAttribute('aria-hidden', 'true');
                starsEl.style.pointerEvents = 'none';
                starsEl.style.opacity = '0.45';
            }
            return;
        }

        if (state.existingRating > 0) {
            state.selectedRating = state.existingRating;
            paintStars(state.existingRating, false);
            setMessage('Thanks! Tap a star to update your rating.');
        }
    }

    function updateBookPageRating(summary) {
        if (!summary || !summary.count) {
            return;
        }

        var summaryEl = document.querySelector('.book-rating-summary');
        if (!summaryEl) {
            return;
        }

        var avg = Number(summary.average) || 0;
        var count = Number(summary.count) || 0;
        var rounded = Math.max(1, Math.min(5, Math.round(avg)));
        var starsHtml = '';
        for (var i = 1; i <= 5; i += 1) {
            starsHtml +=
                '<span class="star ' +
                (i <= rounded ? 'star-filled' : 'star-empty') +
                '">★</span>';
        }

        summaryEl.innerHTML =
            '<p class="book-rating-line"><span class="book-rating-stars">' +
            starsHtml +
            '</span><span class="book-rating-text"><strong>' +
            avg.toFixed(1) +
            '</strong> out of 5 · ' +
            count +
            ' review' +
            (count === 1 ? '' : 's') +
            '</span></p>';
    }

    function submitRating(rating) {
        if (!state.canSubmit || state.submitting || rating < 1 || rating > 5) {
            return;
        }

        state.submitting = true;
        state.selectedRating = rating;
        paintStars(rating, false);
        setMessage('Saving your rating…');

        fetch(state.apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                book_id: state.bookId,
                rating: rating,
            }),
        })
            .then(function (response) {
                return response
                    .json()
                    .catch(function () {
                        return { ok: false, error: 'Could not save your rating.' };
                    })
                    .then(function (data) {
                        return { response: response, data: data };
                    });
            })
            .then(function (result) {
                var data = result.data || {};
                if (!result.response.ok || !data.ok) {
                    if (result.response.status === 401 && data.login_url) {
                        window.location.href = data.login_url;
                        return;
                    }
                    throw new Error(data.error || 'Could not save your rating.');
                }

                state.existingRating = rating;
                setMessage(data.message || 'Thank you for rating this story!');
                updateBookPageRating(data.summary);

                if (global.posthog) {
                    global.posthog.capture('story_rated', {
                        story_id: state.bookId,
                        rating: rating,
                        source: 'last_page_prompt',
                    });
                }
            })
            .catch(function (error) {
                setMessage(error.message || 'Could not save your rating.', true);
            })
            .finally(function () {
                state.submitting = false;
            });
    }

    function placePromptOnPage(pageEl) {
        if (!state.promptEl || !pageEl || state.container.id === 'pdf-reader') {
            return;
        }

        if (state.promptEl.parentElement !== pageEl) {
            pageEl.appendChild(state.promptEl);
        }

        pageEl.classList.add('has-story-rating-prompt');
    }

    function observeLastPage(lastEl) {
        if (!lastEl || typeof IntersectionObserver === 'undefined') {
            showPrompt();
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        showPrompt();
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.35 }
        );
        observer.observe(lastEl);
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
                observeLastPage(lastFrame);
            });
            return;
        }

        var lastStoryPage = state.container.querySelector('.story-page:last-of-type');
        if (lastStoryPage) {
            placePromptOnPage(lastStoryPage);
        }
        observeLastPage(lastStoryPage);
    }

    function initStoryRating(container) {
        if (!container || container.dataset.ratingInit === '1') {
            return;
        }

        if (container.getAttribute('data-story-rating') !== '1') {
            return;
        }

        container.dataset.ratingInit = '1';
        state.container = container;
        state.bookId = parseInt(container.getAttribute('data-book-id') || '0', 10);
        state.apiUrl = container.getAttribute('data-rating-api') || '';
        state.canSubmit = container.getAttribute('data-can-submit') === '1';
        state.loginUrl = container.getAttribute('data-login-url') || '';
        state.existingRating = parseInt(container.getAttribute('data-existing-rating') || '0', 10);

        if (!state.bookId || !state.apiUrl) {
            return;
        }

        ensurePrompt();
        setupRevealTriggers();
    }

    global.StoryRating = {
        initStoryRating: initStoryRating,
    };

    document.addEventListener('DOMContentLoaded', function () {
        var pdfReader = document.getElementById('pdf-reader');
        if (pdfReader) {
            initStoryRating(pdfReader);
        }

        var textContent = document.querySelector('.book-content-text[data-story-rating="1"]');
        if (textContent) {
            initStoryRating(textContent);
        }
    });
})(window);
