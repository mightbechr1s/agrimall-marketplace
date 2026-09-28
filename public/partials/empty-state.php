<?php
/**
 * Empty State Partial
 *
 * Usage:
 *   include __DIR__ . '/partials/empty-state.php';
 *   Variables:
 *     $icon (string)        — SVG markup or icon class (default: database)
 *     $title (string)       — Title text (required)
 *     $description (string) — Description text
 *     $action (array)       — ['label' => 'Button text', 'href' => 'url', 'class' => 'btn-primary']
 *     $size (string)        — 'sm'|'md'|'lg' (default md)
 *     $class (string)       — Extra wrapper classes
 */
$icon = $icon ?? '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon"><path d="M2 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H1.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V6h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V4h-.5a.5.5 0 0 1 0-1H2V2h-.5a.5.5 0 0 1 0-1H2zm9.853-1.354a.5.5 0 0 1 .354-.853H14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H8.5a.5.5 0 0 1 0-1H14a1 1 0 0 0 1-1V4H8.5a.5.5 0 0 1 0-1H15V2a1 1 0 0 0-1-1H10.5v2.5a.5.5 0 0 1-1 0V2a.5.5 0 0 1 .146-.354l.207-.207z"/>';
$title = $title ?? 'No records found';
$description = $description ?? '';
$action = $action ?? null;
$size = $size ?? 'md';
$class = $class ?? '';

$sizeClasses = [
    'sm' => 'py-3 px-3',
    'md' => 'py-5 px-4',
    'lg' => 'py-6 px-6',
];
$sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
?>
<div class="empty-state text-center <?= e($class) ?>" style="padding: <?= e($sizeClass) ?>;">
  <?php if ($icon): ?>
    <div class="empty-state-icon-wrapper mb-3">
      <?= $icon ?>
    </div>
  <?php endif; ?>
  <h3 class="empty-state-title"><?= e($title) ?></h3>
  <?php if ($description): ?>
    <p class="empty-state-description"><?= e($description) ?></p>
  <?php endif; ?>
  <?php if ($action): ?>
    <div class="empty-state-action mt-3">
      <a href="<?= e($action['href']) ?>" class="btn <?= e($action['class'] ?? 'btn-primary') ?>">
        <?= e($action['label']) ?>
      </a>
    </div>
  <?php endif; ?>
</div>