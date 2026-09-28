<?php
/**
 * Loading Skeleton Partial
 *
 * Usage:
 *   include __DIR__ . '/partials/loading-skeleton.php';
 *   Variables:
 *     $type (string) — 'card'|'table-row'|'list-item'|'text'|'avatar' (default 'card')
 *     $count (int)   — number of skeletons to render (default 6)
 *     $class (string) — extra wrapper classes
 */
$type = $type ?? 'card';
$count = $count ?? 6;
$class = $class ?? '';
?>
<div class="loading-skeletons d-flex flex-wrap gap-3 <?= e($class) ?>">
  <?php for ($i = 0; $i < $count; $i++): ?>
    <?php if ($type === 'card'): ?>
      <article class="card skeleton-card flex-shrink-0" style="width: 18rem;">
        <div class="skeleton-loader" style="aspect-ratio: 4/3;"></div>
        <div class="body p-3">
          <div class="skeleton-loader h-5 w-75 mb-2 rounded"></div>
          <div class="skeleton-loader h-4 w-50 mb-1 rounded"></div>
          <div class="skeleton-loader h-4 w-40 rounded"></div>
          <div class="skeleton-loader h-5 w-30 mt-3 rounded"></div>
        </div>
      </article>
    <?php elseif ($type === 'table-row'): ?>
      <tr class="skeleton-row">
        <td><div class="skeleton-loader h-5 w-75"></div></td>
        <td><div class="skeleton-loader h-5 w-50"></div></td>
        <td><div class="skeleton-loader h-5 w-50"></div></td>
        <td><div class="skeleton-loader h-5 w-50"></div></td>
        <td><div class="skeleton-loader h-5 w-25"></div></td>
      </tr>
    <?php elseif ($type === 'list-item'): ?>
      <div class="d-flex align-items-center gap-3 p-3 skeleton-item">
        <div class="skeleton-loader rounded-circle" style="width: 3rem; height: 3rem;"></div>
        <div class="flex-grow-1">
          <div class="skeleton-loader h-5 w-50 mb-1 rounded"></div>
          <div class="skeleton-loader h-4 w-30 rounded"></div>
        </div>
      </div>
    <?php elseif ($type === 'text'): ?>
      <div class="skeleton-text">
        <div class="skeleton-loader h-5 w-75 mb-2 rounded"></div>
        <div class="skeleton-loader h-4 w-100 mb-1 rounded"></div>
        <div class="skeleton-loader h-4 w-80 mb-1 rounded"></div>
        <div class="skeleton-loader h-4 w-60 rounded"></div>
      </div>
    <?php elseif ($type === 'avatar'): ?>
      <div class="d-flex align-items-center gap-3">
        <div class="skeleton-loader rounded-circle" style="width: 3rem; height: 3rem;"></div>
        <div class="flex-grow-1">
          <div class="skeleton-loader h-5 w-50 rounded"></div>
          <div class="skeleton-loader h-4 w-30 rounded"></div>
        </div>
      </div>
    <?php endif; ?>
  <?php endfor; ?>
</div>

<style>
.skeleton-loader {
  background: linear-gradient(90deg, var(--borders) 25%, var(--canvas-deep) 50%, var(--borders) 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
  border-radius: 4px;
}
.skeleton-card { animation: none; }
.skeleton-row td { padding: 0.8rem 1rem; }
.skeleton-item { border-bottom: 1px solid var(--borders); }
.skeleton-text { max-width: 42rem; }

@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton-loader { animation: none; background: var(--borders); }
}
</style>