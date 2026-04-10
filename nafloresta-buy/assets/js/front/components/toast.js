(function (window, document) {
  function ensureContainer() {
    let container = document.querySelector('.nafb-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'nafb-toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  function show(message, type) {
    const container = ensureContainer();
    const toast = document.createElement('div');
    toast.className = `nafb-toast ${type === 'error' ? 'is-error' : 'is-success'}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('is-hide');
      setTimeout(() => toast.remove(), 220);
    }, 2600);
  }

  window.NAFBToast = { show };
})(window, document);
