<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

final class HTML_Verifier {
	public function fetch_home() {
		$response = wp_remote_get( home_url( '/' ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		$html = wp_remote_retrieve_body( $response );
		if ( 200 !== $status || '' === $html ) {
			return new \WP_Error( 'jpp_home_http', sprintf( __( 'Home retornou HTTP %d.', 'jeito-performance-premium' ), $status ) );
		}
		return $this->inspect( $html );
	}

	public function inspect( $html ) {
		$has_parallax = false !== strpos( $html, 'bt_bb_parallax' );
		$hero_url = $this->mobile_hero_url( $html );
		$hero_is_prioritized = $hero_url && $this->mobile_hero_is_prioritized( $html );
		$font_axes_are_limited = $this->font_axes_are_limited( $html );
		$checks = array(
			'hero'               => ! ( Compatibility::aiko() && Compatibility::bold_builder() && Feature_Flags::enabled( 'mobile_hero' ) ) || ! $has_parallax || (bool) $hero_url,
			'lcp_priority'       => ! ( Compatibility::aiko() && Feature_Flags::enabled( 'mobile_hero' ) ) || ! $has_parallax || $hero_is_prioritized,
			'content_visibility' => ! ( Compatibility::aiko() && Feature_Flags::enabled( 'content_visibility' ) ) || false !== strpos( $html, 'contain-intrinsic-size:auto 800px' ),
			'deferred_grid'       => ! ( Compatibility::bold_builder() && Feature_Flags::enabled( 'deferred_grid' ) ) || false === strpos( $html, 'bt_bb_css_post_grid' ) || false !== strpos( $html, 'jppDeferredGridEnqueued' ),
			'font_axes'           => ! ( Compatibility::aiko() && Feature_Flags::enabled( 'font_axes' ) ) || $font_axes_are_limited,
			'aiko_home_css'        => ! ( Compatibility::aiko() && Feature_Flags::enabled( 'aiko_home_css' ) ) || false !== strpos( $html, 'assets/aiko-home.css' ),
			'builder_home_css'     => ! ( Compatibility::bold_builder() && Feature_Flags::enabled( 'builder_home_css' ) ) || false !== strpos( $html, 'assets/builder-home.css' ),
		);
		return array( 'passed' => ! in_array( false, $checks, true ), 'checks' => $checks, 'bytes' => strlen( $html ) );
	}

	private function mobile_hero_url( $html ) {
		if ( ! preg_match( '/<picture\b[^>]*>(.*?)<\/picture>/is', $html, $picture ) || false === strpos( $picture[1], 'jpp-mobile-lcp' ) ) {
			return '';
		}
		if ( ! preg_match( '/<source\b[^>]*\bsrcset=(["\'])(.*?)\1/i', $picture[1], $source ) || ! preg_match( '/<img\b[^>]*\bclass=(["\'])[^"\']*\bjpp-mobile-lcp\b[^"\']*\1/i', $picture[1] ) ) {
			return '';
		}
		return html_entity_decode( $source[2], ENT_QUOTES, 'UTF-8' );
	}

	private function mobile_hero_is_prioritized( $html ) {
		if ( ! preg_match( '/<img\b[^>]*\bclass=(["\'])[^"\']*\bjpp-mobile-lcp\b[^"\']*\1[^>]*>/i', $html, $image ) ) {
			return false;
		}
		return (bool) preg_match( '/\bloading=(["\'])eager\1/i', $image[0] ) && (bool) preg_match( '/\bfetchpriority=(["\'])high\1/i', $image[0] );
	}

	private function font_axes_are_limited( $html ) {
		if ( ! preg_match_all( '/<link\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>/i', $html, $links ) ) {
			return true;
		}
		foreach ( $links[2] as $href ) {
			$href = rawurldecode( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) );
			if ( false === stripos( $href, 'fonts.googleapis.com/css2' ) ) {
				continue;
			}
			$has_families = preg_match_all( '/(?:^|[?&])family=([^&]+)/i', $href, $families );
			if ( false !== stripos( $href, ':ital,' ) || ( $has_families && $this->has_unapproved_font_axis( $families[1] ) ) ) {
				return false;
			}
		}
		return true;
	}

	private function has_unapproved_font_axis( $families ) {
		foreach ( $families as $family ) {
			if ( preg_match( '/:wght@([^&]+)/i', $family, $weights ) && '300;400;500;700' !== $weights[1] ) {
				return true;
			}
		}
		return false;
	}
}
