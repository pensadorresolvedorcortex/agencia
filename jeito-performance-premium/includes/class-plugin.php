<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;

	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		require_once __DIR__ . '/class-feature-flags.php';
		require_once __DIR__ . '/class-compatibility.php';
		require_once __DIR__ . '/class-html-verifier.php';
		require_once __DIR__ . '/class-integration-aiko.php';
		require_once __DIR__ . '/class-integration-bold-builder.php';
		require_once __DIR__ . '/class-admin-dashboard.php';
		require_once __DIR__ . '/class-integration-cache.php';
		( new Integration_Aiko() )->register();
		( new Integration_Bold_Builder() )->register();
		( new Admin_Dashboard() )->register();
		( new Integration_Cache() )->register();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/class-cli-command.php';
			\WP_CLI::add_command( 'jeito-performance', CLI_Command::class );
		}
	}
}
