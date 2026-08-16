<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

final class Feature_Flags {
	private const DEFAULTS = array(
		'mobile_hero'       => true,
		'content_visibility' => true,
		'deferred_grid'      => true,
		'font_axes'          => true,
	);

	public static function enabled( $feature ) {
		$settings = get_option( 'jpp_features', array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::DEFAULTS );
		return ! empty( $settings[ $feature ] );
	}

	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array_map(
			static function ( $feature ) use ( $value ) {
				return ! empty( $value[ $feature ] );
			},
			array_combine( array_keys( self::DEFAULTS ), array_keys( self::DEFAULTS ) )
		);
	}
}
