document.documentElement.classList.add('js');

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

/* ── Cookie banner ────────────────────────────────────────── */
function initCookieBanner() {
  const COOKIE_KEY = 'animCk';
  const cookieBanner = document.getElementById('cookie-banner');
  if (!cookieBanner) return;
  if (localStorage.getItem(COOKIE_KEY) === '1') return;
  const acceptBtn = cookieBanner.querySelector('[data-cookie-accept]');
  if (!acceptBtn) return;
  cookieBanner.classList.add('show');
  acceptBtn.addEventListener('click', () => {
    localStorage.setItem(COOKIE_KEY, '1');
    cookieBanner.classList.remove('show');
    cookieBanner.classList.add('hide');
    cookieBanner.addEventListener('transitionend', () => {
      cookieBanner.style.display = 'none';
    }, { once: true });
  });
}

/* ── Scroll reveals (opt-in [data-reveal]) ────────────────── */
function initScrollReveal() {
  const revealElements = document.querySelectorAll('[data-reveal]');
  if (!revealElements.length) return;
  if (!('IntersectionObserver' in window)) {
    revealElements.forEach(el => el.classList.add('reveal-visible'));
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('reveal-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });
  revealElements.forEach(el => observer.observe(el));

  // Fallback: immediately reveal any elements already in the viewport
  requestAnimationFrame(() => {
    revealElements.forEach(el => {
      if (el.classList.contains('reveal-visible')) return;
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) {
        el.classList.add('reveal-visible');
        observer.unobserve(el);
      }
    });
  });
}

/* ── Locale toggle ───────────────────────────────────────── */
function initLocaleToggle() {
  const toggleBtn = document.querySelector('.locale-toggle');
  if (!toggleBtn) return;
  if (toggleBtn.querySelector('a[data-lang]')) return; // plain links, let browser handle
  const labelEn = toggleBtn.querySelector('[data-lang="en"]');
  const labelTl = toggleBtn.querySelector('[data-lang="tl"]');
  if (!labelEn || !labelTl) return;
  const currentLang = document.documentElement.lang || 'en';
  labelEn.classList.toggle('active', currentLang === 'en');
  labelTl.classList.toggle('active', currentLang === 'tl');
  toggleBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const form = toggleBtn.closest('form');
    if (!form) return;
    const isEn = labelEn.classList.contains('active');
    const targetLang = isEn ? 'tl' : 'en';
    if (isEn) {
      labelEn.classList.remove('active');
      labelEn.classList.add('exit');
      labelTl.classList.remove('exit', 'enter');
      labelTl.classList.add('enter');
    } else {
      labelTl.classList.remove('active');
      labelTl.classList.add('exit');
      labelEn.classList.remove('exit', 'enter');
      labelEn.classList.add('enter');
    }
    setTimeout(() => {
      labelEn.classList.remove('exit', 'enter');
      labelTl.classList.remove('exit', 'enter');
      labelEn.classList.toggle('active', targetLang === 'en');
      labelTl.classList.toggle('active', targetLang === 'tl');
      form.submit();
    }, 120);
    document.cookie = `animal_lang=${targetLang}; path=/; max-age=${60*60*24*365}; SameSite=Lax`;
  });
  [labelEn, labelTl].forEach(label => {
    label.addEventListener('transitionend', (e) => {
      if (e.propertyName === 'transform') {
        label.classList.remove('exit', 'enter');
      }
    });
  });
}

/* ── Modals ───────────────────────────────────────────────── */
function initModals() {
  const modalTriggers = document.querySelectorAll('[data-modal-trigger]');
  modalTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      const modalId = trigger.getAttribute('data-modal-target');
      const modal = document.getElementById(modalId);
      if (modal) openModal(modal);
    });
  });
  const modalCloses = document.querySelectorAll('[data-modal-close]');
  modalCloses.forEach(close => {
    close.addEventListener('click', (e) => {
      e.preventDefault();
      const modal = close.closest('.modal');
      if (modal) closeModal(modal);
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = document.querySelector('.modal.show');
      if (modal) closeModal(modal);
    }
  });
  const backdrops = document.querySelectorAll('.modal-backdrop');
  backdrops.forEach(bd => {
    bd.addEventListener('click', (e) => {
      if (e.target === bd) {
        const modal = bd.closest('.modal');
        if (modal) closeModal(modal);
      }
    });
  });
}

