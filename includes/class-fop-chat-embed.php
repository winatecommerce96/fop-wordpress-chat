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
 * Enqueues the hosted script (async, footer) and adds data-site-key to its tag when a key is saved.
 * WordPress 6.4+ renders it as <script src="..." id="fop-chat-js" async data-wp-strategy="async" data-site-key="...">.
 */
class FOP_Chat_Embed {

	const HANDLE = 'fop-chat';

	/**
	 * Hook into the front end.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'wp_script_attributes', array( __CLASS__, 'filter_attributes' ) );
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
	 * Enqueue the hosted script (no version query arg, async, in the footer). Nothing without a key.
	 */
	public static function enqueue() {
		if ( '' === self::key() ) {
			return;
		}
		wp_enqueue_script( // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the v1 URL is the version.
			self::HANDLE,
			FOP_CHAT_SCRIPT_URL,
			array(),
			null,
			array(
				'in_footer' => true,
				'strategy'  => 'async',
			)
		);
	}

	/**
	 * Add data-site-key to our script tag only. WordPress escapes attribute values.
	 *
	 * @param array $attributes Script tag attributes.
	 * @return array
	 */
	public static function filter_attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! isset( $attributes['id'] ) || self::HANDLE . '-js' !== $attributes['id'] ) {
			return $attributes;
		}
		$key = self::key();
		if ( '' !== $key ) {
			$attributes['data-site-key'] = $key;
		}
		return $attributes;
	}
}
