<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
/** @var \Patylook\Models\Order $o */
$o = $page->data['order'];
use Patylook\Models\OrderStatus;
$view->script('/static/js/order_whatsapp.js');
?>
<div class="section-head">
  <div>
    <h1>Pedido #<?= e($o->id) ?> <?= $view->partial('status_badge', ['status' => $o->status]) ?></h1>
    <p class="muted">Criado em <?= e($fmt->datetime($o->createdAt)) ?> por <?= e($o->userName) ?></p>
  </div>
  <div class="actions-bar">
    <?php if ($o->status === OrderStatus::Open): ?>
    <form method="post" action="/orders/<?= e($o->id) ?>/status" class="inline" data-confirm="Concluir este pedido?">
      <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?><input type="hidden" name="status" value="completed">
      <button type="submit" class="btn btn-primary">Concluir</button>
    </form>
    <?php endif ?>
    <?php if ($o->status === OrderStatus::Completed): ?>
    <button type="button" class="btn" data-whatsapp-order="<?= e($o->id) ?>">Enviar por WhatsApp</button>
    <?php endif ?>
    <?php if ($o->status === OrderStatus::Open || $o->status === OrderStatus::Completed): ?>
    <form method="post" action="/orders/<?= e($o->id) ?>/status" class="inline" data-confirm="Cancelar este pedido? O estoque dos itens será devolvido.">
      <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?><input type="hidden" name="status" value="canceled">
      <button type="submit" class="btn btn-danger">Cancelar pedido</button>
    </form>
    <?php endif ?>
    <a class="btn" href="/orders">Voltar</a>
  </div>
</div>

<dl class="details">
  <div><dt>Cliente</dt><dd><?= e($o->customerName) ?></dd></div>
  <div><dt>Forma de pagamento</dt><dd><?= e($o->paymentTypeName) ?></dd></div>
  <?php if ($o->installments !== null): ?><div><dt>Parcelas</dt><dd><?= e($o->installments) ?>x de <?= e($fmt->moneyOrDash($o->installmentValue())) ?></dd></div><?php endif ?>
</dl>

<div class="table-wrap">
<table>
  <thead><tr><th>Produto</th><th>SKU</th><th class="num">Qtd.</th><th class="num">Preço unit.</th><th class="num">Desconto</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($o->items as $it): ?>
    <tr>
      <td><?= e($it->productName) ?></td>
      <td><code><?= e($it->productSku) ?></code></td>
      <td class="num"><?= e($it->quantity) ?></td>
      <td class="num"><?= e($fmt->money($it->unitPrice)) ?></td>
      <td class="num"><?= e($fmt->money($it->discount)) ?></td>
      <td class="num"><?= e($fmt->money($it->total())) ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>

<dl class="totals">
  <div><dt>Subtotal</dt><dd><?= e($fmt->money($o->subtotal)) ?></dd></div>
  <div><dt>Acréscimo do pagamento</dt><dd><?= e($fmt->money($o->paymentAddition)) ?></dd></div>
  <div><dt>Desconto no pedido</dt><dd><?= e($fmt->money($o->discount)) ?></dd></div>
  <div class="grand"><dt>Total</dt><dd><?= e($fmt->money($o->total)) ?></dd></div>
</dl>

<?php if ($o->notes !== ''): ?><h2>Observações</h2><p class="notes"><?= e($o->notes) ?></p><?php endif ?>

<?php if ($o->status === OrderStatus::Completed): ?>
<?= $view->partial('whatsapp_dialog') ?>
<?php if (!empty($page->data['offerWhatsapp'])): ?><span data-whatsapp-auto="<?= e($o->id) ?>" hidden></span><?php endif ?>
<?php endif ?>
