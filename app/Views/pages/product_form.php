<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
/** @var \Patylook\Models\Product $p */
$p = $page->data['product'];
$errors = $page->errors;
$view->script('/static/js/product_form.js');
$isNew = !$p->id;
?>
<h1><?= $isNew ? 'Novo produto' : 'Editar produto' ?></h1>
<form method="post" action="<?= $isNew ? '/products' : '/products/' . e($p->id) ?>" class="form"<?= $isNew ? ' enctype="multipart/form-data"' : '' ?>>
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $errors]) ?>
  <?= $view->partial('field', ['label' => 'Nome', 'name' => 'name', 'value' => $p->name, 'errors' => $errors, 'required' => true, 'max' => 150]) ?>
  <div class="field<?= isset($errors['product_category_id']) ? ' has-error' : '' ?>">
    <label for="f-product_category_id">Categoria</label>
    <select id="f-product_category_id" name="product_category_id" required<?= $isNew ? ' data-sku-part="category"' : '' ?>>
      <option value="">Selecione</option>
      <?php foreach ($page->data['categories'] as $c): ?><option value="<?= e($c->id) ?>" <?= $c->id === $p->categoryId ? 'selected' : '' ?>><?= e($c->name) ?></option><?php endforeach ?>
    </select>
    <?php if (isset($errors['product_category_id'])): ?><small class="field-error"><?= e($errors['product_category_id']) ?></small><?php endif ?>
  </div>
  <div class="field<?= isset($errors['brand_id']) ? ' has-error' : '' ?>">
    <label for="f-brand_id">Marca</label>
    <select id="f-brand_id" name="brand_id"<?= $isNew ? ' required data-sku-part="brand"' : '' ?>>
      <option value=""><?= $isNew ? 'Selecione' : 'Sem marca' ?></option>
      <?php foreach ($page->data['brands'] as $b): ?><option value="<?= e($b->id) ?>" <?= $b->id === $p->brandId ? 'selected' : '' ?>><?= e($b->name) ?></option><?php endforeach ?>
    </select>
    <?php if ($page->data['brands'] === []): ?><small class="hint">Nenhuma marca ativa. <a href="/brands/new">Cadastre uma marca</a> para continuar.</small><?php endif ?>
    <?php if (isset($errors['brand_id'])): ?><small class="field-error"><?= e($errors['brand_id']) ?></small><?php endif ?>
  </div>
  <div class="field<?= isset($errors['gender']) ? ' has-error' : '' ?>">
    <label for="f-gender">Gênero</label>
    <select id="f-gender" name="gender" required>
      <option value="">Selecione</option>
      <?php foreach (\Patylook\Models\Gender::cases() as $g): ?><option value="<?= e($g->value) ?>" <?= $g === $p->gender ? 'selected' : '' ?>><?= e($g->label()) ?></option><?php endforeach ?>
    </select>
    <?php if (isset($errors['gender'])): ?><small class="field-error"><?= e($errors['gender']) ?></small><?php endif ?>
  </div>
  <div class="field<?= isset($errors['sku']) ? ' has-error' : '' ?>">
    <label for="f-sku">SKU</label>
    <input id="f-sku" type="text" value="<?= e($p->sku) ?>" readonly<?= $isNew ? ' placeholder="Selecione categoria e marca" data-sku-preview' : '' ?>>
    <?php if (!$isNew): ?>
      <small class="hint">O SKU é gerado no cadastro, nomeia a pasta das imagens e não pode ser alterado.</small>
    <?php else: ?>
      <small class="hint">Gerado automaticamente: 3 primeiras letras da categoria e da marca + data e hora (ddmmaaHHMM). A data e hora definitivas são as do momento do cadastro. Nomeia a pasta das imagens: images/products/SKU/</small>
    <?php endif ?>
    <?php if (isset($errors['sku'])): ?><small class="field-error"><?= e($errors['sku']) ?></small><?php endif ?>
  </div>
  <?= $view->partial('field', ['label' => 'Estoque', 'name' => 'stock', 'value' => $p->stock, 'errors' => $errors, 'required' => true, 'type' => 'number', 'mode' => 'numeric']) ?>
  <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
    <label for="f-description">Descrição</label>
    <textarea id="f-description" name="description" rows="3" maxlength="500"><?= e($p->description) ?></textarea>
    <?php if (isset($errors['description'])): ?><small class="field-error"><?= e($errors['description']) ?></small><?php endif ?>
  </div>

  <?php if ($isNew): ?>
  <fieldset class="images-fieldset">
    <legend>Imagens</legend>
    <p class="muted">JPEG ou PNG, até <?= e($page->data['maxMB']) ?> MB cada. Serão convertidas para JPEG e salvas em <code>images/products/SKU/</code>.</p>
    <div class="field<?= isset($errors['main_image']) ? ' has-error' : '' ?>">
      <label for="f-main_image">Imagem principal (obrigatória)</label>
      <input id="f-main_image" name="main_image" type="file" accept="image/jpeg,image/png" required>
      <small class="hint">Vira <code>principal.jpg</code> e é o banner do produto no mostruário.</small>
      <?php if (isset($errors['main_image'])): ?><small class="field-error"><?= e($errors['main_image']) ?></small><?php endif ?>
    </div>
    <div class="field<?= isset($errors['images']) ? ' has-error' : '' ?>">
      <label for="f-images">Imagens adicionais (opcional)</label>
      <input id="f-images" name="images[]" type="file" accept="image/jpeg,image/png" multiple>
      <small class="hint">Até <?= e($page->data['maxExtra']) ?> imagens; aparecem no carrossel do mostruário.</small>
      <?php if (isset($errors['images'])): ?><small class="field-error"><?= e($errors['images']) ?></small><?php endif ?>
    </div>
  </fieldset>
  <?php endif ?>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn" href="/products">Cancelar</a>
  </div>
