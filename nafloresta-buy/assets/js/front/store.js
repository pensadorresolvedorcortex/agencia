(function (window) {
  function createStore(initialState) {
    let state = {
      productId: initialState.productId || 0,
      mode: initialState.mode || 'matrix',
      selectedVariations: {},
      errors: [],
      subtotal: 0,
      isSubmitting: false,
      activeVariationId: null,
      derived: {
        total_items: 0,
        total_missing_names: 0,
        is_valid: false,
      },
    };

    const listeners = [];

    function computeDerived(nextState) {
      const lines = Object.values(nextState.selectedVariations || {});
      const totalItems = lines.reduce((acc, line) => acc + Number(line.quantity || 0), 0);
      const totalMissing = lines.reduce(
        (acc, line) => acc + (line.studentNames || []).filter((name) => !String(name || '').trim()).length,
        0
      );

      return {
        total_items: totalItems,
        total_missing_names: totalMissing,
        is_valid: totalItems > 0 && totalMissing === 0,
      };
    }

    function withDerived(nextState) {
      return { ...nextState, derived: computeDerived(nextState) };
    }

    function notify() {
      listeners.forEach((listener) => listener(state));
    }

    function getState() {
      return state;
    }

    function selectors() {
      return {
        totalItems: state.derived.total_items,
        totalMissingNames: state.derived.total_missing_names,
        isValid: state.derived.is_valid,
      };
    }

    function subscribe(listener) {
      listeners.push(listener);
      return function unsubscribe() {
        const index = listeners.indexOf(listener);
        if (index >= 0) listeners.splice(index, 1);
      };
    }

    function recalcSubtotal(nextState) {
      let subtotal = 0;
      Object.keys(nextState.selectedVariations).forEach((key) => {
        const line = nextState.selectedVariations[key];
        subtotal += Number(line.quantity || 0) * Number(line.price || 0);
      });
      return subtotal;
    }

    function setQuantity(variation, quantity) {
      const current = state.selectedVariations[variation.id] || {
        variationId: variation.id,
        variationName: variation.name,
        quantity: 0,
        maxQty: variation.max_qty || 999,
        inStock: Boolean(variation.in_stock),
        studentNames: [],
        price: Number(variation.raw_price || 0),
      };

      const normalizedQty = Math.max(0, Math.min(Number(quantity || 0), Number(current.maxQty || 999)));
      const studentNames = Array.from({ length: normalizedQty }, (_, index) => current.studentNames[index] || '');

      const nextState = {
        ...state,
        selectedVariations: {
          ...state.selectedVariations,
          [variation.id]: { ...current, quantity: normalizedQty, studentNames },
        },
      };

      state = withDerived({ ...nextState, subtotal: recalcSubtotal(nextState) });
      notify();
    }

    function setStudentName(variationId, index, value) {
      const current = state.selectedVariations[variationId];
      if (!current) return;

      const studentNames = current.studentNames.slice();
      studentNames[index] = String(value || '').trim();

      state = withDerived({
        ...state,
        selectedVariations: {
          ...state.selectedVariations,
          [variationId]: { ...current, studentNames },
        },
      });
      notify();
    }

    function setErrors(errors) {
      const normalized = Array.isArray(errors) ? errors : [];
      if (JSON.stringify(normalized) === JSON.stringify(state.errors)) return;
      state = withDerived({ ...state, errors: normalized });
      notify();
    }

    function setSubmitting(isSubmitting) {
      state = withDerived({ ...state, isSubmitting: Boolean(isSubmitting) });
      notify();
    }

    function setActiveVariation(variationId) {
      state = withDerived({ ...state, activeVariationId: variationId == null ? null : Number(variationId) });
      notify();
    }

    function buildPayload() {
      const items = Object.values(state.selectedVariations)
        .filter((line) => Number(line.quantity) > 0)
        .map((line) => ({
          variation_id: Number(line.variationId),
          quantity: Number(line.quantity),
          fields: { student_names: line.studentNames.map((name) => ({ name: String(name || '').trim() })) },
        }));

      return { product_id: Number(state.productId), mode: state.mode || 'matrix', items };
    }

    state = withDerived(state);

    return {
      getState,
      selectors,
      subscribe,
      setQuantity,
      setStudentName,
      setErrors,
      setSubmitting,
      setActiveVariation,
      buildPayload,
    };
  }

  window.NAFBStore = { createStore };
})(window);
