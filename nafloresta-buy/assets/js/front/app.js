(function (window, document) {
  function toast(message, type) {
    if (window.NAFBToast) window.NAFBToast.show(message, type);
  }

  function track(action, payload) {
    if (window.NAFBEvents && typeof window.NAFBEvents.track === 'function') {
      window.NAFBEvents.track(action, payload || {});
    }
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

  function initNativePersonalization() {
    const layer = document.getElementById('nafb-native-layer');
    const form = document.querySelector('form.variations_form');
    if (!layer || !form) return;

    const config = JSON.parse(layer.dataset.config || '{}');
    const overlay = layer.querySelector('[data-role="nafb-modal-overlay"]');
    const modal = layer.querySelector('[data-role="nafb-modal"]');
    const closeBtn = layer.querySelector('[data-role="nafb-modal-close"]');
    const okBtn = layer.querySelector('[data-role="nafb-modal-ok"]');
    const input = layer.querySelector('[data-role="nafb-modal-input"]');
    const label = layer.querySelector('[data-role="nafb-modal-label"]');
    const qtyInput = form.querySelector('input.qty');
    const variationIdInput = form.querySelector('input[name="variation_id"]');

    const state = {
      currentVariationId: 0,
      currentIndex: 0,
      namesByVariation: {},
      isOpen: false,
      shouldOpenOnNextChange: true,
    };

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
      if (open) setTimeout(() => input.focus(), 20);
    }

    function openModal(variationId, index) {
      state.currentVariationId = variationId;
      state.currentIndex = index;
      const list = state.namesByVariation[variationId] || [];
      const value = list[index] || '';
      const variationLabel = getLabel(String(variationId));
      label.textContent = `Nome do aluno ${index + 1} para o produto: ${variationLabel}`;
      input.value = value;
      setModalOpen(true);
      track('drawer_opened', { variation_id: variationId, index: index + 1 });
    }

    function closeModal() {
      setModalOpen(false);
    }

    function ensureNames(variationId) {
      const count = quantity();
      const current = state.namesByVariation[variationId] || [];
      state.namesByVariation[variationId] = Array.from({ length: count }, (_, i) => current[i] || '');
      return state.namesByVariation[variationId];
    }

    function firstMissingIndex(list) {
      return list.findIndex((name) => !String(name || '').trim());
    }

    function handleVariationChange() {
      const variationId = Number((variationIdInput && variationIdInput.value) || 0);
      if (!variationId || !(config.variations || {})[variationId]) return;
      ensureNames(variationId);

      if (!state.shouldOpenOnNextChange) {
        state.shouldOpenOnNextChange = true;
        return;
      }

      const idx = firstMissingIndex(state.namesByVariation[variationId]);
      openModal(variationId, idx >= 0 ? idx : 0);
    }

    function saveCurrentAndAdvance() {
      if (!state.currentVariationId) return;
      const names = ensureNames(state.currentVariationId);
      names[state.currentIndex] = String(input.value || '').trim();
      state.namesByVariation[state.currentVariationId] = names;

      const next = firstMissingIndex(names);
      if (next >= 0 && next !== state.currentIndex) {
        openModal(state.currentVariationId, next);
        return;
      }

      closeModal();
    }

    form.addEventListener('change', (event) => {
      if (event.target.matches('select, input.qty')) {
        handleVariationChange();
      }
    });

    form.addEventListener('submit', async (event) => {
      const variationId = Number((variationIdInput && variationIdInput.value) || 0);
      if (!variationId || !(config.variations || {})[variationId]) return;

      const names = ensureNames(variationId);
      const missing = firstMissingIndex(names);
      if (missing >= 0) {
        event.preventDefault();
        openModal(variationId, missing);
        track('validation_error', { variation_id: variationId, reason: 'missing_names' });
        return;
      }

      event.preventDefault();
      const payload = {
        product_id: Number(config.product_id || 0),
        mode: config.mode || 'matrix',
        items: [{
          variation_id: variationId,
          quantity: quantity(),
          fields: { student_names: names.map((name) => ({ name })) },
        }],
      };

      const button = form.querySelector('.single_add_to_cart_button');
      if (button) button.disabled = true;

      try {
        const data = await postBatch(payload);
        if (!data.success) {
          const message = normalizeAjaxErrors(data.data || {})[0];
          toast(message, 'error');
          track('add_to_cart_failure', { reason: message });
          return;
        }

        toast('Itens adicionados ao carrinho.', 'success');
        track('add_to_cart_success', { items: 1 });
        state.shouldOpenOnNextChange = false;
        document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
      } finally {
        if (button) button.disabled = false;
      }
    });

    okBtn.addEventListener('click', saveCurrentAndAdvance);
    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && state.isOpen) closeModal();
    });
  }

  function initCartEditing() {
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
