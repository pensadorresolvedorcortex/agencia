<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

final class Compatibility {
	public static function aiko() {
		$theme = wp_get_theme();
		$version = (string) $theme->get( 'Version' );
		return 'aiko' === strtolower( $theme->get_template() )
			&& version_compare( $version, '1.0.5', '>=' )
			&& version_compare( $version, '1.1.0', '<' );
	}

	public static function bold_builder() {
		return defined( 'BT_BB_VERSION' )
			&& version_compare( BT_BB_VERSION, '5.9.6', '>=' )
			&& version_compare( BT_BB_VERSION, '6.0.0', '<' );
	}
}
