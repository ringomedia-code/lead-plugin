<?php

/**
 
 * @package RMFL
 
 */

/*
Plugin Name: RM Form Leads
Plugin URI: 
Description: Collect and manage form leads effortlessly, with support for routing leads from any number of business locations.
Version: 1.7.11
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
define('RMFL_PLUGIN_VERSION', '1.7.11');
// Ringo One's own lead intake endpoint: same contract as RingoLeads (same fields),
// but its own address and its own per-location key. Overridable with the
// `ringoone_url` option or the `rmfl_ringoone_url` filter.
if (!defined('RMFL_RINGOONE_URL')) define('RMFL_RINGOONE_URL', 'https://app.ringomedia.com/api/inbound/lead');

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

    // No "IF NOT EXISTS" here: dbDelta() parses the table name out of the query with
    // `CREATE TABLE ([^ ]*)`, so "IF NOT EXISTS" gets captured as the table name ("IF"),
    // which silently breaks its schema-diffing (it never detects/adds new columns like
    // extra_fields on an existing table). dbDelta is already safe to call on a table that
    // exists; it only issues ALTER statements for columns actually missing.
    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        api_name VARCHAR(255) NOT NULL,
        status VARCHAR(255) NOT NULL,
        customer_name VARCHAR(255),
        customer_phone VARCHAR(20),
        customer_email VARCHAR(255),
        message TEXT NOT NULL,
        extra_fields TEXT,
        response_body TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_api_response_table');

// Forced auto-updates (see below) mean sites go from an old version straight to a new one
// without deactivating/reactivating, so register_activation_hook alone won't add columns
// like extra_fields to an already-existing table. Re-run dbDelta once per version bump.
add_action('plugins_loaded', function() {
    if (get_option('rmfl_db_version') !== RMFL_PLUGIN_VERSION) {
        create_api_response_table();
        update_option('rmfl_db_version', RMFL_PLUGIN_VERSION);
    }
});

add_action('admin_init', function() {
    delete_site_transient('update_plugins');
    wp_clean_plugins_cache();
});