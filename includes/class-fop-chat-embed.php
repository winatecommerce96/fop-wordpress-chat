<?php
/**
 * Front end: enqueue the hosted widget script with the site key.
 *
 * @package FOP_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints <script src="..." data-site-key="..." async></script> on the front end when a key is saved.
 */
class FOP_Chat_Embed {

	const HANDLE = 'fop-chat';

	/**
	 * Hook into the front end.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_tag' ), 10, 3 );
	}

	/**
	 * The saved key if it is well formed, otherwise ''.
	 *
	 * @return string
	 */
	private static function key() {
		$key = get_option( FOP_CHAT_OPTION, '' );
		return FOP_Chat_Settings::is_valid_key( $key ) ? $key : '';
	}

	/**
	 * Enqueue the hosted script (no version query arg, in the footer). Nothing without a key.
	 */
	public static function enqueue() {
		if ( '' === self::key() ) {
			return;
		}
		wp_enqueue_script( self::HANDLE, FOP_CHAT_SCRIPT_URL, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the v1 URL is the version.
	}

	/**
	 * Rewrite our tag into the contract shape. Other handles are untouched.
	 *
	 * @param string $tag    Tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script URL.
	 * @return string
	 */
	public static function filter_tag( $tag, $handle, $src ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		$key = self::key();
		if ( '' === $key ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- this is the enqueued script's own tag.
		return '<script src="' . esc_url( $src ) . '" data-site-key="' . esc_attr( $key ) . '" async></script>' . "\n";
	}
}
