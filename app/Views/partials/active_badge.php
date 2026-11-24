<?php
/**
 * @var bool $active
 * @var string $on   label when active
 * @var string $off  label when inactive
 */
?>
<?php if ($active): ?><span class="badge badge-completed"><?= e($on) ?></span><?php else: ?><span class="badge badge-canceled"><?= e($off) ?></span><?php endif ?>
