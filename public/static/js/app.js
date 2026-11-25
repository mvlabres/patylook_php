// Global behavior. CSP forbids inline handlers, so confirmations are declared with data-confirm.
document.addEventListener('submit', function (event) {
  var message = event.target.getAttribute('data-confirm');
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});

// Side menu (phones): the button opens the drawer; the backdrop, the close button, Esc and picking an
// entry close it. On wide screens the menu is always visible and this does nothing.
(function () {
  'use strict';

  var toggle = document.querySelector('[data-menu-toggle]');
  if (!toggle) return;
  var root = document.body;

  function setOpen(open) {
    root.classList.toggle('menu-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-menu-toggle]')) {
      setOpen(!root.classList.contains('menu-open'));
    } else if (event.target.closest('[data-menu-close], .nav a')) {
      setOpen(false);
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && root.classList.contains('menu-open')) {
      setOpen(false);
      toggle.focus();
    }
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 768) setOpen(false);
  });
})();
