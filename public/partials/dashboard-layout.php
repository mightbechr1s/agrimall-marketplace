<?php
/**
 * Dashboard sidebar layout — replaces landing header/footer for logged-in pages.
 * Expects $role, $activePage, $pageTitle set by including script.
 */
$role = $role ?? (function_exists('current_user_role') ? current_user_role() : 'buyer');
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';
$navCopy = include __DIR__ . '/../../src/i18n_copy.php';

// Sidebar menu items per role
$sidebarMenus = [
    'farmer' => [
        ['page' => 'dashboard',      'label' => 'Dashboard',      'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'listings',       'label' => 'My Listings',    'icon' => 'bi-basket3',        'href' => 'my_listings.php'],
        ['page' => 'orders',         'label' => 'Orders',         'icon' => 'bi-receipt-cutoff', 'href' => 'orders.php'],
        ['page' => 'goods',          'label' => 'Goods',          'icon' => 'bi-shop',           'href' => 'browse.php'],
        ['page' => 'analytics',      'label' => 'Analytics',      'icon' => 'bi-graph-up',       'href' => 'farmer_analytics.php'],
        ['page' => 'reports',        'label' => 'Sales Report',  'icon' => 'bi-bar-chart-line', 'href' => 'farmer_reports.php'],
    ],
    'buyer' => [
        ['page' => 'dashboard',  'label' => 'Dashboard',   'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'browse',     'label' => 'Goods',      'icon' => 'bi-shop',           'href' => 'browse.php'],
        ['page' => 'cart',       'label' => 'Cart',        'icon' => 'bi-basket3-fill',   'href' => 'cart.php'],
        ['page' => 'orders',     'label' => 'My Orders',   'icon' => 'bi-receipt-cutoff', 'href' => 'orders.php'],
        ['page' => 'saved',      'label' => 'Saved',       'icon' => 'bi-heart',          'href' => 'saved.php'],
    ],
    'admin' => [
        // Admin pages not yet implemented; menu links only to existing panels/pages.
        ['page' => 'dashboard',  'label' => 'Dashboard',   'icon' => 'bi-grid-1x2-fill',  'href' => 'dashboard.php'],
        ['page' => 'goods',      'label' => 'Goods',      'icon' => 'bi-shop',           'href' => 'browse.php'],
        ['page' => 'verifications', 'label' => 'Verifications', 'icon' => 'bi-patch-check', 'href' => 'admin_verify.php'],
        ['page' => 'users',      'label' => 'Users',       'icon' => 'bi-people',         'href' => 'admin_reports.php?t=registration'],
        ['page' => 'audit',      'label' => 'Audit Log',   'icon' => 'bi-journal-text',   'href' => 'admin_audit.php'],
        ['page' => 'reports',    'label' => 'Reports',     'icon' => 'bi-bar-chart-line', 'href' => 'admin_reports.php'],
    ],
];
$menu = $sidebarMenus[$role] ?? $sidebarMenus['buyer'];

// Role display info
$roleLabels = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
$roleIcons  = ['farmer' => 'bi-tree-fill', 'buyer' => 'bi-cart3', 'admin' => 'bi-shield-check'];
$roleColors = ['farmer' => 'role-farmer', 'buyer' => 'role-buyer', 'admin' => 'role-admin'];

// Fetch user profile. Display name from role profile (not email local-part — showed "Qa.Buyer" before). Admins fall back to email.
// Theme prefs ride along in the same query; <head> only applies them.
$userProfile = ['email' => 'User', 'role' => $role, 'status' => 'active', 'created_at' => '', 'last_login_at' => ''];
$displayName = '';
$dbTheme = null;
$reduceMotion = false;
$largerText = false;
try {
    $uStmt = DB::pdo()->prepare(
        'SELECT u.email, u.role, u.status, u.created_at, u.last_login_at,
                COALESCE(bp.full_name, fp.contact_person, fp.farm_name) AS profile_name,
                up.theme, up.reduce_motion, up.larger_text
           FROM users u
           LEFT JOIN buyer_profiles  bp ON bp.user_id = u.id
           LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
           LEFT JOIN user_preferences up ON up.user_id = u.id
          WHERE u.id = :id'
    );
    $uStmt->execute(['id' => current_user_id()]);
    $uRow = $uStmt->fetch();
    if ($uRow) {
        $userProfile = array_merge($userProfile, $uRow);
        $displayName = trim((string) ($uRow['profile_name'] ?? ''));
        if (in_array($uRow['theme'] ?? null, ['system', 'light', 'dark'], true)) {
            $dbTheme = $uRow['theme'];
        }
        $reduceMotion = !empty($uRow['reduce_motion']);
        $largerText = !empty($uRow['larger_text']);
    }
} catch (Exception $e) {
    // Decorative query: degrade rather than break the page, but do not
    // swallow it silently -- a failure here is invisible otherwise.
    error_log('dashboard-layout: profile lookup failed: ' . $e->getMessage());
}

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
    <title><?= e($pageTitle) ?> | <?= e($config['app']['name'] ?? 'AniMALL') ?></title>
    <meta name="theme-color" content="#1B3B2B" />
    <link rel="icon" type="image/svg+xml" href="assets/branding/mark.svg" />
    <link rel="apple-touch-icon" href="assets/branding/mark.svg" />

    <?php
    // Theme prefs already fetched with the profile query above; nothing to
    // re-read here.
    $withSidebarState = true;
    require __DIR__ . '/fouc-guard.php';
    ?>

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
            <img src="assets/branding/mark.svg" alt="AniMALL" class="sidebar-logo">
            <span class="sidebar-brand-text">AniMALL</span>
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
            <a href="<?= e($menuItem['href']) ?>" class="sidebar-link <?= $isActive ? 'is-active' : '' ?>" title="<?= e($label) ?>">
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
                <button class="profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
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
      // Mirrors header.php; flash_get() clears on read — first layout wins.
      if ($flashSuccess = flash_get('success')): ?>
      <div class="container px-3 pt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($flashSuccess) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($flashError = flash_get('error')): ?>
      <div class="container px-3 pt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($flashError) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    <?php endif; ?>

    <!-- Page content injected here by including script -->
    <main id="main-content" class="sidebar-content role-<?= e($role) ?>">


