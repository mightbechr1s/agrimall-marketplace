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
        ['page' => 'cart',       'label' => 'Cart',        'icon' => 'bi-basket3-fill',   'href' => 'cart.php'],
        ['page' => 'orders',     'label' => 'My Orders',   'icon' => 'bi-receipt-cutoff', 'href' => 'dashboard.php#orders'],
        ['page' => 'saved',      'label' => 'Saved',       'icon' => 'bi-heart',          'href' => 'dashboard.php#saved'],
    ],
    'admin' => [
        // No admin_users/admin_listings/admin_categories pages exist yet, so the
        // menu only links to panels and pages that actually resolve.
        ['page' => 'dashboard',  'label' => 'Dashboard',   'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'orders',     'label' => 'Orders',      'icon' => 'bi-receipt-cutoff', 'href' => 'dashboard.php#orders'],
        ['page' => 'verifications','label' => 'Verifications','icon' => 'bi-patch-check-fill','href' => 'admin_verify.php'],
        ['page' => 'audit',      'label' => 'Audit Log',   'icon' => 'bi-journal-text',   'href' => 'admin_audit.php'],
        ['page' => 'reports',    'label' => 'Reports',     'icon' => 'bi-bar-chart-line', 'href' => 'admin_reports.php'],
    ],
];
$menu = $sidebarMenus[$role] ?? $sidebarMenus['buyer'];

// Role display info
$roleLabels = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
$roleIcons  = ['farmer' => 'bi-tree-fill', 'buyer' => 'bi-cart3', 'admin' => 'bi-shield-check'];
$roleColors = ['farmer' => 'role-farmer', 'buyer' => 'role-buyer', 'admin' => 'role-admin'];

// Fetch user profile data.
// Display name comes from the role's own profile, not the email local-part:
// deriving it from the email showed "Qa.Buyer" instead of the real name.
// Admins have no profile table, so they fall back to the email local-part.
$userProfile = ['email' => 'User', 'role' => $role, 'status' => 'active', 'created_at' => '', 'last_login_at' => ''];
$displayName = '';
try {
    $uStmt = DB::pdo()->prepare(
        'SELECT u.email, u.role, u.status, u.created_at, u.last_login_at,
                COALESCE(bp.full_name, fp.contact_person, fp.farm_name) AS profile_name
           FROM users u
           LEFT JOIN buyer_profiles  bp ON bp.user_id = u.id
           LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
          WHERE u.id = :id'
    );
    $uStmt->execute(['id' => current_user_id()]);
    $uRow = $uStmt->fetch();
    if ($uRow) {
        $userProfile = array_merge($userProfile, $uRow);
        $displayName = trim((string) ($uRow['profile_name'] ?? ''));
    }
} catch (Exception $e) {}

if ($displayName === '') {
    $displayName = explode('@', (string) $userProfile['email'])[0];
}

// Initials from the first two words of the display name
$initials = '';
foreach (array_slice(preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $word) {
    $initials .= mb_substr($word, 0, 1);
}
$initials = mb_strtoupper($initials) ?: mb_strtoupper(mb_substr($displayName, 0, 1));
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
    <link rel="icon" type="image/svg+xml" href="assets/branding/mark.svg" />
    <link rel="apple-touch-icon" href="assets/branding/mark.svg" />

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
        // ----- JS-available flag -----
        // Set here, before any stylesheet is parsed, so CSS can gate its
        // pre-animation hidden states on .js. If this never runs (JS disabled)
        // or a deferred script fails, .js is absent and those elements stay
        // visible instead of being stranded at opacity: 0.
        document.documentElement.classList.add('js');

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
        // Animations are always on; only the OS prefers-reduced-motion query applies.
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
    <link href="<?= e(asset('assets/fonts/fonts.css')) ?>" rel="stylesheet" />
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
        <button class="sidebar-toggle sidebar-toggle-brand" type="button" aria-label="Toggle sidebar" aria-expanded="false" aria-controls="sidebar">
            <span class="sidebar-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
        <button class="sidebar-close" type="button" aria-label="Hide sidebar" aria-controls="sidebar" id="sidebar-close">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    
    <nav class="sidebar-nav" aria-label="Dashboard menu">
        <?php foreach ($menu as $menuItem): ?>
            <?php
            $isActive = ($activePage === $menuItem['page']);
            if ($menuItem['page'] === 'dashboard' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'dashboard.php' && $activePage === 'dashboard') {
                $isActive = true;
            }
            $label = t($navCopy['dashboard_nav'], $menuItem['page']);
            ?>
            <a href="<?= e($menuItem['href']) ?>" class="sidebar-link <?= $isActive ? 'is-active' : '' ?>">
                <i class="bi <?= e($menuItem['icon']) ?>" aria-hidden="true"></i>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Main content area -->
<div class="sidebar-main">
    <!-- Top bar (mobile) -->
    <header class="sidebar-topbar" id="topbar-utils">
        <div class="sidebar-topbar-side">
<button class="sidebar-toggle" type="button" aria-label="Open sidebar" aria-expanded="false" aria-controls="sidebar" id="sidebar-toggle">
<span class="sidebar-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
</button>
            <span class="sidebar-topbar-title"><?= e($pageTitle) ?></span>
        </div>
        <div class="sidebar-topbar-actions">
            <button class="theme-toggle sidebar-icon-btn" type="button" aria-label="Switch theme">
                <i class="bi bi-moon icon-moon" aria-hidden="true"></i>
                <i class="bi bi-sun icon-sun" aria-hidden="true"></i>
            </button>
            <?php
            $unreadCount = unread_notification_count((int) current_user_id());
            $notifLabel = $unreadCount > 0
                ? sprintf(t($navCopy['notifications'], 'unread_label'), $unreadCount)
                : t($navCopy['notifications'], 'read_label');
            ?>
            <a href="notifications.php" class="sidebar-icon-btn notif-btn" aria-label="<?= e($notifLabel) ?>">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-count"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown profile-dd">
                <button class="profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="profile-avatar" style="background: <?= e($avatarBg[$role] ?? 'var(--accent)') ?>"><?= e($initials) ?></span>
                    <span class="profile-meta">
                        <span class="profile-name"><?= e($displayName) ?></span>
                        <span class="role-pill"><?= e($roleLabels[$role] ?? ucfirst($role)) ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end profile-dd-menu">
                    <li>
                        <div class="profile-dd-head">
                            <span class="profile-dd-name"><?= e($displayName) ?></span>
                            <span class="profile-dd-email"><?= e($userProfile['email']) ?></span>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-sliders me-2" aria-hidden="true"></i><?= e(t($navCopy['dashboard_nav'], 'settings')) ?></a></li>
                    <li><a class="dropdown-item" href="account.php"><i class="bi bi-person-gear me-2" aria-hidden="true"></i><?= e(t($navCopy['dashboard_nav'], 'account')) ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2" aria-hidden="true"></i><?= e(t($navCopy['dashboard_nav'], 'logout')) ?></a></li>
                </ul>
            </div>
        </div>
    </header>

    <?php
      // mirrors partials/header.php; flash_get() clears the message, so whichever
      // layout runs first renders it
      if ($flashSuccess = flash_get('success')): ?>
      <div class="container px-3 pt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($flashSuccess) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($flashError = flash_get('error')): ?>
      <div class="container px-3 pt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($flashError) ?></div>
      </div>
    <?php endif; ?>

    <!-- Page content injected here by including script -->
    <main id="main-content" class="sidebar-content role-<?= e($role) ?>">


