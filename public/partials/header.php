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
$canonicalUrl = $baseUrl ? $baseUrl . $requestUri : "{$protocol}://{$host}{$requestUri}";
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
    <meta property="og:image" content="<?= e($config['app']['base_url'] ?? '') ?>/assets/branding/mark.svg" />
    <meta property="og:url" content="<?= e($canonicalUrl) ?>" />
    <meta property="og:site_name" content="AniMAL" />
    <meta property="og:locale" content="<?= $currentLocale === 'tl' ? 'tl_PH' : 'en_PH' ?>" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= e($appName) ?> — Farm to table, no middlemen" />
    <meta name="twitter:description" content="<?= e($i18nCopy['og_description'][$currentLocale] ?? $i18nCopy['og_description']['en'] ?? '') ?>" />
    <meta name="twitter:image" content="<?= e($config['app']['base_url'] ?? '') ?>/assets/branding/mark.svg" />

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

      // ----- Reduce motion -----
      var reduceMotion = <?= $dbReduceMotion ? 'true' : 'false' ?>;
      var storedRM = localStorage.getItem('reduceMotion');
      if (storedRM !== null) reduceMotion = storedRM === 'true';
      document.documentElement.toggleAttribute('data-reduce-motion', reduceMotion);
      localStorage.setItem('reduceMotion', reduceMotion);

      // ----- Larger text -----
      var largerText = <?= $dbLargerText ? 'true' : 'false' ?>;
      var storedLT = localStorage.getItem('largerText');
      if (storedLT !== null) largerText = storedLT === 'true';
      document.documentElement.toggleAttribute('data-larger-text', largerText);
      localStorage.setItem('largerText', largerText);
    })();
    </script>

    <!-- Local styles -->
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
          "logo": "<?= e($config['app']['base_url'] ?? '') ?>/assets/branding/mark.svg",
          "image": "<?= e($config['app']['base_url'] ?? '') ?>/assets/branding/mark.svg",
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
          <button class="locale-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Language">
            <span class="locale-code"><?= e(strtoupper($currentLocale)) ?></span>
            <i class="bi bi-chevron-down locale-caret" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= e(locale_url('en')) ?>" data-lang="en">English <?= $currentLocale === 'en' ? '<span class="locale-tick small">✓</span>' : '' ?></a></li>
            <li><a class="dropdown-item" href="<?= e(locale_url('tl')) ?>" data-lang="tl">Filipino <?= $currentLocale === 'tl' ? '<span class="locale-tick small">✓</span>' : '' ?></a></li>
          </ul>
        </div>
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
            $cartUrl = current_user_id() ? 'cart.php' : 'login.php';
          ?>
          <a href="<?= e($cartUrl) ?>" class="cart-pill" aria-label="Basket, <?= $cartCount ?> items">
            <span class="cart-count"><?= $cartCount ?></span>
            <i class="bi bi-basket" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
      </div>
      <?php if (current_user_id()): ?>
        <?php
          // Fetch user profile data for avatar
          $userProfile = ['email' => 'User', 'role' => current_user_role(), 'status' => 'active', 'name' => ''];
          try {
              $uStmt = DB::pdo()->prepare('SELECT email, role, status, name FROM users WHERE id = :id');
              $uStmt->execute(['id' => current_user_id()]);
              $uRow = $uStmt->fetch();
              if ($uRow) $userProfile = array_merge($userProfile, $uRow);
          } catch (Exception $e) {}
          $emailUser = explode('@', $userProfile['email'])[0];
          $initials = strtoupper(substr($emailUser, 0, 2));
          $nameUser = $userProfile['name'] ?? $emailUser; // Use name if available, else email username
          $roleLabels = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
          $roleIcons  = ['farmer' => 'bi-tree-fill', 'buyer' => 'bi-cart3', 'admin' => 'bi-shield-check'];
          $avatarBg = ['farmer' => 'var(--primary)', 'buyer' => 'var(--accent)', 'admin' => '#C9A227'];
        ?>
        <div class="dropdown">
          <button class="dropdown-toggle" type="button" id="avatarDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
            <div class="avatar" style="width: 2rem; height: 2rem; background: <?= e($avatarBg[$userProfile['role']] ?? 'var(--primary)') ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-transform: uppercase; font-weight: 600; font-size: 0.75rem; color: var(--paper);">
              <span><?= e($initials) ?></span>
            </div>
            <span class="ms-2 d-none d-lg-block"><?= e($nameUser) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Log out</a></li>
          </ul>
        </div>
      <?php else: ?>
        <?php $role = current_user_role(); ?>
        <a href="login.php" class="nav-text-link"><?= t($i18nCopy['nav'], 'login') ?></a>
        <a href="register.php" class="nav-text-link nav-link-sell"><?= t($i18nCopy['nav'], 'sell') ?></a>
        <a href="browse.php" class="btn-primary-nav nav-sell-btn nav-cta-primary"><?= t($i18nCopy['nav'], 'browse') ?></a>
      <?php endif; ?>
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

