<?php
/**
 * Plugin Name: ZuidWest Staart
 * Description: Experiment om bezoekers meer te laten recirculeren met top 5 posts onder ieder artikel
 * Version: 0.4.0
 * Author: Raymon Mens
 */

defined('ABSPATH') or die('No script kiddies please!');

// Include the admin and front-end functionalities
require_once plugin_dir_path(__FILE__) . 'src/admin.php';
require_once plugin_dir_path(__FILE__) . 'src/front-end.php';
require_once plugin_dir_path(__FILE__) . 'src/tracker.php';

register_activation_hook(__FILE__, 'zw_staart_activate');
register_deactivation_hook(__FILE__, 'zw_staart_deactivate');
