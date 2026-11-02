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

  function pauseVideos(root) {
    root.querySelectorAll('video').forEach(function (video) {
      try {
        video.pause();
      } catch (err) {
        // ignore
      }
    });
  }

  function initCarousel(root) {
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-slide]'));
    if (slides.length < 1) {
      return;
    }

    var prevBtn = root.querySelector('[data-carousel-prev]');
    var nextBtn = root.querySelector('[data-carousel-next]');
    var titleEl = root.querySelector('[data-carousel-title]');
    var captionEl = root.querySelector('[data-carousel-caption]');
    var dotsWrap = root.querySelector('[data-carousel-dots]');
    var index = Math.max(
      0,
      slides.findIndex(function (slide) {
        return slide.classList.contains('is-active');
      })
    );
    var dots = [];

    function renderDots() {
      if (!dotsWrap) {
        return;
      }
      dotsWrap.innerHTML = '';
      dots = slides.map(function (_slide, i) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'impact-carousel-dot' + (i === index ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        dot.addEventListener('click', function () {
          show(i);
        });
        dotsWrap.appendChild(dot);
        return dot;
      });
    }

    function show(nextIndex) {
      if (!slides.length) {
        return;
      }
      index = ((nextIndex % slides.length) + slides.length) % slides.length;
      pauseVideos(root);
      slides.forEach(function (slide, i) {
        var active = i === index;
        slide.classList.toggle('is-active', active);
        if (active) {
          slide.removeAttribute('hidden');
        } else {
          slide.setAttribute('hidden', '');
        }
      });
      var active = slides[index];
      if (titleEl) {
        titleEl.textContent = active.getAttribute('data-title') || '';
      }
      if (captionEl) {
        captionEl.textContent = active.getAttribute('data-caption') || '';
      }
      dots.forEach(function (dot, i) {
        dot.classList.toggle('is-active', i === index);
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        show(index - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        show(index + 1);
      });
    }

    root.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        show(index - 1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        show(index + 1);
      }
    });

    if (!root.hasAttribute('tabindex')) {
      root.setAttribute('tabindex', '0');
    }

    renderDots();
    show(index);
  }

  function initCarousels() {
    document.querySelectorAll('[data-impact-carousel]').forEach(initCarousel);
  }

  function boot() {
    initCounts();
    initCarousels();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
