(function () {
    var container = document.getElementById('pdf-reader');
    if (!container) {
        return;
    }

    var loadingEl = container.querySelector('.pdf-reader-loading');

    function setLoadingMessage(message) {
        if (loadingEl) {
            loadingEl.textContent = message;
        }
    }

    if (typeof pdfjsLib === 'undefined') {
        setLoadingMessage('Could not load the PDF viewer. Try opening the PDF in a new tab below.');
        return;
    }

    var url = container.getAttribute('data-pdf-url');
    if (!url) {
        setLoadingMessage('PDF link is missing for this story.');
        return;
    }

    if (!/^https?:\/\//i.test(url)) {
        try {
            url = new URL(url, window.location.href).href;
        } catch (error) {
            setLoadingMessage('Could not load this PDF. Try opening it in a new tab below.');
            return;
        }
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function cssSize(name, fallback) {
        var raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        var value = parseInt(raw, 10);
        return Number.isFinite(value) && value > 0 ? value : fallback;
    }

    function isIosDevice() {
        return (
            /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
        );
    }

    function isSafariBrowser() {
        var ua = navigator.userAgent;
        return /Safari/i.test(ua) && !/Chrome|CriOS|FxiOS|EdgiOS/i.test(ua);
    }

    function getAvailableWidth() {
        var width = container.getBoundingClientRect().width;
        if (width > 16) {
            return width - 8;
        }

        var gutter = cssSize('--page-gutter-mobile', cssSize('--page-gutter', 16));
        return Math.max(window.innerWidth - gutter * 2 - 24, 280);
    }

    function getDisplayScale(unscaled) {
        var availableWidth = getAvailableWidth();
        var maxWidth = Math.min(availableWidth, cssSize('--pdf-page-max-width', 980));
        return maxWidth / unscaled.width;
    }

    function renderPage(page, pageNum) {
        var pixelRatio = Math.min(window.devicePixelRatio || 1, isIosDevice() ? 1.25 : 2);
        var unscaled = page.getViewport({ scale: 1 });
        var displayScale = getDisplayScale(unscaled);
        var displayViewport = page.getViewport({ scale: displayScale });
        var renderViewport = page.getViewport({ scale: displayScale * pixelRatio });

        var frame = document.createElement('div');
        frame.className = 'pdf-page-frame';
        frame.setAttribute('data-page-number', String(pageNum));

        var toolbar = document.createElement('div');
        toolbar.className = 'pdf-page-toolbar';
        toolbar.setAttribute('aria-hidden', 'false');

        var canvas = document.createElement('canvas');
        canvas.className = 'pdf-page-canvas';
        canvas.width = Math.round(renderViewport.width);
        canvas.height = Math.round(renderViewport.height);
        canvas.style.width = displayViewport.width + 'px';
        canvas.style.height = displayViewport.height + 'px';

        frame.appendChild(toolbar);
        frame.appendChild(canvas);

        return page
            .render({
                canvasContext: canvas.getContext('2d'),
                viewport: renderViewport,
            })
            .promise.then(function () {
                container.appendChild(frame);
            });
    }

    function renderDocument(pdf) {
        if (loadingEl) {
            loadingEl.remove();
        }

        var chain = Promise.resolve();
        for (var pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
            (function (num) {
                chain = chain.then(function () {
                    return pdf.getPage(num).then(function (page) {
                        return renderPage(page, num);
                    });
                });
            })(pageNum);
        }

        return chain.then(function () {
            container.dispatchEvent(
                new CustomEvent('pdf-reader:ready', {
                    bubbles: true,
                    detail: { numPages: pdf.numPages },
                })
            );
        });
    }

    function getDocumentOptions() {
        var options = {
            url: url,
            withCredentials: true,
        };

        if (isIosDevice() || isSafariBrowser()) {
            options.disableRange = true;
            options.disableStream = true;
            options.disableAutoFetch = true;
        }

        return options;
    }

    function startRender() {
        var loadingTask = pdfjsLib.getDocument(getDocumentOptions());

        loadingTask.onProgress = function (progress) {
            if (!progress || !progress.total) {
                return;
            }
            var percent = Math.min(100, Math.round((progress.loaded / progress.total) * 100));
            setLoadingMessage('Loading pages… ' + percent + '%');
        };

        loadingTask.promise
            .then(renderDocument)
            .catch(function () {
                setLoadingMessage('Could not load this PDF. Try opening it in a new tab below.');
            });
    }

    var layoutAttempts = 0;
    var maxLayoutAttempts = 180;

    function waitForLayout() {
        layoutAttempts += 1;
        if (container.getBoundingClientRect().width > 16 || layoutAttempts >= maxLayoutAttempts) {
            startRender();
            return;
        }

        requestAnimationFrame(waitForLayout);
    }

    waitForLayout();
})();
