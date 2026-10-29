<?php
// Footer i18n is self-contained; works on any page without $copy/$l from caller.
$footerCopy = $copy['footer'] ?? [
    'en' => [
        'brand_tagline' => 'Philippine Agricultural Marketplace',
        'heading_market' => 'Market',
        'heading_for_farmers' => 'For farmers',
        'heading_company' => 'Company',
        'link_browse_listings' => 'Browse listings',
        'link_how_it_works' => 'How it works',
        'link_faq' => 'FAQ',
        'link_become_a_seller' => 'Become a seller',
        'link_seller_handbook' => 'Seller handbook',
        'link_verification' => 'Verification',
        'link_our_story' => 'Our story',
        'link_contact' => 'Contact',
        'link_privacy' => 'Privacy',
        'link_terms' => 'Terms',
    ],
    'tl' => [
        'brand_tagline' => 'Pamilihang Agrikultura ng Pilipinas',
        'heading_market' => 'Mercado',
        'heading_for_farmers' => 'Para sa mga magsasaka',
        'heading_company' => 'Kumpanya',
        'link_browse_listings' => 'Mag-browse',
        'link_how_it_works' => 'Paano ito gumagana',
        'link_faq' => 'FAQ',
        'link_become_a_seller' => 'Magbenta',
        'link_seller_handbook' => 'Seller handbook',
        'link_verification' => 'Verification',
        'link_our_story' => 'Ang kwento namin',
        'link_contact' => 'Contact us',
        'link_privacy' => 'Privacy',
        'link_terms' => 'Mga termino',
    ],
];
$f = function ($key) use ($footerCopy) { return t($footerCopy, $key); };
$cookieCopy = $copy['cookie'] ?? [
'en' => ['text' => 'We use essential cookies to make this site work, and we save your choice on this device. No trackers or third-party scripts run here.', 'learn_more' => 'Learn more', 'accept' => 'Accept', 'decline' => 'Decline'],
      'tl' => ['text' => 'Gumagamit kami ng mga essential cookies para gumana ang site, at iniimpoko namin ang iyong pinili sa device na ito. Walang tracker o third-party script na tumatakbo dito.', 'learn_more' => 'Matuto pa', 'accept' => 'Tanggapin', 'decline' => 'Tanggihan'],
];
$ck = function ($key) use ($cookieCopy) { return t($cookieCopy, $key); };
?>
<footer class="footer" role="contentinfo">
  <div class="inside container">
    <div class="brand">
      <img src="assets/branding/logo-reverse.svg" alt="AniMALL" class="brand-logo">
      <div class="brand-tagline"><?= $f('brand_tagline') ?></div>
    </div>
    <div class="cols">
      <div>
        <h2><?= $f('heading_market') ?></h2>
        <a href="browse.php"><?= $f('link_browse_listings') ?></a>
        <a href="index.php#steps"><?= $f('link_how_it_works') ?></a>
        <a href="index.php#faq"><?= $f('link_faq') ?></a>
      </div>
      <div>
<h2><?= $f('heading_for_farmers') ?></h2>
<?php /* register.php redirects signed-in users to dashboard; "Become a seller" would link nowhere. Seller handbook stays useful. */ ?>
<?php if (!current_user_id()): ?><a href="register.php"><?= $f('link_become_a_seller') ?></a><?php endif; ?>
        <a href="index.php#faq"><?= $f('link_seller_handbook') ?></a>
        <a href="index.php#faq"><?= $f('link_verification') ?></a>
      </div>
      <div>
        <h2><?= $f('heading_company') ?></h2>
        <a href="index.php#story"><?= $f('link_our_story') ?></a>
        <a href="mailto:support@animal.ph"><?= $f('link_contact') ?></a>
        <a href="privacy.php"><?= $f('link_privacy') ?></a>
        <a href="terms.php"><?= $f('link_terms') ?></a>
      </div>
    </div>
  </div>
  <p class="copyright">&copy; <?= date('Y') ?> AniMALL &middot; FarmRoute Cooperative. Built for Philippine farms.</p>
</footer>

<!-- Back to top button -->
<button id="backToTop" class="back-to-top" type="button" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

