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

    function isClassroomMode() {
        return document.body && document.body.classList.contains('classroom-mode');
    }

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
        // Classroom links do not track individual students — never show personal quiz-done badges.
        if (isClassroomMode()) {
            document.querySelectorAll('.quiz-done-tag').forEach(function (el) {
                el.remove();
            });
            return;
        }

        var map = getQuizDoneMap();
        document.querySelectorAll('.topic-tags[data-book-id], .story-card-bubbles[data-book-id]').forEach(function (el) {
            var id = el.getAttribute('data-book-id');
            if (!id || !map[String(id)] || el.querySelector('.quiz-done-tag')) {
                return;
            }
            var span = document.createElement('span');
            span.className = 'topic-tag quiz-done-tag' + (el.classList.contains('story-card-bubbles-home') ? ' home-story-tag' : '');
            span.title = (window.SciFablesT && window.SciFablesT('book.quiz_done_title')) || 'Quiz completed';
            span.textContent = (window.SciFablesT && window.SciFablesT('book.quiz_done')) || 'Quiz done ✓';
            el.appendChild(span);
        });
    }

    window.SciFablesQuizDone = {
        applyBadges: applyQuizDoneBadges,
        getMap: getQuizDoneMap,
    };

    function stripLangSwitchParam() {
        try {
            var url = new URL(window.location.href);
            if (!url.searchParams.has('_sw')) {
                return;
            }
            url.searchParams.delete('_sw');
            var qs = url.searchParams.toString();
            var next = url.pathname + (qs ? '?' + qs : '') + url.hash;
            window.history.replaceState({}, '', next);
        } catch (err) {
            // ignore
        }
    }

    function reloadIfLanguageStale(event) {
        // Back-forward cache can restore /search in the old language after EN/ES.
        if (!event || !event.persisted) {
            return;
        }
        try {
            var cookieMatch = document.cookie.match(/(?:^|; )scifables_lang=([^;]*)/);
            var cookieLang = cookieMatch ? decodeURIComponent(cookieMatch[1]) : 'en';
            var htmlLang = (document.documentElement.lang || 'en').toLowerCase();
            if (cookieLang && htmlLang && cookieLang !== htmlLang) {
                window.location.reload();
            }
        } catch (err) {
            // ignore
        }
    }

    function boot() {
        stripLangSwitchParam();
        initMobileNav();
        applyQuizDoneBadges();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.addEventListener('pageshow', reloadIfLanguageStale);
})();
