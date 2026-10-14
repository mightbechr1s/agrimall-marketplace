<?php
/**
 * Error State Partial — include __DIR__ . '/partials/error-state.php', set vars.
 * Required: $message; optional: $title, $code, $retry, $dismissible, $class.
 * $retry: ['label' => ..., 'action' => 'reload'|'back'|'url', 'url' => ...].
 */
$message = $message ?? 'An unexpected error occurred.';
$title = $title ?? 'Something went wrong';
$code = $code ?? 500;
$retry = $retry ?? null;
$dismissible = $dismissible ?? true;
$class = $class ?? '';
?>
<div class="error-state alert alert-danger d-flex flex-column gap-3 p-4 <?= e($class) ?>" role="alert" <?= $dismissible ? 'data-dismissible="true"' : '' ?>>
  <div class="d-flex gap-3">
    <div class="flex-shrink-0 text-danger" style="font-size: 2.5rem;">
      <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
    </div>
    <div class="flex-grow-1">
      <h3 class="error-state-title mb-1"><?= e($title) ?></h3>
      <p class="error-state-message mb-0"><?= e($message) ?></p>
      <?php if ($code): ?>
        <small class="text-soft">Error code: <?= e((string)$code) ?></small>
      <?php endif; ?>
    </div>
    <?php if ($dismissible): ?>
      <button type="button" class="btn-close flex-shrink-0" aria-label="Dismiss" onclick="this.closest('.error-state').remove()"></button>
    <?php endif; ?>
  </div>
  <?php if ($retry): ?>
    <div class="error-state-actions d-flex gap-2">
      <?php
      $action = $retry['action'] ?? 'reload';
      $label = $retry['label'] ?? 'Retry';
      $url = $retry['url'] ?? '#';
      $onclick = '';
      if ($action === 'reload') $onclick = 'location.reload()';
      elseif ($action === 'back') $onclick = 'history.back()';
      elseif ($action === 'url') $onclick = 'window.location.href=' . json_encode($url);
      ?>
      <button type="button" class="btn btn-primary" onclick="<?= e($onclick) ?>">
        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><?= e($label) ?>
      </button>
    </div>
  <?php endif; ?>
</div>