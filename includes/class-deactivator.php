<?php
/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Deactivator {

    /**
     * Deactivate the plugin
     * 
     * Note: We don't drop tables on deactivation to preserve data
     * Tables should only be dropped on plugin uninstall
     */
    public static function deactivate() {
        // Clear any cached data
        self::clear_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear all plugin transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete all transients that start with hamnaghsheh_chat_
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_hamnaghsheh_chat_%' 
            OR option_name LIKE '_transient_timeout_hamnaghsheh_chat_%'"
        );
    }
}
