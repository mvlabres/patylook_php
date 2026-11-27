// Live preview of order totals. The server recalculates everything on save.
(function () {
  'use strict';

  var form = document.getElementById('order-form');
  if (!form) return;

  var rows = document.getElementById('item-rows');
  var template = document.getElementById('row-template');

  // "1.234,56" | "1234,56" | "1234.56" -> cents (NaN when invalid)
  function parseCents(text) {
    text = String(text || '').trim();
    if (text === '') return 0;
    if (text.indexOf(',') !== -1) text = text.replace(/\./g, '').replace(',', '.');
    if (!/^\d*\.?\d{0,2}$/.test(text) || text === '.') return NaN;
    var parts = text.split('.');
    var frac = ((parts[1] || '') + '00').slice(0, 2);
    return parseInt(parts[0] || '0', 10) * 100 + parseInt(frac, 10);
  }

  function formatCents(cents) {
    var negative = cents < 0;
    cents = Math.abs(cents);
    var reais = String(Math.floor(cents / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    var rest = ('0' + (cents % 100)).slice(-2);
    return (negative ? '-' : '') + 'R$ ' + reais + ',' + rest;
  }

  function valid(n) { return typeof n === 'number' && !isNaN(n); }

  function recalc() {
    var subtotal = 0;
    Array.prototype.forEach.call(rows.querySelectorAll('.item-row'), function (row) {
      var option = row.querySelector('.item-product').selectedOptions[0];
      var qty = parseInt(row.querySelector('.item-qty').value, 10);
      var discount = parseCents(row.querySelector('.item-discount').value);
      var price = option && option.value ? parseCents(option.getAttribute('data-price')) : 0;
      var line = 0;
      if (valid(price) && valid(discount) && qty > 0) {
        line = price * qty - discount;
        var stock = option ? parseInt(option.getAttribute('data-stock'), 10) : NaN;
        row.querySelector('.item-qty').max = valid(stock) ? stock : '';
      }
      row.querySelector('.item-total').textContent = formatCents(line);
      subtotal += line;
    });

    var discount = parseCents(document.getElementById('f-discount').value);
    if (!valid(discount)) discount = 0;
    var payment = document.getElementById('f-payment_type_id').selectedOptions[0];
    var pct = payment && payment.value ? parseCents(payment.getAttribute('data-surcharge')) : 0;
    // The surcharge applies over the subtotal; the order discount comes off the final value.
    var addition = Math.round(subtotal * (valid(pct) ? pct : 0) / 10000);

    document.getElementById('t-subtotal').textContent = formatCents(subtotal);
    document.getElementById('t-addition').textContent = formatCents(addition);
    document.getElementById('t-discount').textContent = formatCents(discount);
    var total = Math.max(subtotal + addition - discount, 0);
    document.getElementById('t-total').textContent = formatCents(total);

    // Installments only exist for credit payments; the value shown is informational.
    var credit = !!payment && payment.getAttribute('data-category') === 'credit';
    var installmentsInput = document.getElementById('f-installments');
    document.getElementById('installments-row').hidden = !credit;
    installmentsInput.disabled = !credit;
    var n = parseInt(installmentsInput.value, 10);
    document.getElementById('t-installment').value = credit && n >= 1 ? formatCents(Math.round(total / n)) : '—';
  }

  document.getElementById('add-row').addEventListener('click', function () {
    rows.appendChild(template.content.cloneNode(true));
    recalc();
  });

  rows.addEventListener('click', function (event) {
    if (!event.target.classList.contains('remove-row')) return;
    var row = event.target.closest('.item-row');
    if (rows.querySelectorAll('.item-row').length > 1) {
      row.remove();
    } else {
      row.querySelector('.item-product').value = '';
      row.querySelector('.item-qty').value = '1';
      row.querySelector('.item-discount').value = '';
    }
    recalc();
  });

  form.addEventListener('input', recalc);
  form.addEventListener('change', recalc);
  recalc();
})();
