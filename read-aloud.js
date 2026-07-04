/**
 * Whole-story read-aloud for PDF viewer.
 * Uses uploaded MP3s when available, otherwise OpenAI neural TTS; falls back to browser speech.
 */
(function (global) {
    'use strict';

    var synth = global.speechSynthesis;
    var MAIN_PAGE = 1;
    var PLAYBACK_SPEEDS = [0.75, 1, 1.25, 1.5, 2];
    var PLAYBACK_SPEED_STORAGE_KEY = 'readAloudPlaybackSpeed';

    var state = {
        pageNumber: null,
        status: 'idle',
        utterance: null,
        pageText: {},
        storyMode: false,
        playBtn: null,
        stopBtn: null,
        storyId: null,
        useNeuralAudio: false,
        ttsApiUrl: '',
        ttsSpeed: 0.9,
        playbackSpeedIndex: 1,
        speedBtn: null,
        audioElement: null,
        audioPreload: {},
        playerEl: null,
        seekInput: null,
        timeEl: null,
        pageLabelEl: null,
        pageDurations: {},
        audioPages: null,
        isSeeking: false,
        progressTimer: null,
        browserPageProgress: 0,
        pdfReaderEl: null,
    };

    var PREFERRED_FEMALE_VOICES = [
        'samantha',
        'karen',
        'moira',
        'tessa',
        'fiona',
        'victoria',
        'allison',
        'serena',
        'susan',
        'aria online',
        'microsoft aria',
        'jenny online',
        'microsoft jenny',
        'google us english female',
        'google uk english female',
        'hazel',
        'zira',
        'female',
    ];

    var MALE_VOICE_MARKERS = [
        'male',
        ' man',
        'david',
        'daniel',
        'james',
        'mark',
        'george',
        'alex',
        'fred',
        'bruce',
        'lee',
        'tom',
        'ralph',
    ];

    function formatTime(seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        var total = Math.floor(seconds);
        var mins = Math.floor(total / 60);
        var secs = total % 60;
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function getPlaybackSpeed() {
        return PLAYBACK_SPEEDS[state.playbackSpeedIndex] || 1;
    }

    function formatPlaybackSpeedLabel(speed) {
        if (speed === 1) {
            return '1x';
        }
        return String(speed) + 'x';
    }

    function initPlaybackSpeed() {
        var saved = null;
        try {
            saved = parseFloat(localStorage.getItem(PLAYBACK_SPEED_STORAGE_KEY), 10);
        } catch (err) {
            saved = null;
        }

        if (Number.isFinite(saved)) {
            var exactIndex = PLAYBACK_SPEEDS.indexOf(saved);
            if (exactIndex !== -1) {
                state.playbackSpeedIndex = exactIndex;
                return;
            }
            for (var i = 0; i < PLAYBACK_SPEEDS.length; i += 1) {
                if (PLAYBACK_SPEEDS[i] >= saved) {
                    state.playbackSpeedIndex = i;
                    return;
                }
            }
            state.playbackSpeedIndex = PLAYBACK_SPEEDS.length - 1;
        }
    }

    function updateSpeedButton() {
        if (!state.speedBtn) {
            return;
        }
        var speed = getPlaybackSpeed();
        var label = formatPlaybackSpeedLabel(speed);
        state.speedBtn.textContent = label;
        state.speedBtn.setAttribute('aria-label', 'Playback speed ' + label + '. Click to change.');
        state.speedBtn.title = 'Playback speed: ' + label;
    }

    function applyPlaybackSpeedToActivePlayback() {
        var speed = getPlaybackSpeed();
        if (state.audioElement) {
            state.audioElement.playbackRate = speed;
            return;
        }

        if (!state.useNeuralAudio && state.storyMode && state.pageNumber && state.status === 'playing') {
            var pageNumber = state.pageNumber;
            stopBrowserSpeech();
            state.storyMode = true;
            speakStoryFromBrowser(pageNumber);
        }
    }

    function cyclePlaybackSpeed() {
        state.playbackSpeedIndex = (state.playbackSpeedIndex + 1) % PLAYBACK_SPEEDS.length;
        try {
            localStorage.setItem(PLAYBACK_SPEED_STORAGE_KEY, String(getPlaybackSpeed()));
        } catch (err) {
            /* ignore storage failures */
        }
        updateSpeedButton();
        applyPlaybackSpeedToActivePlayback();
    }

    function getTimelinePages() {
        if (state.audioPages && state.audioPages.length) {
            return state.audioPages.slice();
        }
        return getSortedPagesWithText();
    }

    function estimatePageDuration(pageNumber) {
        var text = getPageText(pageNumber);
        var words = text ? text.split(/\s+/).filter(Boolean).length : 0;
        var charsPerSecond = 11 * state.ttsSpeed;
        return Math.max(2, words > 0 ? (text.length / charsPerSecond) : 2);
    }

    function getPageDuration(pageNumber) {
        var known = state.pageDurations[pageNumber];
        if (typeof known === 'number' && known > 0 && Number.isFinite(known)) {
            return known;
        }
        return estimatePageDuration(pageNumber);
    }

    function getTotalDuration() {
        return getTimelinePages().reduce(function (sum, pageNumber) {
            return sum + getPageDuration(pageNumber);
        }, 0);
    }

    function getGlobalPlaybackTime() {
        var pages = getTimelinePages();
        if (!pages.length) {
            return 0;
        }

        if (!state.pageNumber) {
            return 0;
        }

        var elapsed = 0;
        for (var i = 0; i < pages.length; i += 1) {
            var pageNumber = pages[i];
            if (pageNumber < state.pageNumber) {
                elapsed += getPageDuration(pageNumber);
                continue;
            }
            if (pageNumber === state.pageNumber) {
                if (state.useNeuralAudio && state.audioElement && Number.isFinite(state.audioElement.currentTime)) {
                    return elapsed + state.audioElement.currentTime;
                }
                if (!state.useNeuralAudio && state.status === 'playing') {
                    return elapsed + getPageDuration(pageNumber) * state.browserPageProgress;
                }
                return elapsed;
            }
        }

        return getTotalDuration();
    }

    function getPagePositionLabel(pageNumber) {
        var pages = getTimelinePages();
        var index = pages.indexOf(pageNumber);
        if (index === -1) {
            return '';
        }
        return 'Page ' + (index + 1) + ' of ' + pages.length;
    }

    function updateSeekBar() {
        if (!state.seekInput || state.isSeeking) {
            return;
        }

        var total = getTotalDuration();
        var current = getGlobalPlaybackTime();
        var ratio = total > 0 ? current / total : 0;
        state.seekInput.value = String(Math.round(ratio * 1000));

        if (state.timeEl) {
            state.timeEl.textContent = formatTime(current) + ' / ' + formatTime(total);
        }

        if (state.pageLabelEl) {
            if (state.pageNumber) {
                state.pageLabelEl.textContent = getPagePositionLabel(state.pageNumber);
            } else {
                var pages = getTimelinePages();
                if (pages.length) {
                    state.pageLabelEl.textContent = 'Page 1 of ' + pages.length;
                }
            }
        }
    }

    function startProgressTimer() {
        stopProgressTimer();
        state.progressTimer = window.setInterval(updateSeekBar, 250);
    }

    function stopProgressTimer() {
        if (state.progressTimer) {
            window.clearInterval(state.progressTimer);
            state.progressTimer = null;
        }
    }

    function setPlayerVisible(visible) {
        if (state.playerEl) {
            state.playerEl.hidden = !visible;
        }
    }

    function preloadPageDurations() {
        if (!state.useNeuralAudio) {
            return;
        }

        getTimelinePages().forEach(function (pageNumber) {
            if (state.pageDurations[pageNumber]) {
                return;
            }
            var audio = new Audio(buildTtsUrl(pageNumber));
            audio.preload = 'metadata';
            audio.addEventListener(
                'loadedmetadata',
                function () {
                    if (audio.duration && Number.isFinite(audio.duration)) {
                        state.pageDurations[pageNumber] = audio.duration;
                        updateSeekBar();
                    }
                },
                { once: true }
            );
        });
    }

    function seekToGlobalTime(targetTime) {
        var pages = getTimelinePages();
        if (!pages.length) {
            return;
        }

        var total = getTotalDuration();
        targetTime = Math.max(0, Math.min(total, targetTime));

        var accumulated = 0;
        for (var i = 0; i < pages.length; i += 1) {
            var pageNumber = pages[i];
            var duration = getPageDuration(pageNumber);
            var nextAccumulated = accumulated + duration;

            if (targetTime <= nextAccumulated || i === pages.length - 1) {
                var offset = Math.max(0, targetTime - accumulated);
                if (duration > 0) {
                    offset = Math.min(offset, Math.max(0, duration - 0.05));
                }
                seekToPagePosition(pageNumber, offset);
                return;
            }

            accumulated = nextAccumulated;
        }
    }

    function seekToPagePosition(pageNumber, offsetInPage) {
        if (!getPageText(pageNumber)) {
            return;
        }

        if (!state.storyMode) {
            state.storyMode = true;
            setStopVisible(true);
            setPlayerVisible(true);
        }

        highlightPage(pageNumber);
        scrollToPage(pageNumber);
        state.pageNumber = pageNumber;
        state.browserPageProgress = 0;
        setButtonState('loading');

        if (state.useNeuralAudio) {
            playNeuralPage(pageNumber, offsetInPage || 0);
            return;
        }

        stopBrowserSpeech();
        speakStoryFromBrowser(pageNumber);
    }

    function handleSeekInput() {
        if (!state.seekInput) {
            return;
        }
        var total = getTotalDuration();
        var ratio = parseInt(state.seekInput.value, 10) / 1000;
        if (state.timeEl) {
            state.timeEl.textContent = formatTime(total * ratio) + ' / ' + formatTime(total);
        }
    }

    function handleSeekCommit() {
        if (!state.seekInput) {
            return;
        }
        state.isSeeking = false;
        var total = getTotalDuration();
        var ratio = parseInt(state.seekInput.value, 10) / 1000;
        seekToGlobalTime(total * ratio);
    }

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $all(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function setButtonState(status) {
        var btn = state.playBtn;
        if (!btn) {
            return;
        }

        btn.classList.remove('is-idle', 'is-loading', 'is-playing', 'is-paused', 'is-error', 'is-unavailable');
        btn.classList.add('is-' + status);
        btn.dataset.status = status;

        var label = 'Listen to the whole story';
        var icon = '🔊';
        var text = 'Listen';

        if (status === 'loading') {
            icon = '⏳';
            text = 'Loading';
            label = 'Preparing story audio';
        } else if (status === 'playing') {
            icon = '⏸';
            text = 'Pause';
            label = 'Pause story';
        } else if (status === 'paused') {
            icon = '▶';
            text = 'Resume';
            label = 'Resume story';
        } else if (status === 'unavailable') {
            icon = '🔇';
            text = 'No audio';
            label = 'Read-aloud is not available for this story yet.';
        } else if (status === 'error') {
            icon = '⚠';
            text = 'Retry';
            label = 'Could not read the story. Tap to try again.';
        }

        btn.setAttribute('aria-label', label);
        btn.querySelector('.read-aloud-btn-icon').textContent = icon;
        btn.querySelector('.read-aloud-btn-label').textContent = text;
    }

    function setStopVisible(visible) {
        if (state.stopBtn) {
            state.stopBtn.hidden = !visible;
        }
    }

    function clearHighlights() {
        $all('.pdf-page-frame.is-reading-aloud').forEach(function (frame) {
            frame.classList.remove('is-reading-aloud');
        });
    }

    function resolveAudioPage(pdfPageNumber) {
        var pages = getSortedPagesWithText();
        if (!pages.length) {
            return null;
        }
        if (getPageText(pdfPageNumber)) {
            return pdfPageNumber;
        }

        var best = pages[0];
        var bestDistance = Math.abs(pages[0] - pdfPageNumber);
        for (var i = 1; i < pages.length; i += 1) {
            var distance = Math.abs(pages[i] - pdfPageNumber);
            if (distance < bestDistance) {
                bestDistance = distance;
                best = pages[i];
            }
        }
        return best;
    }

    function startStoryFromPage(pageNumber, offsetInPage) {
        if (!hasStoryAudio()) {
            return;
        }

        var audioPage = resolveAudioPage(pageNumber);
        if (!audioPage || !getPageText(audioPage)) {
            return;
        }

        stopNeuralAudio();
        stopBrowserSpeech();
        state.status = 'idle';
        state.pageNumber = null;
        state.browserPageProgress = 0;
        stopProgressTimer();

        state.storyMode = true;
        setStopVisible(true);
        setPlayerVisible(true);
        seekToPagePosition(audioPage, offsetInPage || 0);
    }

    function setupPageClickListen() {
        if (!state.pdfReaderEl || state.pdfReaderEl.dataset.pageListenBound === '1') {
            return;
        }
        state.pdfReaderEl.dataset.pageListenBound = '1';

        state.pdfReaderEl.addEventListener('click', function (event) {
            if (!hasStoryAudio()) {
                return;
            }

            if (event.target.closest('.story-quiz-cta, .story-quiz-start, .story-quiz, .story-quiz-mount')) {
                return;
            }

            var frame = event.target.closest('.pdf-page-frame');
            if (!frame) {
                return;
            }

            var pdfPageNumber = parseInt(frame.getAttribute('data-page-number'), 10);
            if (Number.isNaN(pdfPageNumber)) {
                return;
            }

            var audioPage = resolveAudioPage(pdfPageNumber);
            if (!audioPage) {
                return;
            }

            startStoryFromPage(audioPage, 0);
        });
    }

    function highlightPage(pageNumber) {
        clearHighlights();
        var frame = $('.pdf-page-frame[data-page-number="' + pageNumber + '"]');
        if (frame) {
            frame.classList.add('is-reading-aloud');
        }
    }

    function scrollToPage(pageNumber) {
        var frame = $('.pdf-page-frame[data-page-number="' + pageNumber + '"]');
        if (!frame) {
            return;
        }
        frame.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest',
        });
    }

    function buildTtsUrl(pageNumber) {
        return (
            state.ttsApiUrl +
            (state.ttsApiUrl.indexOf('?') === -1 ? '?' : '&') +
            'id=' +
            encodeURIComponent(state.storyId) +
            '&page=' +
            encodeURIComponent(pageNumber)
        );
    }

    function stopNeuralAudio() {
        if (state.audioElement) {
            state.audioElement.pause();
            state.audioElement.removeAttribute('src');
            state.audioElement.load();
            state.audioElement = null;
        }
        state.audioPreload = {};
    }

    function stopBrowserSpeech() {
        if (synth && (synth.speaking || synth.pending || synth.paused)) {
            synth.cancel();
        }
        state.utterance = null;
    }

    function isMaleVoiceName(name) {
        var lowered = (' ' + (name || '').toLowerCase() + ' ');
        return MALE_VOICE_MARKERS.some(function (marker) {
            return lowered.indexOf(marker) !== -1;
        });
    }

    function getEnglishVoices(voices) {
        return voices.filter(function (voice) {
            return (voice.lang || '').toLowerCase().indexOf('en') === 0;
        });
    }

    function findVoiceByHints(voices, hints) {
        var lowered = voices.map(function (voice) {
            return {
                voice: voice,
                name: (voice.name || '').toLowerCase(),
            };
        });

        for (var i = 0; i < hints.length; i += 1) {
            var hint = hints[i];
            var match = lowered.find(function (entry) {
                return entry.name.indexOf(hint) !== -1 && !isMaleVoiceName(entry.name);
            });
            if (match) {
                return match.voice;
            }
        }

        return null;
    }

    function pickVoice() {
        if (!synth) {
            return null;
        }

        var voices = synth.getVoices();
        if (!voices.length) {
            return null;
        }

        var englishVoices = getEnglishVoices(voices);
        var pool = englishVoices.length ? englishVoices : voices;
        var preferred = findVoiceByHints(pool, PREFERRED_FEMALE_VOICES);
        if (preferred) {
            return preferred;
        }

        var femaleFallback = pool.find(function (voice) {
            var name = (voice.name || '').toLowerCase();
            return name.indexOf('female') !== -1 && !isMaleVoiceName(name);
        });
        if (femaleFallback) {
            return femaleFallback;
        }

        var naturalFallback = pool.find(function (voice) {
            return !isMaleVoiceName(voice.name);
        });
        return naturalFallback || pool[0];
    }

    function applyNarratorVoice(utterance, voice) {
        if (voice) {
            utterance.voice = voice;
            utterance.lang = voice.lang || 'en-US';
        } else {
            utterance.lang = 'en-US';
        }
        utterance.rate = Math.max(0.5, Math.min(2, state.ttsSpeed * 0.95 * getPlaybackSpeed()));
        utterance.pitch = 1.0;
    }

    function ensureVoicesReady() {
        return new Promise(function (resolve) {
            if (!synth) {
                resolve([]);
                return;
            }

            var voices = synth.getVoices();
            if (voices.length) {
                resolve(voices);
                return;
            }

            var resolved = false;
            function done() {
                if (resolved) {
                    return;
                }
                resolved = true;
                synth.removeEventListener('voiceschanged', done);
                resolve(synth.getVoices());
            }

            synth.addEventListener('voiceschanged', done);
            window.setTimeout(done, 400);
        });
    }

    function getPageText(pageNumber) {
        var text = state.pageText[pageNumber];
        return typeof text === 'string' ? text.trim() : '';
    }

    function getSortedPagesWithText() {
        return Object.keys(state.pageText)
            .map(function (key) {
                return parseInt(key, 10);
            })
            .filter(function (pageNumber) {
                return !Number.isNaN(pageNumber) && getPageText(pageNumber) !== '';
            })
            .sort(function (a, b) {
                return a - b;
            });
    }

    function hasStoryAudio() {
        return getSortedPagesWithText().length > 0;
    }

    function getNextPageWithText(afterPage) {
        var pages = getTimelinePages();
        for (var i = 0; i < pages.length; i += 1) {
            if (pages[i] > afterPage) {
                return pages[i];
            }
        }
        return null;
    }

    function handleNeuralPageFailure(pageNumber) {
        if (!state.storyMode) {
            return;
        }
        var nextPage = getNextPageWithText(pageNumber);
        if (nextPage) {
            speakStoryFrom(nextPage);
            return;
        }
        finishStory();
    }

    function finishStory() {
        var total = getTotalDuration();
        state.storyMode = false;
        state.status = 'idle';
        state.pageNumber = null;
        state.utterance = null;
        state.browserPageProgress = 0;
        stopNeuralAudio();
        stopProgressTimer();
        setButtonState('idle');
        setStopVisible(false);
        clearHighlights();
        if (state.seekInput) {
            state.seekInput.value = '1000';
        }
        if (state.timeEl && total > 0) {
            state.timeEl.textContent = formatTime(total) + ' / ' + formatTime(total);
        }
        if (state.pageLabelEl) {
            var pages = getTimelinePages();
            if (pages.length) {
                state.pageLabelEl.textContent = 'Page ' + pages.length + ' of ' + pages.length;
            }
        }
        if (state.pdfReaderEl) {
            state.pdfReaderEl.dispatchEvent(new CustomEvent('story-quiz:reveal', { bubbles: true }));
        }
        updateSeekBar();
    }

    function stopCurrentAudio() {
        state.storyMode = false;
        stopNeuralAudio();
        stopBrowserSpeech();
        state.status = 'idle';
        state.pageNumber = null;
        state.browserPageProgress = 0;
        stopProgressTimer();
        setButtonState('idle');
        setStopVisible(false);
        clearHighlights();
        updateSeekBar();
    }

    function preloadNeuralPage(pageNumber) {
        if (!pageNumber || state.audioPreload[pageNumber]) {
            return;
        }
        var audio = new Audio(buildTtsUrl(pageNumber));
        audio.preload = 'auto';
        state.audioPreload[pageNumber] = audio;
    }

    function advanceStory(pageNumber) {
        if (!state.storyMode || state.status !== 'playing') {
            return;
        }

        var nextPage = getNextPageWithText(pageNumber);
        if (nextPage) {
            speakStoryFrom(nextPage);
        } else {
            finishStory();
        }
    }

    function playNeuralPage(pageNumber, startOffset) {
        startOffset = startOffset || 0;
        stopNeuralAudio();

        var audio = new Audio(buildTtsUrl(pageNumber));
        state.audioElement = audio;
        state.pageNumber = pageNumber;

        var started = false;

        function beginPlayback() {
            if (started || !state.storyMode) {
                return;
            }
            started = true;
            if (audio.duration && Number.isFinite(audio.duration)) {
                state.pageDurations[pageNumber] = audio.duration;
            }
            if (startOffset > 0 && audio.duration && Number.isFinite(audio.duration)) {
                audio.currentTime = Math.min(startOffset, Math.max(0, audio.duration - 0.05));
            }
            audio.playbackRate = getPlaybackSpeed();
            state.status = 'playing';
            setButtonState('playing');
            setStopVisible(true);
            startProgressTimer();
            updateSeekBar();
            preloadNeuralPage(getNextPageWithText(pageNumber));

            var playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {
                    if (state.storyMode && state.pageNumber === pageNumber) {
                        handleNeuralPageFailure(pageNumber);
                    }
                });
            }
        }

        audio.addEventListener('timeupdate', function () {
            if (!state.isSeeking) {
                updateSeekBar();
            }
        });

        audio.addEventListener('loadedmetadata', beginPlayback, { once: true });
        if (audio.readyState >= 1) {
            beginPlayback();
        }

        audio.addEventListener('ended', function () {
            if (!state.storyMode || state.pageNumber !== pageNumber) {
                return;
            }
            state.pageDurations[pageNumber] = audio.duration || getPageDuration(pageNumber);
            window.setTimeout(function () {
                if (!state.storyMode || state.pageNumber !== pageNumber || state.status !== 'playing') {
                    return;
                }
                advanceStory(pageNumber);
            }, 400);
        });

        audio.addEventListener('error', function () {
            if (!state.storyMode || state.pageNumber !== pageNumber) {
                return;
            }
            handleNeuralPageFailure(pageNumber);
        });
    }

    function speakBrowserChunks(chunks, chunkIndex, pageNumber) {
        if (!state.storyMode || chunkIndex >= chunks.length) {
            if (state.storyMode && chunkIndex >= chunks.length && state.status === 'playing' && state.pageNumber === pageNumber) {
                state.browserPageProgress = 1;
                updateSeekBar();
                advanceStory(pageNumber);
            }
            return;
        }

        var spokenText = chunks[chunkIndex].trim();
        if (!spokenText) {
            speakBrowserChunks(chunks, chunkIndex + 1, pageNumber);
            return;
        }

        state.browserPageProgress = chunks.length > 1 ? chunkIndex / chunks.length : 0;
        updateSeekBar();

        var utterance = new SpeechSynthesisUtterance(spokenText);
        applyNarratorVoice(utterance, pickVoice());

        utterance.onstart = function () {
            if (!state.storyMode) {
                return;
            }
            state.pageNumber = pageNumber;
            state.status = 'playing';
            state.utterance = utterance;
            setButtonState('playing');
            setStopVisible(true);
            startProgressTimer();
        };

        utterance.onpause = function () {
            state.status = 'paused';
            setButtonState('paused');
        };

        utterance.onresume = function () {
            state.status = 'playing';
            setButtonState('playing');
        };

        utterance.onend = function () {
            if (!state.storyMode || state.status !== 'playing' || state.pageNumber !== pageNumber) {
                return;
            }
            if (chunkIndex + 1 < chunks.length) {
                state.browserPageProgress = (chunkIndex + 1) / chunks.length;
                updateSeekBar();
                window.setTimeout(function () {
                    if (state.storyMode && state.status === 'playing' && state.pageNumber === pageNumber) {
                        speakBrowserChunks(chunks, chunkIndex + 1, pageNumber);
                    }
                }, 500);
                return;
            }
            advanceStory(pageNumber);
        };

        utterance.onerror = function () {
            if (!state.storyMode) {
                return;
            }
            state.status = 'error';
            setButtonState('error');
            setStopVisible(true);
        };

        state.utterance = utterance;
        synth.speak(utterance);
    }

    function speakStoryFromBrowser(pageNumber) {
        var spokenText = getPageText(pageNumber);
        var chunks = spokenText.split(/\n\n+/).filter(function (part) {
            return part.trim() !== '';
        });
        if (!chunks.length) {
            chunks = [spokenText];
        }

        ensureVoicesReady()
            .then(function () {
                if (!state.storyMode) {
                    return;
                }
                speakBrowserChunks(chunks, 0, pageNumber);
            })
            .catch(function () {
                if (state.storyMode) {
                    state.status = 'error';
                    setButtonState('error');
                }
            });
    }

    function speakStoryFrom(pageNumber) {
        if (!state.storyMode) {
            return;
        }

        var spokenText = getPageText(pageNumber);
        if (!spokenText) {
            var skipTo = getNextPageWithText(pageNumber - 1);
            if (skipTo) {
                speakStoryFrom(skipTo);
            } else {
                finishStory();
            }
            return;
        }

        highlightPage(pageNumber);
        scrollToPage(pageNumber);
        setButtonState('loading');
        state.pageNumber = pageNumber;

        if (state.useNeuralAudio) {
            playNeuralPage(pageNumber);
            return;
        }

        speakStoryFromBrowser(pageNumber);
    }

    function toggleStoryPlayback() {
        if (!hasStoryAudio()) {
            setButtonState('unavailable');
            return;
        }

        if (state.status === 'playing') {
            if (state.useNeuralAudio && state.audioElement) {
                state.audioElement.pause();
            } else if (synth) {
                synth.pause();
            }
            state.status = 'paused';
            setButtonState('paused');
            stopProgressTimer();
            return;
        }

        if (state.status === 'paused') {
            if (state.useNeuralAudio && state.audioElement) {
                state.audioElement.playbackRate = getPlaybackSpeed();
                state.audioElement.play();
            } else if (synth) {
                synth.resume();
            }
            state.status = 'playing';
            setButtonState('playing');
            startProgressTimer();
            return;
        }

        startStoryFromBeginning();
    }

    function startStoryFromBeginning() {
        stopCurrentAudio();
        state.storyMode = true;
        state.status = 'loading';
        setButtonState('loading');
        setStopVisible(true);
        setPlayerVisible(true);

        var firstPage = getTimelinePages()[0];
        if (!firstPage) {
            state.storyMode = false;
            setButtonState('unavailable');
            setStopVisible(false);
            return;
        }

        speakStoryFrom(firstPage);
    }

    function renderStoryPlayer(viewerWrap) {
        if (!viewerWrap || viewerWrap.querySelector('.read-aloud-player')) {
            return;
        }

        var player = document.createElement('div');
        player.className = 'read-aloud-player';
        player.innerHTML =
            '<div class="read-aloud-player-main">' +
            '  <button type="button" class="read-aloud-btn read-aloud-btn-play is-idle" aria-label="Listen to the whole story">' +
            '    <span class="read-aloud-btn-icon" aria-hidden="true">🔊</span>' +
            '    <span class="read-aloud-btn-label">Listen</span>' +
            '  </button>' +
            '  <button type="button" class="read-aloud-btn read-aloud-btn-speed" aria-label="Playback speed 1x. Click to change." title="Playback speed: 1x">1x</button>' +
            '  <div class="read-aloud-player-track">' +
            '    <input type="range" class="read-aloud-seek" min="0" max="1000" value="0" step="1" aria-label="Move through the story">' +
            '    <div class="read-aloud-player-meta">' +
            '      <span class="read-aloud-player-page">Page 1</span>' +
            '      <span class="read-aloud-player-time">0:00 / 0:00</span>' +
            '    </div>' +
            '  </div>' +
            '  <button type="button" class="read-aloud-btn read-aloud-btn-stop" hidden aria-label="Stop reading the story">' +
            '    <span class="read-aloud-btn-icon" aria-hidden="true">⏹</span>' +
            '    <span class="read-aloud-btn-label">Stop</span>' +
            '  </button>' +
            '</div>';

        viewerWrap.appendChild(player);

        state.playerEl = player;
        state.playBtn = player.querySelector('.read-aloud-btn-play');
        state.speedBtn = player.querySelector('.read-aloud-btn-speed');
        state.stopBtn = player.querySelector('.read-aloud-btn-stop');
        state.seekInput = player.querySelector('.read-aloud-seek');
        state.timeEl = player.querySelector('.read-aloud-player-time');
        state.pageLabelEl = player.querySelector('.read-aloud-player-page');

        state.playBtn.addEventListener('click', toggleStoryPlayback);
        state.stopBtn.addEventListener('click', stopCurrentAudio);
        if (state.speedBtn) {
            state.speedBtn.addEventListener('click', cyclePlaybackSpeed);
        }
        initPlaybackSpeed();
        updateSpeedButton();

        state.seekInput.addEventListener('pointerdown', function () {
            state.isSeeking = true;
        });
        state.seekInput.addEventListener('input', handleSeekInput);
        state.seekInput.addEventListener('change', handleSeekCommit);

        if (!hasStoryAudio()) {
            setButtonState('unavailable');
            state.playBtn.disabled = true;
            if (state.speedBtn) {
                state.speedBtn.disabled = true;
            }
            state.seekInput.disabled = true;
            state.playBtn.title = 'No read-aloud text for this story yet.';
            player.classList.add('is-unavailable');
        } else {
            setPlayerVisible(true);
            if (state.useNeuralAudio) {
                state.playBtn.title = 'Natural voice narration for the whole story';
            }
            if (state.pdfReaderEl) {
                state.pdfReaderEl.classList.add('read-aloud-pages-clickable');
            }
            updateSeekBar();
        }
    }

    function loadStoryPageAudio(storyId, apiUrl) {
        var url = apiUrl + (apiUrl.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(storyId);
        return fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('not_found');
                }
                return response.json();
            })
            .then(function (data) {
                var map = {};
                (data.pages || []).forEach(function (entry) {
                    var pageNum = parseInt(entry.page, 10);
                    if (!Number.isNaN(pageNum)) {
                        var spoken = entry.tts_text || entry.text;
                        if (spoken) {
                            map[pageNum] = String(spoken);
                        }
                    }
                });
                state.pageText = map;
                state.storyId = data.book_id || storyId;
                state.useNeuralAudio = !!data.neural_audio && !!data.tts_api;
                state.ttsApiUrl = data.tts_api || '';
                state.ttsSpeed = typeof data.tts_speed === 'number' ? data.tts_speed : 0.9;
                if (Array.isArray(data.audio_pages) && data.audio_pages.length) {
                    state.audioPages = data.audio_pages
                        .map(function (pageNum) {
                            return parseInt(pageNum, 10);
                        })
                        .filter(function (pageNum) {
                            return !Number.isNaN(pageNum) && getPageText(pageNum) !== '';
                        })
                        .sort(function (a, b) {
                            return a - b;
                        });
                } else {
                    state.audioPages = null;
                }
                preloadPageDurations();
                updateSeekBar();
                return data;
            });
    }

    function mountReadAloudControls(container) {
        var viewerWrap = container.closest('.pdf-viewer-wrap') || container.parentElement;
        if (!viewerWrap) {
            return;
        }

        renderStoryPlayer(viewerWrap);
    }

    function initPdfReadAloud(container) {
        if (!container || container.dataset.readAloudInit === '1') {
            return;
        }
        container.dataset.readAloudInit = '1';
        state.pdfReaderEl = container;

        var storyId = container.getAttribute('data-story-id');
        var apiUrl = container.getAttribute('data-read-aloud-api');
        if (!storyId || !apiUrl) {
            return;
        }

        var controlsMounted = false;

        function mountWhenReady() {
            if (controlsMounted) {
                return;
            }
            controlsMounted = true;
            mountReadAloudControls(container);
        }

        function handleLoadFailure() {
            mountWhenReady();
            if (state.playBtn && !hasStoryAudio()) {
                setButtonState('unavailable');
                state.playBtn.disabled = true;
                state.playBtn.title = 'No read-aloud text for this story yet.';
            }
        }

        var storyDataPromise = loadStoryPageAudio(storyId, apiUrl);

        storyDataPromise.then(mountWhenReady).catch(handleLoadFailure);

        container.addEventListener('pdf-reader:ready', function () {
            setupPageClickListen();
            storyDataPromise.then(function () {
                preloadPageDurations();
                updateSeekBar();
            });
        });
    }

    global.ReadAloud = {
        loadStoryPageAudio: loadStoryPageAudio,
        startStoryFromBeginning: startStoryFromBeginning,
        stopCurrentAudio: stopCurrentAudio,
        initPdfReadAloud: initPdfReadAloud,
    };

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('pdf-reader');
        if (container) {
            initPdfReadAloud(container);
        }
    });

    if (document.readyState !== 'loading') {
        var readyContainer = document.getElementById('pdf-reader');
        if (readyContainer) {
            initPdfReadAloud(readyContainer);
        }
    }
})(window);
