<?php
/**
 * Plugin Name: ZuidWest Staart
 * Description: Toont leestips of podcast-promo onder artikelen voor betere recirculatie en engagement
 * Version: 0.4.1
 * Author: Streekomroep ZuidWest
 * Author URI: https://www.zuidwesttv.nl
 * Plugin URI: https://github.com/oszuidwest/zw-staart
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ZuidWest_Staart
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

// Plugin version and metadata.
if ( ! defined( 'ZW_STAART_VERSION' ) ) {
	define( 'ZW_STAART_VERSION', '0.4.1' );
}
if ( ! defined( 'ZW_STAART_PLUGIN_FILE' ) ) {
	define( 'ZW_STAART_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'ZW_STAART_PLUGIN_DIR' ) ) {
	define( 'ZW_STAART_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ZW_STAART_PLUGIN_URL' ) ) {
	define( 'ZW_STAART_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin configuration constants.
if ( ! defined( 'ZW_STAART_DEFAULT_DAYS' ) ) {
	define( 'ZW_STAART_DEFAULT_DAYS', 5 );           // Default lookback days for Plausible.
}
if ( ! defined( 'ZW_STAART_COOKIE_EXPIRY_DAYS' ) ) {
	define( 'ZW_STAART_COOKIE_EXPIRY_DAYS', 7 );     // Cookie expiration in days.
}
if ( ! defined( 'ZW_STAART_MAX_TOP_ARTICLES' ) ) {
	define( 'ZW_STAART_MAX_TOP_ARTICLES', 25 );      // Maximum articles to cache.
}
if ( ! defined( 'ZW_STAART_MIN_POSTS_DISPLAY' ) ) {
	define( 'ZW_STAART_MIN_POSTS_DISPLAY', 5 );      // Minimum posts to show list.
}
if ( ! defined( 'ZW_STAART_API_TIMEOUT' ) ) {
	define( 'ZW_STAART_API_TIMEOUT', 30 );           // HTTP timeout in seconds.
}
if ( ! defined( 'ZW_STAART_API_PAGINATION_LIMIT' ) ) {
	define( 'ZW_STAART_API_PAGINATION_LIMIT', 100 ); // Plausible API pagination.
}

require_once ZW_STAART_PLUGIN_DIR . 'src/admin.php';
require_once ZW_STAART_PLUGIN_DIR . 'src/front-end.php';
require_once ZW_STAART_PLUGIN_DIR . 'src/tracker.php';

register_activation_hook( file: ZW_STAART_PLUGIN_FILE, callback: zw_staart_activate( ... ) );
register_deactivation_hook( file: ZW_STAART_PLUGIN_FILE, callback: zw_staart_deactivate( ... ) );
