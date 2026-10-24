<?php
/**
 * Settings page: one field (the site key) and a status check on page load.
 *
 * @package FOP_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings → Front Office Pilot Chat.
 */
class FOP_Chat_Settings {

	const PAGE_SLUG = 'fop-chat';
	const GROUP     = 'fop_chat';
	const SECTION   = 'fop_chat_main';
	const KEY_REGEX = '/^fop_pk_[A-Za-z0-9]{32}$/D';

	/**
	 * Result of the status check for this request, or null when no check ran.
	 *
	 * @var array|null
	 */
	public static $status = null;

	/**
	 * Hook into admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Whether a value is a well-formed site key.
	 *
	 * @param mixed $key Candidate.
	 * @return bool
	 */
	public static function is_valid_key( $key ) {
		return is_string( $key ) && 1 === preg_match( self::KEY_REGEX, $key );
	}

	/**
	 * Add the page under Settings. The status check is tied to this page's load hook only.
	 */
	public static function add_page() {
		$hook = add_options_page(
			__( 'Front Office Pilot Chat', 'front-office-pilot-chat' ),
			__( 'Front Office Pilot Chat', 'front-office-pilot-chat' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'on_load' ) );
		}
	}

	/**
	 * Register the single setting, its section and field.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			FOP_CHAT_OPTION,
			array(
				'type'              => 'string',
				'description'       => __( 'Front Office Pilot site key.', 'front-office-pilot-chat' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize_key' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);
		add_settings_section( self::SECTION, '', '__return_false', self::PAGE_SLUG );
		add_settings_field(
			FOP_CHAT_OPTION,
			__( 'Site key', 'front-office-pilot-chat' ),
			array( __CLASS__, 'render_field' ),
			self::PAGE_SLUG,
			self::SECTION,
			array( 'label_for' => FOP_CHAT_OPTION )
		);
	}

	/**
	 * Sanitize callback: trim, accept a valid key or empty; otherwise keep the previous value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_key( $value ) {
		$value = is_string( $value ) ? trim( $value ) : null;
		if ( '' === $value ) {
			return '';
		}
		if ( self::is_valid_key( $value ) ) {
			return $value;
		}
		add_settings_error(
			FOP_CHAT_OPTION,
			'fop_chat_invalid_key',
			__( 'That is not a valid site key. It starts with fop_pk_ followed by 32 letters and numbers. Your previous key was kept.', 'front-office-pilot-chat' ),
			'error'
		);
		$previous = get_option( FOP_CHAT_OPTION, '' );
		return self::is_valid_key( $previous ) ? $previous : '';
	}

	/**
	 * Runs on load-settings_page_fop-chat only: check whether the widget is live for this site.
	 */
	public static function on_load() {
		$key = get_option( FOP_CHAT_OPTION, '' );
		if ( ! self::is_valid_key( $key ) ) {
			return;
		}
		self::$status = self::check_status( $key );
	}

	/**
	 * Scheme://host[:port] of a URL, lower-cased host, or '' when it cannot be parsed.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function origin_of( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
		return $origin;
	}

	/**
	 * This site's origin, from home_url().
	 *
	 * @return string
	 */
	public static function site_origin() {
		return self::origin_of( home_url() );
	}

	/**
	 * The widget config endpoint, on the same origin as the script.
	 *
	 * @param string|null $script_url Script URL; defaults to FOP_CHAT_SCRIPT_URL.
	 * @return string
	 */
	public static function config_url( $script_url = null ) {
		if ( null === $script_url ) {
			$script_url = FOP_CHAT_SCRIPT_URL;
		}
		return self::origin_of( $script_url ) . '/widget/v1/config';
	}

	/**
	 * Ask the config endpoint whether this site's origin is enabled for the key.
	 *
	 * @param string $key Site key.
	 * @return array{live: bool, origin: string}
	 */
	public static function check_status( $key ) {
		$origin   = self::site_origin();
		$response = wp_remote_get(
			self::config_url() . '?k=' . rawurlencode( $key ),
			array(
				'timeout' => 3,
				'headers' => array( 'Origin' => $origin ),
			)
		);
		$live = false;
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$live = is_array( $data ) && isset( $data['enabled'] ) && true === $data['enabled'];
		}
		return array(
			'live'   => $live,
			'origin' => $origin,
		);
	}

	/**
	 * Print the status notice.
	 *
	 * @param array $status Result of check_status().
	 */
	public static function render_status( $status ) {
		if ( ! empty( $status['live'] ) ) {
			/* translators: %s: site origin, e.g. https://shop.example.com */
			$message = sprintf( __( 'Chat is live on %s.', 'front-office-pilot-chat' ), $status['origin'] );
			echo '<div class="notice notice-success inline"><p>' . esc_html( $message ) . '</p></div>';
			return;
		}
		/* translators: %s: site origin, e.g. https://shop.example.com */
		$message = sprintf( __( 'Add %s in your Front Office Pilot dashboard under Chat widget.', 'front-office-pilot-chat' ), $status['origin'] );
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $message ) . ' <a href="' . esc_url( FOP_CHAT_DASHBOARD_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Chat widget settings', 'front-office-pilot-chat' ) . '</a></p></div>';
	}

	/**
	 * Render the site key input.
	 */
	public static function render_field() {
		$value = get_option( FOP_CHAT_OPTION, '' );
		printf(
			'<input type="text" class="regular-text code" id="%1$s" name="%1$s" value="%2$s" placeholder="fop_pk_..." autocomplete="off" spellcheck="false" />',
			esc_attr( FOP_CHAT_OPTION ),
			esc_attr( is_string( $value ) ? $value : '' )
		);
		echo '<p class="description">' . esc_html__( 'Find your site key in your Front Office Pilot dashboard under Chat widget. Leave empty to remove the chat widget.', 'front-office-pilot-chat' ) . '</p>';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'front-office-pilot-chat' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( __( 'Front Office Pilot Chat', 'front-office-pilot-chat' ) ) . '</h1>';
		if ( null !== self::$status ) {
			self::render_status( self::$status );
		}
		echo '<form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		echo '<p>' . esc_html__( 'The chat widget only appears on sites you have added in your dashboard.', 'front-office-pilot-chat' ) . ' <a href="' . esc_url( FOP_CHAT_DASHBOARD_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Chat widget settings', 'front-office-pilot-chat' ) . '</a></p>';
		echo '</div>';
	}
}
