<?php
/**
 * Plugin Name: CDNJS Script Loader
 * Plugin URI: https://github.com/rafael-minuesa/cdnjs-script-loader
 * Description: Intelligent CDN management for WordPress - Load JavaScript libraries from CDNJS with automatic fallback and performance monitoring
 * Version: 2.0.0
 * Author: Rafael Minuesa
 * Author URI: http://prowoos.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: cdnjs-script-loader
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.4
 *
 * @package CDNJS_Script_Loader
 * @version 2.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('CDNJS_SCRIPT_LOADER_VERSION', '2.0.0');

/**
 * Plugin base path
 */
define('CDNJS_SCRIPT_LOADER_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin base URL
 */
define('CDNJS_SCRIPT_LOADER_URL', plugin_dir_url(__FILE__));

// Include the admin page and script handler functionalities
require_once(CDNJS_SCRIPT_LOADER_PATH . 'admin-page.php');
require_once(CDNJS_SCRIPT_LOADER_PATH . 'script-handler.php');

/**
 * Load plugin CSS for admin
 */
function cdnjs_script_loader_admin_css($hook) {
    if ($hook !== 'settings_page_cdnjs-script-loader') {
        return;
    }
    wp_enqueue_style('cdnjs-admin-css', CDNJS_SCRIPT_LOADER_URL . 'admin-custom.css', array(), CDNJS_SCRIPT_LOADER_VERSION);
}
add_action('admin_enqueue_scripts', 'cdnjs_script_loader_admin_css');

/**
 * Activation hook - create necessary directories
 */
function cdnjs_script_loader_activate() {
    $upload_dir = wp_upload_dir();
    $fallback_dir = $upload_dir['basedir'] . '/cdnjs-fallbacks/';

    if (!file_exists($fallback_dir)) {
        wp_mkdir_p($fallback_dir);
    }
}
register_activation_hook(__FILE__, 'cdnjs_script_loader_activate');

/**
 * Add settings link on plugin page
 */
function cdnjs_script_loader_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=cdnjs-script-loader') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cdnjs_script_loader_settings_link');
