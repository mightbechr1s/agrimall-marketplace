<?php
/**
 * Pre-paint FOUC guard, shared by header.php and dashboard-layout.php so the
 * two can never resolve a different theme. Require inside <head>, before
 * <body>, with $dbTheme, $reduceMotion, $largerText and $withSidebarState
 * already set ($withSidebarState is dashboard shell only).
 */
$withSidebarState = $withSidebarState ?? false;
?>
    <!-- FOUC guard -->
    <script>
    (function () {
      try {
        // Add .js before stylesheets parse so CSS hides pre-animation; absent .js keeps elements visible
        document.documentElement.classList.add('js');

        // localStorage is truth; the DB preference only seeds it when nothing is stored yet
        window.AniMALL = window.AniMALL || {};
        window.AniMALL.userId = <?= json_encode(current_user_id() ?: null) ?>;
        window.AniMALL.csrf = <?= json_encode(csrf_token()) ?>;
        var pref = localStorage.getItem('animal_theme');
        if (pref !== 'system' && pref !== 'light' && pref !== 'dark') {
          pref = <?= json_encode($dbTheme) ?> || 'system';
          localStorage.setItem('animal_theme', pref);
        }
        var resolved = pref === 'system'
          ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
          : pref;
        document.documentElement.setAttribute('data-theme', resolved);

        // Animations always on; only the OS prefers-reduced-motion query applies
        document.documentElement.toggleAttribute('data-reduce-motion', <?= $reduceMotion ? 'true' : 'false' ?>);
        document.documentElement.toggleAttribute('data-larger-text', <?= $largerText ? 'true' : 'false' ?>);
        <?php if ($withSidebarState): ?>document.documentElement.toggleAttribute('data-sidebar-collapsed', localStorage.getItem('sidebarCollapsed') === '1');<?php endif; ?>
      } catch (e) {}
    })();
    </script>
