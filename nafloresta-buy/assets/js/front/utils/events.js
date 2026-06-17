(function (window) {
  const recent = new Map();

  function shouldEmit(action, payload) {
    const key = `${action}:${JSON.stringify(payload || {})}`;
    const now = Date.now();
    const last = recent.get(key) || 0;
    if (now - last < 350) {
      return false;
    }

    recent.set(key, now);
    return true;
  }

  function pushDataLayer(action, payload) {
    if (!Array.isArray(window.dataLayer)) return;
    window.dataLayer.push({
      nafb_event: true,
      event: 'nafb_event',
      action,
      payload: payload || {},
    });
  }

  function postInternal(action, payload) {
    if (!window.nafbApp || !window.nafbApp.ajaxUrl || !window.nafbApp.trackNonce) return;

    fetch(window.nafbApp.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams({
        action: 'nafb_track_event',
        nonce: window.nafbApp.trackNonce,
        event_action: action,
        payload: JSON.stringify(payload || {}),
      }),
    }).catch(() => {});
  }

  function track(action, payload) {
    const safePayload = payload || {};
    if (!shouldEmit(action, safePayload)) {
      return;
    }

    pushDataLayer(action, safePayload);
    postInternal(action, safePayload);
    document.dispatchEvent(new CustomEvent('nafb_event', { detail: { action, payload: safePayload } }));
  }

  window.NAFBEvents = { track };
})(window);
