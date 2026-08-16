<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

final class Admin_Dashboard {
	private const PAGE = 'jeito-performance-premium';

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_jpp_verify_home', array( $this, 'verify_home' ) );
	}

	public function register_settings() {
		register_setting( 'jpp_features', 'jpp_features', array( 'sanitize_callback' => array( Feature_Flags::class, 'sanitize' ) ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Jeito Performance', 'jeito-performance-premium' ),
			__( 'Jeito Performance', 'jeito-performance-premium' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' ),
			'dashicons-performance',
			58
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		$base = plugin_dir_url( JPP_FILE );
		wp_enqueue_style( 'jpp-admin', $base . 'assets/admin.css', array(), JPP_VERSION );
		wp_enqueue_script( 'jpp-admin', $base . 'assets/admin.js', array(), JPP_VERSION, true );
		wp_localize_script( 'jpp-admin', 'jppAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'jpp_verify_home' ) ) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$theme = wp_get_theme();
		$aiko = Compatibility::aiko();
		$builder = Compatibility::bold_builder();
		$cache = get_option( 'jpp_last_cache_flush', array() );
		?>
		<div class="wrap jpp-shell">
			<div class="jpp-orb jpp-orb--one"></div><div class="jpp-orb jpp-orb--two"></div>
			<header class="jpp-hero jpp-glass">
				<div><span class="jpp-eyebrow"><?php esc_html_e( 'Mobile performance studio', 'jeito-performance-premium' ); ?></span><h1><?php esc_html_e( 'Velocidade com clareza.', 'jeito-performance-premium' ); ?></h1><p><?php esc_html_e( 'Diagnóstico técnico, integrações seguras e nenhum número inventado.', 'jeito-performance-premium' ); ?></p></div>
				<div class="jpp-score" aria-label="<?php esc_attr_e( 'Baseline Lighthouse informado: 84', 'jeito-performance-premium' ); ?>"><strong>84</strong><span>baseline</span></div>
			</header>

			<section class="jpp-grid" aria-label="<?php esc_attr_e( 'Resumo da auditoria', 'jeito-performance-premium' ); ?>">
				<?php $this->card( 'violet', '810.676 B', 'Aiko CSS', 'Render-blocking · 515,3 ms', 'aiko' ); ?>
				<?php $this->card( 'cyan', '303.083 B', 'Builder CSS', 'Global · 339,2 ms', 'builder' ); ?>
				<?php $this->card( 'amber', '264.648 B', 'Icon fonts', '2 famílias locais', 'fonts' ); ?>
				<?php $this->card( 'rose', '692.604 B', 'HTML', 'DOM excessivo', 'html' ); ?>
			</section>

			<section class="jpp-panel jpp-glass">
				<div class="jpp-panel__heading"><div><span class="jpp-eyebrow"><?php esc_html_e( 'Integrações', 'jeito-performance-premium' ); ?></span><h2><?php esc_html_e( 'Estado do ambiente', 'jeito-performance-premium' ); ?></h2></div><span class="jpp-live"><i></i><?php esc_html_e( 'Leitura ao vivo', 'jeito-performance-premium' ); ?></span></div>
				<div class="jpp-status-list">
					<?php $this->status( 'Aiko', $aiko, $aiko ? $theme->get( 'Version' ) : __( 'Tema não detectado', 'jeito-performance-premium' ) ); ?>
					<?php $this->status( 'Bold Page Builder', $builder, $builder ? BT_BB_VERSION : __( 'Plugin não detectado', 'jeito-performance-premium' ) ); ?>
					<?php $this->status( __( 'Hero LCP mobile', 'jeito-performance-premium' ), $aiko && $builder, __( 'Imagem semântica + parallax removido', 'jeito-performance-premium' ) ); ?>
					<?php $this->status( __( 'Cache coordenado', 'jeito-performance-premium' ), ! empty( $cache['success'] ), ! empty( $cache['provider'] ) ? $cache['provider'] : __( 'Aguardando ativação', 'jeito-performance-premium' ) ); ?>
				</div>
			</section>
			<form class="jpp-panel jpp-glass jpp-settings" action="options.php" method="post">
				<?php settings_fields( 'jpp_features' ); $features = get_option( 'jpp_features', array() ); ?>
				<div class="jpp-panel__heading"><div><span class="jpp-eyebrow"><?php esc_html_e( 'Rollback granular', 'jeito-performance-premium' ); ?></span><h2><?php esc_html_e( 'Intervenções ativas', 'jeito-performance-premium' ); ?></h2></div></div>
				<div class="jpp-toggles">
					<?php $this->toggle( 'mobile_hero', __( 'Hero/LCP mobile', 'jeito-performance-premium' ), $features ); ?>
					<?php $this->toggle( 'content_visibility', __( 'Content visibility', 'jeito-performance-premium' ), $features ); ?>
					<?php $this->toggle( 'deferred_grid', __( 'Post Grid tardio', 'jeito-performance-premium' ), $features ); ?>
					<?php $this->toggle( 'font_axes', __( 'Eixos de fontes da Home', 'jeito-performance-premium' ), $features ); ?>
				</div>
				<?php submit_button( __( 'Salvar intervenções', 'jeito-performance-premium' ), 'primary jpp-save' ); ?>
			</form>
			<section class="jpp-panel jpp-glass jpp-verifier"><div class="jpp-panel__heading"><div><span class="jpp-eyebrow"><?php esc_html_e( 'HTML pós-cache', 'jeito-performance-premium' ); ?></span><h2><?php esc_html_e( 'Verificação do documento servido', 'jeito-performance-premium' ); ?></h2></div><button type="button" class="button jpp-button" data-jpp-verify><?php esc_html_e( 'Verificar Home', 'jeito-performance-premium' ); ?></button></div><div class="jpp-verify-result" data-jpp-result aria-live="polite"><?php esc_html_e( 'Nenhuma verificação executada nesta sessão.', 'jeito-performance-premium' ); ?></div></section>

			<footer class="jpp-footer"><span>Jeito Performance <?php echo esc_html( JPP_VERSION ); ?></span><button type="button" class="button jpp-button" data-jpp-open><?php esc_html_e( 'Abrir relatório técnico', 'jeito-performance-premium' ); ?></button></footer>
			<div class="jpp-modal" data-jpp-modal hidden><div class="jpp-modal__backdrop" data-jpp-close></div><section class="jpp-modal__content jpp-glass" role="dialog" aria-modal="true" aria-labelledby="jpp-dialog-title"><button class="jpp-close" type="button" data-jpp-close aria-label="<?php esc_attr_e( 'Fechar', 'jeito-performance-premium' ); ?>">×</button><span class="jpp-eyebrow">AUDIT / 2026-08-15</span><h2 id="jpp-dialog-title"><?php esc_html_e( 'Prioridades confirmadas', 'jeito-performance-premium' ); ?></h2><ol><li><strong>Aiko CSS</strong><span>810.676 B descompactados</span></li><li><strong>Builder CSS</strong><span>303.083 B descompactados</span></li><li><strong>FontAwesome</strong><span>264.648 B descompactados</span></li><li><strong>HTML</strong><span>692.604 B descompactados</span></li></ol><p class="jpp-note"><?php esc_html_e( 'Resultados pós-instalação dependem de staging e permanecem NOT VALIDATED.', 'jeito-performance-premium' ); ?></p></section></div>
		</div>
		<?php
	}

	private function card( $tone, $value, $label, $detail, $key ) {
		printf( '<button type="button" class="jpp-card jpp-glass jpp-card--%1$s" data-jpp-open data-metric="%5$s"><span>%2$s</span><strong>%3$s</strong><small>%4$s</small><i aria-hidden="true">↗</i></button>', esc_attr( $tone ), esc_html( $value ), esc_html( $label ), esc_html( $detail ), esc_attr( $key ) );
	}

	private function status( $name, $active, $detail ) {
		printf( '<div><span class="jpp-status-icon %1$s">%2$s</span><strong>%3$s</strong><small>%4$s</small></div>', $active ? 'is-active' : '', $active ? '✓' : '!', esc_html( $name ), esc_html( $detail ) );
	}

	private function toggle( $key, $label, $features ) {
		$checked = ! array_key_exists( $key, $features ) || ! empty( $features[ $key ] );
		printf( '<label><span>%1$s</span><input type="checkbox" name="jpp_features[%2$s]" value="1" %3$s><i aria-hidden="true"></i></label>', esc_html( $label ), esc_attr( $key ), checked( $checked, true, false ) );
	}

	public function verify_home() {
		check_ajax_referer( 'jpp_verify_home', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'jeito-performance-premium' ) ), 403 );
		}
		$result = ( new HTML_Verifier() )->fetch_home();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}
		wp_send_json_success( $result );
	}

	public function inspect_html( $html ) {
		return ( new HTML_Verifier() )->inspect( $html );
	}
}
