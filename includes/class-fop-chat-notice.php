<?php
/**
 * One-time "clear your cache" notice after activation (PM 2026-11-21).
 *
 * A caching plugin (WP Rocket, W3 Total Cache, LiteSpeed Cache, ...) keeps serving pages it cached before the chat
 * was installed, and a logged-in admin usually bypasses that cache, so the chat looks "up" to them while visitors
 * don't see it. On activation a flag is set; the next admin screen shows one dismissible notice to someone who can
 * manage options, then clears the flag. Plain escaped text, no links, no remote calls.
 *
 * @package FOP_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FOP_Chat_Notice {

	const OPTION = FOP_CHAT_NOTICE_OPTION;

	public static function init( $plugin_file ) {
		register_activation_hook( $plugin_file, array( __CLASS__, 'on_activate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	public static function on_activate( $network_wide = false ) {
		update_option( FOP_CHAT_NOTICE_OPTION, '1', false );
	}

	public static function text() {
		return __( 'Front Office Pilot Chat is on. Using a caching plugin (WP Rocket, W3 Total Cache, LiteSpeed Cache...)? Clear its cache now so visitors see the chat. You see your site uncached while logged in, so check it in a private window.', 'front-office-pilot-chat' );
	}

	public static function render() {
		if ( '1' !== get_option( self::OPTION, '' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_option( self::OPTION );
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( self::text() ) . '</p></div>';
	}
}
