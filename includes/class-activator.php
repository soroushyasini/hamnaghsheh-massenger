<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation tasks including:
 * - Creating database tables with proper indexes
 * - Backfilling historical file logs
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::backfill_file_logs();
        
        // Set plugin version
        update_option('hamnaghsheh_massenger_version', HAMNAGHSHEH_MASSENGER_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables with proper indexes
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table 1: Chat Messages
        $table_messages = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            message_type ENUM('user', 'system') DEFAULT 'user',
            mentioned_file_id bigint(20) UNSIGNED DEFAULT NULL,
            file_log_id bigint(20) UNSIGNED DEFAULT NULL,
            is_edited TINYINT(1) DEFAULT 0,
            edited_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project_created (project_id, created_at),
            KEY idx_project_id_lookup (project_id, id),
            KEY file_log_id (file_log_id)
        ) $charset_collate;";

        // Table 2: Read Status
        $table_read_status = $wpdb->prefix . 'hamnaghsheh_chat_read_status';
        $sql_read_status = "CREATE TABLE $table_read_status (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY message_user_unique (message_id, user_id),
            KEY idx_message_user (message_id, user_id)
        ) $charset_collate;";

        // Table 3: Chat Metadata
        $table_metadata = $wpdb->prefix . 'hamnaghsheh_chat_metadata';
        $sql_metadata = "CREATE TABLE $table_metadata (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id bigint(20) UNSIGNED NOT NULL UNIQUE,
            chat_enabled TINYINT(1) DEFAULT 1,
            last_activity DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_messages);
        dbDelta($sql_read_status);
        dbDelta($sql_metadata);
    }

    /**
     * Backfill historical file logs into chat
     * Reads from wp_hamnaghsheh_file_logs and creates system messages
     */
    private static function backfill_file_logs() {
        global $wpdb;
        
        // Check if the main plugin's file logs table exists
        $file_logs_table = $wpdb->prefix . 'hamnaghsheh_file_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$file_logs_table'") != $file_logs_table) {
            // Main plugin table doesn't exist yet, skip backfill
            return;
        }

        // Get all historical file logs
        $logs = $wpdb->get_results(
            "SELECT * FROM $file_logs_table ORDER BY created_at ASC",
            ARRAY_A
        );

        if (empty($logs)) {
            return;
        }

        // Persian action labels
        $actions_fa = array(
            'upload'   => 'آپلود کرد',
            'replace'  => 'جایگزین کرد',
            'delete'   => 'حذف کرد',
            'download' => 'دانلود کرد',
            'see'      => 'مشاهده کرد'
        );

        $messages_table = $wpdb->prefix . 'hamnaghsheh_chat_messages';

        foreach ($logs as $log) {
            // Get user info
            $user = get_userdata($log['user_id']);
            if (!$user) {
                continue;
            }

            // Get file name (assuming file info is available)
            $file_name = isset($log['file_name']) ? $log['file_name'] : 'فایل';
            
            // Generate Persian message
            $action_label = isset($actions_fa[$log['action_type']]) 
                ? $actions_fa[$log['action_type']] 
                : $log['action_type'];
                
            $message = sprintf(
                '%s %s %s',
                $user->display_name,
                $action_label,
                $file_name
            );

            // Insert system message
            $wpdb->insert(
                $messages_table,
                array(
                    'project_id' => $log['project_id'],
                    'user_id' => $log['user_id'],
                    'message' => $message,
                    'message_type' => 'system',
                    'file_log_id' => $log['id'],
                    'created_at' => $log['created_at']
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );
        }
    }
}
