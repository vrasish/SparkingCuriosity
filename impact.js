(function () {
  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function animateCount(el) {
    if (!el || el.dataset.countDone === '1') {
      return;
    }

    var target = parseInt(el.getAttribute('data-count-to') || '0', 10);
    if (!target || target < 1) {
      return;
    }

    el.dataset.countDone = '1';

    if (prefersReducedMotion()) {
      el.textContent = String(target);
      return;
    }

    var duration = parseInt(el.getAttribute('data-count-duration') || '2000', 10);
    var start = null;

    function frame(ts) {
      if (start === null) {
        start = ts;
      }
      var progress = Math.min((ts - start) / duration, 1);
      // Ease-out so it slows near the end
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = Math.max(1, Math.round(eased * target));
      el.textContent = String(value);
      if (progress < 1) {
        window.requestAnimationFrame(frame);
      } else {
        el.textContent = String(target);
      }
    }

    el.textContent = '1';
    window.requestAnimationFrame(frame);
  }

  function initCounts() {
    var nodes = document.querySelectorAll('.impact-count[data-count-to]');
    if (!nodes.length) {
      return;
    }

    if (!('IntersectionObserver' in window)) {
      nodes.forEach(animateCount);
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCount(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.35 }
    );

    nodes.forEach(function (node) {
      observer.observe(node);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCounts);
  } else {
    initCounts();
  }
})();
