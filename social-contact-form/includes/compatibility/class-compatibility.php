<?php
/**
 * Compatibility class for FormyChat.
 *
 * @package FormyChat
 * @since 2.9.3
 */
// Namespace .
namespace FormyChat;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Compatibility extends \FormyChat\Base {
    /**
     * Actions.
     *
     * @since 2.9.3
     */
    public function actions() {
        $themes_support = [
            'twentytwentyfive' => [ $this, 'twentytwentyfive' ],
        ];

        // Check if current theme is supported.
        if ( array_key_exists( wp_get_theme()->template, $themes_support ) ) {
            // Call theme specific function.
            if ( is_callable( $themes_support[ wp_get_theme()->template ] ) ) {
                call_user_func( $themes_support[ wp_get_theme()->template ] );
            }
        }
    }

    /**
     * Admin notice.
     *
     * @since 2.9.3
     */
    public function twentytwentyfive() {
        // Extend formychat_inline_css.
        add_filter( 'formychat_inline_css', function ( $css ) {
            $css .= '.formychat-widget-custom-form input max-width: 98% !important;';
            return $css;
        } );
    }
}


// Instantiate Compatibility.
Compatibility::init();
