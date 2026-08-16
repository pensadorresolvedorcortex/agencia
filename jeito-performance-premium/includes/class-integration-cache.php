<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

/** Coordinates a single cache purge after activation/update. */
final class Integration_Cache {
	public function register() {
		$this->schedule_after_update();
		add_action( 'admin_init', array( $this, 'maybe_flush' ), 20 );
	}

	private function schedule_after_update() {
		if ( JPP_VERSION === get_option( 'jpp_installed_version', '' ) ) {
			return;
		}
		update_option( 'jpp_installed_version', JPP_VERSION, false );
		update_option( 'jpp_cache_flush_pending', JPP_VERSION, false );
	}

	public function maybe_flush() {
		$pending = get_option( 'jpp_cache_flush_pending', '' );
		if ( JPP_VERSION !== $pending || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$provider = 'wordpress';
		$flushed = false;
		if ( function_exists( 'w3tc_flush_all' ) ) {
			$provider = 'w3-total-cache';
			$flushed = (bool) w3tc_flush_all();
		} elseif ( function_exists( 'wp_cache_flush' ) ) {
			$flushed = (bool) wp_cache_flush();
		}

		update_option(
			'jpp_last_cache_flush',
			array(
				'provider' => $provider,
				'success'  => $flushed,
				'time'     => time(),
				'version'  => JPP_VERSION,
			),
			false
		);
		delete_option( 'jpp_cache_flush_pending' );
		return $flushed;
	}
}
