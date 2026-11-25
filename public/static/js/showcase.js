// Gender filter: choosing an option applies it right away, keeping the search text. It lives in its
// own block because the gallery below stops early on pages without products (empty filter result).
(function () {
  'use strict';

  var form = document.querySelector('[data-filter-form]');
  var select = form && form.querySelector('select[name="gender"]');
  if (!select) return;
  select.addEventListener('change', function () { form.submit(); });
})();

// Showcase gallery: clicking a product banner opens its images, the clicked one highlighted,
// with the remaining images in a thumbnail carousel.
(function () {
  'use strict';

  var dialog = document.getElementById('gallery');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  var stage = document.getElementById('gallery-image');
  var thumbsBox = document.getElementById('gallery-thumbs');
  var title = document.getElementById('gallery-title');
  var prev = document.getElementById('gallery-prev');
  var next = document.getElementById('gallery-next');

  var images = [];
  var index = 0;

  function show(i) {
    index = (i + images.length) % images.length;
    stage.src = images[index];
    Array.prototype.forEach.call(thumbsBox.children, function (thumb, n) {
      thumb.classList.toggle('active', n === index);
      thumb.setAttribute('aria-current', n === index ? 'true' : 'false');
      if (n === index) thumb.scrollIntoView({ block: 'nearest', inline: 'center' });
    });
  }

  function open(card) {
    images = Array.prototype.map.call(card.querySelectorAll('.gallery-data li'), function (li) {
      return li.getAttribute('data-src');
    });
    if (images.length === 0) return;

    title.textContent = card.getAttribute('data-name') || '';
    stage.alt = title.textContent;
    thumbsBox.textContent = '';
    images.forEach(function (src, n) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'gallery-thumb';
      button.setAttribute('aria-label', 'Imagem ' + (n + 1) + ' de ' + images.length);
      var img = document.createElement('img');
      img.src = src;
      img.alt = '';
      button.appendChild(img);
      button.addEventListener('click', function () { show(n); });
      thumbsBox.appendChild(button);
    });

    var many = images.length > 1;
    prev.hidden = next.hidden = thumbsBox.hidden = !many;
    show(0);
    dialog.showModal();
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-gallery-open]'), function (button) {
    button.addEventListener('click', function () { open(button.closest('.product-card')); });
  });

  prev.addEventListener('click', function () { show(index - 1); });
  next.addEventListener('click', function () { show(index + 1); });
  document.getElementById('gallery-close').addEventListener('click', function () { dialog.close(); });

  // Clicking the backdrop (the dialog element itself) closes it.
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) dialog.close();
  });

  dialog.addEventListener('keydown', function (event) {
    if (images.length < 2) return;
    if (event.key === 'ArrowLeft') show(index - 1);
    if (event.key === 'ArrowRight') show(index + 1);
  });
})();
