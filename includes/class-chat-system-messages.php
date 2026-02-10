<?php
/**
 * System Messages Class
 * Injects system messages based on file log actions
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_System_Messages {
    
    /**
     * Deduplication window in seconds
     */
    private static $dedup_window = 300; // 5 minutes
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // We'll process file logs on each fetch request
        // This is more reliable than trying to hook into main plugin's AJAX actions
    }
    
    /**
     * Process file logs and inject system messages
     * 
     * @param int $project_id Project ID
     */
    public static function process_file_logs($project_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $file_logs_table = $table_prefix . 'file_logs';
        $messages_table = $table_prefix . 'chat_messages';
        
        // Get last processed file log ID for this project
        $option_key = 'hmchat_last_file_log_' . $project_id;
        $last_processed_id = get_option($option_key, 0);
        
        // Get new file logs
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$file_logs_table} 
             WHERE project_id = %d AND id > %d 
             ORDER BY id ASC 
             LIMIT 50",
            $project_id,
            $last_processed_id
        ));
        
        if (empty($logs)) {
            return;
        }
        
        foreach ($logs as $log) {
            // Check if we should skip this log (deduplication)
            if (self::should_skip_log($log)) {
                $last_processed_id = $log->id;
                continue;
            }
            
            // Create system message
            $message = self::create_system_message($log);
            
            if ($message) {
                // Insert system message
                $wpdb->insert(
                    $messages_table,
                    array(
                        'project_id' => $log->project_id,
                        'user_id' => $log->user_id,
                        'message' => $message,
                        'message_type' => 'system',
                        'created_at' => $log->created_at
                    ),
                    array('%d', '%d', '%s', '%s', '%s')
                );
            }
            
            $last_processed_id = $log->id;
        }
        
        // Update last processed ID
        update_option($option_key, $last_processed_id);
    }
    
    /**
     * Check if we should skip this log entry (deduplication)
     * 
     * @param object $log File log entry
     * @return bool True if should skip, false otherwise
     */
    private static function should_skip_log($log) {
        // Only deduplicate 'see' and 'download' actions
        if (!in_array($log->action_type, array('see', 'download'))) {
            return false;
        }
        
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $file_logs_table = $table_prefix . 'file_logs';
        
        // Check if same user did same action on same file within dedup window
        $recent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$file_logs_table} 
             WHERE file_id = %d 
             AND user_id = %d 
             AND action_type = %s 
             AND id < %d
             AND created_at > DATE_SUB(%s, INTERVAL %d SECOND)",
            $log->file_id,
            $log->user_id,
            $log->action_type,
            $log->id,
            $log->created_at,
            self::$dedup_window
        ));
        
        return $recent_count > 0;
    }
    
    /**
     * Create system message text from file log
     * 
     * @param object $log File log entry
     * @return string|null System message text or null if not applicable
     */
    private static function create_system_message($log) {
        // Get user display name
        $user = get_userdata($log->user_id);
        if (!$user) {
            return null;
        }
        $display_name = $user->display_name;
        
        // Get file name
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $files_table = $table_prefix . 'files';
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT file_name FROM {$files_table} WHERE id = %d",
            $log->file_id
        ));
        
        $file_name = $file ? $file->file_name : 'فایل';
        
        // Create mention format for file
        $file_mention = "#[{$log->file_id}:{$file_name}]";
        
        // Create message based on action type
        switch ($log->action_type) {
            case 'upload':
                return "{$display_name} فایل {$file_mention} را آپلود کرد";
                
            case 'replace':
                return "{$display_name} فایل {$file_mention} را جایگزین کرد";
                
            case 'delete':
                return "{$display_name} فایل {$file_name} را حذف کرد";
                
            case 'download':
                return "{$display_name} فایل {$file_mention} را دانلود کرد";
                
            case 'see':
                return "{$display_name} فایل {$file_mention} را مشاهده کرد";
                
            default:
                return null;
        }
    }
    
    /**
     * Manually inject a system message
     * 
     * @param int $project_id Project ID
     * @param string $message Message text
     * @param int $user_id User ID (0 for system)
     * @return int|false Message ID or false on failure
     */
    public static function inject_message($project_id, $message, $user_id = 0) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $inserted = $wpdb->insert(
            $messages_table,
            array(
                'project_id' => $project_id,
                'user_id' => $user_id,
                'message' => $message,
                'message_type' => 'system',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if ($inserted) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
}
