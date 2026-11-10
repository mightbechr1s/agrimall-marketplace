// Auto-submit report filter forms on change (lessens clicking).
(function () {
  'use strict';
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.closest) return;
    var form = el.closest('form.report-form');
    if (!form) return;
    // Defer so the value is committed before submit.
    setTimeout(function () { form.submit(); }, 0);
  });
})();
