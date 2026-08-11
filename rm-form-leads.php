<?php

/**
 
 * @package RMFL
 
 */

/*
Plugin Name: RM Form Leads
Plugin URI: 
Description: Collect and manage form leads effortlessly, with support for routing leads from any number of business locations.
Version: 1.6.3
Author: Ringo Media
Author URI: https://ringomedia.com
License: GPLv2 or later
Text Domain: rm-form-leads
*/


define('RMFL_WP', __FILE__);
if (!defined('RMFL_PLUGIN_PATH')) define('RMFL_PLUGIN_PATH', plugin_dir_path(__FILE__));
if (!defined('RMFL_PLUGIN_URI')) define('RMFL_PLUGIN_URI', plugins_url('/', __FILE__));
if (!defined('RMFL_PLUGIN_INC')) define('RMFL_PLUGIN_INC', RMFL_PLUGIN_PATH . 'includes/');
if (!defined('RMFL_PLUGIN_TEMP')) define('RMFL_PLUGIN_TEMP', RMFL_PLUGIN_PATH . 'templates/');
define('RMFL_PLUGIN_VERSION', '1.6.3');

require_once(RMFL_PLUGIN_INC . 'updater.php');
if (!class_exists('RMFL')) {
    include_once dirname(__FILE__) . '/includes/class-rm-form-leads.php';
}

function RMFL()
{
    return RMFL::instance();
}
$GLOBALS['RMFL'] = RMFL();

// Add custom settings link
function rmfl_settings_link($links) {
    $nonce = wp_create_nonce('rmfl-settings-nonce');
    $settings_link = '<a href="admin.php?page=rm-form-leads&_wpnonce=' . $nonce . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'rmfl_settings_link');

// Create database table for API response history
function create_api_response_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'api_response_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        api_name VARCHAR(255) NOT NULL,
        status VARCHAR(255) NOT NULL,
        customer_name VARCHAR(255),
        customer_phone VARCHAR(20),
        customer_email VARCHAR(255),
        message TEXT NOT NULL,
        response_body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_api_response_table');

add_action('admin_init', function() {
    delete_site_transient('update_plugins');
    wp_clean_plugins_cache();
});