<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

/** Safe optimizations for the Aiko theme's global stylesheet. */
final class Integration_Aiko {
	public function register() {
		add_action( 'wp_head', array( $this, 'mobile_hero_css' ), 40 );
		add_filter( 'style_loader_src', array( $this, 'limit_front_page_font_axes' ), 10, 2 );
		add_filter( 'style_loader_src', array( $this, 'home_stylesheet_src' ), 20, 2 );
	}

	/** Keep only font weights evidenced by the supplied Home snapshot. */
	public function limit_front_page_font_axes( $src, $handle ) {
		if ( ! Feature_Flags::enabled( 'font_axes' ) || ! $this->is_supported_theme() || ! is_front_page() || ! in_array( $handle, array( 'boldthemes-fonts', 'bt_bb_google_fonts' ), true ) ) {
			return $src;
		}
		return preg_replace( '/(?::|%3A)ital(?:,|%2C)wght(?:@|%40)[^&]+/i', ':wght@300;400;500;700', $src );
	}

	public function is_supported_theme() {
		return Compatibility::aiko();
	}

	/** Opt-in source-level build with WooCommerce modules removed for the audited Home. */
	public function home_stylesheet_src( $src, $handle ) {
		if ( 'aiko-style' !== $handle || ! is_front_page() || ! Feature_Flags::enabled( 'aiko_home_css' ) || ! $this->is_supported_theme() || $this->home_context_has_commerce() ) {
			return $src;
		}
		$theme = wp_get_theme();
		if ( method_exists( $theme, 'get_stylesheet' ) && 'aiko' !== $theme->get_stylesheet() ) {
			return $src;
		}
		return plugin_dir_url( JPP_FILE ) . 'assets/aiko-home.css?ver=' . rawurlencode( JPP_VERSION );
	}

	private function home_context_has_commerce() {
		foreach ( array( 'is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page' ) as $conditional ) {
			if ( function_exists( $conditional ) && call_user_func( $conditional ) ) {
				return true;
			}
		}
		if ( function_exists( 'wp_get_sidebars_widgets' ) ) {
			foreach ( (array) wp_get_sidebars_widgets() as $sidebar => $widgets ) {
				if ( 'wp_inactive_widgets' === $sidebar || 0 === strpos( (string) $sidebar, 'orphaned_widgets' ) ) {
					continue;
				}
				foreach ( (array) $widgets as $widget ) {
					if ( preg_match( '/^(?:woocommerce[_-]|widget_shopping_cart)/i', $widget ) ) {
						return true;
					}
				}
			}
		}
		$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		if ( false !== stripos( $content, '<!-- wp:woocommerce/' ) ) {
			return true;
		}
		return (bool) preg_match( '/\[(?:woocommerce_[a-z_]+|products?|product_page|product_category|product_categories|sale_products|best_selling_products|top_rated_products|featured_products|recent_products)(?:\s|\]|\/)/i', $content );
	}

	/** Make the semantic hero image fill the existing background layer on mobile. */
	public function mobile_hero_css() {
		if ( ! $this->is_supported_theme() || ! is_front_page() ) {
			return;
		}
		$css = '.jpp-mobile-lcp{display:none}';
		if ( Feature_Flags::enabled( 'content_visibility' ) ) {
			$css .= '.bt_bb_wrapper>.bt_bb_section.bt_bb_animation_no_animation:nth-child(n+3):not(:has(.bt_bb_slider,.bt_bb_content_slider,.bt_bb_css_post_grid,.bt_bb_masonry_post_grid,.bt_bb_google_maps,.bt_bb_leaflet_map,.bt_bb_parallax,.ti-widget,.wpcf7)){content-visibility:auto;contain-intrinsic-size:auto 800px}';
		}
		if ( Feature_Flags::enabled( 'mobile_hero' ) ) {
			$css .= '@media(max-width:767px){.bt_bb_section.jpp-has-mobile-lcp .bt_bb_background_image_holder{background-image:none!important;transform:none!important;transition:none!important;animation:none!important;will-change:auto!important}.bt_bb_section.jpp-has-mobile-lcp .jpp-mobile-lcp{display:block;position:absolute;inset:0;width:100%;height:100%;object-fit:cover}}';
		}
		echo '<style id="jpp-aiko-mobile-hero">' . $css . '</style>' . "\n";
	}
}
