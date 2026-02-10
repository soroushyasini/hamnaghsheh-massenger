<?php
/**
 * Fired during plugin deactivation
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Deactivator {
    
    /**
     * Deactivate the plugin
     * 
     * Note: We don't delete tables on deactivation to preserve data
     * Tables are only deleted if user explicitly uninstalls the plugin
     */
    public static function deactivate() {
        // Clear any scheduled events if we add cron jobs later
        wp_clear_scheduled_hook('hmchat_cleanup_old_messages');
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
}
