// Keyboard shortcuts for power users. Loaded globally via footer.
(function () {
  'use strict';
  let gPressed = false;
  let gTimer = null;

  function isTyping() {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName.toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
  }

  function go(url) {
    window.location.href = url;
  }

  function focusSearch() {
    const search = document.querySelector('input[type="search"], #market-search, #hero-search-q');
    if (search) { search.focus(); search.select(); }
  }

  function showHelp() {
    if (document.getElementById('shortcut-help')) return;
    const overlay = document.createElement('div');
    overlay.id = 'shortcut-help';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
    const box = document.createElement('div');
    box.style.cssText = 'background:var(--paper,#fff);color:var(--text,#111);border:1px solid var(--borders,#ddd);border-radius:12px;padding:24px;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.3);font-family:var(--font-body,system-ui,sans-serif);';
    box.innerHTML = '<h3 style="margin:0 0 12px;font-size:18px;">Keyboard shortcuts</h3>' +
      '<table style="width:100%;font-size:14px;border-collapse:collapse;">' +
      '<tr><td style="padding:4px 8px;"><kbd>/</kbd></td><td style="padding:4px 8px;">Focus search</td></tr>' +
      '<tr><td style="padding:4px 8px;"><kbd>g</kbd> <kbd>b</kbd></td><td style="padding:4px 8px;">Go to Browse</td></tr>' +
      '<tr><td style="padding:4px 8px;"><kbd>g</kbd> <kbd>d</kbd></td><td style="padding:4px 8px;">Go to Dashboard</td></tr>' +
      '<tr><td style="padding:4px 8px;"><kbd>g</kbd> <kbd>o</kbd></td><td style="padding:4px 8px;">Go to Orders</td></tr>' +
      '<tr><td style="padding:4px 8px;"><kbd>g</kbd> <kbd>s</kbd></td><td style="padding:4px 8px;">Go to Saved</td></tr>' +
      '<tr><td style="padding:4px 8px;"><kbd>?</kbd></td><td style="padding:4px 8px;">Show this help</td></tr>' +
      '<tr><td style="padding:4px 8px;"><kbd>Esc</kbd></td><td style="padding:4px 8px;">Close dialog</td></tr>' +
      '</table>' +
      '<button type="button" id="sc-close" class="btn btn-primary btn-sm" style="margin-top:12px;width:100%;">Close</button>';
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    document.getElementById('sc-close').addEventListener('click', closeHelp);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeHelp(); });
    function closeHelp() { overlay.remove(); }
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { closeHelp(); document.removeEventListener('keydown', esc); } });
  }

  document.addEventListener('keydown', function (e) {
    if (isTyping()) return;
    if (e.key === '?') { e.preventDefault(); showHelp(); return; }
    if (e.key === '/') { e.preventDefault(); focusSearch(); return; }
    if (e.key === 'Escape') {
      const help = document.getElementById('shortcut-help');
      if (help) help.remove();
      return;
    }
    if (e.key === 'g') {
      gPressed = true;
      clearTimeout(gTimer);
      gTimer = setTimeout(function () { gPressed = false; }, 1200);
      return;
    }
    if (gPressed) {
      gPressed = false;
      clearTimeout(gTimer);
      switch (e.key) {
        case 'b': go('browse.php'); break;
        case 'd': go('dashboard.php'); break;
        case 'o': go('orders.php'); break;
        case 's': go('saved.php'); break;
      }
    }
  });
})();
