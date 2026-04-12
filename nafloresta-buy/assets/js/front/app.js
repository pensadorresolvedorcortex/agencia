(function (window, document) {
  function toast(message, type) {
    if (window.NAFBToast) window.NAFBToast.show(message, type);
  }

  function track(action, payload) {
    if (window.NAFBEvents && typeof window.NAFBEvents.track === 'function') {
      window.NAFBEvents.track(action, payload || {});
    }
  }

  function emitUxEvent(name, payload) {
    document.dispatchEvent(new CustomEvent(`nafb:${name}`, {
      detail: payload || {},
    }));
  }

  function escHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function sanitizeWooPriceHtml(html) {
    let source = String(html || '');
    for (let i = 0; i < 5; i += 1) {
      if (!/&(?:lt|gt|amp|quot|#0*39);/i.test(source)) break;
      const textarea = document.createElement('textarea');
      textarea.innerHTML = source;
      source = textarea.value;
    }
    const template = document.createElement('template');
    template.innerHTML = source;
    const allowed = new Set(['SPAN', 'BDI']);
    const walk = (node) => {
      Array.from(node.children).forEach((child) => {
        if (!allowed.has(child.tagName)) {
          const text = document.createTextNode(child.textContent || '');
          child.replaceWith(text);
          return;
        }
        Array.from(child.attributes).forEach((attr) => {
          if (!(child.tagName === 'SPAN' && attr.name === 'class')) {
            child.removeAttribute(attr.name);
          }
        });
        walk(child);
      });
    };
    walk(template.content);
    return template.innerHTML;
  }

  function postBatch(payload) {
    return fetch(nafbApp.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams({
        action: 'nafb_batch_add_to_cart',
        nonce: nafbApp.nonce,
        payload: JSON.stringify(payload),
      }),
    }).then((response) => response.json());
  }

  function normalizeAjaxErrors(responseData) {
    if (!responseData || !Array.isArray(responseData.errors)) return ['Erro inesperado ao processar sua solicitação.'];
    const mapped = responseData.errors.map((error) => (error && error.message ? String(error.message) : 'Erro desconhecido.')).filter(Boolean);
    return mapped.length ? mapped : ['Erro inesperado ao processar sua solicitação.'];
  }

  function fetchCartSummary(productId) {
    return fetch(nafbApp.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams({
        action: 'nafb_get_cart_summary',
        nonce: nafbApp.summaryNonce,
        product_id: String(productId || 0),
      }),
    }).then((response) => response.json());
  }

  function removeCartItem(cartKey) {
    return fetch(nafbApp.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams({
        action: 'nafb_remove_cart_item',
        nonce: nafbApp.summaryNonce,
        cart_key: String(cartKey || ''),
      }),
    }).then((response) => response.json());
  }

  function isSubtotalDebugEnabled() {
    const byFlag = !!(window.nafbApp && nafbApp.debugSubtotal);
    const byQuery = new URLSearchParams(window.location.search).get('nafb_debug_subtotal') === '1';
    return byFlag || byQuery;
  }

  function initNativePersonalization() {
    const layers = Array.from(document.querySelectorAll('#nafb-native-layer'));
    if (!layers.length) return;
    const [layer, ...duplicates] = layers;
    duplicates.forEach((node) => node.remove());

    const form = document.querySelector('form.variations_form');
    if (!layer || !form) return;
    if (layer.dataset.nafbInit === '1') return;
    layer.dataset.nafbInit = '1';

    const config = JSON.parse(layer.dataset.config || '{}');
    const overlay = layer.querySelector('[data-role="nafb-modal-overlay"]');
    const modal = layer.querySelector('[data-role="nafb-modal"]');
    const closeBtn = layer.querySelector('[data-role="nafb-modal-close"]');
    const okBtn = layer.querySelector('[data-role="nafb-modal-ok"]');
    const subtitle = layer.querySelector('[data-role="nafb-modal-subtitle"]');
    const helper = layer.querySelector('[data-role="nafb-modal-helper"]');
    const progress = layer.querySelector('[data-role="nafb-modal-progress"]');
    const fieldsWrap = layer.querySelector('[data-role="nafb-modal-fields"]');
    const qtyInput = form.querySelector('input.qty');
    const variationIdInput = form.querySelector('input[name="variation_id"]');
    const submitBtn = form.querySelector('.single_add_to_cart_button');
    const orderSummary = layer.querySelector('[data-role="nafb-order-summary"]');
    const orderSummaryList = layer.querySelector('[data-role="nafb-order-summary-list"]');
    const orderSummarySubtotal = layer.querySelector('[data-role="nafb-order-summary-subtotal"]');
    const orderSummaryEmpty = layer.querySelector('[data-role="nafb-order-summary-empty"]');
    const orderSummaryStatus = layer.querySelector('[data-role="nafb-order-summary-status"]');
    const addMoreBtn = layer.querySelector('[data-role="nafb-order-add-more"]');
    const goCartLink = layer.querySelector('[data-role="nafb-order-go-cart"]');
    const exitHint = layer.querySelector('[data-role="nafb-exit-hint"]');
    const exitCheckout = layer.querySelector('[data-role="nafb-exit-checkout"]');
    const exitContinue = layer.querySelector('[data-role="nafb-exit-continue"]');
    const mobileCheckout = layer.querySelector('[data-role="nafb-mobile-checkout"]');
    const mobileCheckoutLink = layer.querySelector('[data-role="nafb-mobile-checkout-link"]');
    const subtotalDebug = {
      enabled: isSubtotalDebugEnabled(),
      panel: null,
      fields: null,
      state: 'initial',
      lastWriter: 'none',
      insertionMethod: 'none',
      raw: '',
      sanitized: '',
      finalHtml: '',
    };

    const state = {
      currentVariationId: 0,
      namesByVariation: {},
      isOpen: false,
      bodyScrollY: 0,
      hasAutoScrolledToSummary: false,
      lastSubtotalText: '',
      checkoutClicked: false,
      idleReminderTimer: 0,
      hasLoadedSummaryOnce: false,
    };
    if (goCartLink && nafbApp.cartUrl) goCartLink.setAttribute('href', nafbApp.cartUrl);
    if (exitCheckout && nafbApp.cartUrl) exitCheckout.setAttribute('href', nafbApp.cartUrl);
    if (mobileCheckoutLink && nafbApp.cartUrl) mobileCheckoutLink.setAttribute('href', nafbApp.cartUrl);

    function ensureSubtotalDebugPanel() {
      if (!subtotalDebug.enabled || subtotalDebug.panel) return;
      const panel = document.createElement('aside');
      panel.className = 'nafb-subtotal-debug';
      panel.innerHTML = `
        <h5>NAFB Subtotal Debug</h5>
        <div><strong>state:</strong> <span data-role="state"></span></div>
        <div><strong>node exists:</strong> <span data-role="node"></span></div>
        <div><strong>writer:</strong> <span data-role="writer"></span></div>
        <div><strong>method:</strong> <span data-role="method"></span></div>
        <label>raw subtotal_html</label><pre data-role="raw"></pre>
        <label>sanitized</label><pre data-role="sanitized"></pre>
        <label>final footer innerHTML</label><pre data-role="final"></pre>
      `;
      document.body.appendChild(panel);
      subtotalDebug.panel = panel;
      subtotalDebug.fields = {
        state: panel.querySelector('[data-role="state"]'),
        node: panel.querySelector('[data-role="node"]'),
        writer: panel.querySelector('[data-role="writer"]'),
        method: panel.querySelector('[data-role="method"]'),
        raw: panel.querySelector('[data-role="raw"]'),
        sanitized: panel.querySelector('[data-role="sanitized"]'),
        final: panel.querySelector('[data-role="final"]'),
      };
    }

    function updateSubtotalDebug() {
      if (!subtotalDebug.enabled) return;
      ensureSubtotalDebugPanel();
      const fields = subtotalDebug.fields;
      if (!fields) return;
      fields.state.textContent = subtotalDebug.state;
      fields.node.textContent = orderSummarySubtotal ? 'yes' : 'no';
      fields.writer.textContent = subtotalDebug.lastWriter;
      fields.method.textContent = subtotalDebug.insertionMethod;
      fields.raw.textContent = String(subtotalDebug.raw || '');
      fields.sanitized.textContent = String(subtotalDebug.sanitized || '');
      fields.final.textContent = String(subtotalDebug.finalHtml || '');
    }

    function hasSummaryItems() {
      return orderSummaryList && orderSummaryList.children.length > 0;
    }

    function scheduleIdleReminder() {
      clearTimeout(state.idleReminderTimer);
      if (!hasSummaryItems()) return;
      state.idleReminderTimer = window.setTimeout(() => {
        toast('Você já pode finalizar seu pedido quando quiser', 'success');
      }, 17000);
    }

    function lockBodyScroll() {
      state.bodyScrollY = window.scrollY || window.pageYOffset || 0;
      document.body.classList.add('nafb-modal-open');
      document.body.style.position = 'fixed';
      document.body.style.top = `-${state.bodyScrollY}px`;
      document.body.style.left = '0';
      document.body.style.right = '0';
      document.body.style.width = '100%';
    }

    function unlockBodyScroll() {
      document.body.classList.remove('nafb-modal-open');
      document.body.style.position = '';
      document.body.style.top = '';
      document.body.style.left = '';
      document.body.style.right = '';
      document.body.style.width = '';
      window.scrollTo(0, state.bodyScrollY);
    }

    function quantity() {
      return Math.max(1, Number((qtyInput && qtyInput.value) || 1));
    }

    function getLabel(variationId) {
      const variation = (config.variations || {})[variationId] || {};
      return variation.label || `Variação ${variationId}`;
    }

    function setModalOpen(open) {
      state.isOpen = open;
      overlay.classList.toggle('is-open', open);
      modal.classList.toggle('is-open', open);
      overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
      if (open) {
        lockBodyScroll();
        setTimeout(() => {
          const first = fieldsWrap.querySelector('input');
          if (first) first.focus();
        }, 20);
      } else {
        unlockBodyScroll();
      }
    }

    function ensureNames(variationId) {
      const count = quantity();
      const current = state.namesByVariation[variationId] || [];
      state.namesByVariation[variationId] = Array.from({ length: count }, (_, i) => current[i] || '');
      return state.namesByVariation[variationId];
    }

    function renderFields(variationId) {
      const names = ensureNames(variationId);
      fieldsWrap.innerHTML = '';

      names.forEach((value, index) => {
        const label = names.length === 1 ? 'Nome do aluno' : `Nome do aluno ${index + 1}`;
        const row = document.createElement('div');
        row.className = 'nafb-modal__field';
        row.innerHTML = `
          <label class="nafb-modal__label" for="nafb-student-name-${index}">${label}</label>
          <input id="nafb-student-name-${index}" type="text" class="nafb-modal__input" data-index="${index}" placeholder="Digite o nome do aluno" value="${String(value || '').replace(/"/g, '&quot;')}" />
          <p class="nafb-modal__error" data-role="error"></p>
        `;
        fieldsWrap.appendChild(row);
      });
    }

    function openModal(variationId) {
      state.currentVariationId = variationId;
      subtitle.textContent = getLabel(String(variationId));
      renderFields(variationId);
      refreshProgress();
      setModalOpen(true);
      emitUxEvent('modal_open', { variation_id: variationId });
      track('drawer_opened', { variation_id: variationId });
    }

    function closeModal() {
      if (!state.isOpen) return;
      modal.classList.add('is-closing');
      setModalOpen(false);
      setTimeout(() => {
        modal.classList.remove('is-closing');
      }, 120);
    }

    function refreshFragments() {
      document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
      if (window.jQuery) {
        window.jQuery(document.body).trigger('wc_fragment_refresh');
      }
    }

    function renderOrderSummary(items, subtotalHtml) {
      if (!orderSummary || !orderSummaryList || !orderSummarySubtotal) return;
      if (!Array.isArray(items) || !items.length) {
        subtotalDebug.state = state.hasLoadedSummaryOnce ? 'empty' : 'initial';
        subtotalDebug.lastWriter = 'renderOrderSummary(empty)';
        subtotalDebug.insertionMethod = 'textContent';
        subtotalDebug.raw = '';
        subtotalDebug.sanitized = '';
        orderSummary.hidden = false;
        orderSummaryList.innerHTML = '';
        orderSummarySubtotal.textContent = '';
        subtotalDebug.finalHtml = orderSummarySubtotal.innerHTML;
        if (orderSummaryEmpty) orderSummaryEmpty.hidden = false;
        if (orderSummaryStatus) orderSummaryStatus.textContent = 'Escolha uma série e adicione o nome do aluno.';
        if (mobileCheckout) mobileCheckout.hidden = true;
        if (exitHint) exitHint.hidden = true;
        state.hasLoadedSummaryOnce = true;
        updateSubtotalDebug();
        return;
      }

      orderSummary.hidden = false;
      if (orderSummaryEmpty) orderSummaryEmpty.hidden = true;
      orderSummaryList.innerHTML = items.map((item) => {
        const names = Array.isArray(item.student_names) ? item.student_names.filter(Boolean).join(', ') : '';
        const namesMarkup = Array.isArray(item.student_names) ? item.student_names.filter(Boolean).map((name) => `<li>${escHtml(name)}</li>`).join('') : '';
        const safeLineSubtotalHtml = sanitizeWooPriceHtml(item.line_subtotal_html || '');
        return `
          <article class="nafb-order-summary__item" data-cart-key="${escHtml(item.cart_key || '')}" data-variation-label="${escHtml(item.variation_label || '')}" data-student-names="${escHtml((item.student_names || []).join('|'))}">
            <div class="nafb-order-summary__row">
              <strong>${escHtml(item.variation_label || 'Série')}</strong>
              <span class="nafb-order-summary__price">${safeLineSubtotalHtml || '—'}</span>
            </div>
            <div class="nafb-order-summary__meta">Quantidade: ${Number(item.quantity || 0)}</div>
            <ul class="nafb-order-summary__names">${namesMarkup || `<li>${escHtml(names || '—')}</li>`}</ul>
            <div class="nafb-order-summary__item-actions">
              <button type="button" class="nafb-order-summary__link" data-action="edit-item">Editar</button>
              <button type="button" class="nafb-order-summary__link is-danger" data-action="remove-item">Remover</button>
            </div>
          </article>
        `;
      }).join('');
      const safeSubtotalHtml = sanitizeWooPriceHtml(subtotalHtml || '');
      subtotalDebug.raw = subtotalHtml || '';
      subtotalDebug.sanitized = safeSubtotalHtml || '';
      subtotalDebug.lastWriter = 'renderOrderSummary(filled)';
      subtotalDebug.insertionMethod = 'innerHTML';
      const nextSubtotalText = `Subtotal do pedido: ${safeSubtotalHtml || '—'}`;
      if (state.lastSubtotalText && state.lastSubtotalText !== nextSubtotalText) {
        orderSummarySubtotal.classList.remove('is-updated');
        void orderSummarySubtotal.offsetWidth;
        orderSummarySubtotal.classList.add('is-updated');
        setTimeout(() => orderSummarySubtotal.classList.remove('is-updated'), 120);
      }
      state.lastSubtotalText = nextSubtotalText;
      orderSummarySubtotal.innerHTML = `Subtotal do pedido: ${safeSubtotalHtml || '—'}`;
      subtotalDebug.finalHtml = orderSummarySubtotal.innerHTML;
      state.hasLoadedSummaryOnce = true;
      updateSubtotalDebug();
      if (orderSummaryStatus) orderSummaryStatus.textContent = 'Seu pedido está pronto para finalizar';
      if (mobileCheckout && mobileCheckoutLink) {
        mobileCheckoutLink.textContent = `Finalizar pedido • ${String(subtotalHtml || '').replace(/<[^>]+>/g, '').trim()}`;
        mobileCheckout.hidden = window.matchMedia('(max-width: 768px)').matches === false;
      }
      scheduleIdleReminder();
    }

    async function refreshOrderSummary() {
      const data = await fetchCartSummary(config.product_id);
      if (!data || !data.success) return;
      const payload = data.data || {};
      renderOrderSummary(payload.items || [], payload.subtotal_html || '');
    }

    function collectNamesFromFields() {
      return Array.from(fieldsWrap.querySelectorAll('input')).map((input) => String(input.value || '').trim());
    }

    function refreshProgress() {
      const names = collectNamesFromFields();
      const filled = names.filter((name) => name).length;
      const total = names.length;
      progress.textContent = `${filled} de ${total} preenchidos`;

      const complete = total > 0 && filled === total;
      helper.textContent = complete ? 'Tudo pronto ✔' : 'Digite o nome para personalizar o livro';
      okBtn.classList.toggle('is-complete', complete);
    }

    function validateFields() {
      let valid = true;
      const inputs = Array.from(fieldsWrap.querySelectorAll('input'));

      inputs.forEach((input) => {
        const row = input.closest('.nafb-modal__field');
        const error = row.querySelector('[data-role="error"]');
        if (!String(input.value || '').trim()) {
          valid = false;
          row.classList.add('is-invalid', 'is-shaking');
          error.textContent = 'Digite o nome do aluno';
          setTimeout(() => row.classList.remove('is-shaking'), 280);
        } else {
          row.classList.remove('is-invalid');
          error.textContent = '';
        }
      });

      return valid;
    }

    async function addCurrentSelection() {
      const variationId = Number(state.currentVariationId || 0);
      if (!variationId || !(config.variations || {})[variationId]) return;
      if (!validateFields()) {
        const firstInvalid = fieldsWrap.querySelector('.is-invalid input');
        if (firstInvalid) firstInvalid.focus();
        return;
      }

      const names = collectNamesFromFields();
      state.namesByVariation[variationId] = names;

      const payload = {
        product_id: Number(config.product_id || 0),
        mode: config.mode || 'matrix',
        items: [{
          variation_id: variationId,
          quantity: quantity(),
          fields: { student_names: names.map((name) => ({ name })) },
        }],
      };

      okBtn.classList.add('is-loading');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const data = await postBatch(payload);
        if (!data.success) {
          const message = normalizeAjaxErrors(data.data || {})[0];
          toast(message, 'error');
          track('add_to_cart_failure', { reason: message });
          return;
        }

        closeModal();
        subtotalDebug.state = 'add';
        const series = getLabel(String(variationId));
        toast(`Livro adicionado ao pedido • ${series}`, 'success');
        emitUxEvent('modal_confirm', { variation_id: variationId, names_count: names.length });
        emitUxEvent('item_added', { variation_id: variationId, series, quantity: quantity() });
        track('add_to_cart_success', { items: 1 });
        refreshFragments();
        await refreshOrderSummary();
        if (!state.hasAutoScrolledToSummary && orderSummary && !orderSummary.hidden) {
          orderSummary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          state.hasAutoScrolledToSummary = true;
        }
      } finally {
        okBtn.classList.remove('is-loading');
        if (submitBtn) submitBtn.disabled = false;
      }
    }

    form.addEventListener('change', (event) => {
      if (event.target.matches('select, input.qty')) {
        const variationId = Number((variationIdInput && variationIdInput.value) || 0);
        if (variationId) ensureNames(variationId);
      }
    });

    form.addEventListener('submit', async (event) => {
      const variationId = Number((variationIdInput && variationIdInput.value) || 0);
      if (!variationId || !(config.variations || {})[variationId]) return;

      ensureNames(variationId);
      event.preventDefault();
      openModal(variationId);
      track('name_input_started', { variation_id: variationId });
    });

    fieldsWrap.addEventListener('input', (event) => {
      if (!event.target.matches('input[data-index]')) return;
      const row = event.target.closest('.nafb-modal__field');
      const error = row.querySelector('[data-role="error"]');
      row.classList.remove('is-invalid');
      error.textContent = '';

      if (String(event.target.value || '').trim()) {
        const next = fieldsWrap.querySelector(`input[data-index="${Number(event.target.dataset.index || 0) + 1}"]`);
        if (next) next.focus();
      }

      refreshProgress();
    });

    fieldsWrap.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      const input = event.target.closest('input[data-index]');
      if (!input) return;
      const next = fieldsWrap.querySelector(`input[data-index="${Number(input.dataset.index || 0) + 1}"]`);
      if (next && String(input.value || '').trim()) {
        event.preventDefault();
        next.focus();
        return;
      }

      event.preventDefault();
      addCurrentSelection();
    });

    okBtn.addEventListener('click', addCurrentSelection);
    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);
    if (addMoreBtn) {
      addMoreBtn.addEventListener('click', () => {
        const select = form.querySelector('.variations select, select[name^="attribute_"], select');
        const focusTarget = select || form.querySelector('.single_add_to_cart_button, input.qty');
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        form.classList.add('nafb-highlight-target');
        setTimeout(() => form.classList.remove('nafb-highlight-target'), 240);

        if (focusTarget && typeof focusTarget.focus === 'function') {
          focusTarget.focus({ preventScroll: true });
        }

        if (select) {
          select.classList.add('nafb-highlight');
          setTimeout(() => select.classList.remove('nafb-highlight'), 120);
          toast('Escolha a série e continue o pedido.', 'success');
        } else {
          toast('Escolha uma opção do produto para adicionar outro livro.', 'success');
        }
        scheduleIdleReminder();
      });
    }
    if (goCartLink) {
      goCartLink.addEventListener('click', () => {
        state.checkoutClicked = true;
        emitUxEvent('checkout_click', { destination: goCartLink.getAttribute('href') || '' });
      });
    }
    if (mobileCheckoutLink) {
      mobileCheckoutLink.addEventListener('click', () => {
        state.checkoutClicked = true;
        emitUxEvent('checkout_click', { destination: mobileCheckoutLink.getAttribute('href') || '' });
      });
    }
    if (exitCheckout) {
      exitCheckout.addEventListener('click', () => {
        state.checkoutClicked = true;
        if (exitHint) exitHint.hidden = true;
      });
    }
    if (exitContinue) {
      exitContinue.addEventListener('click', () => {
        if (exitHint) exitHint.hidden = true;
      });
    }
    if (orderSummaryList) {
      orderSummaryList.addEventListener('click', async (event) => {
        const action = event.target.dataset.action;
        if (!action) return;
        const item = event.target.closest('.nafb-order-summary__item');
        if (!item) return;

        if (action === 'edit-item') {
          subtotalDebug.state = 'edit';
          const variationLabel = item.dataset.variationLabel || '';
          const variationEntry = Object.entries(config.variations || {}).find(([, value]) => String(value.label || '') === variationLabel);
          if (!variationEntry) return;
          const [variationId] = variationEntry;
          const names = String(item.dataset.studentNames || '').split('|').filter(Boolean);
          state.namesByVariation[variationId] = names;
          if (qtyInput) qtyInput.value = String(Math.max(1, names.length));
          openModal(Number(variationId));
          return;
        }

        if (action === 'remove-item') {
          subtotalDebug.state = 'remove';
          const cartKey = item.dataset.cartKey || '';
          if (!cartKey) return;
          const originalText = event.target.textContent;
          event.target.disabled = true;
          event.target.textContent = 'Removendo...';
          const data = await removeCartItem(cartKey);
          if (!data || !data.success) {
            toast('Não foi possível remover este item.', 'error');
            event.target.disabled = false;
            event.target.textContent = originalText;
            return;
          }
          item.classList.add('is-removing');
          setTimeout(async () => {
            await refreshOrderSummary();
            refreshFragments();
            emitUxEvent('item_removed', { cart_key: cartKey });
            toast('Item removido do pedido.', 'success');
          }, 110);
        }
      });
    }
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && state.isOpen) closeModal();
    });
    document.addEventListener('mousemove', (event) => {
      if (window.matchMedia('(max-width: 992px)').matches) return;
      if (state.checkoutClicked || !hasSummaryItems()) return;
      if (event.clientY <= 8 && exitHint && exitHint.hidden) exitHint.hidden = false;
    });
    window.addEventListener('beforeunload', (event) => {
      if (!hasSummaryItems() || state.checkoutClicked) return;
      event.preventDefault();
      event.returnValue = '';
    });
    ['click', 'keydown', 'touchstart'].forEach((eventName) => {
      document.addEventListener(eventName, () => {
        if (hasSummaryItems()) scheduleIdleReminder();
      }, { passive: true });
    });
    refreshOrderSummary().catch(() => null);
  }

  function initCartEditing() {
    if (window.__nafbCartEditInit) return;
    window.__nafbCartEditInit = true;
    const editButtons = Array.from(document.querySelectorAll('.nafb-cart-edit'));
    if (!editButtons.length || !window.NAFBDrawer) return;

    const { overlay, drawer } = window.NAFBDrawer.createDrawer();
    const qtyInput = drawer.querySelector('[data-role="cart-qty"]');
    const studentsWrap = drawer.querySelector('[data-role="cart-students"]');
    const message = drawer.querySelector('[data-role="cart-message"]');

    let active = null;

    function renderDrawer() {
      if (!active) return;
      drawer.classList.add('is-open');
      overlay.classList.add('is-open');
      qtyInput.value = String(active.quantity || 0);
      studentsWrap.innerHTML = '';

      for (let i = 0; i < Number(active.quantity || 0); i += 1) {
        const row = active.fields.student_names[i] || { name: '' };
        const wrapper = document.createElement('div');
        wrapper.className = 'nafb-student-row';
        wrapper.innerHTML = `<label>${(nafbApp.texts && nafbApp.texts.studentLabel) || 'Aluno'} ${i + 1}</label><input type="text" data-index="${i}" value="${String(row.name || '').replace(/"/g, '&quot;')}" />`;
        studentsWrap.appendChild(wrapper);
      }
    }

    function close() {
      active = null;
      drawer.classList.remove('is-open');
      overlay.classList.remove('is-open');
      message.textContent = '';
    }

    editButtons.forEach((button) => {
      button.addEventListener('click', () => {
        try {
          active = JSON.parse(atob(button.dataset.nafb || ''));
          active.cartKey = button.dataset.cartKey;
          renderDrawer();
        } catch (_e) {
          toast('Não foi possível abrir a edição deste item.', 'error');
        }
      });
    });

    overlay.addEventListener('click', close);
    drawer.addEventListener('click', async (event) => {
      const action = event.target.dataset.action;
      if (!action || !active) return;

      if (action === 'close-cart-drawer') return close();
      if (action === 'increase-qty' || action === 'decrease-qty') {
        const delta = action === 'increase-qty' ? 1 : -1;
        active.quantity = Math.max(0, Number(active.quantity || 0) + delta);
        active.fields.student_names = Array.from({ length: active.quantity }, (_, i) => active.fields.student_names[i] || { name: '' });
        return renderDrawer();
      }

      if (action === 'save-cart-edit') {
        const missing = active.fields.student_names.some((row) => !String((row && row.name) || '').trim());
        if (missing || Number(active.quantity) <= 0) {
          message.textContent = 'Preencha todos os nomes e mantenha quantidade válida.';
          return;
        }

        const payload = {
          product_id: Number(active.product_id),
          mode: 'matrix',
          items: [{
            variation_id: Number(active.variation_id),
            quantity: Number(active.quantity),
            fields: { student_names: active.fields.student_names },
          }],
        };

        const data = await postBatch(payload);
        if (!data.success) {
          message.textContent = normalizeAjaxErrors(data.data || {})[0] || 'Falha ao salvar edição.';
          return;
        }

        close();
        window.location.reload();
      }
    });

    studentsWrap.addEventListener('input', (event) => {
      if (!active) return;
      const index = Number(event.target.dataset.index || -1);
      if (index < 0) return;
      active.fields.student_names[index] = { name: String(event.target.value || '').trim() };
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && active) close();
    });
  }

  initNativePersonalization();
  initCartEditing();
})(window, document);
