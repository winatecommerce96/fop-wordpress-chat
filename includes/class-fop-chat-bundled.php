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

	const MAX_BYTES = 2048;
	const KEY_LINE  = "/^return array\\( 'site_key' => '(fop_pk_[A-Za-z0-9]{32})' \\);$/m";   // LF files only, as generated

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
	 * Hook callback: import from the plugin's own fop-config.php. Never on multisite: a network shares one copy of
	 * the plugin files, so one site's download would hand its key to every site that runs the plugin (activated
	 * network-wide or per site; admin_init fires on each subsite, even on logged-out admin-ajax requests). Network
	 * sites paste their own key.
	 *
	 * @param bool $network_wide True on a network activation (ignored: multisite never imports).
	 */
	public static function on_hook( $network_wide = false ) {
		unset( $network_wide );
		if ( ! is_file( FOP_CHAT_BUNDLED_CONFIG ) || is_multisite() ) {
			return;                                         // a generic install, or a network: no lookup at all
		}
		self::import( FOP_CHAT_BUNDLED_CONFIG );
	}

	/**
	 * The site key a bundled config file holds, or '' when it is missing or not in the dashboard's exact format.
	 *
	 * The file is READ, never executed: no include, so a truncated or hand-edited file can never fatal wp-admin, print
	 * output or touch output buffers. It must be at most MAX_BYTES, start with "<?php" and a newline (no BOM), and
	 * hold exactly one `return array( 'site_key' => 'fop_pk_...' );` line. It stays valid PHP (with the ABSPATH guard),
	 * so a direct request for it prints nothing.
	 *
	 * @param string $path Path of fop-config.php (always the plugin's own; never from a request).
	 * @return string
	 */
	private static function bundled_key( $path ) {
		if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		// Capped read (a file that grows after the checks is still bounded); @: a file removed in between is no key,
		// never a warning printed into wp-admin.
		$text = @file_get_contents( $path, false, null, 0, self::MAX_BYTES + 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.PHP.NoSilencedErrors.Discouraged -- a local file in the plugin's own folder.
		if ( ! is_string( $text ) || strlen( $text ) > self::MAX_BYTES || 0 !== strpos( $text, "<?php\n" ) || false !== strpos( $text, "\r" ) ) {
			return '';
		}
		$found = preg_match_all( self::KEY_LINE, $text, $m );
		if ( 1 !== $found ) {
			return '';
		}
		return FOP_Chat_Settings::is_valid_key( $m[1][0] ) ? $m[1][0] : '';
	}

	/**
	 * Import once. Writes nothing when there is no valid bundled key, so a later per-site download still imports.
	 *
	 * @param string $path Path of fop-config.php.
	 */
	public static function import( $path ) {
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return;                                         // a generic install: no lookup at all
		}
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
		if ( 'imported' !== get_option( FOP_CHAT_BUNDLED_OPTION, '' ) ) {
			return false;
		}
		$key = get_option( FOP_CHAT_OPTION, '' );
		return FOP_Chat_Settings::is_valid_key( $key ) && self::bundled_key( FOP_CHAT_BUNDLED_CONFIG ) === $key;
	}
}
