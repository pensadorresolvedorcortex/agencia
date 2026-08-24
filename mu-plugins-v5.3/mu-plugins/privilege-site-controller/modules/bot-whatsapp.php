<?php
/**
 * Privilege Site Controller — Bot WhatsApp
 *
 * Separado do antigo performance-extended.php.
 * Não contém mais regras de WP Rocket, LCP, LazyLoad ou output buffer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pe_render_bot_whatsapp_modal() {
	ob_start();
	?>
	<div id="privilege-wa-modal" class="privilege-wa-modal">
		<div class="privilege-wa-header">
			<div class="privilege-wa-avatar-wrap">
				<div class="privilege-wa-avatar">🤖</div>
				<span class="privilege-wa-pulse-dot"></span>
			</div>
			<div class="privilege-wa-title-area">
				<strong>Agência Privilége</strong>
				<span class="privilege-wa-status-text">
					<span class="green-dot"></span> Online agora
				</span>
			</div>
			<button type="button" class="privilege-wa-close" id="privilege-wa-close" aria-label="Fechar">&times;</button>
		</div>

		<div class="privilege-wa-body">
			<p class="privilege-wa-greeting">
				Olá! 👋 Sou o assistente virtual da <strong>Privilége</strong>. Como podemos impulsionar seu negócio?
			</p>
			
			<div class="privilege-wa-options">
				<a href="https://wa.me/5532988167666?text=Ol%C3%A1!%20Gostaria%20de%20um%20or%C3%A7amento%20para%20Cria%C3%A7%C3%A3o%20de%20Site%20Institucional." target="_blank" rel="noopener" class="privilege-wa-btn">
					<span class="privilege-wa-icon">🏢</span>
					<div class="privilege-wa-btn-text">
						<strong>Site Institucional</strong>
						<span>Criação de sites profissionais</span>
					</div>
					<span class="privilege-wa-arrow">&rarr;</span>
				</a>

				<a href="https://wa.me/5532988167666?text=Ol%C3%A1!%20Gostaria%20de%20um%20or%C3%A7amento%20para%20Cria%C3%A7%C3%A3o%20de%20E-commerce%20%2F%20Loja%20Virtual." target="_blank" rel="noopener" class="privilege-wa-btn">
					<span class="privilege-wa-icon">🛒</span>
					<div class="privilege-wa-btn-text">
						<strong>E-commerce / Loja Virtual</strong>
						<span>Venda seus produtos online</span>
					</div>
					<span class="privilege-wa-arrow">&rarr;</span>
				</a>

				<a href="https://wa.me/5532988167666?text=Ol%C3%A1!%20Gostaria%20de%20um%20or%C3%A7amento%20para%20Landing%20Page%20de%20Alta%20Convers%C3%A3o." target="_blank" rel="noopener" class="privilege-wa-btn">
					<span class="privilege-wa-icon">🚀</span>
					<div class="privilege-wa-btn-text">
						<strong>Landing Page</strong>
						<span>Página focada em vendas</span>
					</div>
					<span class="privilege-wa-arrow">&rarr;</span>
				</a>

				<a href="https://wa.me/5532988167666?text=Ol%C3%A1!%20Gostaria%20de%20mais%20informa%C3%A7%C3%B5es%20sobre%20Otimiza%C3%A7%C3%A3o%2C%20SEO%20e%20Tr%C3%A1fego%20Pago." target="_blank" rel="noopener" class="privilege-wa-btn">
					<span class="privilege-wa-icon">📈</span>
					<div class="privilege-wa-btn-text">
						<strong>Otimização / SEO / Tráfego</strong>
						<span>Apareça no topo do Google</span>
					</div>
					<span class="privilege-wa-arrow">&rarr;</span>
				</a>

				<a href="https://wa.me/5532988167666?text=Ol%C3%A1!%20Gostaria%20de%20saber%20mais%20sobre%20outras%20solu%C3%A7%C3%B5es%20e%20servi%C3%A7os%20da%20Ag%C3%AAncia." target="_blank" rel="noopener" class="privilege-wa-btn privilege-wa-btn-highlight">
					<span class="privilege-wa-icon">💡</span>
					<div class="privilege-wa-btn-text">
						<strong>Outras soluções...</strong>
						<span>Fale com nossos especialistas</span>
					</div>
					<span class="privilege-wa-arrow">&rarr;</span>
				</a>
			</div>
		</div>
	</div>

	<div class="privilege-bot-floating-wrapper">
		<div class="privilege-bot-tooltip" id="privilege-bot-tooltip">
			<span>Precisa de ajuda? 👋</span>
		</div>

		<div class="privilege-bot-floating-container" id="privilege-bot-trigger" title="Fale com nosso assistente">
			<div class="privilege-bot-radar-ring"></div>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 450" class="privilege-bot-svg">
			  <defs>
			    <linearGradient id="botHelmet" x1="0%" y1="0%" x2="100%" y2="100%">
			      <stop offset="0%" stop-color="#FFE4DE"/>
			      <stop offset="35%" stop-color="#FFFFFF"/>
			      <stop offset="85%" stop-color="#E1EEFB"/>
			      <stop offset="100%" stop-color="#D0E3F7"/>
			    </linearGradient>
			    <linearGradient id="botScreen" x1="0%" y1="0%" x2="0%" y2="100%">
			      <stop offset="0%" stop-color="#0A1836"/>
			      <stop offset="50%" stop-color="#0D2451"/>
			      <stop offset="100%" stop-color="#050C1E"/>
			    </linearGradient>
			    <linearGradient id="botNavy" x1="0%" y1="0%" x2="100%" y2="100%">
			      <stop offset="0%" stop-color="#113D6B"/>
			      <stop offset="100%" stop-color="#0A223D"/>
			    </linearGradient>
			    <linearGradient id="botVisorBorder" x1="0%" y1="0%" x2="0%" y2="100%">
			      <stop offset="0%" stop-color="#E8ECEF"/>
			      <stop offset="100%" stop-color="#BACEE0"/>
			    </linearGradient>
			  </defs>
			  <g class="bot-body-group">
			    <path d="M 170 330 Q 250 375 330 330 Q 250 350 170 330 Z" fill="#0A223D"/>
			    <ellipse cx="108" cy="255" rx="36" ry="46" fill="url(#botNavy)" class="bot-ear-left"/>
			    <ellipse cx="392" cy="255" rx="36" ry="46" fill="url(#botNavy)" class="bot-ear-right"/>
			    <g class="bot-antenna-group">
			      <path d="M 250 140 L 262 90" stroke="#0A223D" stroke-width="6" stroke-linecap="round"/>
			      <circle cx="264" cy="82" r="16" fill="url(#botNavy)"/>
			      <circle cx="260" cy="78" r="5" fill="#00F5D4" class="bot-antenna-light"/>
			    </g>
			    <path d="M 225 145 C 225 133 275 133 275 145 Z" fill="url(#botNavy)"/>
			    <path d="M 120 250 C 115 140, 385 140, 380 250 C 380 340, 120 340, 120 250 Z" fill="url(#botHelmet)"/>
			    <path d="M 190 168 C 220 185, 280 185, 310 168" fill="none" stroke="#BACEE0" stroke-width="3" stroke-linecap="round"/>
			    <line x1="250" y1="182" x2="250" y2="340" stroke="#CBDCEE" stroke-width="2.5"/>
			    <rect x="135" y="180" width="230" height="135" rx="45" fill="url(#botVisorBorder)"/>
			    <rect x="142" y="186" width="216" height="123" rx="38" fill="url(#botScreen)"/>
			    <path d="M 142 210 L 358 210 M 142 230 L 358 230 M 142 250 L 358 250 M 142 270 L 358 270 M 142 290 L 358 290" stroke="#12316C" stroke-width="1" opacity="0.4"/>
			    <g class="bot-led">
			      <g class="bot-eyes-group">
			        <ellipse cx="195" cy="242" rx="16" ry="24" fill="#00F5D4" class="bot-eye-left"/>
			        <ellipse cx="305" cy="242" rx="16" ry="24" fill="#00F5D4" class="bot-eye-right"/>
			      </g>
			      <path d="M 232 265 Q 250 282 268 265 Z" fill="#00F5D4"/>
			    </g>
			    <path d="M 152 196 A 30 30 0 0 1 348 196 C 280 208 220 208 152 196 Z" fill="#FFFFFF" opacity="0.15"/>
			  </g>
			</svg>
		</div>
	</div>

	<style>
		.privilege-bot-floating-wrapper { position: fixed; bottom: 15px; right: 15px; z-index: 999999; display: flex; flex-direction: column; align-items: flex-end; }
		.privilege-bot-tooltip { background: linear-gradient(135deg, #0A1836 0%, #112c5b 100%); color: #00F5D4; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); border: 1px solid rgba(0, 245, 212, 0.4); margin-bottom: 5px; margin-right: 15px; cursor: pointer; white-space: nowrap; }
		.privilege-bot-floating-container { position: relative; width: 110px; height: 110px; background: transparent !important; display: flex; align-items: center; justify-content: center; cursor: pointer; }
		.privilege-bot-radar-ring { position: absolute; width: 70px; height: 70px; border-radius: 50%; border: 2px solid #00F5D4; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.8); opacity: 0; pointer-events: none; }
		.privilege-bot-svg { width: 110px; height: 110px; overflow: visible; display: block; filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.4)); }
		.privilege-wa-modal { position: fixed; bottom: 135px; right: 20px; width: 350px; max-width: calc(100vw - 30px); background: rgba(10, 24, 54, 0.96); backdrop-filter: blur(16px); border: 1px solid rgba(0, 245, 212, 0.3); border-radius: 24px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5); z-index: 1000000; overflow: hidden; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; opacity: 0; visibility: hidden; transform: translateY(25px) scale(0.92); transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
		.privilege-wa-modal.is-active { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
		.privilege-wa-header { background: linear-gradient(135deg, #0A1836 0%, #112c5b 100%); color: #ffffff; padding: 18px 20px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid rgba(0, 245, 212, 0.2); }
		.privilege-wa-avatar { width: 46px; height: 46px; background: rgba(0, 245, 212, 0.1); border: 2px solid #00F5D4; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; }
		.privilege-wa-pulse-dot { width: 12px; height: 12px; background: #25d366; border: 2px solid #0A1836; border-radius: 50%; position: absolute; bottom: 0; right: 0; }
		.privilege-wa-title-area { display: flex; flex-direction: column; flex-grow: 1; }
		.privilege-wa-title-area strong { font-size: 16px; font-weight: 700; color: #ffffff; }
		.privilege-wa-status-text { font-size: 12px; color: #00F5D4; display: flex; align-items: center; gap: 5px; margin-top: 2px; }
		.green-dot { width: 6px; height: 6px; background: #25d366; border-radius: 50%; display: inline-block; }
		.privilege-wa-close { background: rgba(255, 255, 255, 0.08); border: none; color: #ffffff; font-size: 22px; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
		.privilege-wa-body { padding: 20px; }
		.privilege-wa-greeting { font-size: 13px; color: #cbd5e0; margin: 0 0 16px 0; line-height: 1.5; }
		.privilege-wa-greeting strong { color: #00F5D4; }
		.privilege-wa-options { display: flex; flex-direction: column; gap: 10px; }
		.privilege-wa-btn { display: flex; align-items: center; gap: 12px; background: rgba(255, 255, 255, 0.04); padding: 12px 16px; border-radius: 14px; text-decoration: none !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.08); transition: all 0.25s ease; }
		.privilege-wa-btn:hover { background: rgba(0, 245, 212, 0.15); border-color: #00F5D4; transform: translateX(6px); }
		.privilege-wa-btn-highlight { background: rgba(0, 245, 212, 0.08); border: 1px dashed rgba(0, 245, 212, 0.4); }
		.privilege-wa-icon { font-size: 20px; }
		.privilege-wa-btn-text { display: flex; flex-direction: column; flex-grow: 1; }
		.privilege-wa-btn-text strong { font-size: 13px; font-weight: 700; color: #ffffff; }
		.privilege-wa-btn-text span { font-size: 11px; color: #a0aec0; margin-top: 2px; }
		.privilege-wa-arrow { font-size: 16px; color: #00F5D4; opacity: 0.5; }
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var botTrigger = document.getElementById('privilege-bot-trigger');
			var botTooltip = document.getElementById('privilege-bot-tooltip');
			var waModal = document.getElementById('privilege-wa-modal');
			var waClose = document.getElementById('privilege-wa-close');

			function toggleModal(e) {
				if (e) e.stopPropagation();
				if (waModal) {
					waModal.classList.toggle('is-active');
					if (botTooltip) {
						botTooltip.style.display = waModal.classList.contains('is-active') ? 'none' : 'block';
					}
				}
			}

			if (botTrigger) botTrigger.addEventListener('click', toggleModal);
			if (botTooltip) botTooltip.addEventListener('click', toggleModal);
			if (waClose) {
				waClose.addEventListener('click', function(e) {
					e.stopPropagation();
					if (waModal) waModal.classList.remove('is-active');
					if (botTooltip) botTooltip.style.display = 'block';
				});
			}
		});
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'privilege_bot', 'pe_render_bot_whatsapp_modal' );

add_action( 'wp_footer', function() {
	if ( is_admin() ) {
		return;
	}
	echo do_shortcode( '[privilege_bot]' );
}, 50 );