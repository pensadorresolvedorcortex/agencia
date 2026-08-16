<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

/** LCP discovery improvements that do not rewrite Builder markup. */
final class Integration_Bold_Builder {
	private $hero_done = false;

	public function register() {
		add_filter( 'do_shortcode_tag', array( $this, 'render_mobile_lcp_image' ), 10, 4 );
		add_action( 'wp_footer', array( $this, 'enqueue_deferred_grid' ), 1 );
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
		$image = sprintf( '<picture><source media="(max-width:767px)" srcset="%1$s"><img class="jpp-mobile-lcp" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="" loading="eager" decoding="async" fetchpriority="high" aria-hidden="true"></picture>', esc_attr( $url ) );
		$output = preg_replace( '/class=(["\'])bt_bb_section\b/', 'class=$1bt_bb_section jpp-has-mobile-lcp', $output, 1 );
		return preg_replace( '/(<div\s+class=(["\'])bt_bb_background_image_holder[^>]*>)/i', '$1' . $image, $output, 1 );
	}
}
