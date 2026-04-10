(function (window, document) {
  const STORAGE_PREFIX = 'nafb_builder_state_';

  function debounce(fn, wait) {
    let timer = null;
    return function debounced(...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  function normalizeAjaxErrors(responseData) {
    if (!responseData || !Array.isArray(responseData.errors)) {
      return ['Erro inesperado ao processar sua solicitação.'];
    }

    const mapped = responseData.errors
      .map((error) => (error && error.message ? String(error.message) : 'Erro desconhecido.'))
      .filter(Boolean);

    return mapped.length ? mapped : ['Erro inesperado ao processar sua solicitação.'];
  }

  function toast(message, type) {
    if (window.NAFBToast) {
      window.NAFBToast.show(message, type);
    }
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

  function initProductBuilder() {
    const root = document.getElementById('nafb-builder-root');
    if (!root || !window.NAFBStore) return;

    const config = JSON.parse(root.dataset.config || '{}');
    const list = root.querySelector('[data-role="variation-list"]');
    const summary = root.querySelector('[data-role="summary"]');
    const subtotal = root.querySelector('[data-role="subtotal"]');
    const submitButton = root.querySelector('[data-role="submit"]');
    const messages = root.querySelector('[data-role="messages"]');

    const drawer = root.querySelector('[data-role="drawer"]');
    const drawerOverlay = root.querySelector('[data-role="drawer-overlay"]');
    const drawerTitle = root.querySelector('[data-role="drawer-title"]');
    const drawerQuantity = root.querySelector('[data-role="drawer-qty"]');
    const drawerStudents = root.querySelector('[data-role="drawer-students"]');
    const drawerClose = root.querySelector('[data-role="drawer-close"]');
    const drawerMinus = root.querySelector('[data-role="drawer-minus"]');
    const drawerPlus = root.querySelector('[data-role="drawer-plus"]');

    let lastFocusedElement = null;
    const store = window.NAFBStore.createStore({
      productId: Number(config.product_id || 0),
      mode: config.mode || nafbApp.mode || 'matrix',
    });
    const texts = (nafbApp && nafbApp.texts) ? nafbApp.texts : {};

    const storageKey = `${STORAGE_PREFIX}${Number(config.product_id || 0)}`;
    const abandonmentKey = `${storageKey}_abandonment`;
    let submitLock = false;

    function getVariationById(variationId) {
      return (config.items || []).find((item) => Number(item.id) === Number(variationId));
    }

    function parseCurrency(value) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
    }

    function deriveStep(line) {
      if (!line || Number(line.quantity || 0) === 0) return 1;
      if (line.studentNames.some((name) => !String(name || '').trim())) return 2;
      return 3;
    }

    function openDrawer(variationId, triggerElement) {
      if (!getVariationById(variationId)) return;
      lastFocusedElement = triggerElement || document.activeElement;
      store.setActiveVariation(variationId);
    }

    function closeDrawer() {
      store.setActiveVariation(null);
      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }

    }

    function quantityFor(variationId) {
      const line = store.getState().selectedVariations[variationId];
      return Number((line && line.quantity) || 0);
    }

    function applyQuantity(variationId, nextQuantity) {
      const variation = getVariationById(variationId);
      if (!variation) return;
      store.setQuantity(variation, nextQuantity);
      track('quantity_changed', { variation_id: variationId, quantity: nextQuantity });
    }

    function collectValidationErrors(payload) {
      const errors = [];
      if (!payload.product_id) errors.push('Produto inválido.');
      if (payload.mode !== 'matrix') errors.push('Apenas modo matrix está disponível no MVP.');

      if (!Array.isArray(payload.items) || payload.items.length === 0) {
        errors.push('Selecione ao menos uma variação com quantidade.');
        return errors;
      }

      payload.items.forEach((line, index) => {
        const lineNumber = index + 1;
        const variation = getVariationById(line.variation_id);
        if (!variation || !variation.in_stock) errors.push(`Variação inválida na linha ${lineNumber}.`);
        if (Number(line.quantity) <= 0) errors.push(`Quantidade inválida na linha ${lineNumber}.`);

        const names = (line.fields && Array.isArray(line.fields.student_names)) ? line.fields.student_names : [];
        if (names.length !== Number(line.quantity)) {
          errors.push(`Quantidade de nomes deve ser igual à quantidade na linha ${lineNumber}.`);
        }

        names.forEach((row, idx) => {
          if (!String((row && row.name) || '').trim()) {
            errors.push(`Nome obrigatório na linha ${lineNumber} item ${idx + 1}.`);
          }
        });
      });

      return errors;
    }

    function persistState(state) {
      try {
        const payload = {
          selectedVariations: state.selectedVariations,
          activeVariationId: state.activeVariationId,
        };
        localStorage.setItem(storageKey, JSON.stringify(payload));
      } catch (_e) {}
    }

    function restoreState() {
      try {
        const raw = localStorage.getItem(storageKey);
        if (!raw) return;
        const saved = JSON.parse(raw);
        const selected = saved.selectedVariations || {};

        Object.keys(selected).forEach((variationId) => {
          const variation = getVariationById(Number(variationId));
          if (!variation) return;
          const line = selected[variationId];
          store.setQuantity(variation, Number(line.quantity || 0));
          (line.studentNames || []).forEach((name, index) => {
            store.setStudentName(Number(variationId), index, name);
          });
        });
      } catch (_e) {}
    }

    function clearPersistedState() {
      try {
        localStorage.removeItem(storageKey);
      } catch (_e) {}
    }

    function renderCards(state) {
      list.innerHTML = '';

      (config.items || []).forEach((variation) => {
        const quantity = Number((state.selectedVariations[variation.id] || {}).quantity || 0);
        const isSelected = quantity > 0;

        const card = document.createElement('article');
        card.className = `nafb-card ${isSelected ? 'is-selected' : ''}`;
        card.innerHTML = `
          <header class="nafb-card__header">
            <h4>${variation.name}</h4>
            <span class="nafb-card__price">${variation.price || ''}</span>
          </header>
          <div class="nafb-stepper" role="group" aria-label="Quantidade de ${variation.name}">
            <button type="button" class="nafb-stepper__btn" data-action="decrease" data-variation-id="${variation.id}" aria-label="Diminuir quantidade">−</button>
            <input class="nafb-stepper__input" type="number" min="0" max="${variation.max_qty || 999}" value="${quantity}" readonly />
            <button type="button" class="nafb-stepper__btn" data-action="increase" data-variation-id="${variation.id}" aria-label="Aumentar quantidade" ${variation.in_stock ? '' : 'disabled'}>+</button>
          </div>
          <button type="button" class="nafb-card__customize vs-btn" data-action="customize" data-variation-id="${variation.id}">${isSelected ? 'Editar personalização' : 'Personalizar'}</button>
        `;
        list.appendChild(card);
      });
    }

    function renderDrawer(state) {
      const variationId = state.activeVariationId;
      const isOpen = variationId != null;

      drawerOverlay.classList.toggle('is-open', isOpen);
      drawer.classList.toggle('is-open', isOpen);
      drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

      if (!isOpen) {
        drawerStudents.innerHTML = '';
        return;
      }

      const variation = getVariationById(variationId);
      if (!variation) return;
      const line = state.selectedVariations[variationId] || { quantity: 0, studentNames: [] };
      const step = deriveStep(line);

      const completed = (line.studentNames || []).filter((name) => String(name || '').trim()).length;
      drawerTitle.textContent = `${variation.name} · Passo ${step} · ${completed}/${line.quantity || 0}`;
      drawerQuantity.value = String(line.quantity || 0);
      drawer.dataset.step = String(step);

      drawerStudents.innerHTML = '';
      for (let i = 0; i < Number(line.quantity || 0); i += 1) {
        const value = line.studentNames[i] || '';
        const invalid = !String(value).trim();
        const row = document.createElement('div');
        row.className = `nafb-student-row ${invalid ? 'is-invalid' : ''}`;
        row.innerHTML = `
          <label for="nafb-student-${variationId}-${i}">${texts.studentLabel || 'Aluno'} ${i + 1}</label>
          <input id="nafb-student-${variationId}-${i}" type="text" value="${value.replace(/"/g, '&quot;')}" data-action="student-name" data-variation-id="${variationId}" data-student-index="${i}" aria-invalid="${invalid ? 'true' : 'false'}" />
          <p class="nafb-inline-error">${invalid ? (texts.validationRequired || 'Nome obrigatório.') : ''}</p>
        `;
        drawerStudents.appendChild(row);
      }

      const nextRequired = drawerStudents.querySelector('.nafb-student-row.is-invalid input');
      if (nextRequired) {
        nextRequired.classList.add('nafb-next-required');
      }

      if (line.quantity > 0 && completed === Number(line.quantity || 0)) {
        messages.textContent = texts.allReady || 'Tudo pronto!';
        messages.classList.remove('is-error');
        track('configuration_completed', { variation_id: variationId, quantity: line.quantity });
      }
    }

    function renderSummary(state) {
      const lines = Object.values(state.selectedVariations).filter((line) => Number(line.quantity) > 0);
      summary.innerHTML = '';

      if (!lines.length) {
        summary.innerHTML = '<p class="nafb-empty">Nenhuma variação selecionada.</p>';
        subtotal.textContent = parseCurrency(0);
        return;
      }

      const missingTotal = lines.reduce((acc, line) => acc + line.studentNames.filter((name) => !String(name || '').trim()).length, 0);
      document.dispatchEvent(new CustomEvent('nafb_before_render_summary', { detail: { lines, missingTotal } }));
      lines.forEach((line) => {
        const missing = line.studentNames.filter((name) => !String(name || '').trim()).length;
        const item = document.createElement('section');
        item.className = `nafb-summary-item ${missing ? 'is-invalid' : 'is-valid'}`;
        item.innerHTML = `
          <header><strong>${line.variationName}</strong><span>Qtd: ${line.quantity}</span></header>
          <p class="nafb-badge ${missing ? 'is-invalid' : 'is-valid'}">${missing ? '⚠ Incompleto' : '✓ Completo'}</p>
          <ul>${line.studentNames.map((name, idx) => `<li class="${name ? '' : 'is-invalid'}">${name || `Aluno ${idx + 1} sem nome`}</li>`).join('')}</ul>
        `;
        summary.appendChild(item);
      });

      const footer = document.createElement('p');
      footer.className = `nafb-summary-missing ${missingTotal ? 'is-invalid' : 'is-valid'}`;
      footer.textContent = missingTotal ? `${missingTotal} nome(s) pendente(s)` : 'Todos os nomes preenchidos';
      summary.appendChild(footer);
      subtotal.textContent = parseCurrency(state.subtotal);
    }

    function renderSubmitState(state) {
      const payload = store.buildPayload();
      const errors = collectValidationErrors(payload);
      store.setErrors(errors);
      if (errors.length) { track('validation_error', { count: errors.length, first: errors[0] }); }

      submitButton.disabled = errors.length > 0 || state.isSubmitting;
      submitButton.textContent = state.isSubmitting ? 'Adicionando...' : (texts.addToCart || 'Adicionar ao carrinho');

      messages.textContent = errors[0] || '';
      messages.classList.toggle('is-error', Boolean(errors.length));
    }

    let cardsHash = '';
    let drawerHash = '';
    const debouncedRenderSummary = debounce(renderSummary, 60);
    const debouncedRenderSubmit = debounce(renderSubmitState, 60);

    function renderAll() {
      const state = store.getState();
      const selectors = store.selectors();

      const nextCardsHash = JSON.stringify(Object.values(state.selectedVariations).map((line) => [line.variationId, line.quantity]));
      if (nextCardsHash !== cardsHash) {
        cardsHash = nextCardsHash;
        renderCards(state);
      }

      const nextDrawerHash = `${state.activeVariationId || ''}:${nextCardsHash}`;
      if (nextDrawerHash !== drawerHash) {
        drawerHash = nextDrawerHash;
        renderDrawer(state);
      }

      debouncedRenderSummary(state);
      debouncedRenderSubmit(state);
      persistState(state);

      const abandonment = {
        has_quantity: selectors.totalItems > 0,
        missing_names: selectors.totalMissingNames,
        submitted: false,
        updated_at: Date.now(),
      };
      localStorage.setItem(abandonmentKey, JSON.stringify(abandonment));

      root.dataset.totalItems = String(selectors.totalItems);
      root.dataset.totalMissingNames = String(selectors.totalMissingNames);
      root.dataset.isValid = String(selectors.isValid);
    }

    list.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]');
      if (!button) return;

      const action = button.dataset.action;
      const variationId = Number(button.dataset.variationId || 0);
      const currentQty = quantityFor(variationId);

      if (action === 'increase') applyQuantity(variationId, currentQty + 1);
      if (action === 'decrease') applyQuantity(variationId, currentQty - 1);
      if (action === 'customize') {
        track('variation_selected', { variation_id: variationId });
        track('drawer_opened', { variation_id: variationId });
        openDrawer(variationId, button);
      }
    });

    drawerStudents.addEventListener('input', (event) => {
      if (event.target.dataset.action !== 'student-name') return;
      const variationId = Number(event.target.dataset.variationId);
      const studentIndex = Number(event.target.dataset.studentIndex);
      const value = event.target.value;
      store.setStudentName(variationId, studentIndex, value);
      track(value ? 'name_input_completed' : 'name_input_started', { variation_id: variationId, index: studentIndex });

      if (value && value.trim()) {
        const next = drawerStudents.querySelector(`input[data-student-index="${studentIndex + 1}"]`);
        if (next) next.focus();
      }
    });

    drawerOverlay.addEventListener('click', closeDrawer);
    drawerClose.addEventListener('click', closeDrawer);
    drawerMinus.addEventListener('click', () => {
      const activeId = store.getState().activeVariationId;
      if (activeId != null) applyQuantity(activeId, quantityFor(activeId) - 1);
    });
    drawerPlus.addEventListener('click', () => {
      const activeId = store.getState().activeVariationId;
      if (activeId != null) applyQuantity(activeId, quantityFor(activeId) + 1);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && store.getState().activeVariationId != null) {
        closeDrawer();
      }
    });

    window.addEventListener('beforeunload', () => {
      const snapshot = JSON.parse(localStorage.getItem(abandonmentKey) || '{}');
      if (snapshot.has_quantity && Number(snapshot.missing_names || 0) > 0) {
        track('abandonment_detected', snapshot);
        document.dispatchEvent(new CustomEvent('nafb_abandonment_detected', { detail: snapshot }));
      }
    });

    submitButton.addEventListener('click', async () => {
      const payload = store.buildPayload();
      const errors = collectValidationErrors(payload);
      if (errors.length || store.getState().isSubmitting || submitLock) return;

      submitLock = true;
      store.setSubmitting(true);
      try {
        const data = await postBatch(payload);
        if (!data.success) {
          const backendErrors = normalizeAjaxErrors(data.data || {});
          store.setErrors(backendErrors);
          messages.textContent = backendErrors[0];
          messages.classList.add('is-error');
          toast(backendErrors[0], 'error');
          track('add_to_cart_failure', { reason: backendErrors[0] });
          return;
        }

        store.setErrors([]);
        clearPersistedState();
        messages.textContent = 'Itens adicionados ao carrinho.';
        messages.classList.remove('is-error');
        toast('Itens adicionados ao carrinho.', 'success');
        track('add_to_cart_success', { items: payload.items.length });
        localStorage.setItem(abandonmentKey, JSON.stringify({ has_quantity: false, missing_names: 0, submitted: true, updated_at: Date.now() }));
        localStorage.removeItem(abandonmentKey);
        document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh'));
      } finally {
        store.setSubmitting(false);
        submitLock = false;
      }
    });

    store.subscribe(renderAll);
    restoreState();
    renderAll();
  }

  function initCartEditing() {
    const editButtons = Array.from(document.querySelectorAll('.nafb-cart-edit'));
    if (!editButtons.length || !window.NAFBDrawer) return;

    const { overlay, drawer } = window.NAFBDrawer.createDrawer();
    const qtyInput = drawer.querySelector('[data-role="cart-qty"]');
    const studentsWrap = drawer.querySelector('[data-role="cart-students"]');
    const message = drawer.querySelector('[data-role="cart-message"]');
    const steps = drawer.querySelector('[data-role="cart-steps"]');

    let active = null;

    function getStep() {
      if (!active || Number(active.quantity) === 0) return 1;
      const missing = (active.fields.student_names || []).some((row) => !String((row && row.name) || '').trim());
      return missing ? 2 : 3;
    }

    function renderDrawer() {
      if (!active) return;

      const step = getStep();
      drawer.classList.add('is-open');
      overlay.classList.add('is-open');
      steps.querySelectorAll('[data-step]').forEach((node) => {
        node.classList.toggle('is-active', Number(node.dataset.step) === step);
      });

      qtyInput.value = String(active.quantity || 0);
      studentsWrap.innerHTML = '';

      for (let i = 0; i < Number(active.quantity || 0); i += 1) {
        const row = active.fields.student_names[i] || { name: '' };
        const invalid = !String(row.name || '').trim();
        const wrapper = document.createElement('div');
        wrapper.className = `nafb-student-row ${invalid ? 'is-invalid' : ''}`;
        wrapper.innerHTML = `
          <label>${texts.studentLabel || 'Aluno'} ${i + 1}</label>
          <input type="text" data-index="${i}" value="${String(row.name || '').replace(/"/g, '&quot;')}" />
          <p class="nafb-inline-error">${invalid ? (texts.validationRequired || 'Nome obrigatório.') : ''}</p>
        `;
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
          const row = button.closest('tr');
          const removeLink = row ? row.querySelector('a.remove') : null;
          active.removeUrl = removeLink ? removeLink.href : '';
          renderDrawer();
        } catch (_e) {
          toast('Não foi possível abrir a edição deste item.', 'error');
          track('add_to_cart_failure', { reason: 'cart_edit_open_failed' });
        }
      });
    });

    overlay.addEventListener('click', close);
    drawer.addEventListener('click', async (event) => {
      const action = event.target.dataset.action;
      if (!action || !active) return;

      if (action === 'close-cart-drawer') {
        close();
        return;
      }

      if (action === 'increase-qty' || action === 'decrease-qty') {
        const delta = action === 'increase-qty' ? 1 : -1;
        active.quantity = Math.max(0, Number(active.quantity || 0) + delta);
        active.fields.student_names = Array.from({ length: active.quantity }, (_, index) => active.fields.student_names[index] || { name: '' });
        renderDrawer();
        return;
      }

      if (action === 'save-cart-edit') {
        const missing = active.fields.student_names.some((row) => !String((row && row.name) || '').trim());
        if (missing || Number(active.quantity) <= 0) {
          message.textContent = 'Preencha todos os nomes e mantenha quantidade válida.';
          message.classList.add('is-error');
          toast('Corrija os campos obrigatórios antes de salvar.', 'error');
          track('validation_error', { context: 'cart_edit' });
          return;
        }

        message.textContent = 'Salvando...';
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
          const text = normalizeAjaxErrors(data.data || {})[0] || 'Falha ao salvar edição.';
          message.textContent = text;
          message.classList.add('is-error');
          toast(text, 'error');
          track('add_to_cart_failure', { reason: text, context: 'cart_edit' });
          return;
        }

        if (active.removeUrl) {
          await fetch(active.removeUrl, { credentials: 'same-origin' });
        }

        toast('Item atualizado no carrinho.', 'success');
        track('add_to_cart_success', { context: 'cart_edit' });
        window.location.reload();
      }
    });

    studentsWrap.addEventListener('input', (event) => {
      if (!active) return;
      const index = Number(event.target.dataset.index || -1);
      if (index < 0) return;
      active.fields.student_names[index] = { name: String(event.target.value || '').trim() };
      renderDrawer();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && active) {
        close();
      }
    });
  }

  initProductBuilder();
  initCartEditing();
})(window, document);