<!-- Toast region removed -->


<!-- Cookie Consent Banner -->
<div id="cookie-banner" class="cookie-banner" role="dialog" aria-label="Cookie consent" aria-describedby="cookie-banner-text">
    <div class="cookie-banner__content">
        <p id="cookie-banner-text" class="cookie-banner__text mb-0">
            <?= $ck('text') ?>
            <a href="cookies.php"><?= $ck('learn_more') ?></a>
        </p>
<div class="cookie-banner__actions">
              <button type="button" class="btn btn-primary btn-sm" data-cookie-accept><?= $ck('accept') ?></button>
              <button type="button" class="btn btn-sm cookie-banner__decline" data-cookie-decline><?= $ck('decline') ?></button>
          </div>
    </div>
</div>

    <?php /* Emitted at most once per request. browse.php and dashboard.php render
     this partial alongside dashboard-footer.php, and a classic script included
     twice is evaluated twice: the second pass re-declares motion.min.js's
     top-level `const prefersReducedMotion`, which is a SyntaxError that takes
     the entire file down with it -- not just the duplicate. Whichever partial
     renders first wins, so the set is the same either way. */ ?>
<?php if (!defined('ANIMAL_ASSETS_EMITTED')): define('ANIMAL_ASSETS_EMITTED', true); ?>
    <script src="<?= e(asset('assets/js/motion.min.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/swipe.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/cart-ajax.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/save-ajax.js')) ?>" defer></script>
<?php endif; ?>
    <script>
    (function () {
      const btn = document.getElementById('backToTop');
      if (!btn) return;
      const toggle = () => {
        btn.classList.toggle('is-visible', window.scrollY > 200);
      };
      window.addEventListener('scroll', toggle, { passive: true });
      toggle();
      btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: document.documentElement.dataset.animations === 'off' || window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      });
    })();
    </script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
<script>
(function () {
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.dataset.passwordToggle) return;
    input.dataset.passwordToggle = '1';
    var wrap = document.createElement('div');
    wrap.style.position = 'relative';
    wrap.style.width = '100%';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.style.paddingRight = '44px';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Show password');
    btn.style.position = 'absolute';
    btn.style.right = '10px';
    btn.style.top = '50%';
    btn.style.transform = 'translateY(-50%)';
    btn.style.background = 'transparent';
    btn.style.border = 'none';
    btn.style.padding = '2px 4px';
    btn.style.color = 'currentColor';
    btn.style.cursor = 'pointer';
    btn.innerHTML = '<i class="bi bi-eye"></i>';
    wrap.appendChild(btn);
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });
})();
</script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').then((reg) => {
        console.log('SW registered:', reg.scope);
      }).catch((err) => {
        console.log('SW registration failed:', err);
      });
    });
  }
</script>
<script>
  // Seamless pagination on the marketplace grid (no full page reload).
  (function () {
    var grid = document.getElementById('product-grid');
    if (!grid) return;
    var container = grid.closest('section') || grid.parentElement;
    function loadPage(url, push) {
      fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var newGrid = doc.getElementById('product-grid');
        var newPager = doc.querySelector('.browse-pager');
        var oldPager = document.querySelector('.browse-pager');
        if (newGrid) {
          grid.innerHTML = newGrid.innerHTML;
          // reveal new cards
          grid.querySelectorAll('[data-reveal]').forEach(function (el) { el.classList.add('reveal-visible'); });
        }
        if (newPager && oldPager) {
          oldPager.outerHTML = newPager.outerHTML;
        } else if (!newPager && oldPager) {
          oldPager.remove();
        }
        if (push) history.pushState({ url: url }, '', url);
        grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function () { window.location.href = url; });
    }
    document.addEventListener('click', function (e) {
      var a = e.target.closest('.browse-pager a');
      if (!a) return;
      e.preventDefault();
      loadPage(a.getAttribute('href'), true);
    });
    window.addEventListener('popstate', function (e) {
      if (e.state && e.state.url) loadPage(e.state.url, false);
    });
  })();
