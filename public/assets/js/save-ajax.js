/**
 * Save / unsave listing without a full page reload.
 * Intercepts submits on form[data-save-ajax="true"] (the bookmark button on
 * listing cards and the Save for Later button on the listing page), POSTs
 * saved_toggle.php via fetch, and flips the bookmark button in place. Falls
 * back to a normal form submit if the server answer is not JSON.
 */
(function () {
  'use strict';

  function toggleSaved(btn, saved) {
    var icon = btn.querySelector('i.bi');
    if (icon) {
      var base = null;
      ['bi-bookmark-fill', 'bi-bookmark', 'bi-heart-fill', 'bi-heart'].forEach(function (cls) {
        if (icon.classList.contains(cls)) base = cls.replace('-fill', '');
      });
      if (base) {
        icon.classList.add(saved ? base + '-fill' : base);
        icon.classList.remove(saved ? base : base + '-fill');
      }
    }
    btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
    btn.setAttribute('aria-label', saved ? 'Remove from saved' : 'Save listing');
    var text = Array.prototype.find.call(btn.childNodes, function (n) {
      return n.nodeType === 3 && n.textContent.trim().length > 0;
    });
    if (text) text.textContent = saved ? 'Saved' : 'Save for Later';
    btn.classList.toggle('btn-danger', saved);
    btn.classList.toggle('btn-outline-danger', !saved);
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches || !form.matches('form[data-save-ajax="true"]')) return;

    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    })
      .then(function (res) { return res.text(); })
      .then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (err) { data = null; }
        if (data && data.ok) {
          toggleSaved(btn, !!data.saved);
        } else if (data && data.redirect) {
          window.location.href = data.redirect;
        } else {
          form.submit();
        }
      })
      .catch(function () {
        form.submit();
      });
  });
})();