<?php
/**
 * Empty State Partial — set vars below, then include.
 * Required: $title; optional: $icon, $description, $action, $actions, $size, $class.
 * Read with ?? so caller values win and nothing needs unsetting.
 */
$icon = $icon ?? '<i class="bi bi-clipboard2" aria-hidden="true"></i>';
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
