<?php
/**
 * PHPStan Bootstrap for WordPress
 *
 * Provides WordPress globals and functions that PHPStan needs.
 *
 * @phpstan-bootstrap
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/wp-admin/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

// Mock WordPress globals
$GLOBALS['wpdb'] = null;
$GLOBALS['wp_query'] = null;
$GLOBALS['wp'] = null;
$GLOBALS['pagenow'] = 'wp-admin/admin.php';
$GLOBALS['current_screen'] = null;
$GLOBALS['post'] = null;
$GLOBALS['wp_version'] = '6.7';

// Mock common functions if not available
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_x' ) ) {
	function esc_html_x( $text, $context, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'add_allowed_options' ) ) {
	function add_allowed_options( $options ) {
		return true;
	}
}

if ( ! function_exists( 'add_option_whitelist' ) ) {
	function add_option_whitelist( $options ) {
		return true;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ) {
		return true;
	}
}

if ( ! function_exists( 'current_filter' ) ) {
	function current_filter() {
		return '';
	}
}

// Mock WordPress classes
class WP_User {
	public $ID = 1;
}

class WP_Post {
	public $ID = 1;
	public $post_title = 'Test Post';
	public $post_status = 'publish';
	public $post_content = '';
	public $post_type = 'post';
}

class WP_REST_Request {
	public function get_json_params() {
		return array();
	}
}

class WP_REST_Response {
	public function __construct( $data, $status = 200 ) {
		$this->data = $data;
		$this->status = $status;
	}
}

class WP_Error {
	public $code = '';
	public $message = '';
	public $data = array();

	public function __construct( $code, $message, $data = array() ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
}

class WP_REST_Server {
	public const READABLE = 'GET';
	public const EDITABLE = 'POST';
}

// Define WordPress constants
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}