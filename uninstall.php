<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 * This file should only be executed when the plugin is explicitly deleted.
 *
 * @package Hamnaghsheh_Massenger
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin tables
 * 
 * Note: This only runs on plugin deletion, not deactivation
 * This ensures data is preserved if plugin is temporarily deactivated
 */
function hamnaghsheh_massenger_uninstall_tables() {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'hamnaghsheh_chat_messages',
        $wpdb->prefix . 'hamnaghsheh_chat_read_status',
        $wpdb->prefix . 'hamnaghsheh_chat_metadata'
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}

/**
 * Delete plugin options
 */
function hamnaghsheh_massenger_uninstall_options() {
    delete_option('hamnaghsheh_massenger_version');
}

/**
 * Clear all plugin transients
 */
function hamnaghsheh_massenger_uninstall_transients() {
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_hamnaghsheh_chat_%' 
        OR option_name LIKE '_transient_timeout_hamnaghsheh_chat_%'"
    );
}

// Execute uninstall
hamnaghsheh_massenger_uninstall_tables();
hamnaghsheh_massenger_uninstall_options();
hamnaghsheh_massenger_uninstall_transients();