</script>
<script>
  // Make whole product cards clickable.
  (function () {
    document.addEventListener('click', function (e) {
      var card = e.target.closest('[data-listing-url]');
      if (!card) return;
      if (e.target.closest('a, button, input, select, textarea, form, .btn')) return;
      window.location.href = card.getAttribute('data-listing-url');
    });
  })();
</script>
<script>
  // Category filter applies to the whole catalogue (server-side).
  (function () {
    var grid = document.getElementById('product-grid');
    if (!grid) return;
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.filter-pill');
      if (!btn) return;
      var url = btn.getAttribute('data-filter-url');
      if (!url) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      document.querySelectorAll('.filter-pill').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var newGrid = doc.getElementById('product-grid');
        var newPager = doc.querySelector('.browse-pager');
        var oldPager = document.querySelector('.browse-pager');
        if (newGrid) {
          grid.innerHTML = newGrid.innerHTML;
          grid.querySelectorAll('[data-reveal]').forEach(function (el) { el.classList.add('reveal-visible'); });
        }
        if (newPager && oldPager) oldPager.outerHTML = newPager.outerHTML;
        else if (!newPager && oldPager) oldPager.remove();
        history.pushState({ url: url }, '', url);
        grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function () { window.location.href = url; });
    });
  })();
</script>
<script>
  // Logout confirmation (toast-style dialog) before navigating to logout.php.
  (function () {
    function confirmLogout(href) {
      if (document.getElementById('logout-confirm-overlay')) return;
      var overlay = document.createElement('div');
      overlay.id = 'logout-confirm-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:9999;';
      var box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);color:var(--text,#111);border-radius:12px;padding:20px 24px;max-width:320px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.25);font-family:var(--font-body,system-ui,sans-serif);';
      box.innerHTML = '<p style="margin:0 0 16px;font-size:16px;font-weight:600;">Log out?</p>' +
        '<p style="margin:0 0 16px;font-size:14px;opacity:.8;">You will need to sign in again to access your account.</p>' +
        '<div style="display:flex;gap:8px;justify-content:flex-end;">' +
        '<button type="button" id="lc-cancel" class="btn btn-outline btn-sm">Cancel</button>' +
        '<button type="button" id="lc-ok" class="btn btn-primary btn-sm">Log out</button>' +
        '</div>';
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) cleanup(); });
      document.getElementById('lc-cancel').addEventListener('click', cleanup);
      document.getElementById('lc-ok').addEventListener('click', function () { window.location.href = href; });
      function cleanup() { overlay.remove(); }
      document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { cleanup(); document.removeEventListener('keydown', esc); } });
    }
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href*="logout.php"]');
      if (!a) return;
      e.preventDefault();
      confirmLogout(a.getAttribute('href'));
    });
  })();
</script>
<script>
  (function () {
    var box = document.getElementById('recently-viewed');
    var list = document.getElementById('recently-viewed-list');
    if (!box || !list) return;
    try {
      var items = JSON.parse(localStorage.getItem('animall_recent') || '[]');
      if (!items.length) return;
      items.forEach(function (it) {
        var a = document.createElement('a');
        a.href = 'listing.php?id=' + it.id;
        a.className = 'text-decoration-none';
        a.style.cssText = 'min-width:120px;max-width:140px;';
        a.innerHTML = '<div class="card h-100"><img src="' + (it.img || 'assets/images/products/market.jpg') + '" style="height:80px;object-fit:cover;" class="card-img-top" alt=""><div class="card-body p-2"><div class="small fw-semibold text-truncate">' + (it.name || '') + '</div><div class="small text-muted">' + (it.price || '') + '</div></div></div>';
        list.appendChild(a);
      });
      box.style.display = '';
    } catch (e) {}
  })();
</script>
<script>
  // Search autocomplete (debounced) using the FULLTEXT index via api/search_suggest.php
  (function () {
    var input = document.getElementById('market-search');
    var dl = document.getElementById('search-suggestions');
    if (!input || !dl) return;
    var timer = null;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { dl.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch('api/search_suggest.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            dl.innerHTML = '';
            (data.suggestions || []).forEach(function (s) {
              var o = document.createElement('option');
              o.value = s;
              dl.appendChild(o);
            });
          }).catch(function () {});
      }, 250);
    });
  })();
</script>
</body>
</html>
