// "Send the order summary by WhatsApp". WhatsApp links cannot attach an image or pick the sending
// account, so the flow is: draw the image on the server, show it, open the customer's chat (from
// whichever WhatsApp account is signed in on this device) and put the image in the clipboard so it
// can be pasted into the conversation (or downloaded / shared). The user presses send in WhatsApp.
(function () {
  'use strict';

  var dialog = document.getElementById('wa-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  var lead = document.getElementById('wa-lead');
  var help = document.getElementById('wa-help');
  var image = document.getElementById('wa-image');
  var btnOpen = document.getElementById('wa-open');
  var btnShare = document.getElementById('wa-share');
  var btnDownload = document.getElementById('wa-download');

  var current = null; // { id, info, blob, file }
  var token = 0; // ignores answers that arrive after the dialog was closed or reopened

  function setHelp(text) {
    help.textContent = text;
    help.hidden = !text;
  }

  function reset() {
    current = null;
    lead.textContent = 'Preparando a imagem…';
    image.hidden = true;
    image.removeAttribute('src');
    setHelp('');
    btnOpen.disabled = true;
    btnDownload.disabled = true;
    btnShare.hidden = true;
    btnShare.disabled = true;
  }

  function fetchOk(url, asJson) {
    return fetch(url, { credentials: 'same-origin', headers: { Accept: asJson ? 'application/json' : 'image/png' } })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return asJson ? response.json() : response.blob();
      });
  }

  function openFor(orderId) {
    var mine = ++token;
    reset();
    dialog.showModal();

    Promise.all([fetchOk('/orders/' + orderId + '/whatsapp', true), fetchOk('/orders/' + orderId + '/banner.png', false)])
      .then(function (result) {
        if (mine !== token) return;
        var info = result[0];
        var blob = result[1];
        var file = typeof File === 'function' ? new File([blob], 'pedido-' + orderId + '.png', { type: 'image/png' }) : null;
        current = { id: orderId, info: info, blob: blob, file: file };

        var reader = new FileReader(); // a data: URL, so the preview stays inside the page's CSP
        reader.onload = function () {
          if (mine !== token) return;
          image.src = reader.result;
          image.hidden = false;
        };
        reader.readAsDataURL(blob);

        btnDownload.disabled = false;
        if (info.chatUrl) {
          lead.textContent = 'Enviar o resumo do pedido #' + orderId + ' para ' + info.customerName + ' (' + info.customerPhone + ')?';
          btnOpen.disabled = false;
          var from = info.sellerName ? 'Use o WhatsApp de ' + info.sellerName + (info.sellerPhone ? ' (' + info.sellerPhone + ')' : '') + '. ' : '';
          setHelp(from + 'Ao abrir o WhatsApp a imagem é copiada: na conversa, cole (Ctrl+V) e envie. Se não colar, baixe a imagem e anexe.');
        } else {
          lead.textContent = info.customerName + ' não tem um telefone válido cadastrado, então não é possível abrir a conversa.';
          setHelp('Você ainda pode baixar a imagem e enviá-la por outro meio.');
        }
        if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
          btnShare.hidden = false;
          btnShare.disabled = false;
        }
      })
      .catch(function () {
        if (mine !== token) return;
        lead.textContent = 'Não foi possível gerar a imagem deste pedido. Tente novamente.';
      });
  }

  btnOpen.addEventListener('click', function () {
    if (!current || !current.info.chatUrl) return;
    var copied = false;
    // Both calls stay inside the click so the browser allows the clipboard and the new tab.
    if (navigator.clipboard && window.ClipboardItem) {
      try {
        navigator.clipboard.write([new window.ClipboardItem({ 'image/png': current.blob })]).then(function () {
          setHelp('Imagem copiada. Na conversa do WhatsApp, cole (Ctrl+V) e envie.');
        }, function () {
          setHelp('Não foi possível copiar a imagem. Use "Baixar imagem" e anexe na conversa.');
        });
        copied = true;
      } catch (e) { /* falls through to the hint below */ }
    }
    window.open(current.info.chatUrl, '_blank', 'noopener');
    if (!copied) setHelp('Baixe a imagem e anexe na conversa do WhatsApp (clipe de anexo).');
  });

  btnDownload.addEventListener('click', function () {
    if (!current) return;
    var url = URL.createObjectURL(current.blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = 'pedido-' + current.id + '.png';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  });

  btnShare.addEventListener('click', function () {
    if (!current || !current.file) return;
    navigator.share({ files: [current.file], title: 'Pedido #' + current.id }).catch(function () { /* cancelled */ });
  });

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-whatsapp-order]');
    if (trigger) {
      event.preventDefault();
      openFor(trigger.getAttribute('data-whatsapp-order'));
    } else if (event.target.closest('[data-wa-close]')) {
      dialog.close();
    }
  });
  dialog.addEventListener('close', function () { token++; reset(); });

  // Just after an order is completed the page asks right away; the flag is dropped from the address
  // so a reload does not ask again.
  var auto = document.querySelector('[data-whatsapp-auto]');
  if (auto) {
    openFor(auto.getAttribute('data-whatsapp-auto'));
    if (window.history && history.replaceState) {
      history.replaceState(null, '', window.location.pathname);
    }
  }
})();
