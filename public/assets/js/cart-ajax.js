/**
 * Cart add without a full page reload.
 * Intercepts submits on form[data-ajax="true"] (the add-to-basket forms in
 * listing-card.php and listing.php), POSTs cart_add.php via fetch, and shows
 * inline button feedback. Falls back to a normal form submit if the server
 * answer is not JSON (e.g. login redirect).
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
        } else if (data && data.message) {
          swapButtonText(btn, data.message, 2600);
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