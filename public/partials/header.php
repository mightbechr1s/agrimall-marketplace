<?php
$pageTitle = $pageTitle ?? ($config['app']['name'] ?? 'AgriMALL');
$appName = $config['app']['name'] ?? 'AgriMALL';
$currentLocale = function_exists('locale_current') ? locale_current() : 'en';
$isIndex = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';

// Build canonical URL and hreflang alternates
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$baseUrl = app_base_url();
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>
    <meta name="description" content="<?= e($currentLocale === 'tl' ? 'AgriMALL — Merkadong agrikultura at alagang hayop ng Pilipinas. Mga na-verify na magsasaka at raiser, patas na presyo, sariwa mula sa sakahan.' : 'AgriMALL — Philippine agribusiness marketplace connecting verified farms and raisers directly with buyers. Farm to table, no middlemen.') ?>" />
    <meta name="theme-color" content="#1b4332" />

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
    <meta property="og:site_name" content="AgriMALL" />
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
    <meta name="theme-color" content="#1b4332" />

    <!-- Bootstrap 5.3.3 CSS (local vendor copy — offline) -->
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet" />

    <!-- Bootstrap Icons (local vendor copy — offline) -->
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet" />

    <!-- Prevent dark-mode flash: apply theme class before first paint -->
    <?php
    // One query for everything <head> and the avatar need (theme prefs + user
    // row); the old two lookups per page share an indexed primary key anyway.
    $dbTheme = null;
    $reduceMotion = false;
    $largerText = false;
    $userProfile = ['email' => 'User', 'role' => current_user_role(), 'status' => 'active', 'name' => ''];
    if (current_user_id()) {
        try {
            $stmt = DB::pdo()->prepare(
                'SELECT p.theme, p.reduce_motion, p.larger_text, u.email, u.role, u.status
                   FROM users u LEFT JOIN user_preferences p ON p.user_id = u.id
                  WHERE u.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => current_user_id()]);
            $row = $stmt->fetch();
            if ($row) {
                if (in_array($row['theme'] ?? null, ['system', 'light', 'dark'], true)) {
                    $dbTheme = $row['theme'];
                }
                $reduceMotion = !empty($row['reduce_motion']);
                $largerText = !empty($row['larger_text']);
                $userProfile = array_merge($userProfile, [
                    'email' => $row['email'] ?? 'User',
                    'role' => $row['role'] ?? current_user_role(),
                    'status' => $row['status'] ?? 'active',
                ]);
            }
        } catch (Throwable $e) {
            // fallback to default behavior below
            error_log('header: user+prefs lookup failed: ' . $e->getMessage());
        }
    }
    $withSidebarState = false;
    require __DIR__ . '/fouc-guard.php';
    ?>
    <!-- Local styles. design-system.css is the single token source; duplicate sheets are render-blocking no-ops. -->
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
          "name": "AgriMALL",
          "description": <?= json_encode($currentLocale === 'tl'
                ? 'Merkadong agrikultura ng Pilipinas. Mga na-verify na magsasaka, patas na presyo, sariwa mula sa sakahan.'
                : 'Philippine agribusiness marketplace connecting verified farms directly with buyers. Farm to table, no middlemen.', JSON_UNESCAPED_UNICODE) ?>,
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
    <script>
      // Close any open profile dropdown when the page scrolls, so it never
      // overlaps/sticks over scrolling content (e.g. the reports section).
      (function () {
        var closeOpen = function () {
          document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
            if (window.bootstrap && bootstrap.Dropdown) {
              var t = document.querySelector('[data-bs-toggle="dropdown"][aria-expanded="true"]');
              if (t) bootstrap.Dropdown.getInstance(t)?.hide();
            } else {
              m.classList.remove('show');
            }
          });
        };
        window.addEventListener('scroll', closeOpen, true);
      })();
    </script>
</head>
<body>
<!-- Skip link for accessibility -->
<a href="#main-content" class="visually-hidden-focusable skip-link">Skip to main content</a>

