<?php
namespace JPP;

defined( 'ABSPATH' ) || exit;

/** Coordinates cache purges after activation, update, or a feature change. */
final class Integration_Cache {
	private const MAX_FLUSH_ATTEMPTS = 3;

	public function register() {
		$this->schedule_after_update();
		add_action( 'add_option_jpp_features', array( $this, 'schedule_after_feature_add' ), 10, 2 );
		add_action( 'update_option_jpp_features', array( $this, 'schedule_after_feature_change' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_flush' ), 20 );
	}

	/** First settings save uses add_option(), not update_option(). */
	public function schedule_after_feature_add( $option, $value ) {
		if ( 'jpp_features' !== $option || ! is_array( $value ) ) {
			return;
		}
		$this->schedule_flush();
	}

	/** Ensure cached HTML cannot retain a feature state that was just changed. */
	public function schedule_after_feature_change( $old_value, $new_value ) {
		if ( $old_value === $new_value ) {
			return;
		}
		$this->schedule_flush();
	}

	private function schedule_after_update() {
		$installed_version = get_option( 'jpp_installed_version', '' );
		if ( JPP_VERSION === $installed_version ) {
			return;
		}
		$this->migrate_features( $installed_version );
		update_option( 'jpp_installed_version', JPP_VERSION, false );
		$this->schedule_flush();
	}

	private function schedule_flush() {
		update_option( 'jpp_cache_flush_pending', JPP_VERSION, false );
		delete_option( 'jpp_cache_flush_attempts' );
	}

	/** Disable the unvalidated CLS-sensitive feature once when upgrading. */
	private function migrate_features( $installed_version ) {
		if ( '' === $installed_version ) {
			return;
		}
		$features = get_option( 'jpp_features', array() );
		if ( ! is_array( $features ) ) {
			$features = array();
		}
		if ( version_compare( $installed_version, '2.2.1', '<' ) ) {
			$features['content_visibility'] = false;
		}
		if ( version_compare( $installed_version, '3.1.0', '<' ) ) {
			$features['aiko_home_css'] = true;
			$features['builder_home_css'] = true;
		}
		update_option( 'jpp_features', $features, false );
	}

	public function maybe_flush() {
		$pending = get_option( 'jpp_cache_flush_pending', '' );
		if ( JPP_VERSION !== $pending || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$provider = 'wordpress';
		$flushed = false;
		$attempts = (int) get_option( 'jpp_cache_flush_attempts', 0 ) + 1;
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
				'attempts' => $attempts,
			),
			false
		);
		if ( $flushed || self::MAX_FLUSH_ATTEMPTS <= $attempts ) {
			delete_option( 'jpp_cache_flush_pending' );
			delete_option( 'jpp_cache_flush_attempts' );
		} else {
			update_option( 'jpp_cache_flush_attempts', $attempts, false );
		}
		return $flushed;
	}
}
