<?php
$pageTitle = $pageTitle ?? ($config['app']['name'] ?? 'AniMAL');
$appName = $config['app']['name'] ?? 'AniMAL';
$currentLocale = function_exists('locale_current') ? locale_current() : 'en';
$isIndex = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';

// Build canonical URL and hreflang alternates
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$baseUrl = rtrim($config['app']['base_url'] ?? '', '/');
// Fall back to the origin actually serving this request. Without this, an empty
// or stale base_url emits a canonical on the wrong host, and og/twitter images
// become relative URLs, which crawlers reject.
if ($baseUrl === '') {
    $baseUrl = "{$protocol}://{$host}";
}
$canonicalUrl = $baseUrl . $requestUri;
$canonicalUrl = strtok($canonicalUrl, '?'); // strip query for canonical
?>
<?php
// Load i18n copy for header nav
$i18nCopy = include __DIR__ . '/../../src/i18n_copy.php';
?>
<!doctype html>
<html lang="<?= e($currentLocale) ?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>
    <meta name="description" content="<?= e($currentLocale === 'tl' ? 'AniMAL — Merkadong agrikultura ng Pilipinas. Mga na-verify na magsasaka, patas na presyo, sariwa mula sa sakahan.' : 'AniMAL — Philippine agricultural marketplace connecting verified farms directly with buyers. Farm to table, no middlemen.') ?>" />
    <meta name="theme-color" content="#1F4B32" />

    <!-- Canonical & hreflang -->
    <link rel="canonical" href="<?= e($canonicalUrl) ?>" />
    <link rel="alternate" hreflang="en" href="<?= e($canonicalUrl . ($currentLocale === 'en' ? '' : '?lang=en')) ?>" />
    <link rel="alternate" hreflang="tl" href="<?= e($canonicalUrl . ($currentLocale === 'tl' ? '' : '?lang=tl')) ?>" />
    <link rel="alternate" hreflang="x-default" href="<?= e($canonicalUrl . '?lang=en') ?>" />

    <!-- Open Graph / Social -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?= e($appName) ?> — Farm to table, no middlemen" />
    <meta property="og:description" content="<?= e($i18nCopy['og_description'][$currentLocale] ?? $i18nCopy['og_description']['en'] ?? '') ?>" />
    <meta property="og:image" content="<?= e($baseUrl) ?>/assets/branding/mark.svg" />
    <meta property="og:url" content="<?= e($canonicalUrl) ?>" />
    <meta property="og:site_name" content="AniMAL" />
    <meta property="og:locale" content="<?= $currentLocale === 'tl' ? 'tl_PH' : 'en_PH' ?>" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= e($appName) ?> — Farm to table, no middlemen" />
    <meta name="twitter:description" content="<?= e($i18nCopy['og_description'][$currentLocale] ?? $i18nCopy['og_description']['en'] ?? '') ?>" />
    <meta name="twitter:image" content="<?= e($baseUrl) ?>/assets/branding/mark.svg" />

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/branding/mark.svg" />
    <link rel="apple-touch-icon" href="assets/branding/mark.svg" />

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json" />
    <meta name="theme-color" content="#1F4B32" />

    <!-- Bootstrap 5.3.3 CSS (local vendor copy — offline) -->
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet" />

    <!-- Bootstrap Icons (local vendor copy — offline) -->
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet" />

    <!-- Prevent dark-mode flash: apply theme class before first paint -->
    <?php
    $dbTheme = null;
    $dbReduceMotion = false;
    $dbLargerText = false;
    if (current_user_id()) {
        try {
            $stmt = DB::pdo()->prepare('SELECT theme, reduce_motion, larger_text FROM user_preferences WHERE user_id = :id LIMIT 1');
            $stmt->execute(['id' => current_user_id()]);
            $row = $stmt->fetch();
            if ($row && in_array($row['theme'] ?? null, ['system', 'light', 'dark'], true)) {
                $dbTheme = $row['theme'];
            }
            $dbReduceMotion = !empty($row['reduce_motion']);
            $dbLargerText = !empty($row['larger_text']);
        } catch (Throwable $e) {
            // fallback to default behavior below
        }
    }
    ?>
    <script>
    (function () {
      // ----- JS-available flag -----
      // Set here, before any stylesheet is parsed, so CSS can gate its
      // pre-animation hidden states on .js. If this never runs (JS disabled)
      // or a deferred script fails, .js is absent and those elements stay
      // visible instead of being stranded at opacity: 0.
      document.documentElement.classList.add('js');

      // ----- Theme (persistent: localStorage is the source of truth; the DB
      // preference seeds it only when nothing is stored yet, so page actions
      // never flip the user's choice back) -----
      window.AniMAL = window.AniMAL || {};
      window.AniMAL.userId = <?= json_encode(current_user_id() ?: null) ?>;
      window.AniMAL.csrf = <?= json_encode(csrf_token()) ?>;
      var pref = localStorage.getItem('animal_theme');
      if (pref !== 'system' && pref !== 'light' && pref !== 'dark') {
        pref = <?= json_encode($dbTheme) ?> || 'system';
        localStorage.setItem('animal_theme', pref);
      }
      var resolved = pref === 'system'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : pref;
      document.documentElement.setAttribute('data-theme', resolved);

      // ----- Animations (localStorage 'animations' = 'on'|'off'; absent ⇒ follow OS) -----
      var animPref = localStorage.getItem('animations');
      if (animPref === 'on') document.documentElement.setAttribute('data-animations', 'on');
      else if (animPref === 'off') document.documentElement.setAttribute('data-animations', 'off');

      // ----- Reduce motion / larger text -----
      // The stored preference is the source of truth. A cached localStorage copy
      // used to win here, so a change saved on settings.php was ignored until
      // the browser storage was cleared.
      document.documentElement.toggleAttribute('data-reduce-motion', <?= $dbReduceMotion ? 'true' : 'false' ?>);
      document.documentElement.toggleAttribute('data-larger-text', <?= $dbLargerText ? 'true' : 'false' ?>);
    })();
    </script>
    <script>
    (function () {
      var themeBtn = document.querySelector('.theme-toggle');
      if (themeBtn) {
        themeBtn.addEventListener('click', function () {
          var pref = localStorage.getItem('animal_theme');
          if (pref !== 'system' && pref !== 'light' && pref !== 'dark') pref = 'system';
          var next = pref === 'dark' ? 'light' : pref === 'light' ? 'system' : 'dark';
          localStorage.setItem('animal_theme', next);
          document.cookie = 'animal_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
          location.reload();
        });
        var updateThemeLabel = function () {
          var pref = localStorage.getItem('animal_theme');
          if (!pref) pref = 'system';
          var resolved = pref === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : pref;
          themeBtn.setAttribute('aria-label', resolved === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        };
        updateThemeLabel();
        var observer = new MutationObserver(updateThemeLabel);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
      }

    })();
    </script>

    <!-- Local styles. design-system.css owns the :root token definitions and
         loads last, so it is the single source of truth. Do not add a second
         token sheet here: the page pays a render-blocking request and the
         duplicate definitions have no effect. -->
    <link href="<?= e(asset('assets/css/styles.min.css')) ?>" rel="stylesheet" />
    <link href="<?= e(asset('assets/css/motion.min.css')) ?>" rel="stylesheet" />
    <!-- Self-hosted harvest typography (offline) -->
    <link href="<?= e(asset('assets/fonts/fonts.css')) ?>" rel="stylesheet" />
    <link href="<?= e(asset('assets/css/design-system.css')) ?>" rel="stylesheet" />
    <link rel="preload" as="font" type="font/woff2" href="<?= e(asset('assets/fonts/Fraunces-400-normal-5.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(asset('assets/fonts/Source Sans Pro-400-normal-13.woff2')) ?>" crossorigin>

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "LocalBusiness",
          "@id": "<?= e($canonicalUrl) ?>#business",
          "name": "AniMAL",
          "description": <?= json_encode($currentLocale === 'tl'
                ? 'Merkadong agrikultura ng Pilipinas. Mga na-verify na magsasaka, patas na presyo, sariwa mula sa sakahan.'
                : 'Philippine agricultural marketplace connecting verified farms directly with buyers. Farm to table, no middlemen.', JSON_UNESCAPED_UNICODE) ?>,
          "url": "<?= e($canonicalUrl) ?>",
          "logo": "<?= e($baseUrl) ?>/assets/branding/mark.svg",
          "image": "<?= e($baseUrl) ?>/assets/branding/mark.svg",
          "address": {
            "@type": "PostalAddress",
            "addressCountry": "PH"
          },
          "priceRange": "PHP",
          "currenciesAccepted": "PHP",
          "paymentAccepted": "Cash, GCash, Bank Transfer",
          "areaServed": "Philippines",
          "slogan": <?= json_encode($currentLocale === 'tl' ? 'Sariwa mula sa sakahan, sa pintuan mo sa 24 oras. Walang gitna.' : 'Farm to your door in 24 hours. No middlemen.', JSON_UNESCAPED_UNICODE) ?>
        },
        {
          "@type": "FAQPage",
          "mainEntity": [
            <?php
            $faqItems = $copy['faq'][$currentLocale]['items'] ?? $copy['faq']['en']['items'] ?? [];
            $faqJson = [];
            foreach ($faqItems as $faq) {
                $faqJson[] = '{
                    "@type": "Question",
                    "name": ' . json_encode($faq['question']) . ',
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": ' . json_encode($faq['answer']) . '
                    }
                }';
            }
            echo implode(',' . PHP_EOL . '            ', $faqJson);
            ?>
          ]
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "<?= e($canonicalUrl) ?>"
            }
          ]
        }
      ]
    }
    </script>