function openModal(modal) {
  if (!modal) return;
  modal.classList.add('show');
  trapFocus(modal);
  const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
  if (focusable) focusable.focus();
}

function closeModal(modal) {
  if (!modal) return;
  modal.classList.remove('show');
  document.body.focus();
}

function trapFocus(modal) {
  const focusableEls = modal.querySelectorAll('a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable]');
  const first = focusableEls[0];
  const last = focusableEls[focusableEls.length - 1];
  function trapKey(e) {
    if (e.key === 'Tab') {
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }
    if (e.key === 'Escape') closeModal(modal);
  }
  modal.addEventListener('keydown', trapKey);
  modal._trapFocus = trapKey;
  modal.addEventListener('transitionend', function listener(e) {
    if (e.propertyName === 'opacity' && !modal.classList.contains('show')) {
      modal.removeEventListener('keydown', trapKey);
      modal.removeEventListener('transitionend', listener);
    }
  });
}

/* ── Flash auto-dismiss ───────────────────────────────────── */
function initFlashAutoDismiss() {
  const flashMessages = document.querySelectorAll('.alert[data-auto-dismiss]');
  flashMessages.forEach(flash => {
    flash.style.opacity = '0';
    flash.style.transform = 'translateY(8px)';
    flash.style.transition = `opacity ${prefersReducedMotion.matches ? '80ms' : '150ms'} ease-out, transform ${prefersReducedMotion.matches ? '80ms' : '150ms'} ease-out`;
    requestAnimationFrame(() => {
      flash.style.opacity = '1';
      flash.style.transform = 'translateY(0)';
    });
    setTimeout(() => {
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-6px)';
      flash.addEventListener('transitionend', () => flash.remove(), { once: true });
    }, 5000);
  });
}

/* ── FAQ accordion ────────────────────────────────────────── */
function initFaqToggle() {
  document.querySelectorAll('.faq-q').forEach(btn => {
    btn.addEventListener('click', function() {
      const expanded = this.getAttribute('aria-expanded') === 'true';
      const item = this.closest('.faq-item');
      const answer = item ? item.querySelector('.faq-a') : null;
      document.querySelectorAll('.faq-item').forEach(other => {
        if (other !== item) {
          other.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
          const otherAnswer = other.querySelector('.faq-a');
          if (otherAnswer) otherAnswer.style.maxHeight = '0px';
        }
      });
      this.setAttribute('aria-expanded', String(!expanded));
      if (answer) {
        answer.style.maxHeight = expanded ? '0px' : answer.scrollHeight + 'px';
      }
    });
  });
}

/* ── Category filter pills (no animation) ─────────────────── */
function initFilterButtons() {
  const filters = document.querySelectorAll('.filter, .filter-pill');
  const grid = document.getElementById('product-grid');
  if (!filters.length || !grid) return;
  filters.forEach(filter => {
    filter.addEventListener('click', function() {
      filters.forEach(f => f.classList.remove('is-active'));
      this.classList.add('is-active');
      const filterVal = this.getAttribute('data-filter');
      const cards = grid.querySelectorAll('.card');
      cards.forEach(card => {
        const cat = card.getAttribute('data-category') || '';
        const show = filterVal === 'all' || cat === filterVal;
        const hidden = card.classList.contains('is-filtered-out');
        if (show && hidden) {
          card.style.display = '';
          void card.offsetWidth;
          requestAnimationFrame(() => card.classList.remove('is-filtered-out'));
        } else if (!show && !hidden) {
          card.classList.add('is-filtered-out');
          const delay = prefersReducedMotion.matches ? 0 : 200;
          setTimeout(() => { card.style.display = 'none'; }, delay);
        }
      });
    });
  });
}

