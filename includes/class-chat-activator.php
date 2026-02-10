<?php
/**
 * Fired during plugin activation
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create chat_messages table
        $sql_messages = "CREATE TABLE IF NOT EXISTS {$table_prefix}chat_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            message text NOT NULL,
            message_type enum('text','system') DEFAULT 'text',
            is_edited tinyint(1) DEFAULT 0,
            edited_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project_id_desc (project_id, id DESC),
            KEY idx_project_created (project_id, created_at),
            KEY idx_user_messages (user_id, created_at)
        ) ENGINE=InnoDB {$charset_collate};";
        
        dbDelta($sql_messages);
        
        // Create chat_seen table
        $sql_seen = "CREATE TABLE IF NOT EXISTS {$table_prefix}chat_seen (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            seen_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_message_user (message_id, user_id),
            KEY idx_user_seen (user_id, seen_at)
        ) ENGINE=InnoDB {$charset_collate};";
        
        dbDelta($sql_seen);
        
        // Store plugin version
        add_option('hmchat_version', HMCHAT_VERSION);
        add_option('hmchat_db_version', '1.0');
        
        // Create options for tracking last processed file log
        add_option('hmchat_last_processed_file_log', 0);
    }
}