<header class="nav" role="banner">
  <div class="nav-inner">
    <div class="nav-left">
      <div class="brand">
        <a href="index.php" class="brand-link" aria-label="AgriMALL home">
          <img src="assets/branding/mark.svg" alt="" class="brand-mark" width="30" height="30">
          <span class="brand-wordmark">AgriMALL</span>
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
    </div>
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
        <div class="dropdown locale-dd">
          <button class="locale-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Language: <?= e(strtoupper($currentLocale)) ?>" title="<?= e(strtoupper($currentLocale)) ?>">
            <i class="bi bi-globe" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= e(locale_url('en')) ?>" data-lang="en">English <?= $currentLocale === 'en' ? '<span class="locale-tick small"><i class="bi bi-check-lg" aria-hidden="true"></i></span>' : '' ?></a></li>
            <li><a class="dropdown-item" href="<?= e(locale_url('tl')) ?>" data-lang="tl">Filipino <?= $currentLocale === 'tl' ? '<span class="locale-tick small"><i class="bi bi-check-lg" aria-hidden="true"></i></span>' : '' ?></a></li>
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
                } catch (Exception $e) {
                    // Decorative query: degrade rather than break the page, but do not
                    // swallow it silently -- a failure here is invisible otherwise.
                    error_log('header: cart-count lookup failed: ' . $e->getMessage());
                }
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
            // Avatar data already fetched in <head> (user+prefs single query).
            $emailUser = explode('@', $userProfile['email'])[0];
            $initials = strtoupper(substr($emailUser, 0, 2));
            $nameUser = !empty($userProfile['name']) ? $userProfile['name'] : $emailUser;
            $role = $userProfile['role'];
            $roleLabels = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
            $avatarBg = ['farmer' => 'var(--primary)', 'buyer' => 'var(--accent)', 'admin' => '#C9A227'];
          ?>
          <div class="dropdown profile-dd">
            <button class="profile-btn dropdown-toggle" type="button" id="avatarDropdown" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Account menu">
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
              <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i><?= e($i18nCopy['nav']['en']['dashboard']) ?></a></li>
              <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i><?= e($i18nCopy['settings']['en']['nav_settings']) ?></a></li>
              <li><a class="dropdown-item" href="account.php"><i class="bi bi-person-gear me-2" aria-hidden="true"></i><?= e($i18nCopy['dashboard_nav']['en']['account']) ?></a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i><?= e($i18nCopy['nav']['en']['logout']) ?></a></li>
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

<?php
// Flash rendered here so redirect targets show it; flash_get() clears on read.
    if ($flashSuccess = flash_get('success')): ?>
  <div class="container">
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($flashSuccess) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
<?php endif; ?>
<?php if ($flashError = flash_get('error')): ?>
  <div class="container">
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($flashError) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
<?php endif; ?>

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
        <a href="get_app.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'get_app') ?></a>
      <?php else: ?>
        <a href="browse.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'marketplace') ?></a>
        <a href="index.php#steps" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'how_it_works') ?></a>
        <a href="index.php#story" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'our_story') ?></a>
        <a href="index.php#faq" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'faq') ?></a>
        <a href="get_app.php" class="mobile-nav-link"><?= t($i18nCopy['nav'], 'get_app') ?></a>
      <?php endif; ?>
    </nav>
    <?php if (current_user_id()): ?>
    <div class="mobile-menu-utils" style="display:flex;gap:0.5rem;align-items:center;justify-content:center;flex-wrap:wrap;margin-bottom:0.5rem;">
      <a href="notifications.php" class="notif-pill" aria-label="<?= e($notifLabel) ?>">
        <?php if ($unreadCount > 0): ?>
          <span class="notif-count"><?= $unreadCount ?></span>
        <?php endif; ?>
        <i class="bi bi-bell" aria-hidden="true"></i>
      </a>
    </div>
    <?php endif; ?>
    <div class="mobile-menu-actions">
      <?php if (current_user_id()): ?>
        <a href="settings.php" class="btn btn-outline"><i class="bi bi-gear" aria-hidden="true"></i> <?= t($i18nCopy['settings'], 'nav_settings') ?></a>
        <a href="account.php" class="btn btn-outline"><i class="bi bi-person-gear" aria-hidden="true"></i> <?= t($i18nCopy['dashboard_nav'], 'account') ?></a>
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