/* ── Toast system ─────────────────────────────────────────── */
const toastRegistry = [];
const toastRegion = document.getElementById('toast-region');

function createToast(message, type = 'info', duration = 6000) {
  if (toastRegistry.some(t => t.message === message && t.type === type)) return;
  const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  const toast = document.createElement('div');
  toast.className = `toast-item toast-${type}`;
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
  toast.innerHTML = `
    <span class="toast-icon">${getToastIcon(type)}</span>
    <span class="toast-message">${message}</span>
  `;
  toastRegion.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('toast-visible'));
  const timeoutId = setTimeout(() => dismissToast(toast, toastId), duration);
  toast.addEventListener('mouseenter', () => {
    clearTimeout(timeoutId);
    toast.classList.add('toast-paused');
  });
  toast.addEventListener('mouseleave', () => {
    toast.classList.remove('toast-paused');
    const remaining = Math.max(0, duration - (Date.now() - toast._startTime));
    toast._startTime = Date.now() - (duration - remaining);
    toast._timeoutId = setTimeout(() => dismissToast(toast, toastId), remaining);
  });
  const toastInstance = {
    id: toastId, element: toast, message: message, type: type,
    timeoutId: timeoutId, _startTime: Date.now()
  };
  toastRegistry.push(toastInstance);
  toast.addEventListener('transitionend', (e) => {
    if (e.propertyName === 'opacity' && !toast.classList.contains('toast-visible')) {
      toast.remove();
      const index = toastRegistry.findIndex(t => t.id === toastId);
      if (index !== -1) toastRegistry.splice(index, 1);
    }
  });
  return toastId;
}

function dismissToast(toast, toastId) {
  if (!toast || !toast.parentNode) return;
  toast.classList.remove('toast-visible');
  toast.classList.add('toast-exiting');
}

function getToastIcon(type) {
  switch (type) {
    case 'success': return '✓';
    case 'error': return '✗';
    case 'warning': return '⚠';
    default: return 'ℹ';
  }
}

window.showToast = function(message, type = 'info', duration = 6000) {
  return createToast(message, type, duration);
};

/* ── Cart (localStorage) ─────────────────────────────────── */
const CART_KEY = 'animal_cart';

function getCart() {
  try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
  catch { return []; }
}

function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  updateCartPill(cart);
}

function updateCartPill(cart) {
  const count = cart.reduce((sum, item) => sum + item.qty, 0);
  const pill = document.querySelector('.cart-pill');
  const countEl = document.querySelector('.cart-count');
  if (countEl) countEl.textContent = count;
  if (pill) {
    pill.setAttribute('aria-label', 'Basket, ' + count + ' item' + (count !== 1 ? 's' : ''));
  }
}

function addToCart(name, price, unit, farm) {
  const cart = getCart();
  const existing = cart.find(item => item.name === name);
  if (existing) { existing.qty += 1; }
  else { cart.push({ name, price, unit, farm, qty: 1 }); }
  saveCart(cart);
  showToast(name + ' added to basket', 'success', 3000);
}

