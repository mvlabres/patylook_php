// Global behavior. CSP forbids inline handlers, so confirmations are declared with data-confirm.
document.addEventListener('submit', function (event) {
  var message = event.target.getAttribute('data-confirm');
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});
