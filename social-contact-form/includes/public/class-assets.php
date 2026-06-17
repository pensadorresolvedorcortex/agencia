<?php
/**
 * Public Assets Class.
 * Handles all assets for the public side.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace .
namespace FormyChat\Publics;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


if ( ! class_exists( __NAMESPACE__ . '\Assets' ) ) {
	/**
	 * Public Assets.
	 * Handles all assets for the public side.
	 *
	 * @package FormyChat
	 * @since 1.0.0
	 */
	class Assets extends \FormyChat\Base {
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		public function actions() {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}

		/**
		 * Enqueue scripts and styles.
		 *
		 * @return void
		 */
		public function enqueue_scripts() {

			wp_enqueue_style( 'formychat-frontend', FORMYCHAT_PUBLIC . '/css/frontend.min.css', [], FORMYCHAT_VERSION );

			if ( defined('FORMYCHAT_FORM_ADMIN') ) {
				return;
			}

			wp_enqueue_script( 'formychat-frontend', FORMYCHAT_PUBLIC . '/js/frontend.min.js', [ 'jquery' ], FORMYCHAT_VERSION, true );

			wp_localize_script(
				'formychat-frontend',
				'formychat_vars',
				apply_filters( 'scf_localize_script', [

					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'formychat_widget_nonce' ),

					'rest_url'    => rest_url( 'formychat/v1' ),
					'rest_nonce'  => wp_create_nonce( 'wp_rest' ),

					'is_premium'      => $this->is_ultimate_active(),
					'is_form_page'    => defined('FORMYCHAT_FORM_PAGE'),

					'current' => [
						'post_type' => get_post_type(),
						'post_id'   => get_the_ID(),
						'is_home'   => is_home(),
						'is_front_page' => is_front_page(),
					],
					'data' => [
						'countries' => \FormyChat\App::countries(),
						'widgets'     => \FormyChat\Models\Widget::get_active_widgets(),
						'default_config' => \FormyChat\App::widget_config(),
						'form_fields' => \FormyChat\App::form_fields(),
					],
					'site' => [
						'url' => get_site_url(),
						'name' => get_bloginfo( 'name' ),
						'description' => get_bloginfo( 'description' ),
					],
					'user' => $this->get_user(),
					'custom_tags' => \FormyChat\App::custom_tags(),
				] )
			);

			// Embed fonts.
			$font_css = \FormyChat\App::embed_fonts();
			$inline_css = apply_filters( 'formychat_inline_css', $font_css );
			if ( ! empty( $font_css ) ) {
				wp_add_inline_style( 'formychat-frontend', $inline_css );
			}
		}

		/**
		 * Get user.
		 *
		 * @return array
		 */
		public function get_user() {

			// Bail if user is not logged in.
			if ( ! is_user_logged_in() ) {
				return [];
			}

			$user = wp_get_current_user();

			$name = $user->display_name;

			if ( empty( $name ) ) {
				$name = trim( get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true ) );
			}

			$user_data = [
				'id' => $user->ID,
				'email' => $user->user_email,
				'first_name' => get_user_meta( $user->ID, 'first_name', true ),
				'last_name' => get_user_meta( $user->ID, 'last_name', true ),
				'name' => $name,
				'phone' => get_user_meta( $user->ID, 'billing_phone', true ),
			];

			return $user_data;
		}
	}

	// Init.
	Assets::init();
}
