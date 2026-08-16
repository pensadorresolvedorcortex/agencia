<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

final class CLI_Command extends \WP_CLI_Command {
	/** Verify the public cached Home and emit machine-readable JSON. */
	public function verify() {
		$result = ( new HTML_Verifier() )->fetch_home();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		if ( ! $result['passed'] ) {
			\WP_CLI::halt( 1 );
		}
	}
}
