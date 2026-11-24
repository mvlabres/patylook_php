<?php /** @var \Patylook\Core\PageData $page */ ?>
<div class="error-page">
  <p class="error-code"><?= e($page->data['Status']) ?></p>
  <h1><?= e($page->title) ?></h1>
  <p class="muted"><?= e($page->data['Message']) ?></p>
  <p><a class="btn btn-primary" href="/">Voltar ao início</a></p>
</div>
