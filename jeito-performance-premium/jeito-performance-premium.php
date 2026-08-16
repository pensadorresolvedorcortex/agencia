<?php
/**
 * Plugin Name: Jeito Performance Premium
 * Description: Integrações conservadoras de performance para Aiko e Bold Page Builder.
 * Version: 3.1.0
 * Requires PHP: 7.4
 * Text Domain: jeito-performance-premium
 */

defined( 'ABSPATH' ) || exit;

define( 'JPP_VERSION', '3.1.0' );
define( 'JPP_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'JPP\\Plugin', 'boot' ) );

register_activation_hook( __FILE__, static function () {
	update_option( 'jpp_cache_flush_pending', JPP_VERSION, false );
	delete_option( 'jpp_cache_flush_attempts' );
} );
