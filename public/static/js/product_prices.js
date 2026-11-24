// Shows the markup of a new price entry while the form is filled. The markup is the reverse of
// "final price = total cost + markup": (final price - total cost) / total cost. The server
// recalculates it when the entry is saved; the markup field is read-only and never submitted.
(function () {
  var form = document.getElementById('price-form');
  if (!form) {
    return;
  }
  var markup = document.getElementById('f-markup_percent');
  var finalPrice = form.elements['final_price'];
  var required = form.elements['product_cost'];
  var optional = [form.elements['packaging_cost'], form.elements['logistics_cost'], form.elements['other_costs']];
  if (!markup || !finalPrice || !required) {
    return;
  }

  // Same formats the server accepts ("1234.56", "1234,56", "1.234,56"); returns cents or null.
  function cents(text, allowEmpty) {
    var s = text.trim();
    if (s === '') {
      return allowEmpty ? 0n : null;
    }
    if (s.indexOf(',') >= 0) {
      s = s.replace(/\./g, '').replace(',', '.');
    }
    var m = /^(\d*)(?:\.(\d{0,2}))?$/.exec(s);
    if (!m || (m[1] === '' && !m[2])) {
      return null;
    }
    return BigInt(m[1] || '0') * 100n + BigInt(((m[2] || '') + '00').slice(0, 2));
  }

  // Integer division rounding half away from zero, like the server.
  function roundDiv(n, d) {
    if (n < 0n) {
      return -roundDiv(-n, d);
    }
    return (n + d / 2n) / d;
  }

  function format(hundredths) {
    var abs = hundredths < 0n ? -hundredths : hundredths;
    return (hundredths < 0n ? '-' : '') + (abs / 100n) + ',' + String(abs % 100n).padStart(2, '0');
  }

  function update() {
    var cost = cents(required.value, false);
    var final = cents(finalPrice.value, false);
    for (var i = 0; i < optional.length && cost !== null; i++) {
      var extra = cents(optional[i].value, true);
      cost = extra === null ? null : cost + extra;
    }
    if (cost === null || final === null) {
      markup.value = '';
      return;
    }
    markup.value = cost === 0n ? '0,00' : format(roundDiv((final - cost) * 10000n, cost));
  }

  form.addEventListener('input', update);
  update();
})();
