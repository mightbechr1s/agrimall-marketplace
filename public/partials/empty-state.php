<?php
/**
 * Empty State Partial — set vars below, then include.
 * Required: $title; optional: $icon, $description, $action, $actions, $size, $class.
 * Read with ?? so caller values win and nothing needs unsetting.
 */
$icon = $icon ?? '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon"><path d="M2 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H1.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V6h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V4h-.5a.5.5 0 0 1 0-1H2V2h-.5a.5.5 0 0 1 0-1H2zm9.853-1.354a.5.5 0 0 1 .354-.853H14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H8.5a.5.5 0 0 1 0-1H14a1 1 0 0 0 1-1V4H8.5a.5.5 0 0 1 0-1H15V2a.5.5 0 0 0-1-1H10.5v2.5a.5.5 0 0 1-1 0V2a.5.5 0 0 1 .146-.354l.207-.207z"/>';
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
    <div class="empty-state-icon-wrapper mb-3">
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
