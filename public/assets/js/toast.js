/**
 * Ephemeral status toasts.
 *
 * The visual system already defined this: motion.css ships #toast-region,
 * .toast-item, .toast-visible and .toast-exiting, and its own comment says
 * "JS toggles .toast-visible / .toast-exiting". This supplies the missing half.
 * Nothing here invents a look -- it only drives the classes that already exist.
 *
 * Design notes:
 *  - Text is set with textContent, never innerHTML. Toast copy can originate
 *    from a server response, so building markup from it would be an injection
 *    sink.
 *  - Exit waits for the real transition rather than a guessed delay, so the
 *    toast is never yanked out mid-fade, and a reduced-motion user (where the
 *    transition is ~0ms) is not made to wait for it. A timer backstops the
 *    transitionend in case the element is removed or the transition never fires.
 *  - The stack is capped so a burst of actions cannot cover the page.
 *  - Errors are assertive, everything else polite. The region is switched to
 *    aria-live="off" when JS takes over, because each toast carries its own live
 *    role and nesting two live regions announces everything twice.
 */
(function () {
  'use strict';

  var REGION_ID = 'toast-region';
  var DEFAULT_DURATION = 4000;
  var ERROR_DURATION = 7000;
  var MAX_VISIBLE = 4;
  var EXIT_FALLBACK_MS = 600;

  var ICONS = {
    success: 'bi-check-circle',
    error: 'bi-exclamation-triangle',
    info: 'bi-info-circle'
  };

  var ROLES = { success: 'status', error: 'alert', info: 'status' };

  function ensureRegion() {
    var region = document.getElementById(REGION_ID);
    if (!region) {
      // The dashboard shell has no server-rendered region, so create one rather
      // than silently do nothing on every signed-in page.
      region = document.createElement('div');
      region.id = REGION_ID;
      document.body.appendChild(region);
    }
    // Toasts carry their own live role; see the note above.
    region.setAttribute('aria-live', 'off');
    return region;
  }

  function trimOldest(region) {
    var items = region.querySelectorAll('.toast-item');
    for (var i = 0; i < items.length - MAX_VISIBLE + 1; i++) {
      removeNow(items[i]);
    }
  }

  function removeNow(el) {
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  function dismiss(el) {
    if (!el || el.dataset.toastClosing === '1') return;
    el.dataset.toastClosing = '1';
    el.classList.remove('toast-visible');
    el.classList.add('toast-exiting');

    var done = function () { removeNow(el); };
    el.addEventListener('transitionend', done, { once: true });
    // Backstop: no transition (reduced motion, or display:none ancestor) would
    // otherwise leave the node in the region forever.
    setTimeout(done, EXIT_FALLBACK_MS);
  }

  /**
   * @param {string} message Plain text. Never pass user or server HTML.
   * @param {Object} [opts] { type: 'success'|'error'|'info', duration: ms }
   */
  function toast(message, opts) {
    opts = opts || {};
    if (!message) return null;

    var type = ICONS[opts.type] ? opts.type : 'info';
    var region = ensureRegion();

    var el = document.createElement('div');
    el.className = 'toast-item toast-' + type;
    el.setAttribute('role', ROLES[type]);

    var icon = document.createElement('i');
    icon.className = 'bi ' + ICONS[type];
    icon.setAttribute('aria-hidden', 'true');

    var text = document.createElement('span');
    text.className = 'toast-message';
    text.textContent = String(message);

    el.appendChild(icon);
    el.appendChild(text);

    // Hovering or focusing a toast holds it open; moving away lets the timer run.
    var timer = null;
    var duration = opts.duration || (type === 'error' ? ERROR_DURATION : DEFAULT_DURATION);
    var start = function () {
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () { dismiss(el); }, duration);
    };
    var hold = function () { if (timer) clearTimeout(timer); };
    el.addEventListener('mouseenter', hold);
    el.addEventListener('focusin', hold);
    el.addEventListener('mouseleave', start);
    el.addEventListener('focusout', start);
    el.addEventListener('click', function () { dismiss(el); });

    region.appendChild(el);
    trimOldest(region);

    // The enter transition needs the element painted in its initial state first,
    // otherwise there is nothing to transition from and it just appears.
    void el.offsetWidth;
    el.classList.add('toast-visible');

    start();
    return el;
  }

  // Public API. Namespaced so it cannot collide with anything on the page.
  window.animalToast = toast;
  window.animalToast.dismissAll = function () {
    var region = document.getElementById(REGION_ID);
    if (!region) return;
    var items = region.querySelectorAll('.toast-item');
    for (var i = 0; i < items.length; i++) dismiss(items[i]);
  };
})();