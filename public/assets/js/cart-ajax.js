/**
 * Cart add without a page reload: intercepts form[data-ajax="true"] (listing-card.php,
 * listing.php), POSTs cart_add.php, shows inline feedback or falls back to a plain
 * submit when the server answer is not JSON (e.g. login redirect).
 */
(function () {
  'use strict';

  function setBtnBusy(btn, busy) {
    if (btn) btn.disabled = busy;
  }

  function swapButtonText(btn, text, ms) {
    if (!btn) return;
    var original = btn.innerHTML;
    btn.innerHTML = text;
    setTimeout(function () {
      btn.innerHTML = original;
    }, ms);
  }

  function bumpCartCount() {
    var badges = document.querySelectorAll('.cart-count');
    for (var i = 0; i < badges.length; i++) {
      var el = badges[i];
      var n = parseInt(el.textContent.replace(/[^0-9]/g, ''), 10);
      el.textContent = (isNaN(n) ? 0 : n) + 1;
      if (el.animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        el.animate(
          [{ transform: 'scale(1)' }, { transform: 'scale(1.4)' }, { transform: 'scale(1)' }],
          { duration: 320, easing: 'ease-out' }
        );
      }
    }
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches || !form.matches('form[data-ajax="true"]')) return;

    var btn = form.querySelector('button[type="submit"]');
    var action = form.getAttribute('action') || form.action;

    e.preventDefault();
    setBtnBusy(btn, true);

    fetch(action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    })
      .then(function (res) { return res.text(); })
      .then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (err) { data = null; }
        if (data && !data.ok && data.redirect) {
          window.location.href = data.redirect;
          return;
        }
        if (data && data.ok) {
          bumpCartCount();
          if (btn) {
            var icon = btn.querySelector('i.bi');
            if (icon) {
              var previous = icon.className;
              icon.className = 'bi bi-check-lg';
              setTimeout(function () { icon.className = previous; }, 1400);
            } else {
              swapButtonText(btn, data.message || 'Added to cart', 1400);
            }
          }
          // Only ever show what the server said. An English fallback here would put
          // untranslated text on a Tagalog page: getting the copy right is the
          // server's job, not the toast's.
          if (!btn && data.message && window.animalToast) {
            window.animalToast(data.message, { type: 'success' });
          }
        } else if (data && data.message) {
          swapButtonText(btn, data.message, 2600);
          if (window.animalToast) window.animalToast(data.message, { type: 'error' });
        } else {
          form.submit();
          return;
        }
        setBtnBusy(btn, false);
      })
      .catch(function () {
        setBtnBusy(btn, false);
        form.submit();
      });
  });
})();