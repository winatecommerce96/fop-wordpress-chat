<?php
/**
 * Minimal WordPress stubs so the plugin can be unit-tested without a WordPress install.
 *
 * Every stub records what it was called with in FOP_Test_State so tests can assert on it.
 *
 * @package FOP_Chat
 */

// phpcs:disable

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

class FOP_Test_State {
	/** @var array<int, array<string, mixed>> Options per blog id. */
	public static $options = array();
	public static $blog_id = 1;
	public static $actions = array();
	public static $filters = array();
	public static $settings = array();
	public static $settings_errors = array();
	public static $settings_fields_calls = array();
	public static $menu_pages = array();
	public static $enqueued = array();
	public static $remote_calls = array();
	public static $remote_response = null;
	public static $esc_calls = array();
	public static $can = true;
	public static $home_url = 'https://shop.example.com';
	public static $multisite = false;
	public static $sites = array( 1 );

	public static function reset() {
		self::$options               = array();
		self::$blog_id               = 1;
		self::$actions               = array();
		self::$filters               = array();
		self::$settings              = array();
		self::$settings_errors       = array();
		self::$settings_fields_calls = array();
		self::$menu_pages            = array();
		self::$enqueued              = array();
		self::$remote_calls          = array();
		self::$remote_response       = null;
		self::$esc_calls             = array( 'esc_html' => array(), 'esc_attr' => array(), 'esc_url' => array() );
		self::$can                   = true;
		self::$home_url              = 'https://shop.example.com';
		self::$multisite             = false;
		self::$sites                 = array( 1 );
	}
}

class WP_Error {
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}
}

class FOP_Test_Die extends Exception {}

// Hooks.
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	FOP_Test_State::$actions[ $hook ][] = $callback;
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	FOP_Test_State::$filters[ $hook ][] = array( $callback, $priority, $accepted_args );
	return true;
}

// Options.
function get_option( $name, $default = false ) {
	$blog = FOP_Test_State::$blog_id;
	return array_key_exists( $name, FOP_Test_State::$options[ $blog ] ?? array() ) ? FOP_Test_State::$options[ $blog ][ $name ] : $default;
}
function delete_option( $name ) {
	unset( FOP_Test_State::$options[ FOP_Test_State::$blog_id ][ $name ] );
	return true;
}

// Multisite.
function is_multisite() {
	return FOP_Test_State::$multisite;
}
function get_sites( $args = array() ) {
	return FOP_Test_State::$sites;
}
function switch_to_blog( $id ) {
	FOP_Test_State::$blog_id = (int) $id;
	return true;
}
function restore_current_blog() {
	FOP_Test_State::$blog_id = 1;
	return true;
}

// Settings API.
function register_setting( $group, $name, $args = array() ) {
	FOP_Test_State::$settings[ $name ] = array( 'group' => $group, 'args' => $args );
}
function add_settings_section( $id, $title, $callback, $page ) {}
function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {}
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	FOP_Test_State::$settings_errors[] = compact( 'setting', 'code', 'message', 'type' );
}
function settings_fields( $group ) {
	FOP_Test_State::$settings_fields_calls[] = $group;
	echo '<!--settings_fields:' . $group . '-->';
}
function do_settings_sections( $page ) {
	echo '<!--do_settings_sections:' . $page . '-->';
}
function submit_button() {
	echo '<!--submit_button-->';
}
function add_options_page( $page_title, $menu_title, $capability, $slug, $callback ) {
	FOP_Test_State::$menu_pages[] = compact( 'page_title', 'menu_title', 'capability', 'slug', 'callback' );
	return 'settings_page_' . $slug;
}
function current_user_can( $cap ) {
	return FOP_Test_State::$can && 'manage_options' === $cap;
}
function wp_die( $message = '' ) {
	throw new FOP_Test_Die( (string) $message );
}

// i18n (identity).
function __( $text, $domain = 'default' ) {
	return $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
}
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( $text );
}

// Escaping spies (escape for real, and record the input).
function esc_html( $text ) {
	FOP_Test_State::$esc_calls['esc_html'][] = $text;
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	FOP_Test_State::$esc_calls['esc_attr'][] = $text;
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	FOP_Test_State::$esc_calls['esc_url'][] = $url;
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

// URLs.
function home_url( $path = '' ) {
	return FOP_Test_State::$home_url . $path;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

// Scripts.
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
	FOP_Test_State::$enqueued[ $handle ] = compact( 'handle', 'src', 'deps', 'ver', 'args' );
}

// HTTP.
function wp_remote_get( $url, $args = array() ) {
	FOP_Test_State::$remote_calls[] = array( 'url' => $url, 'args' => $args );
	return FOP_Test_State::$remote_response;
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : '';
}
function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
}

FOP_Test_State::reset();

require_once dirname( __DIR__ ) . '/front-office-pilot-chat.php';
