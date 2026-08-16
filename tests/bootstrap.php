<?php
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['jpp_test_options'] = array();
function add_filter() {}
function add_action() {}
function is_front_page() { return true; }
function get_option( $key, $default = '' ) { return $GLOBALS['jpp_test_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['jpp_test_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['jpp_test_options'][ $key ] ); return true; }
function current_user_can() { return true; }
function wp_cache_flush() { $GLOBALS['jpp_cache_flush_count'] = ( $GLOBALS['jpp_cache_flush_count'] ?? 0 ) + 1; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function wp_script_is( $handle ) { return ! empty( $GLOBALS['jpp_scripts'][ $handle ] ); }
function wp_enqueue_script( $handle, $src = '', $deps = array() ) { $GLOBALS['jpp_scripts'][ $handle ] = compact( 'src', 'deps' ); }
function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['jpp_inline_scripts'][ $handle ][ $position] = $data; return true; }
function plugin_dir_url() { return 'https://example.test/wp-content/plugins/jeito-performance-premium/'; }
function esc_url( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_get_theme() { return new class { public function get_template() { return $GLOBALS['jpp_theme_template'] ?? 'aiko'; } public function get( $key ) { return 'Version' === $key ? ( $GLOBALS['jpp_theme_version'] ?? '1.0.5' ) : ''; } }; }
