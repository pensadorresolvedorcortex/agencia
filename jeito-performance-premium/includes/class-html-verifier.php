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
		$checks = array(
			'hero'               => ! ( Compatibility::aiko() && Compatibility::bold_builder() && Feature_Flags::enabled( 'mobile_hero' ) ) || false === strpos( $html, 'bt_bb_parallax' ) || false !== strpos( $html, 'jpp-mobile-lcp' ),
			'content_visibility' => ! ( Compatibility::aiko() && Feature_Flags::enabled( 'content_visibility' ) ) || false !== strpos( $html, 'contain-intrinsic-size:auto 800px' ),
			'deferred_grid'       => ! ( Compatibility::bold_builder() && Feature_Flags::enabled( 'deferred_grid' ) ) || false === strpos( $html, 'bt_bb_css_post_grid' ) || false !== strpos( $html, 'jppDeferredGridEnqueued' ),
			'font_axes'           => ! ( Compatibility::aiko() && Feature_Flags::enabled( 'font_axes' ) ) || false === strpos( $html, 'fonts.googleapis.com/css2' ) || false !== strpos( html_entity_decode( $html, ENT_QUOTES, 'UTF-8' ), 'wght@300;400;500;700' ),
		);
		return array( 'passed' => ! in_array( false, $checks, true ), 'checks' => $checks, 'bytes' => strlen( $html ) );
	}
}