</head>
<body>
<!-- Skip link for accessibility -->
<a href="#main-content" class="visually-hidden-focusable skip-link">Skip to main content</a>

<header class="nav" role="banner">
  <div class="nav-inner container">
    <div class="brand">
      <a href="index.php" class="brand-link" aria-label="AniMAL home">
        <img src="assets/branding/logo-horizontal.svg" alt="AniMAL" class="brand-logo">
      </a>
    </div>
    <nav class="nav-links" aria-label="Main navigation" id="mainNav">
      <?php if (current_user_id()): ?>
        <?php $role = current_user_role(); ?>
        <?php if ($role === 'farmer'): ?>
          <a href="create_listing.php"><span class="label"><?= t($i18nCopy['nav'], 'sell') ?></span></a>
        <?php endif; ?>
        <a href="browse.php"><span class="label"><?= t($i18nCopy['nav'], 'marketplace') ?></span></a>
        <a href="index.php#steps"><span class="label"><?= t($i18nCopy['nav'], 'how_it_works') ?></span></a>
        <a href="index.php#faq"><span class="label"><?= t($i18nCopy['nav'], 'faq') ?></span></a>
      <?php else: ?>
        <a href="browse.php"><span class="label"><?= t($i18nCopy['nav'], 'marketplace') ?></span></a>
        <a href="index.php#steps"><span class="label"><?= t($i18nCopy['nav'], 'how_it_works') ?></span></a>
        <a href="index.php#story"><span class="label"><?= t($i18nCopy['nav'], 'our_story') ?></span></a>
        <a href="index.php#faq"><span class="label"><?= t($i18nCopy['nav'], 'faq') ?></span></a>
      <?php endif; ?>
    </nav>
    <button class="hamburger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
    </button>
    <div class="nav-cta">
      <div class="nav-utils">
        <?php if (current_user_id()): ?>
        <a href="dashboard.php" class="nav-text-link"><?= t($i18nCopy['nav'], 'dashboard') ?></a>
        <?php endif; ?>
        <button class="theme-toggle" type="button" aria-label="Switch to dark mode">
          <i class="bi bi-moon icon-moon" aria-hidden="true"></i>
          <i class="bi bi-sun icon-sun" aria-hidden="true"></i>
        </button>
        <button class="motion-toggle" type="button" role="switch" aria-checked="true" aria-label="Turn animations off">
          <i class="bi bi-lightning-charge motion-icon-on" aria-hidden="true"></i>
          <i class="bi bi-lightning motion-icon-off" aria-hidden="true"></i>
        </button>
        <div class="dropdown locale-dd">
          <button class="locale-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Language: <?= e(strtoupper($currentLocale)) ?>" title="<?= e(strtoupper($currentLocale)) ?>">
            <i class="bi bi-globe" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= e(locale_url('en')) ?>" data-lang="en">English <?= $currentLocale === 'en' ? '<span class="locale-tick small">✓</span>' : '' ?></a></li>
            <li><a class="dropdown-item" href="<?= e(locale_url('tl')) ?>" data-lang="tl">Filipino <?= $currentLocale === 'tl' ? '<span class="locale-tick small">✓</span>' : '' ?></a></li>
          </ul>
        </div>
        <?php if (current_user_id()): ?>
          <?php
            $unreadCount = unread_notification_count((int) current_user_id());
            $notifLabel = $unreadCount > 0
                ? sprintf(t($i18nCopy['notifications'], 'unread_label'), $unreadCount)
                : t($i18nCopy['notifications'], 'read_label');
          ?>
          <a href="notifications.php" class="notif-pill" aria-label="<?= e($notifLabel) ?>">
            <?php if ($unreadCount > 0): ?>
              <span class="notif-count"><?= $unreadCount ?></span>
            <?php endif; ?>
            <i class="bi bi-bell" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
        <?php if (current_user_role() === 'buyer' || !current_user_id()): ?>
          <?php
            $cartCount = 0;
            if (current_user_id() && current_user_role() === 'buyer') {
                try {
                    $cc = DB::pdo()->prepare('SELECT COUNT(*) FROM cart_items WHERE buyer_id = :id');
                    $cc->execute(['id' => current_user_id()]);
                    $cartCount = (int) $cc->fetchColumn();
                } catch (Exception $e) {}
            }
            $cartUrl = current_user_id() ? 'cart.php' : login_url('cart.php');
          ?>
          <a href="<?= e($cartUrl) ?>" class="cart-pill" aria-label="Basket, <?= $cartCount ?> items">
            <span class="cart-count"><?= $cartCount ?></span>
            <i class="bi bi-basket" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
        <?php if (current_user_id()): ?>
          <?php
            // Fetch user profile data for avatar
            $userProfile = ['email' => 'User', 'role' => current_user_role(), 'status' => 'active', 'name' => ''];
            try {
                $uStmt = DB::pdo()->prepare('SELECT email, role, status FROM users WHERE id = :id');
                $uStmt->execute(['id' => current_user_id()]);
                $uRow = $uStmt->fetch();
                if ($uRow) $userProfile = array_merge($userProfile, $uRow);
            } catch (Exception $e) {}
            $emailUser = explode('@', $userProfile['email'])[0];
            $initials = strtoupper(substr($emailUser, 0, 2));
            $nameUser = !empty($userProfile['name']) ? $userProfile['name'] : $emailUser;
            $role = $userProfile['role'];
            $roleLabels = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
            $avatarBg = ['farmer' => 'var(--primary)', 'buyer' => 'var(--accent)', 'admin' => '#C9A227'];
          ?>
          <div class="dropdown profile-dd">
            <button class="profile-btn dropdown-toggle" type="button" id="avatarDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
              <span class="profile-avatar" style="background: <?= e($avatarBg[$role] ?? 'var(--accent)') ?>"><?= e($initials) ?></span>
              <span class="profile-meta">
                <span class="profile-name"><?= e(ucfirst($nameUser)) ?></span>
                <span class="role-pill"><?= e($roleLabels[$role] ?? ucfirst($role)) ?></span>
              </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end profile-dd-menu" aria-labelledby="avatarDropdown">
              <li>
                <div class="profile-dd-head">
                  <span class="profile-dd-name"><?= e(ucfirst($nameUser)) ?></span>
                  <span class="profile-dd-email"><?= e($userProfile['email']) ?></span>
                </div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Log out</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="login.php" class="nav-text-link"><?= t($i18nCopy['nav'], 'login') ?></a>
          <a href="register.php" class="nav-text-link nav-link-sell"><?= t($i18nCopy['nav'], 'sell') ?></a>
          <a href="browse.php" class="btn-primary-nav nav-sell-btn nav-cta-primary"><?= t($i18nCopy['nav'], 'browse') ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<!-- Mobile menu drawer -->
