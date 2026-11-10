// Auto-submit the advanced price/sort filter form on change.
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.browse-advanced .browse-filters');
    if (!form) return;
    var sort = form.querySelector('select[name="sort"]');
    if (sort) {
      sort.addEventListener('change', function () { form.submit(); });
    }
    // Auto-submit price inputs on Enter.
    form.querySelectorAll('input[type="number"]').forEach(function (inp) {
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); form.submit(); }
      });
    });
  });
})();
