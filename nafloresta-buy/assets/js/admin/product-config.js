(function ($) {
  const panel = document.getElementById('nafloresta_buy_panel');
  if (!panel) return;

  const enabled = panel.querySelector('#nafb_enabled');
  const variationSelect = panel.querySelector('#nafb_variation_ids');
  const errorBox = panel.querySelector('[data-role="nafb-admin-error"]');
  const previewCanvas = panel.querySelector('[data-role="nafb-preview-canvas"]');
  const previewWrap = panel.querySelector('[data-role="nafb-preview"]');
  const presetAction = panel.querySelector('#nafb_preset_action');
  const form = panel.closest('form');

  if (!form) return;

  const variations = JSON.parse((previewWrap && previewWrap.dataset.variations) || '[]');

  function selectedVariationIds() {
    return Array.from(variationSelect.options || []).filter((option) => option.selected).map((option) => Number(option.value));
  }

  function validateConfig() {
    const isEnabled = enabled.value === 'yes';
    const hasVariations = selectedVariationIds().length > 0;

    if (isEnabled && !hasVariations) {
      errorBox.hidden = false;
      errorBox.textContent = 'Selecione ao menos uma variação para salvar o builder ativo.';
      return false;
    }

    errorBox.hidden = true;
    errorBox.textContent = '';
    return true;
  }

  function renderPreview() {
    const selectedIds = selectedVariationIds();
    const selected = variations.filter((item) => selectedIds.includes(Number(item.id)));

    if (!selected.length) {
      previewCanvas.innerHTML = '<p class="description">Nenhuma variação selecionada para preview.</p>';
      return;
    }

    previewCanvas.innerHTML = selected.map((item) => `
      <article class="nafb-preview-card">
        <strong>${item.label}</strong>
        <span>${item.price || ''}</span>
        <small>Stepper + campos dinâmicos serão exibidos no front.</small>
      </article>
    `).join('');
  }

  form.addEventListener('submit', (event) => {
    if (!validateConfig()) {
      event.preventDefault();
      event.stopPropagation();
    }
  });

  panel.addEventListener('click', (event) => {
    const button = event.target.closest('[data-nafb-action]');
    if (!button) return;

    presetAction.value = button.dataset.nafbAction;
    form.requestSubmit();
  });

  enabled.addEventListener('change', validateConfig);
  variationSelect.addEventListener('change', () => {
    validateConfig();
    renderPreview();
  });

  if (window.jQuery && $.fn.selectWoo) {
    $('#nafb_variation_ids').on('change', () => {
      validateConfig();
      renderPreview();
    });
  }

  renderPreview();
})(jQuery);
