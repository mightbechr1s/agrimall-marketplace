<?php
/**
 * Custom 404 page - renders when router.php handles an unknown path.
 * Loads bootstrap for config, helpers, and DB (for any dynamic content if needed).
 */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = 'Page Not Found';
$activeNav = '';
$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};
// The errors deck is keyed locale-first, so the nested 404 group has to be
// resolved before it can be indexed. Reading the nested group directly returns
// null, and every lookup below then falls back to hardcoded English in every
// language, leaving the whole Tagalog 404 block dead.
$notFound = $copy['errors'][$locale]['404'] ?? $copy['errors']['en']['404'] ?? [];
?>
<?php require __DIR__ . '/partials/header.php'; ?>

<main id="main-content" class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="container text-center" style="max-width: 620px;">
        <div class="error-404" aria-hidden="true" data-reveal>
            <span class="error-icon"><i class="bi bi-basket2"></i></span>
            <span class="error-code">404</span>
        </div>
        <h1 class="display-5 fw-bold mb-3" data-reveal><?= e($notFound['title'] ?? 'Page Not Found') ?></h1>
        <p class="fs-5 text-muted mb-4" data-reveal><?= e($notFound['message'] ?? 'The page you\'re looking for doesn\'t exist or has been moved.') ?></p>

        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3" data-reveal>
            <a href="/" class="btn btn-primary btn-lg px-4 py-3">
                <?= e($notFound['cta_home'] ?? 'Go Home') ?>
            </a>
            <a href="/index.php" class="btn btn-outline-primary btn-lg px-4 py-3">
                <?= e($notFound['cta_browse'] ?? 'Browse Listings') ?>
            </a>
        </div>

        <p class="small mt-4 mb-0 text-muted">
            <?= e($notFound['help_text'] ?? 'If you think this is a mistake, please') ?>
            <a href="mailto:support@animal.ph"><?= e($notFound['contact_link'] ?? 'contact us') ?></a>.
        </p>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>