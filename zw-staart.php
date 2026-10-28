<?php
/**
 * Plugin Name: ZuidWest Staart
 * Description: Toont leestips onder artikelen voor betere recirculatie en engagement
 * Version: 0.5.7
 * Author: Streekomroep ZuidWest
 * Author URI: https://www.zuidwesttv.nl
 * Plugin URI: https://github.com/oszuidwest/zw-staart
 * Requires at least: 7.0
 * Tested up to: 7.0
 * Requires PHP: 8.3
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ZuidWest_Staart
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! defined( 'ZW_STAART_VERSION' ) ) {
	define( 'ZW_STAART_VERSION', '0.5.7' );
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

if ( ! defined( 'ZW_STAART_DEFAULT_DAYS' ) ) {
	define( 'ZW_STAART_DEFAULT_DAYS', 5 );
}
if ( ! defined( 'ZW_STAART_DEFAULT_HEADING' ) ) {
	define( 'ZW_STAART_DEFAULT_HEADING', 'Leestips voor jou' );
}
if ( ! defined( 'ZW_STAART_COOKIE_EXPIRY_DAYS' ) ) {
	define( 'ZW_STAART_COOKIE_EXPIRY_DAYS', 7 );
}
if ( ! defined( 'ZW_STAART_MAX_TOP_ARTICLES' ) ) {
	define( 'ZW_STAART_MAX_TOP_ARTICLES', 25 );
}
if ( ! defined( 'ZW_STAART_MAX_VISITED_POSTS' ) ) {
	define( 'ZW_STAART_MAX_VISITED_POSTS', 100 );
}
if ( ! defined( 'ZW_STAART_MIN_POSTS_DISPLAY' ) ) {
	define( 'ZW_STAART_MIN_POSTS_DISPLAY', 5 );
}
if ( ! defined( 'ZW_STAART_API_TIMEOUT' ) ) {
	define( 'ZW_STAART_API_TIMEOUT', 30 ); // Seconds.
}
if ( ! defined( 'ZW_STAART_API_PAGINATION_LIMIT' ) ) {
	define( 'ZW_STAART_API_PAGINATION_LIMIT', 100 );
}

require_once ZW_STAART_PLUGIN_DIR . 'src/admin.php';
require_once ZW_STAART_PLUGIN_DIR . 'src/front-end.php';
require_once ZW_STAART_PLUGIN_DIR . 'src/next-article.php';
require_once ZW_STAART_PLUGIN_DIR . 'src/tracker.php';

register_activation_hook( file: ZW_STAART_PLUGIN_FILE, callback: zw_staart_activate( ... ) );
register_deactivation_hook( file: ZW_STAART_PLUGIN_FILE, callback: zw_staart_deactivate( ... ) );
