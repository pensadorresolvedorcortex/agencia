(function () {
  'use strict';

  var buttonPagamento = document.getElementById('btnPagamento');
  if (buttonPagamento) {
    buttonPagamento.addEventListener('click', function () {
      var checkoutBase = (window.rmaGlassTheme && window.rmaGlassTheme.checkoutUrl) ? window.rmaGlassTheme.checkoutUrl : '/checkout/';
      var productId = Number(buttonPagamento.getAttribute('data-product-id') || 3407);
      var target = buttonPagamento.getAttribute('data-checkout-url');
      if (!target) {
        try {
          var checkoutUrl = new URL(checkoutBase, window.location.origin);
          checkoutUrl.searchParams.set('add-to-cart', String(productId));
          target = checkoutUrl.toString();
        } catch (e) {
          var separator = checkoutBase.indexOf('?') === -1 ? '?' : '&';
          target = checkoutBase + separator + 'add-to-cart=' + productId;
        }
      }
      buttonPagamento.disabled = true;
      buttonPagamento.textContent = 'Processando pagamento...';
      window.location.href = target;
    });
  }

  var buttonVoltar = document.getElementById('btnVoltar');
  if (buttonVoltar) {
    buttonVoltar.addEventListener('click', function () {
      window.history.back();
    });
  }

})();
