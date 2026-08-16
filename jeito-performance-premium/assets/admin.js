(() => {
	'use strict';
	const modal = document.querySelector('[data-jpp-modal]');
	if (!modal) return;
	let trigger = null;
	const close = () => {
		modal.hidden = true;
		document.body.style.overflow = '';
		if (trigger) trigger.focus();
	};
	document.querySelectorAll('[data-jpp-open]').forEach((button) => {
		button.addEventListener('click', () => {
			trigger = button;
			modal.hidden = false;
			document.body.style.overflow = 'hidden';
			modal.querySelector('.jpp-close').focus();
		});
	});
	modal.querySelectorAll('[data-jpp-close]').forEach((button) => button.addEventListener('click', close));
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !modal.hidden) close();
		if (event.key !== 'Tab' || modal.hidden) return;
		const controls = [...modal.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])')];
		if (!controls.length) return;
		const first = controls[0];
		const last = controls[controls.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	});
	const verify = document.querySelector('[data-jpp-verify]');
	const result = document.querySelector('[data-jpp-result]');
	if (verify && result && window.jppAdmin) {
		verify.addEventListener('click', async () => {
			verify.disabled = true;
			result.textContent = 'Verificando documento servido…';
			try {
				const body = new URLSearchParams({action: 'jpp_verify_home', nonce: window.jppAdmin.nonce});
				const response = await fetch(window.jppAdmin.ajaxUrl, {method: 'POST', credentials: 'same-origin', body});
				const payload = await response.json();
				if (!payload.success) throw new Error(payload.data?.message || 'Falha na verificação.');
				const failed = Object.entries(payload.data.checks).filter(([, passed]) => !passed).map(([name]) => name);
				result.textContent = failed.length ? `Falhou: ${failed.join(', ')}.` : `Aprovado — ${payload.data.bytes.toLocaleString('pt-BR')} bytes de HTML.`;
				result.classList.toggle('is-failed', failed.length > 0);
			} catch (error) {
				result.textContent = error.message;
				result.classList.add('is-failed');
			} finally {
				verify.disabled = false;
			}
		});
	}
})();
