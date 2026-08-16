<?php
require __DIR__ . '/bootstrap.php';

define( 'JPP_VERSION', '2.0.1' );
define( 'BT_BB_VERSION', '5.9.6' );
class WP_CLI_Command {}
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
class WP_CLI {
	public static $lines = array();
	public static function line( $line ) { self::$lines[] = $line; }
	public static function error( $message ) { throw new RuntimeException( $message, 2 ); }
	public static function halt( $code ) { throw new RuntimeException( 'halt', $code ); }
}
function home_url() { return 'https://example.test/'; }
function wp_remote_get() { return $GLOBALS['jpp_http_response']; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function __( $text ) { return $text; }

require dirname( __DIR__ ) . '/jeito-performance-premium/includes/class-feature-flags.php';
require dirname( __DIR__ ) . '/jeito-performance-premium/includes/class-compatibility.php';
require dirname( __DIR__ ) . '/jeito-performance-premium/includes/class-html-verifier.php';
require dirname( __DIR__ ) . '/jeito-performance-premium/includes/class-cli-command.php';

$GLOBALS['jpp_http_response'] = array(
	'status' => 200,
	'body'   => '<div class="bt_bb_parallax"></div><i class="jpp-mobile-lcp"></i><style>contain-intrinsic-size:auto 800px</style><script>window.jppDeferredGridEnqueued=true</script><div class="bt_bb_css_post_grid"></div><link href="https://fonts.googleapis.com/css2?family=x:wght@300;400;500;700">',
);
( new JPP\CLI_Command() )->verify();
$success = json_decode( WP_CLI::$lines[0], true );
assert( true === $success['passed'] );

$GLOBALS['jpp_http_response']['body'] = '<div class="bt_bb_css_post_grid"></div>';
try {
	( new JPP\CLI_Command() )->verify();
	assert( false, 'Expected non-zero halt for failed checks.' );
} catch ( RuntimeException $error ) {
	assert( 1 === $error->getCode() );
}

$GLOBALS['jpp_http_response'] = array( 'status' => 503, 'body' => '' );
try {
	( new JPP\CLI_Command() )->verify();
	assert( false, 'Expected WP-CLI error for HTTP failure.' );
} catch ( RuntimeException $error ) {
	assert( 2 === $error->getCode() );
}
echo "WP-CLI verifier tests passed.\n";
