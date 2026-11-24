<?php
/**
 * Text input. Variables: label, name, value, errors; optional: type, required, max, mode, hint.
 *
 * @var string $label
 * @var string $name
 * @var string|int $value
 * @var array<string, string> $errors
 */
$type ??= 'text';
$required ??= false;
$max ??= null;
$mode ??= null;
$hint ??= null;
$error = $errors[$name] ?? '';
?>
<div class="field<?= $error !== '' ? ' has-error' : '' ?>">
  <label for="f-<?= e($name) ?>"><?= e($label) ?></label>
  <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>"<?= $required ? ' required' : '' ?><?= $max !== null ? ' maxlength="' . e($max) . '"' : '' ?><?= $mode !== null ? ' inputmode="' . e($mode) . '"' : '' ?>>
  <?php if ($hint !== null): ?><small class="hint"><?= e($hint) ?></small><?php endif ?>
  <?php if ($error !== ''): ?><small class="field-error"><?= e($error) ?></small><?php endif ?>
</div>
