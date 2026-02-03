<?php
/**
 * Installer class - handles plugin activation and database setup
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installer class
 */
class Hamnaghsheh_Messenger_Installer {
    
    /**
     * Activate plugin
     */
    public static function activate() {
        self::create_tables();
        self::schedule_cleanup();
        
        // Set plugin version
        update_option('hamnaghsheh_messenger_version', HAMNAGHSHEH_MESSENGER_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        self::unschedule_cleanup();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Table 1: Messages
        $sql_messages = "CREATE TABLE {$prefix}hamnaghsheh_chat_messages (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NULL COMMENT 'NULL for system messages',
            message_type ENUM('text', 'system', 'file_activity') DEFAULT 'text',
            message TEXT NOT NULL,
            metadata TEXT NULL COMMENT 'Stores file refs, mentions, edit history (JSON)',
            parent_id BIGINT(20) UNSIGNED NULL COMMENT 'For threading (future)',
            edited_at DATETIME NULL,
            deleted_at DATETIME NULL COMMENT 'Soft delete',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_project_time (project_id, created_at),
            INDEX idx_user (user_id),
            INDEX idx_deleted (deleted_at)
        ) ENGINE=InnoDB $charset_collate;";
        
        // Table 2: Read status (CRITICAL for "seen by")
        $sql_reads = "CREATE TABLE {$prefix}hamnaghsheh_chat_reads (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_read (message_id, user_id),
            INDEX idx_user (user_id),
            INDEX idx_message (message_id)
        ) ENGINE=InnoDB $charset_collate;";
        
        // Table 3: Typing indicators
        $sql_typing = "CREATE TABLE {$prefix}hamnaghsheh_chat_typing (
            project_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            last_typed_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (project_id, user_id)
        ) ENGINE=InnoDB $charset_collate;";
        
        // Execute table creation
        dbDelta($sql_messages);
        dbDelta($sql_reads);
        dbDelta($sql_typing);
    }
    
    /**
     * Schedule cleanup cron job
     */
    private static function schedule_cleanup() {
        if (!wp_next_scheduled('hamnaghsheh_messenger_cleanup_typing')) {
            wp_schedule_event(time(), 'hourly', 'hamnaghsheh_messenger_cleanup_typing');
        }
    }
    
    /**
     * Unschedule cleanup cron job
     */
    private static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled('hamnaghsheh_messenger_cleanup_typing');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hamnaghsheh_messenger_cleanup_typing');
        }
    }
    
    /**
     * Cleanup old data (optional - called on uninstall)
     */
    public static function cleanup_tables() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        
        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}hamnaghsheh_chat_messages");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}hamnaghsheh_chat_reads");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}hamnaghsheh_chat_typing");
        
        // Delete options
        delete_option('hamnaghsheh_messenger_version');
    }
}