function initCartButtons() {
  document.querySelectorAll('.card .add, .card .add-to-basket').forEach(btn => {
    btn.addEventListener('click', function() {
      const card = this.closest('.card');
      if (!card) return;
      const name = card.querySelector('h3') ? card.querySelector('h3').textContent.trim() : '';
      const priceEl = card.querySelector('.price');
      const price = priceEl ? priceEl.textContent.replace(/[^0-9.]/g, '') : '0';
      const unitEl = priceEl ? priceEl.querySelector('small') : null;
      const unit = unitEl ? unitEl.textContent.replace(/^\//, '').trim() : 'kg';
      const farmEl = card.querySelector('.farm') || card.querySelector('.badge-location');
      const farm = farmEl ? farmEl.textContent.trim() : '';
      addToCart(name, price, unit, farm);
    });
  });
  updateCartPill(getCart());
}

/* ── Carousel prev/next (dashboard) ──────────────────────── */
function initCarouselControls() {
  const carousel = document.querySelector('.horizontal-carousel');
  const prev = document.getElementById('prevSlide');
  const next = document.getElementById('nextSlide');
  if (!carousel || (!prev && !next)) return;
  const scroll = (dir) => {
    const card = carousel.querySelector('.carousel-item, .card, .farm-card, *');
    const step = card ? card.offsetWidth + 16 : 240;
    carousel.scrollBy({ left: dir * step, behavior: prefersReducedMotion.matches ? 'auto' : 'smooth' });
  };
  if (prev) prev.addEventListener('click', () => scroll(-1));
  if (next) next.addEventListener('click', () => scroll(1));
}

/* ── Fast-add buttons (dashboard) ────────────────────────── */
function initFastAddButtons() {
  document.querySelectorAll('.fast-add-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const card = this.closest('.card, .carousel-item');
      if (!card) return;
      const name = card.querySelector('.card-title, h3, h6') ? (card.querySelector('.card-title') || card.querySelector('h3') || card.querySelector('h6')).textContent.trim() : '';
      const priceEl = card.querySelector('.fw-medium, .price');
      const price = priceEl ? priceEl.textContent.replace(/[^0-9.]/g, '') : '0';
      const unitEl = priceEl ? priceEl.querySelector('small') : null;
      const unit = unitEl ? unitEl.textContent.replace(/^\//, '').trim() : (priceEl ? priceEl.textContent.split('/').pop().trim() : 'kg');
      addToCart(name, price, unit, '');
    });
  });
}

/* ── Stock toggle labels ──────────────────────────────────── */
function initStockToggles() {
  document.querySelectorAll('.stock-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
      const wrap = this.closest('.form-check') || this.parentElement;
      const label = wrap ? wrap.querySelector('.form-check-label') : null;
      if (label) label.textContent = this.checked ? 'In Stock' : 'Sold Out';
    });
  });
}

