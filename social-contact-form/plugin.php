<?php
/**
 * Plugin name:      FormyChat
 * Plugin URI:       https://wppool.dev/social-contact-form-pricing/
 * Description:      Add a contact form on your website that sends form leads directly to your WhatsApp web or mobile, including WooCommerce orders, cart, etc
 * Version:          2.10.7
 * Author:           WPPOOL
 * Author URI:       https://wppool.dev
 * License:          GPLv2 or later
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      social-contact-form
 * Domain Path:      /languages
 *
 * @package FormyChat
 * @since 1.0.0
 * @author WPPOOL
 * @link https://wppool.dev
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define constants.
define('FORMYCHAT_FILE', __FILE__ );
define('FORMYCHAT_VERSION', '2.10.7' );

// Include files.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-boot.php';

/**
 * The main function responsible for returning the one true FormyChat instance to functions everywhere.
 *
 * @author WPPOOL
 * @link https://wppool.dev/social-contact-form/
 */
