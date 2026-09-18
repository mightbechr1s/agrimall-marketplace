/* Swipe gestures for product cards — reveals contextual actions.
   Desktop: hidden (no touch). Mobile only. touch-action: pan-y keeps scroll. */
(function () {
  'use strict';

  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  if (finePointer.matches) return; // skip on mouse/trackpad

  const CARD_SELECTOR = '#product-grid .card';
  const SWIPE_THRESHOLD = 48;
  const MAX_DX = 80;

  document.querySelectorAll(CARD_SELECTOR).forEach(card => {
    let startX = 0;
    let startY = 0;
    let dx = 0;
    let swiping = false;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    card.addEventListener('touchstart', (e) => {
      const touch = e.changedTouches[0];
      startX = touch.clientX;
      startY = touch.clientY;
      dx = 0;
      swiping = true;
    }, { passive: true });

    card.addEventListener('touchmove', (e) => {
      if (!swiping) return;
      const touch = e.changedTouches[0];
      const moveX = touch.clientX - startX;
      const moveY = touch.clientY - startY;
      // Only treat as horizontal swipe if horizontal dominates
      if (Math.abs(moveY) > Math.abs(moveX)) {
        return; // vertical scroll, let it pass
      }
      dx = Math.max(-MAX_DX, Math.min(MAX_DX, moveX * 0.5));
      if (reduced) return;
      card.style.transform = `translateX(${dx}px)`;
    }, { passive: true });

    card.addEventListener('touchend', () => {
      if (!swiping) return;
      swiping = false;
      card.style.transform = '';
      if (dx > SWIPE_THRESHOLD) {
        reveal(card, 'right', reduced);
      } else if (dx < -SWIPE_THRESHOLD) {
        reveal(card, 'left', reduced);
      } else {
        dismiss(card);
      }
    });
  });

  function reveal(card, dir, reduced) {
    const cls = dir === 'left' ? 'is-swiped-left' : 'is-swiped-right';
    const opposite = dir === 'left' ? 'is-swiped-right' : 'is-swiped-left';
    card.classList.remove(opposite);
    card.classList.add(cls);
    const btn = card.querySelector(dir === 'left' ? '.swipe-reveal--left' : '.swipe-reveal--right');
    if (btn) {
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.add('is-hot');
        setTimeout(() => icon.classList.remove('is-hot'), 400);
      }
    }
    // Auto-dismiss after 1.6s
    clearTimeout(card._swipeTimer);
    card._swipeTimer = setTimeout(() => dismiss(card), reduced ? 0 : 1600);
  }

  function dismiss(card) {
    card.classList.remove('is-swiped-left', 'is-swiped-right');
  }

  // Tap anywhere else on a swiped card dismisses it
  document.addEventListener('touchstart', (e) => {
    const card = e.target.closest(CARD_SELECTOR);
    document.querySelectorAll(CARD_SELECTOR + '.is-swiped-left, ' + CARD_SELECTOR + '.is-swiped-right').forEach(open => {
      if (open !== card) dismiss(open);
    });
  }, { passive: true });
})();