/* ── Nav scroll shadow (handled by design-system flat rule) ─ */
function initNavScroll() {
  const nav = document.querySelector('.nav');
  if (!nav) return;
  let ticking = false;
  function onScroll() {
    if (!ticking) {
      requestAnimationFrame(() => {
        nav.classList.toggle('is-scrolled', window.scrollY > 40);
        ticking = false;
      });
      ticking = true;
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

/* ── Mobile menu ──────────────────────────────────────────── */
function initMobileMenu() {
  const hamburger = document.querySelector('.hamburger');
  const mobileMenu = document.getElementById('mobile-menu');
  if (!hamburger || !mobileMenu) return;
  const focusables = () => [...mobileMenu.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])')];
  let restoreFocus = hamburger;

  function openMenu() {
    restoreFocus = document.activeElement || hamburger;
    hamburger.classList.add('is-open');
    hamburger.setAttribute('aria-expanded', 'true');
    hamburger.setAttribute('aria-label', 'Close menu');
    mobileMenu.classList.add('is-open');
    mobileMenu.setAttribute('aria-hidden', 'false');
    mobileMenu.removeAttribute('inert');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => focusables()[0]?.focus());
  }

  function closeMenu(restore = true) {
    hamburger.classList.remove('is-open');
    hamburger.setAttribute('aria-expanded', 'false');
    hamburger.setAttribute('aria-label', 'Open menu');
    mobileMenu.classList.remove('is-open');
    mobileMenu.setAttribute('aria-hidden', 'true');
    mobileMenu.setAttribute('inert', '');
    document.body.style.overflow = '';
    if (restore) requestAnimationFrame(() => (restoreFocus || hamburger).focus());
  }

  hamburger.addEventListener('click', () => {
    hamburger.getAttribute('aria-expanded') === 'true' ? closeMenu() : openMenu();
  });
  mobileMenu.querySelectorAll('.mobile-nav-link').forEach(link => link.addEventListener('click', () => closeMenu(false)));
  document.addEventListener('keydown', (e) => {
    if (hamburger.getAttribute('aria-expanded') !== 'true') return;
    if (e.key === 'Escape') return closeMenu();
    if (e.key !== 'Tab') return;
    const items = focusables();
    if (!items.length) return;
    if (e.shiftKey && document.activeElement === items[0]) { e.preventDefault(); items.at(-1).focus(); }
    else if (!e.shiftKey && document.activeElement === items.at(-1)) { e.preventDefault(); items[0].focus(); }
  });
}

/* ── Dark mode toggle ────────────────────────────────────── */
function initThemeToggle() {
  const STORAGE_KEY = 'animal_theme';
  const toggles = document.querySelectorAll('.theme-toggle');
  if (!toggles.length) return;

  function getPreference() {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === 'system' || stored === 'light' || stored === 'dark' ? stored : 'system';
  }

  function resolveTheme(pref) {
    if (pref === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    return pref;
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    toggles.forEach(toggle => {
      toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    });
  }

  function persistTheme(pref) {
    localStorage.setItem(STORAGE_KEY, pref);
    // Sync to the account so the choice sticks across pages and devices.
    const api = window.AniMAL || {};
    if (api.userId && api.csrf) {
      const body = new URLSearchParams();
      body.append('csrf_token', api.csrf);
      body.append('theme', pref);
      fetch('api/preferences.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .catch(() => {});
    }
  }

  applyTheme(resolveTheme(getPreference()));

  toggles.forEach(toggle => toggle.addEventListener('click', () => {
    const next = resolveTheme(getPreference()) === 'dark' ? 'light' : 'dark';
    persistTheme(next);
    applyTheme(next);
  }));

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (getPreference() === 'system') {
      applyTheme(resolveTheme('system'));
    }
  });
}

/* ── Motion toggle (animations on/off, overrides OS reduced-motion) ── */
function initMotionToggle() {
  const M_KEY = 'animations';
  const toggles = document.querySelectorAll('.motion-toggle');
  if (!toggles.length) return;

  function currentMotion() {
    const stored = localStorage.getItem(M_KEY);
    if (stored === 'on') return true;
    if (stored === 'off') return false;
    return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function applyMotion(on) {
    if (on) {
      document.documentElement.setAttribute('data-animations', 'on');
    } else {
      document.documentElement.setAttribute('data-animations', 'off');
    }
    toggles.forEach(toggle => {
      toggle.classList.toggle('is-on', on);
      toggle.setAttribute('aria-checked', on ? 'true' : 'false');
      toggle.setAttribute('aria-label', on ? 'Turn animations off' : 'Turn animations on');
    });
  }

  applyMotion(currentMotion());

  toggles.forEach(toggle => toggle.addEventListener('click', () => {
    const next = !currentMotion();
    localStorage.setItem(M_KEY, next ? 'on' : 'off');
    applyMotion(next);
  }));
}

/* ── Sidebar (dashboard) ─────────────────────────────────── */
function initSidebar() {
  const toggle = document.getElementById('sidebar-toggle');
  const close = document.getElementById('sidebar-close');
  const overlay = document.getElementById('sidebar-overlay');
  const sidebar = document.getElementById('sidebar');
  const reopen = document.getElementById('sidebar-reopen');
  if (!sidebar) return;
  let restoreFocus = toggle;
  const isDesktop = () => window.innerWidth >= 992;
  const isCollapsed = () => document.documentElement.hasAttribute('data-sidebar-collapsed');
  const setCollapsed = (collapsed) => {
    document.documentElement.toggleAttribute('data-sidebar-collapsed', collapsed);
    try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
  };
  const focusables = () => [...sidebar.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])')];
  const syncDesktop = () => {
    if (isDesktop()) {
      if (isCollapsed()) {
        sidebar.setAttribute('inert', '');
        sidebar.setAttribute('aria-hidden', 'true');
      } else {
        sidebar.removeAttribute('inert');
        sidebar.setAttribute('aria-hidden', 'false');
      }
    } else if (sidebar.classList.contains('is-open')) {
      sidebar.removeAttribute('inert');
      sidebar.setAttribute('aria-hidden', 'false');
    } else {
      sidebar.setAttribute('inert', '');
      sidebar.setAttribute('aria-hidden', 'true');
    }
  };

  function openSidebar() {
    restoreFocus = document.activeElement || toggle;
    sidebar.classList.add('is-open');
    sidebar.removeAttribute('inert');
    sidebar.setAttribute('aria-hidden', 'false');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    if (overlay) overlay.classList.add('visible');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => focusables()[0]?.focus());
  }

  function closeSidebar(restore = true) {
    sidebar.classList.remove('is-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    if (overlay) overlay.classList.remove('visible');
    document.body.style.overflow = '';
    syncDesktop();
    if (restore && window.innerWidth < 992) requestAnimationFrame(() => (restoreFocus || toggle)?.focus());
  }

  syncDesktop();
  if (toggle) toggle.addEventListener('click', openSidebar);
  if (close) close.addEventListener('click', () => {
    if (isDesktop()) { setCollapsed(true); syncDesktop(); }
    else closeSidebar();
  });
  if (reopen) reopen.addEventListener('click', () => { setCollapsed(false); syncDesktop(); });
  if (overlay) overlay.addEventListener('click', closeSidebar);
  window.addEventListener('resize', syncDesktop);
  document.addEventListener('keydown', (e) => {
    if (!sidebar.classList.contains('is-open')) return;
    if (e.key === 'Escape') return closeSidebar();
    if (e.key !== 'Tab') return;
    const items = focusables();
    if (!items.length) return;
    if (e.shiftKey && document.activeElement === items[0]) { e.preventDefault(); items.at(-1).focus(); }
    else if (!e.shiftKey && document.activeElement === items.at(-1)) { e.preventDefault(); items[0].focus(); }
  });
  sidebar.querySelectorAll('.sidebar-link').forEach(link => link.addEventListener('click', () => {
    if (window.innerWidth < 992) closeSidebar(false);
  }));
}

/* ── Table of contents scrollspy ──────────────────────────── */
function initTocSpy() {
  const links = [...document.querySelectorAll('.toc-link')];
  if (!links.length) return;
  const headings = [...document.querySelectorAll('.legal-body h2[id]')];
  if (!headings.length) return;

  const byId = new Map(links.map(l => [(l.getAttribute('href') || '').slice(1), l]));
  const setActive = (id) => {
    links.forEach(l => l.classList.toggle('is-active', l === byId.get(id)));
  };

  let paused = false;
  let ticking = false;
  const update = () => {
    ticking = false;
    if (paused) return;
    const line = 140;
    let id = headings[0].id;
    for (const h of headings) {
      if (h.getBoundingClientRect().top <= line) id = h.id;
    }
    setActive(id);
  };
  const onScroll = () => {
    if (!ticking) { ticking = true; requestAnimationFrame(update); }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(ev => {
    window.addEventListener(ev, () => { paused = false; }, { passive: true });
  });
  links.forEach(link => link.addEventListener('click', () => {
    paused = true;
    setActive((link.getAttribute('href') || '').slice(1));
  }));

  update();
}

/* ── Initialize ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();
  initMotionToggle();
  initCookieBanner();
  initScrollReveal();
  initLocaleToggle();
  initModals();
  initFlashAutoDismiss();
  initFaqToggle();
  initFilterButtons();
  initCartButtons();
  initCarouselControls();
  initFastAddButtons();
  initStockToggles();
  initNavScroll();
  initMobileMenu();
  initSidebar();
  initTocSpy();

  // Show any toasts already in markup
  document.querySelectorAll('.toast-item').forEach(toast => {
    toast.classList.add('toast-visible');
  });
});