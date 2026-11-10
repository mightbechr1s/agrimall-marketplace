    </main>
</div><!-- /.sidebar-main -->

    <?php /* See footer.php: a page may render both footers, and a classic script
     included twice re-declares its top-level const, killing the whole file.
     This set is skipped when footer.php has already emitted it. */ ?>
<?php if (!defined('ANIMAL_ASSETS_EMITTED')): define('ANIMAL_ASSETS_EMITTED', true); ?>
    <script src="<?= e(asset('assets/js/motion.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/toast.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/cart-ajax.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/save-ajax.js')) ?>" defer></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
<?php endif; ?>

<script>
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
  (function () {
    var grid = document.getElementById('product-grid');
    if (!grid) return;
    function loadPage(url, push) {
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
  (function () {
    function confirmLogout(href) {
      if (document.getElementById('logout-confirm-overlay')) return;
      var overlay = document.createElement('div');
      overlay.id = 'logout-confirm-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:9999;';
      var box = document.createElement('div');
      box.style.cssText = 'background:var(--paper,#1e1e1e);color:var(--text,#f1f3f5);border:1px solid var(--borders,rgba(255,255,255,.1));border-radius:12px;padding:20px 24px;max-width:320px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.35);font-family:var(--font-body,system-ui,sans-serif);';
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
<script src="assets/js/shortcuts.js" defer></script>
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
  <ul class="mobile-bottom-nav__list">
    <?php
    $bottomNavRole = current_user_role() ?: 'guest';
    $bottomNavItems = [];
    if (in_array($bottomNavRole, ['buyer', 'admin', 'manager'], true)) {
        $bottomNavItems = [
            ['href' => 'dashboard.php', 'icon' => 'bi-grid-1x2', 'label' => 'Home', 'page' => 'dashboard'],
            ['href' => 'browse.php', 'icon' => 'bi-shop', 'label' => 'Browse', 'page' => 'browse'],
            ['href' => 'cart.php', 'icon' => 'bi-cart3', 'label' => 'Cart', 'page' => 'cart', 'badge' => true],
            ['href' => 'orders.php', 'icon' => 'bi-receipt', 'label' => 'Orders', 'page' => 'orders'],
            ['href' => '#more', 'icon' => 'bi-grid-3x3-gap', 'label' => 'More', 'page' => 'more', 'toggle' => true],
        ];
    } elseif ($bottomNavRole === 'farmer') {
        $bottomNavItems = [
            ['href' => 'dashboard.php', 'icon' => 'bi-grid-1x2', 'label' => 'Home', 'page' => 'dashboard'],
            ['href' => 'browse.php', 'icon' => 'bi-shop', 'label' => 'Browse', 'page' => 'browse'],
            ['href' => 'my_listings.php', 'icon' => 'bi-basket3', 'label' => 'Listings', 'page' => 'listings'],
            ['href' => 'orders.php', 'icon' => 'bi-receipt', 'label' => 'Orders', 'page' => 'orders'],
            ['href' => '#more', 'icon' => 'bi-grid-3x3-gap', 'label' => 'More', 'page' => 'more', 'toggle' => true],
        ];
    } else {
        $bottomNavItems = [
            ['href' => 'index.php', 'icon' => 'bi-house', 'label' => 'Home', 'page' => 'home'],
            ['href' => 'browse.php', 'icon' => 'bi-shop', 'label' => 'Browse', 'page' => 'browse'],
            ['href' => 'login.php', 'icon' => 'bi-box-arrow-in-right', 'label' => 'Sign in', 'page' => 'login'],
        ];
    }
    $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
    foreach ($bottomNavItems as $item):
        $isActive = (basename($item['href']) === $currentPage);
        $isToggle = !empty($item['toggle']);
    ?>
    <li class="mobile-bottom-nav__item">
      <?php if ($isToggle): ?>
      <button type="button" class="mobile-bottom-nav__link mobile-bottom-nav__more" aria-label="More navigation">
        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
        <span><?= e($item['label']) ?></span>
      </button>
      <?php else: ?>
      <a href="<?= e($item['href']) ?>" class="mobile-bottom-nav__link<?= $isActive ? ' is-active' : '' ?>">
        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
        <span><?= e($item['label']) ?></span>
        <?php if (!empty($item['badge'])): ?>
        <span class="mobile-bottom-nav__badge cart-count" aria-label="Cart items">0</span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</nav>
<script>
(function () {
  var moreBtn = document.querySelector('.mobile-bottom-nav__more');
  var sidebar = document.querySelector('.sidebar');
  if (!moreBtn || !sidebar) return;
  function setOpen(open) {
    document.documentElement.setAttribute('data-mobile-drawer-open', open ? '1' : '0');
    // motion.js marks the closed drawer inert (out of the tab order). This
    // drawer is a second opener, so it has to undo that or every link in it
    // stays unclickable.
    sidebar.toggleAttribute('inert', !open);
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    // Lock body scroll
    document.body.style.overflow = open ? 'hidden' : '';
  }
  moreBtn.addEventListener('click', function () {
    var open = document.documentElement.getAttribute('data-mobile-drawer-open') === '1';
    setOpen(!open);
  });
  // Close drawer when a sidebar link is clicked
  if (sidebar) {
    sidebar.addEventListener('click', function (e) {
      var link = e.target.closest('a');
      if (link) setOpen(false);
    });
  }
  // Hide section labels on mobile where all items in the section are bottom-nav duplicates
  if (window.innerWidth <= 767) {
    var labels = sidebar.querySelectorAll('.sidebar-section-label');
    labels.forEach(function (label) {
      var next = label.nextElementSibling;
      var hasVisible = false;
      while (next && !next.classList.contains('sidebar-section-label')) {
        if (next.classList.contains('sidebar-link') && !next.classList.contains('mobile-bottom-dup')) {
          hasVisible = true;
          break;
        }
        next = next.nextElementSibling;
      }
      if (!hasVisible) label.style.display = 'none';
    });
  }
  // Close on escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
  // Swipe down to close
  var startY = 0;
  if (sidebar) {
    sidebar.addEventListener('touchstart', function (e) {
      startY = e.touches[0].clientY;
    }, { passive: true });
    sidebar.addEventListener('touchmove', function (e) {
      var dy = e.touches[0].clientY - startY;
      if (dy > 0 && sidebar.scrollTop === 0) {
        sidebar.style.transform = 'translateY(' + dy + 'px)';
      }
    }, { passive: true });
    sidebar.addEventListener('touchend', function (e) {
      var dy = e.changedTouches[0].clientY - startY;
      sidebar.style.transform = '';
      if (dy > 80) setOpen(false);
    }, { passive: true });
  }
})();
</script>
</body>
</html>
