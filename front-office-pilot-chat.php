<?php
/**
 * Plugin Name:       Front Office Pilot Chat
 * Plugin URI:        https://frontofficepilot.com/
 * Description:       Adds the Front Office Pilot chat widget to your site. Paste your site key under Settings, Front Office Pilot Chat.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Front Office Pilot
 * Author URI:        https://frontofficepilot.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       front-office-pilot-chat
 *
 * @package FOP_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define FOP_CHAT_SCRIPT_URL in wp-config.php to test against another deployment.
// The status check derives its config URL from this script's origin, so the override moves both.
if ( ! defined( 'FOP_CHAT_SCRIPT_URL' ) ) {
	define( 'FOP_CHAT_SCRIPT_URL', 'https://chat.frontofficepilot.com/widget/v1/fop-chat.js' );
}
define( 'FOP_CHAT_OPTION', 'fop_chat_site_key' );
define( 'FOP_CHAT_DASHBOARD_URL', 'https://app.frontofficepilot.com/chat-widget' );

require_once __DIR__ . '/includes/class-fop-chat-settings.php';
require_once __DIR__ . '/includes/class-fop-chat-embed.php';

FOP_Chat_Settings::init();
FOP_Chat_Embed::init();
