<?php
/**
 * Empty State Partial — set vars below, then include.
 * Required: $title; optional: $icon, $description, $action, $actions, $size, $class.
 * Read with ?? so caller values win and nothing needs unsetting.
 */
$icon = $icon ?? '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon"><path d="M2 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4z"/><path d="M2 7h4l1 2h2l1-2h4"/></svg>';
$title = $title ?? 'No records found';
$description = $description ?? '';
$action = $action ?? null;
$actions = $actions ?? null;
$size = $size ?? 'md';
$class = $class ?? '';

// A single $action is the common case; normalise both shapes to one list.
if ($actions === null && $action !== null) {
    $actions = [$action];
}
$actions = is_array($actions) ? array_values(array_filter($actions, 'is_array')) : [];

$sizes = ['sm' => 'empty-state--sm', 'md' => 'empty-state--md', 'lg' => 'empty-state--lg'];
$sizeClass = $sizes[$size] ?? $sizes['md'];
?>
<div class="empty-state text-center <?= e($class) ?> <?= e($sizeClass) ?>" data-reveal>
  <?php if ($icon): ?>
    <div class="empty-state-icon-wrapper mb-0">
      <?= $icon ?>
    </div>
  <?php endif; ?>
  <h3 class="empty-state-title"><?= e($title) ?></h3>
  <?php if ($description): ?>
    <p class="empty-state-description"><?= e($description) ?></p>
  <?php endif; ?>
  <?php if ($actions): ?>
    <div class="empty-state-action mt-3 d-flex flex-wrap gap-2 justify-content-center">
      <?php foreach ($actions as $a): ?>
        <a href="<?= e($a['href'] ?? '#') ?>" class="btn <?= e($a['class'] ?? 'btn-primary') ?>">
          <?= e($a['label'] ?? '') ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
