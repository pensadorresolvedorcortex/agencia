<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

/** LCP discovery improvements that do not rewrite Builder markup. */
final class Integration_Bold_Builder {
	private $hero_done = false;

	public function register() {
		add_filter( 'do_shortcode_tag', array( $this, 'render_mobile_lcp_image' ), 10, 4 );
		add_filter( 'style_loader_src', array( $this, 'home_stylesheet_src' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'start_front_page_buffer' ), 0 );
		add_action( 'wp_footer', array( $this, 'enqueue_deferred_grid' ), 1 );
	}

	public function home_stylesheet_src( $src, $handle ) {
		if ( 'bt_bb_content_elements' !== $handle || ! is_front_page() || ! Compatibility::bold_builder() || ! Feature_Flags::enabled( 'builder_home_css' ) ) {
			return $src;
		}
		if ( class_exists( 'BoldThemes_BB_Settings' ) && ! empty( \BoldThemes_BB_Settings::$custom_content_elements ) ) {
			return $src;
		}
		$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		if ( preg_match( '/bt_bb_(?:accordion|cost_calculator|countdown|counter|css_image_grid|instagram|latest_post|open_map|price_list|progress_bar|service|tabs|twitter|video)|lightbox/i', $content ) ) {
			return $src;
		}
		return plugin_dir_url( JPP_FILE ) . 'assets/builder-home.css?ver=' . rawurlencode( JPP_VERSION );
	}

	/** Final-HTML fallback for Builder paths that bypass do_shortcode_tag. */
	public function start_front_page_buffer() {
		if ( Compatibility::bold_builder() && Feature_Flags::enabled( 'mobile_hero' ) && is_front_page() ) {
			ob_start( array( $this, 'transform_front_page_html' ) );
		}
	}

	public function transform_front_page_html( $html ) {
		if ( false !== strpos( $html, 'jpp-mobile-lcp' ) ) {
			return $this->ensure_hero_preload( $html );
		}
		$html = $this->transform_first_parallax( $html );
		return $this->ensure_hero_preload( $html );
	}

	/** Add the adapter only when Builder actually queued the post-grid script. */
	public function enqueue_deferred_grid() {
		if ( ! Compatibility::bold_builder() || ! Feature_Flags::enabled( 'deferred_grid' ) || ! wp_script_is( 'bt_bb_css_post_grid', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script(
			'jpp-deferred-post-grid',
			plugin_dir_url( JPP_FILE ) . 'assets/deferred-post-grid.js',
			array( 'bt_bb_css_post_grid' ),
			JPP_VERSION,
			true
		);
		wp_add_inline_script( 'jpp-deferred-post-grid', 'window.jppDeferredGridEnqueued=true;', 'before' );
	}

	/** Convert only the first parallax background into a discoverable mobile img. */
	public function render_mobile_lcp_image( $output, $tag, $attr, $m ) {
		if ( ! Compatibility::bold_builder() || ! Feature_Flags::enabled( 'mobile_hero' ) || $this->hero_done || 'bt_bb_section' !== $tag || ! is_front_page() || false === strpos( $output, 'bt_bb_parallax' ) ) {
			return $output;
		}
		if ( ! preg_match( '/background-image:\s*url\((?:&quot;|["\']?)(https?:\/\/.*?)(?:&quot;|["\']?)\)/i', $output, $match ) ) {
			return $output;
		}
		$url = esc_url( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
		if ( ! $url ) {
			return $output;
		}
		$this->hero_done = true;
		return $this->transform_first_parallax( $output );
	}

	private function transform_first_parallax( $output ) {
		if ( false !== strpos( $output, 'jpp-mobile-lcp' ) || false === strpos( $output, 'bt_bb_parallax' ) ) {
			return $output;
		}
		if ( ! preg_match( '/background-image:\s*url\((?:&quot;|["\']?)(https?:\/\/.*?)(?:&quot;|["\']?)\)/i', $output, $match ) ) {
			return $output;
		}
		$url = esc_url( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
		if ( ! $url ) {
			return $output;
		}
		$image = sprintf( '<picture><source media="(max-width:767px)" srcset="%1$s"><img class="jpp-mobile-lcp" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="" loading="eager" decoding="async" fetchpriority="high" aria-hidden="true"></picture>', esc_attr( $url ) );
		$output = preg_replace( '/class=(["\'])bt_bb_section\b/', 'class=$1bt_bb_section jpp-has-mobile-lcp', $output, 1 );
		return preg_replace( '/(<div\s+class=(["\'])bt_bb_background_image_holder[^>]*>)/i', '$1' . $image, $output, 1 );
	}

	private function ensure_hero_preload( $html ) {
		if ( ! preg_match( '/<picture\b[^>]*>(?:(?!<\/picture>).)*<img\b[^>]*\bclass=(["\'])[^"\']*\bjpp-mobile-lcp\b[^"\']*\1[^>]*>(?:(?!<\/picture>).)*<\/picture>/is', $html, $picture ) || ! preg_match( '/<source\b[^>]*\bsrcset=(["\'])(.*?)\1/i', $picture[0], $source ) ) {
			return $html;
		}
		$url = html_entity_decode( $source[2], ENT_QUOTES, 'UTF-8' );
		if ( preg_match( '/<link\b(?=[^>]*\brel=(["\'])preload\1)(?=[^>]*\bas=(["\'])image\2)[^>]*\bhref=(["\'])' . preg_quote( $url, '/' ) . '\3[^>]*>/i', $html, $preload ) ) {
			$tag = preg_replace( '/\s+media=(["\']).*?\1/i', '', $preload[0] );
			if ( false === stripos( $tag, 'fetchpriority=' ) ) {
				$tag = preg_replace( '/\s*(\/?)>$/', ' fetchpriority="high"$1>', $tag );
			}
			return preg_replace( '/' . preg_quote( $preload[0], '/' ) . '/', $tag, $html, 1 );
		}
		$tag = '<link rel="preload" as="image" href="' . esc_attr( $url ) . '" fetchpriority="high">';
		return preg_replace( '/<\/head>/i', $tag . "\n</head>", $html, 1 );
	}
}