</form>

<?php if (!$isNew): ?>
<h2>Imagens</h2>
<p class="muted">Pasta: <code>images/products/<?= e($p->sku) ?>/</code></p>
<?php if ($page->data['images'] !== []): ?>
<ul class="thumbs">
  <?php foreach ($page->data['images'] as $img): ?>
  <li>
    <img src="<?= e($img->url()) ?>" alt="<?= e($img->fileName) ?>" loading="lazy">
    <span class="thumb-name"><?= $img->isMain() ? 'Principal' : e($img->fileName) ?></span>
    <?php if (!$img->isMain()): ?>
    <form method="post" action="/products/<?= e($img->productId) ?>/images/<?= e($img->id) ?>/delete" class="inline" data-confirm="Remover esta imagem?">
      <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
      <button type="submit" class="btn-link danger">Remover</button>
    </form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
<?php else: ?>
<p class="empty">Este produto ainda não tem imagens.</p>
<?php endif ?>

<form method="post" action="/products/<?= e($p->id) ?>/images" enctype="multipart/form-data" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <div class="field<?= isset($errors['main_image']) ? ' has-error' : '' ?>">
    <label for="f-main_image"><?= $page->data['hasMain'] ? 'Substituir imagem principal' : 'Imagem principal (obrigatória)' ?></label>
    <input id="f-main_image" name="main_image" type="file" accept="image/jpeg,image/png">
    <?php if (isset($errors['main_image'])): ?><small class="field-error"><?= e($errors['main_image']) ?></small><?php endif ?>
  </div>
  <div class="field<?= isset($errors['images']) ? ' has-error' : '' ?>">
    <label for="f-images">Adicionar imagens ao carrossel</label>
    <input id="f-images" name="images[]" type="file" accept="image/jpeg,image/png" multiple>
    <small class="hint">JPEG ou PNG, até <?= e($page->data['maxMB']) ?> MB cada; no máximo <?= e($page->data['maxExtra']) ?> imagens adicionais por produto.</small>
    <?php if (isset($errors['images'])): ?><small class="field-error"><?= e($errors['images']) ?></small><?php endif ?>
  </div>
  <div class="form-actions"><button type="submit" class="btn">Enviar imagens</button></div>
</form>
<?php endif ?>
