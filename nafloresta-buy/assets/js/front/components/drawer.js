(function (window, document) {
  function createDrawer() {
    const overlay = document.createElement('div');
    overlay.className = 'nafb-drawer-overlay';

    const drawer = document.createElement('aside');
    drawer.className = 'nafb-drawer nafb-drawer--cart';
    drawer.innerHTML = `
      <header class="nafb-drawer__header">
        <h4 data-role="cart-drawer-title">Editar personalização</h4>
        <button type="button" class="nafb-drawer__close" data-action="close-cart-drawer" aria-label="Fechar">×</button>
      </header>
      <nav class="nafb-steps" data-role="cart-steps">
        <span data-step="1">1. Seleção</span>
        <span data-step="2">2. Personalização</span>
        <span data-step="3">3. Revisão</span>
      </nav>
      <div class="nafb-drawer__quantity" role="group">
        <button type="button" data-action="decrease-qty">−</button>
        <input type="number" data-role="cart-qty" readonly value="0" />
        <button type="button" data-action="increase-qty">+</button>
      </div>
      <div data-role="cart-students"></div>
      <button type="button" class="button alt vs-btn" data-action="save-cart-edit">Salvar alteração</button>
      <div class="nafb-messages" data-role="cart-message"></div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(drawer);

    return { overlay, drawer };
  }

  window.NAFBDrawer = { createDrawer };
})(window, document);
