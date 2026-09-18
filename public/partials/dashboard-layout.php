<?php
/**
 * Dashboard sidebar layout — replaces landing header/footer for logged-in pages.
 * Expects $role, $activePage, $pageTitle already set by the including script.
 */
$role = $role ?? (function_exists('current_user_role') ? current_user_role() : 'buyer');
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';
$navCopy = include __DIR__ . '/../../src/i18n_copy.php';

// Sidebar menu items per role
$sidebarMenus = [
    'farmer' => [
        ['page' => 'dashboard',      'label' => 'Dashboard',      'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'listings',       'label' => 'My Listings',    'icon' => 'bi-basket3-fill',   'href' => 'dashboard.php#listings'],
        ['page' => 'create_listing', 'label' => 'New Listing',    'icon' => 'bi-plus-circle-fill','href' => 'create_listing.php'],
        ['page' => 'orders',         'label' => 'Orders',         'icon' => 'bi-receipt-cutoff', 'href' => 'dashboard.php#orders'],
        ['page' => 'analytics',      'label' => 'Analytics',      'icon' => 'bi-graph-up',       'href' => 'dashboard.php#analytics'],
    ],
    'buyer' => [
        ['page' => 'dashboard',  'label' => 'Dashboard',   'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'browse',     'label' => 'Browse',      'icon' => 'bi-shop',           'href' => 'browse.php'],
        ['page' => 'orders',     'label' => 'My Orders',   'icon' => 'bi-receipt-cutoff', 'href' => 'dashboard.php#orders'],
        ['page' => 'saved',      'label' => 'Saved',       'icon' => 'bi-heart',          'href' => 'dashboard.php#saved'],
    ],
    'admin' => [
        ['page' => 'dashboard',  'label' => 'Dashboard',   'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'users',      'label' => 'Users',       'icon' => 'bi-people-fill',    'href' => 'dashboard.php#users'],
        ['page' => 'listings',   'label' => 'Listings',    'icon' => 'bi-basket3-fill',   'href' => 'dashboard.php#listings'],
        ['page' => 'orders',     'label' => 'Orders',      'icon' => 'bi-receipt-cutoff', 'href' => 'dashboard.php#orders'],
        ['page' => 'verifications','label' => 'Verifications','icon' => 'bi-patch-check-fill','href' => 'admin_verify.php'],
        ['page' => 'audit',      'label' => 'Audit Log',   'icon' => 'bi-journal-text',   'href' => 'admin_audit.php'],
        ['page' => 'reports',    'label' => 'Reports',     'icon' => 'bi-bar-chart-line', 'href' => 'admin_reports.php'],
        ['page' => 'categories', 'label' => 'Categories',  'icon' => 'bi-tags-fill',      'href' => 'dashboard.php#categories'],
    ],
];
$menu = $sidebarMenus[$role] ?? $sidebarMenus['buyer'];

// Role display info
$roleLabels = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
$roleIcons  = ['farmer' => 'bi-tree-fill', 'buyer' => 'bi-cart3', 'admin' => 'bi-shield-check'];
$roleColors = ['farmer' => 'role-farmer', 'buyer' => 'role-buyer', 'admin' => 'role-admin'];

// Fetch user profile data
$userProfile = ['email' => 'User', 'role' => $role, 'status' => 'active', 'created_at' => '', 'last_login_at' => ''];
try {
    $uStmt = DB::pdo()->prepare('SELECT email, role, status, created_at, last_login_at FROM users WHERE id = :id');
    $uStmt->execute(['id' => current_user_id()]);
    $uRow = $uStmt->fetch();
    if ($uRow) $userProfile = array_merge($userProfile, $uRow);
} catch (Exception $e) {}

// Generate initials from email
$emailUser = explode('@', $userProfile['email'])[0];
$initials = strtoupper(substr($emailUser, 0, 2));
$memberSince = $userProfile['created_at'] ? date('M Y', strtotime($userProfile['created_at'])) : '';
$lastLogin = $userProfile['last_login_at'] ? date('M j, g:i A', strtotime($userProfile['last_login_at'])) : 'Never';

// Avatar color based on role
$avatarBg = ['farmer' => 'var(--primary)', 'buyer' => 'var(--accent)', 'admin' => '#C9A227'];
?>
<!doctype html>
<html lang="<?= e(function_exists('locale_current') ? locale_current() : 'en') ?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= e($pageTitle) ?> | <?= e($config['app']['name'] ?? 'AniMAL') ?></title>
    <meta name="theme-color" content="#1B3B2B" />

    <?php
    $dbTheme = null;
    $reduceMotion = false;
    $largerText = false;
    try {
        $themeStmt = DB::pdo()->prepare('SELECT theme, reduce_motion, larger_text FROM user_preferences WHERE user_id = :id LIMIT 1');
        $themeStmt->execute(['id' => current_user_id()]);
        $themePrefs = $themeStmt->fetch();
        $themeValue = $themePrefs['theme'] ?? null;
        $reduceMotion = !empty($themePrefs['reduce_motion']);
        $largerText = !empty($themePrefs['larger_text']);
        if (in_array($themeValue, ['system', 'light', 'dark'], true)) {
            $dbTheme = $themeValue;
        }
    } catch (Throwable $e) {
        $dbTheme = null;
    }
    ?>
    <!-- FOUC guard -->
    <script>
    (function () {
      try {
        // Persistent theme: localStorage is the source of truth; the DB
        // preference seeds it only when nothing is stored yet. Must mirror
        // header.php exactly so pages never disagree after an action.
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
        // Animations (localStorage 'animations' = 'on'|'off'; absent ⇒ follow OS)
        var animPref = localStorage.getItem('animations');
        if (animPref === 'on') document.documentElement.setAttribute('data-animations', 'on');
        else if (animPref === 'off') document.documentElement.setAttribute('data-animations', 'off');
        document.documentElement.toggleAttribute('data-reduce-motion', <?= $reduceMotion ? 'true' : 'false' ?>);
        document.documentElement.toggleAttribute('data-larger-text', <?= $largerText ? 'true' : 'false' ?>);
        document.documentElement.toggleAttribute('data-sidebar-collapsed', localStorage.getItem('sidebarCollapsed') === '1');
      } catch (e) {}
    })();
    </script>

    <!-- Bootstrap 5.3.3 + Icons (local vendor copy — offline) -->
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet" />

    <!-- App styles -->
    <link href="<?= e(asset('assets/css/styles.min.css')) ?>" rel="stylesheet" />
    <link href="<?= e(asset('assets/css/motion.min.css')) ?>" rel="stylesheet" />
    <link href="<?= e(asset('assets/css/design-system.css')) ?>" rel="stylesheet" />
</head>
<body class="has-sidebar">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar" aria-label="Dashboard navigation">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <img src="assets/branding/mark.svg" alt="AniMAL" class="sidebar-logo">
            <span class="sidebar-brand-text">AniMAL</span>
        </a>
        <button class="sidebar-close" type="button" aria-label="Hide sidebar" aria-controls="sidebar" id="sidebar-close">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    
    <nav class="sidebar-nav" aria-label="Dashboard menu">
        <?php foreach ($menu as $item): ?>
            <?php
            $isActive = ($activePage === $item['page']);
            // For hash links, also check if current script matches
            if ($item['page'] === 'dashboard' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'dashboard.php' && $activePage === 'dashboard') {
                $isActive = true;
            }
            ?>
            <a href="<?= e($item['href']) ?>" class="sidebar-link <?= $isActive ? 'is-active' : '' ?>">
                <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                <span><?= e(t($navCopy['dashboard_nav'], $item['page'])) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-theme-toggle">
            <button class="theme-toggle" type="button" aria-label="Switch theme">
                <i class="bi bi-moon icon-moon" aria-hidden="true"></i>
                <i class="bi bi-sun icon-sun" aria-hidden="true"></i>
            </button>
            <span class="sidebar-theme-label"><?= e(t($navCopy['dashboard_nav'], 'theme')) ?></span>
        </div>
        <div class="sidebar-theme-toggle">
            <button class="motion-toggle" type="button" role="switch" aria-checked="true" aria-label="Turn animations off">
                <i class="bi bi-lightning-charge motion-icon-on" aria-hidden="true"></i>
                <i class="bi bi-lightning motion-icon-off" aria-hidden="true"></i>
            </button>
            <span class="sidebar-theme-label"><?= e(t($navCopy['dashboard_nav'], 'animations')) ?></span>
        </div>
        <a href="settings.php" class="sidebar-link sidebar-settings">
            <i class="bi bi-gear" aria-hidden="true"></i>
            <span><?= e(t($navCopy['dashboard_nav'], 'settings')) ?></span>
        </a>
        <a href="logout.php" class="sidebar-link sidebar-logout">
            <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
            <span><?= e(t($navCopy['dashboard_nav'], 'logout')) ?></span>
        </a>
        <a href="index.php" class="sidebar-link sidebar-home">
            <i class="bi bi-house-door" aria-hidden="true"></i>
            <span><?= e(t($navCopy['dashboard_nav'], 'home')) ?></span>
        </a>
    </div>
</aside>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Reopen control (desktop, shown only while the sidebar is collapsed) -->
<button class="sidebar-reopen" type="button" id="sidebar-reopen" aria-label="Show navigation" aria-controls="sidebar">
    <i class="bi bi-list" aria-hidden="true"></i>
</button>

<!-- Main content area -->
<div class="sidebar-main">
    <!-- Top bar (mobile) -->
    <header class="sidebar-topbar">
        <button class="sidebar-toggle" type="button" aria-label="Open sidebar" aria-expanded="false" aria-controls="sidebar" id="sidebar-toggle">
            <i class="bi bi-list"></i>
        </button>
        <span class="sidebar-topbar-title"><?= e($pageTitle) ?></span>
        <div class="sidebar-topbar-actions">
            <button class="theme-toggle" type="button" aria-label="Switch theme">
                <i class="bi bi-moon icon-moon" aria-hidden="true"></i>
                <i class="bi bi-sun icon-sun" aria-hidden="true"></i>
            </button>
            <button class="motion-toggle" type="button" role="switch" aria-checked="true" aria-label="Turn animations off">
                <i class="bi bi-lightning-charge motion-icon-on" aria-hidden="true"></i>
                <i class="bi bi-lightning motion-icon-off" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <!-- Page content injected here by including script -->
    <main id="main-content" class="sidebar-content role-<?= e($role) ?>">


