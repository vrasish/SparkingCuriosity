(function () {
    function initMobileNav() {
        var header = document.querySelector('.site-header');
        var toggle = document.querySelector('.nav-toggle');
        var nav = document.getElementById('site-nav');
        if (!header || !toggle || !nav) {
            return;
        }

        function setOpen(open) {
            header.classList.toggle('is-nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }

        toggle.addEventListener('click', function () {
            setOpen(!header.classList.contains('is-nav-open'));
        });

        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 899) {
                setOpen(false);
            }
        });
    }

    var QUIZ_DONE_STORAGE_KEY = 'scifables_quiz_done';

    function getQuizDoneMap() {
        try {
            var raw = window.localStorage.getItem(QUIZ_DONE_STORAGE_KEY);
            var parsed = raw ? JSON.parse(raw) : {};
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (err) {
            return {};
        }
    }

    function applyQuizDoneBadges() {
        var map = getQuizDoneMap();
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

    window.SciFablesQuizDone = {
        applyBadges: applyQuizDoneBadges,
        getMap: getQuizDoneMap,
    };

    function boot() {
        initMobileNav();
        applyQuizDoneBadges();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
