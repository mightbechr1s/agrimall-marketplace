// Cold-start indicator for Render free tier (and any slow host).
// Shows a subtle top bar when a fetch() takes longer than 2.5s.
(function () {
  'use strict';
  let bar = null;
  let timer = null;
  let pending = 0;

  function ensureBar() {
    if (bar) return bar;
    if (!document.body) {
      document.addEventListener('DOMContentLoaded', ensureBar, { once: true });
      return null;
    }
    bar = document.createElement('div');
    bar.id = 'cold-start-bar';
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');
    bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:3px;z-index:9999;background:var(--primary,#1b4332);transform:scaleX(0);transform-origin:left;transition:transform .3s ease;pointer-events:none;opacity:0;';
    document.body.appendChild(bar);
    return bar;
  }

  function show() {
    ensureBar();
    bar.style.opacity = '1';
    bar.style.transform = 'scaleX(0.3)';
    // Animate to 90% over 30s (indeterminate feel)
    if (timer) clearInterval(timer);
    let pct = 0.3;
    timer = setInterval(function () {
      pct = Math.min(0.9, pct + 0.02);
      bar.style.transform = 'scaleX(' + pct + ')';
    }, 500);
  }

  function hide() {
    if (timer) { clearInterval(timer); timer = null; }
    if (!bar) return;
    bar.style.transform = 'scaleX(1)';
    setTimeout(function () {
      if (bar) { bar.style.opacity = '0'; bar.style.transform = 'scaleX(0)'; }
    }, 300);
  }

  const origFetch = window.fetch;
  window.fetch = function () {
    pending++;
    const t = setTimeout(show, 2500);
    const p = origFetch.apply(this, arguments);
    p.then(function () { clearTimeout(t); if (--pending <= 0) hide(); },
           function () { clearTimeout(t); if (--pending <= 0) hide(); });
    return p;
  };
})();
