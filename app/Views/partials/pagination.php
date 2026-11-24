<?php /** @var \Patylook\Http\Pager $pager */ ?>
<?php if ($pager->pages > 1): ?>
<nav class="pagination" aria-label="Paginação">
  <?php if ($pager->prevUrl !== ''): ?><a href="<?= e($pager->prevUrl) ?>">« Anterior</a><?php endif ?>
  <span>Página <?= e($pager->page) ?> de <?= e($pager->pages) ?> (<?= e($pager->total) ?> registros)</span>
  <?php if ($pager->nextUrl !== ''): ?><a href="<?= e($pager->nextUrl) ?>">Próxima »</a><?php endif ?>
</nav>
<?php endif ?>
