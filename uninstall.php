<?php
/**
 * Uninstall script
 * Cleans up all plugin data when uninstalled
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly or not during uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load installer class
require_once plugin_dir_path(__FILE__) . 'includes/class-installer.php';

// Check if user wants to keep data
$keep_data = get_option('hamnaghsheh_messenger_keep_data', false);

if (!$keep_data) {
    // Cleanup all tables and options
    Hamnaghsheh_Messenger_Installer::cleanup_tables();
    
    // Delete uploaded export files
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/hamnaghsheh-messenger/';
    
    if (is_dir($export_dir)) {
        $files = glob($export_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($export_dir);
    }
    
    // Clear all transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_hamnaghsheh_unread_%' 
        OR option_name LIKE '_transient_timeout_hamnaghsheh_unread_%'"
    );
}
