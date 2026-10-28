<?php
/**
 * Shared pagination control.
 * Expects: $page (int, 1-based), $totalPages (int), $param (string, extra query params WITHOUT leading ? or &).
 * Optional: $labels (array) with keys 'prev', 'next', 'page', 'of' for localization.
 * Optional: $pageParam (string) query-string key holding the page number. Defaults
 *   to 'page'. Pages needing two independent pagers pass distinct keys
 *   ('listings_page', 'saved_page') so one section's paging never moves the other.
 * Renders nothing when $totalPages <= 1.
 */
if (!isset($page, $totalPages)) {
    return;
}
$page = max(1, (int)$page);
$totalPages = max(1, (int)$totalPages);
if ($totalPages <= 1) {
    return;
}
$pageParam = $pageParam ?? 'page';
$param = $param ?? '';
$paramPrefix = $param !== '' ? '&' . $param : '';
$labels = $labels ?? [
    'prev' => 'Previous',
    'next' => 'Next',
    'page' => 'Page',
    'of'   => 'of',
];

/** Build page URL preserving all other query params. Guarded: a page with two
    pagers includes this partial twice and would otherwise fatal on redeclare. */
if (!function_exists('pager_href')) {
    function pager_href(int $p, string $pageParam, string $paramPrefix): string
    {
        return '?' . $pageParam . '=' . $p . $paramPrefix;
    }
}
?>
<nav aria-label="Pagination" class="browse-pager d-flex justify-content-center gap-1 flex-wrap my-4" role="navigation">
    <!-- Previous -->
    <?php if ($page > 1): ?>
        <a href="<?= e(pager_href($page - 1, $pageParam, $paramPrefix)) ?>" class="btn btn-outline btn-sm" aria-label="<?= e($labels['prev']) ?> page">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
            <span class="d-none d-sm-inline"><?= e($labels['prev']) ?></span>
        </a>
    <?php else: ?>
        <button class="btn btn-outline btn-sm" disabled aria-label="<?= e($labels['prev']) ?> page" aria-disabled="true">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
            <span class="d-none d-sm-inline"><?= e($labels['prev']) ?></span>
        </button>
    <?php endif; ?>

    <!-- Numbered pages with ellipsis -->
    <?php
    $range = [];
    $delta = 1; // show current ±1
    $range[] = 1;
    if ($page - $delta > 2) {
        $range[] = '...';
    }
    for ($p = max(2, $page - $delta); $p <= min($totalPages - 1, $page + $delta); $p++) {
        $range[] = $p;
    }
    if ($page + $delta < $totalPages - 1) {
        $range[] = '...';
    }
    if ($totalPages > 1) {
        $range[] = $totalPages;
    }
    ?>
    <?php foreach ($range as $p): ?>
        <?php if ($p === '...'): ?>
            <span class="btn btn-outline btn-sm disabled" aria-hidden="true">…</span>
        <?php elseif ($p === $page): ?>
            <button class="btn btn-primary btn-sm" disabled aria-current="page" aria-label="<?= e($labels['page']) ?> <?= (int)$p ?>, current">
                <?= (int)$p ?>
            </button>
        <?php else: ?>
            <a href="<?= e(pager_href((int)$p, $pageParam, $paramPrefix)) ?>" class="btn btn-outline btn-sm" aria-label="<?= e($labels['page']) ?> <?= (int)$p ?>">
                <?= (int)$p ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Next -->
    <?php if ($page < $totalPages): ?>
        <a href="<?= e(pager_href($page + 1, $pageParam, $paramPrefix)) ?>" class="btn btn-outline btn-sm" aria-label="<?= e($labels['next']) ?> page">
            <span class="d-none d-sm-inline"><?= e($labels['next']) ?></span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    <?php else: ?>
        <button class="btn btn-outline btn-sm" disabled aria-label="<?= e($labels['next']) ?> page" aria-disabled="true">
            <span class="d-none d-sm-inline"><?= e($labels['next']) ?></span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
    <?php endif; ?>
</nav>