<div id="mobile-menu" class="mobile-menu" aria-hidden="true" inert>
  <div class="mobile-menu-inner">
    <nav class="mobile-nav" aria-label="Mobile navigation">
      <?php if (current_user_id()): ?>
        <?php $mobRole = current_user_role(); ?>
        <?php if ($mobRole === 'farmer'): ?>
          <a href="dashboard.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'dashboard') ?></a>
          <a href="create_listing.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'sell') ?></a>
        <?php elseif ($mobRole === 'admin'): ?>
          <a href="dashboard.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'dashboard') ?></a>
        <?php else: ?>
          <a href="dashboard.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'dashboard') ?></a>
          <a href="browse.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'browse') ?></a>
        <?php endif; ?>
        <a href="index.php#steps" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'how_it_works') ?></a>
        <a href="index.php#faq" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'faq') ?></a>
      <?php else: ?>
        <a href="browse.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'marketplace') ?></a>
        <a href="index.php#steps" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'how_it_works') ?></a>
        <a href="index.php#story" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'our_story') ?></a>
        <a href="index.php#faq" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'faq') ?></a>
      <?php endif; ?>
    </nav>
    <div class="mobile-menu-utils" style="display:flex;gap:0.5rem;align-items:center;justify-content:center;flex-wrap:wrap;margin-bottom:0.5rem;">
      <?php if (current_user_id()): ?>
        <a href="notifications.php" class="notif-pill" aria-label="Notifications" style="width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--paper);border:1px solid var(--borders);color:var(--text);">
          <?php $unreadCount = unread_notification_count((int) current_user_id()); ?>
          <?php if ($unreadCount > 0): ?><span class="notif-count" style="position:absolute;top:0;right:0;background:var(--danger);color:#fff;font-size:0.6rem;padding:0.1rem 0.35rem;border-radius:999px;"><?= $unreadCount ?></span><?php endif; ?>
          <i class="bi bi-bell" aria-hidden="true"></i>
        </a>
        <a href="cart.php" class="cart-pill" aria-label="Basket" style="width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--paper);border:1px solid var(--borders);color:var(--text);">
          <?php
            $cartCount = 0;
            if (current_user_role() === 'buyer') {
              try { $cc = DB::pdo()->prepare('SELECT COUNT(*) FROM cart_items WHERE buyer_id = :id'); $cc->execute(['id'=>current_user_id()]); $cartCount = (int)$cc->fetchColumn(); } catch(Exception $e){}
            }
          ?>
          <?php if ($cartCount > 0): ?><span class="cart-count" style="position:absolute;top:0;right:0;background:var(--danger);color:#fff;font-size:0.6rem;padding:0.1rem 0.35rem;border-radius:999px;"><?= $cartCount ?></span><?php endif; ?>
          <i class="bi bi-basket" aria-hidden="true"></i>
        </a>
      <?php else: ?>
        <a href="<?= e(login_url('cart.php')) ?>" class="cart-pill" aria-label="Basket (login to view)" style="width:2.75rem;height:2.75rem;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--paper);border:1px solid var(--borders);color:var(--text);"><i class="bi bi-basket" aria-hidden="true"></i></a>
      <?php endif; ?>
    </div>
    <div class="mobile-menu-actions">
      <?php if (current_user_id()): ?>
        <a href="settings.php" class="btn btn-outline"><i class="bi bi-gear" aria-hidden="true"></i> <?= t($i18nCopy['settings'], 'nav_settings') ?></a>
        <a href="logout.php" class="btn btn-primary"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> <?= t($i18nCopy['nav'], 'logout') ?></a>
      <?php else: ?>
        <a href="register.php" class="btn btn-primary"><?= t($i18nCopy['nav'], 'sell') ?></a>
        <a href="login.php" class="btn btn-outline"><?= t($i18nCopy['nav'], 'login') ?></a>
      <?php endif; ?>
    </div>
    <div class="mobile-menu-locale">
      <span class="locale-label <?= $currentLocale === 'en' ? 'active' : '' ?>"><a href="<?= e(locale_url('en')) ?>">EN</a></span>
      <span class="locale-sep" aria-hidden="true">/</span>
      <span class="locale-label <?= $currentLocale === 'tl' ? 'active' : '' ?>"><a href="<?= e(locale_url('tl')) ?>">TL</a></span>
    </div>
  </div>
</div>

