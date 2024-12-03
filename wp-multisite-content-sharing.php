<?php
/**
 * Plugin Name:         WP Multisite Content Sharing
 * Plugin URI:          https://github.com/9ete/wp-multisite-content-sharing
 * GitHub Plugin URI:   9ete/wp-multisite-content-sharing
 * Description:         Add custom post types on the fly via WP post type functionality
 * Author:              9ete
 * Author URI:          9ete.com
 * Text Domain:         wp-custom-multisite-content-sharing
 * Domain Path:         /languages
 * Version:             0.0.3
 *
 * @package         WP_Multisite_Content_Sharing
 */

// Define constants
define('WP_MULTISITE_CONTENT_SHARING_VERSION', '0.0.3');
define('WP_MULTISITE_CONTENT_SHARING_PATH', plugin_dir_path(__FILE__));
define('WP_MULTISITE_CONTENT_SHARING_URL', plugin_dir_url(__FILE__));

// Autoload classes
require_once WP_MULTISITE_CONTENT_SHARING_PATH . 'includes/class-wp-multisite-content-sharing-admin.php';
require_once WP_MULTISITE_CONTENT_SHARING_PATH . 'includes/class-wp-multisite-content-sharing-importer.php';
require_once WP_MULTISITE_CONTENT_SHARING_PATH . 'includes/cron.php';

// Initialize the plugin
add_action('plugins_loaded', function () {
    new WP_Multisite_Content_Sharing_Admin();
    new WP_Multisite_Content_Sharing_Importer();
});