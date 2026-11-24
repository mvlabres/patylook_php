<?php /** @var array<string, string> $errors */ ?>
<?php if (($errors[''] ?? '') !== ''): ?><div class="alert alert-error" role="alert"><?= e($errors['']) ?></div><?php endif ?>
