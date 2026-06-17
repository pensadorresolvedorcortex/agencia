<?php
/**
 * Plugin Name: NaFloresta Buy
 * Description: Matrix-style personalization builder for WooCommerce variable products with batch add-to-cart.
 * Version: 1.0.0
 * Author: NaFloresta
 * Text Domain: nafloresta-buy
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NAFB_PLUGIN_FILE', __FILE__);
define('NAFB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NAFB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAFB_VERSION', '1.0.0');

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    require_once __DIR__ . '/includes/Helpers/functions.php';
    nafb_require_directory(__DIR__ . '/includes/Core');
    nafb_require_directory(__DIR__ . '/includes/Domain');
    nafb_require_directory(__DIR__ . '/includes/Application');
    nafb_require_directory(__DIR__ . '/includes/Infrastructure');
    nafb_require_directory(__DIR__ . '/includes/Presentation');
}

register_activation_hook(__FILE__, static function () {
    if (get_option('nafb_presets', null) === null) {
        add_option('nafb_presets', [], '', false);
    }

    if (get_option('nafb_insights', null) === null) {
        add_option('nafb_insights', [
            'started' => 0,
            'completed' => 0,
            'errors' => 0,
            'events' => 0,
        ], '', false);
    }
});

add_action('plugins_loaded', static function () {
    $plugin = new \NaFlorestaBuy\Core\Plugin();
    $plugin->boot();
});
