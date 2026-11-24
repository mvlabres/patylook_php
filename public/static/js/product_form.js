// Previews the SKU of a new product while the form is filled. The server generates the real SKU
// (same rule, its own clock) when the product is saved; the field is read-only and never submitted.
(function () {
  var sku = document.querySelector('[data-sku-preview]');
  if (!sku) {
    return;
  }
  var category = document.querySelector('select[data-sku-part="category"]');
  var brand = document.querySelector('select[data-sku-part="brand"]');
  if (!category || !brand) {
    return;
  }

  // First three letters or digits, upper case, without accents; "XXX" when there are none.
  // The class in the first replace() spans the combining marks U+0300 to U+036F left by NFD.
  function prefix(text) {
    var clean = text.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    return clean.slice(0, 3) || 'XXX';
  }

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  // ddMMyyHHmm
  function stamp(d) {
    return pad(d.getDate()) + pad(d.getMonth() + 1) + pad(d.getFullYear() % 100) + pad(d.getHours()) + pad(d.getMinutes());
  }

  function label(select) {
    return select.value ? select.options[select.selectedIndex].text : '';
  }

  function update() {
    var c = label(category);
    var b = label(brand);
    sku.value = c && b ? prefix(c) + '-' + prefix(b) + '-' + stamp(new Date()) : '';
  }

  category.addEventListener('change', update);
  brand.addEventListener('change', update);
  update();
})();
