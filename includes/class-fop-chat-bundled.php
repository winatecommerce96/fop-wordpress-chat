<?php
/**
 * The site key bundled in a per-site download from the Front Office Pilot dashboard.
 *
 * The dashboard's "Download for WordPress" adds one file, fop-config.php, holding that site's public site key. It is
 * imported ONCE into the plugin's option, so a later update from the generic zip (which has no fop-config.php) keeps
 * the key, and a key the site owner clears on the settings page stays cleared. A key already saved always wins.
 *
 * @package FOP_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time import of fop-config.php into the site key option.
 */
class FOP_Chat_Bundled {

	/**
	 * Import on activation (a fresh install) and on admin_init (an upload that replaces an existing copy).
	 *
	 * @param string $plugin_file The main plugin file.
	 */
	public static function init( $plugin_file ) {
		register_activation_hook( $plugin_file, array( __CLASS__, 'on_hook' ) );
		add_action( 'admin_init', array( __CLASS__, 'on_hook' ) );
	}

	/**
	 * Hook callback: import from the plugin's own fop-config.php. Ignores hook arguments.
	 */
	public static function on_hook() {
		self::import( FOP_CHAT_BUNDLED_CONFIG );
	}

	/**
	 * The site key a bundled config file holds, or '' when it is missing or malformed.
	 *
	 * @param string $path Path of fop-config.php (always the plugin's own; never from a request).
	 * @return string
	 */
	private static function bundled_key( $path ) {
		if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$config = include $path;
		if ( ! is_array( $config ) || ! isset( $config['site_key'] ) ) {
			return '';
		}
		return FOP_Chat_Settings::is_valid_key( $config['site_key'] ) ? $config['site_key'] : '';
	}

	/**
	 * Import once. Writes nothing when there is no valid bundled key, so a later per-site download still imports.
	 *
	 * @param string $path Path of fop-config.php.
	 */
	public static function import( $path ) {
		if ( '' !== (string) get_option( FOP_CHAT_BUNDLED_OPTION, '' ) ) {
			return;                                         // already decided once: never again
		}
		$bundled = self::bundled_key( $path );
		if ( '' === $bundled ) {
			return;
		}
		if ( FOP_Chat_Settings::is_valid_key( get_option( FOP_CHAT_OPTION, '' ) ) ) {
			update_option( FOP_CHAT_BUNDLED_OPTION, 'kept' );            // the saved key wins
			return;
		}
		update_option( FOP_CHAT_OPTION, $bundled );
		update_option( FOP_CHAT_BUNDLED_OPTION, 'imported' );
	}

	/**
	 * Whether the key in the option now is the one imported from a download.
	 *
	 * @return bool
	 */
	public static function key_was_imported() {
		return 'imported' === get_option( FOP_CHAT_BUNDLED_OPTION, '' )
			&& FOP_Chat_Settings::is_valid_key( get_option( FOP_CHAT_OPTION, '' ) );
	}
}
