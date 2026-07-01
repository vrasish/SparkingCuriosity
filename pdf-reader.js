(function () {
    var container = document.getElementById('pdf-reader');
    if (!container || typeof pdfjsLib === 'undefined') {
        return;
    }

    var url = container.getAttribute('data-pdf-url');
    if (!url) {
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    var loadingEl = container.querySelector('.pdf-reader-loading');

    function cssSize(name, fallback) {
        var raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        var value = parseInt(raw, 10);
        return Number.isFinite(value) && value > 0 ? value : fallback;
    }

    function setLoadingMessage(message) {
        if (loadingEl) {
            loadingEl.textContent = message;
        }
    }

    function getAvailableWidth() {
        var width = container.getBoundingClientRect().width;
        if (width > 16) {
            return width - 8;
        }

        var gutter = cssSize('--page-gutter', 200);
        return Math.max(window.innerWidth - gutter * 2 - 48, 280);
    }

    function getDisplayScale(unscaled) {
        var availableWidth = getAvailableWidth();
        var maxWidth = Math.min(availableWidth, cssSize('--pdf-page-max-width', 980));
        return maxWidth / unscaled.width;
    }

    function renderPage(page, pageNum) {
        var pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
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

    function startRender() {
        pdfjsLib
            .getDocument({ url: url, withCredentials: true })
            .promise.then(renderDocument)
            .catch(function () {
                setLoadingMessage('Could not load this PDF. Try opening it in a new tab below.');
            });
    }

    function waitForLayout() {
        if (container.getBoundingClientRect().width > 16) {
            startRender();
            return;
        }

        requestAnimationFrame(waitForLayout);
    }

    waitForLayout();
})();
