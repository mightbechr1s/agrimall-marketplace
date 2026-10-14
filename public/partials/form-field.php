<?php
/**
 * Form Field Partial — renders a labeled input with validation/error/help.
 * Required: $label, $name. Optional: $type, $value, $options, $placeholder,
 * $required, $disabled, $readonly, $error, $help, $attrs, $before, $after,
 * $inline, $labelClass, $inputClass, $wrapperClass, $id.
 */
$type = $type ?? 'text';
$label = $label ?? '';
$fid = $id ?? $name;
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$required = $required ?? false;
$disabled = $disabled ?? false;
$readonly = $readonly ?? false;
$error = $error ?? '';
$help = $help ?? '';
$attrs = $attrs ?? [];
$before = $before ?? '';
$after = $after ?? '';
$inline = $inline ?? false;
$labelClass = $labelClass ?? '';
$inputClass = $inputClass ?? '';
$wrapperClass = $wrapperClass ?? '';
$options = $options ?? [];

$hasError = $error !== '';
$inputId = 'field-' . $fid;

// Merge extra attributes
$attrStr = '';
foreach ($attrs as $k => $v) {
    if ($v === true) { $attrStr .= ' ' . $k; }
    elseif ($v !== false && $v !== null && $v !== '') { $attrStr .= ' ' . $k . '="' . e($v) . '"'; }
}

$wrapperClasses = ['form-group'];
if ($hasError) $wrapperClasses[] = 'has-error';
// No 'form-check' wrapper for $inline: inline branch renders its own; Bootstrap padding would compound.
if ($wrapperClass) $wrapperClasses[] = $wrapperClass;
$wrapperClassStr = implode(' ', $wrapperClasses);

$labelClasses = ['form-label'];
if ($labelClass) $labelClasses[] = $labelClass;
$labelClassStr = implode(' ', $labelClasses);

$inputClasses = ['form-control'];
if ($type === 'file') $inputClasses[] = 'form-control-file';
if ($inputClass) $inputClasses[] = $inputClass;
$inputClassStr = implode(' ', $inputClasses);
?>
<div class="<?= e($wrapperClassStr) ?>" data-field="<?= e($fid) ?>">
  <?php if (!$inline && $label): ?>
    <label for="<?= e($inputId) ?>" class="<?= e($labelClassStr) ?>">
      <?= e($label) ?>
      <?php if ($required): ?><span class="text-danger" aria-hidden="true">*</span><?php endif; ?>
    </label>
  <?php endif; ?>

  <?php if ($before): ?><div class="input-group-prepend"><?= $before ?></div><?php endif; ?>

  <?php if ($type === 'textarea'): ?>
    <textarea id="<?= e($inputId) ?>" name="<?= e($name) ?>" class="<?= e($inputClassStr) ?>"
              placeholder="<?= e($placeholder) ?>"
              rows="<?= e($attrs['rows'] ?? 4) ?>"
              <?= $required ? 'required' : '' ?>
              <?= $disabled ? 'disabled' : '' ?>
              <?= $readonly ? 'readonly' : '' ?>
              <?= $hasError ? 'aria-invalid="true" aria-describedby="' . e($inputId) . '-error"' : '' ?>
              <?= $attrStr ?>><?= e($value) ?></textarea>

  <?php elseif ($type === 'select'): ?>
    <select id="<?= e($inputId) ?>" name="<?= e($name) ?>" class="<?= e($inputClassStr) ?>"
            <?= $required ? 'required' : '' ?>
            <?= $disabled ? 'disabled' : '' ?>
            <?= $hasError ? 'aria-invalid="true" aria-describedby="' . e($inputId) . '-error"' : '' ?>
            <?= $attrStr ?>>
      <?php foreach ($options as $val => $lbl): ?>
        <option value="<?= e($val) ?>" <?= $value == $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>

  <?php elseif ($inline && ($type === 'checkbox' || $type === 'radio')): ?>
    <div class="form-check">
      <input type="<?= $type ?>" id="<?= e($inputId) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
             class="form-check-input"
             <?= $value ? 'checked' : '' ?>
             <?= $disabled ? 'disabled' : '' ?>
             <?= $required ? 'required' : '' ?>
             <?= $hasError ? 'aria-invalid="true" aria-describedby="' . e($inputId) . '-error"' : '' ?>
             <?= $attrStr ?>>
      <label class="form-check-label" for="<?= e($inputId) ?>">
        <?= e($label) ?>
      </label>
    </div>

  <?php elseif ($type === 'radio' || $type === 'checkbox'): ?>
    <div class="<?= $type === 'radio' ? 'radio-group' : 'checkbox-group' ?>">
      <?php foreach ($options as $val => $lbl): ?>
        <div class="form-check form-check-<?= $type ?>">
          <input type="<?= $type ?>" id="<?= e($inputId) . '-' . e($val) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>"
                 class="form-check-input"
                 <?= $value == $val ? 'checked' : '' ?>
                 <?= $disabled ? 'disabled' : '' ?>
                 <?= $required && $type === 'radio' ? 'required' : '' ?>
                 <?= $attrStr ?>>
          <label class="form-check-label" for="<?= e($inputId) . '-' . e($val) ?>"><?= e($lbl) ?></label>
        </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($type === 'file'): ?>
    <input type="file" id="<?= e($inputId) ?>" name="<?= e($name) ?>" class="<?= e($inputClassStr) ?>"
           <?= $required ? 'required' : '' ?>
           <?= $disabled ? 'disabled' : '' ?>
           <?= $hasError ? 'aria-invalid="true" aria-describedby="' . e($inputId) . '-error"' : '' ?>
           <?= $attrStr ?>>
    <?php if (!empty($attrs['accept'])): ?><small class="form-text"><?= e($attrs['accept']) ?></small><?php endif; ?>

  <?php else: ?>
    <input type="<?= e($type) ?>" id="<?= e($inputId) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
           class="<?= e($inputClassStr) ?>"
           placeholder="<?= e($placeholder) ?>"
           <?= $required ? 'required' : '' ?>
           <?= $disabled ? 'disabled' : '' ?>
           <?= $readonly ? 'readonly' : '' ?>
           <?= $hasError ? 'aria-invalid="true" aria-describedby="' . e($inputId) . '-error"' : '' ?>
           <?= $attrStr ?>>
  <?php endif; ?>

  <?php if ($after): ?><div class="input-group-append"><?= $after ?></div><?php endif; ?>

  <?php if ($error): ?>
    <div id="<?= e($inputId) ?>-error" class="invalid-feedback d-block" role="alert"><?= e($error) ?></div>
  <?php elseif ($help): ?>
    <div id="<?= e($inputId) ?>-help" class="form-text"><?= e($help) ?></div>
  <?php endif; ?>
</div>