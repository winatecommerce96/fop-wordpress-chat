<?php
/**
 * Uninstall: delete the plugin's two options (the site key and the one-time import marker), on every site of a network.
 *
 * @package FOP_Chat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $fop_chat_site_id ) {
		switch_to_blog( (int) $fop_chat_site_id );
		delete_option( 'fop_chat_site_key' );
		delete_option( 'fop_chat_bundled_import' );
		restore_current_blog();
	}
} else {
	delete_option( 'fop_chat_site_key' );
	delete_option( 'fop_chat_bundled_import' );
}
