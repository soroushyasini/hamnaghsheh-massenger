<?php
/**
 * System Messages Class
 * Injects system messages based on file log actions
 * Now with daily digest support to reduce message spam
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
     * Add custom cron schedule for 10 minutes
     */
    public static function add_cron_schedule($schedules) {
        if (!isset($schedules['hmchat_10min'])) {
            $schedules['hmchat_10min'] = array(
                'interval' => 600, // 10 minutes
                'display' => __('Every 10 Minutes', 'hamnaghsheh-messenger')
            );
        }
        return $schedules;
    }
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Cron-based digest generation disabled - now using real-time file log display via JOIN
        // add_action('hmchat_generate_digests', array(__CLASS__, 'generate_daily_digests'));
    }
    
    /**
     * Generate daily digest messages from file logs
     * Runs via cron job every 10 minutes
     */
    public static function generate_daily_digests() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $file_logs_table = $table_prefix . 'file_logs';
        $messages_table = $table_prefix . 'chat_messages';
        $files_table = $table_prefix . 'files';
        
        // Get all projects with unprocessed file logs
        $projects = $wpdb->get_col("SELECT DISTINCT project_id FROM {$file_logs_table}");
        
        foreach ($projects as $project_id) {
            // Get last processed file log ID for this project
            $option_key = 'hmchat_last_file_log_' . $project_id;
            $last_processed_id = get_option($option_key, 0);
            
            // Get new file logs grouped by user and date
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    user_id,
                    DATE(created_at) as action_date,
                    file_id,
                    action_type,
                    created_at,
                    id
                FROM {$file_logs_table} 
                WHERE project_id = %d AND id > %d 
                ORDER BY created_at ASC",
                $project_id,
                $last_processed_id
            ));
            
            if (empty($logs)) {
                continue;
            }
            
            // Group logs by user and date
            $grouped = array();
            foreach ($logs as $log) {
                $key = $log->user_id . '_' . $log->action_date;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = array(
                        'user_id' => $log->user_id,
                        'date' => $log->action_date,
                        'project_id' => $project_id,
                        'actions' => array()
                    );
                }
                
                // Get file details
                $file = $wpdb->get_row($wpdb->prepare(
                    "SELECT file_name, file_path FROM {$files_table} WHERE id = %d",
                    $log->file_id
                ));
                
                if ($file) {
                    // Generate viewer URL using shared method
                    $viewer_url = HMChat_Mentions::get_file_viewer_url($file->file_name, $file->file_path);
                    
                    // Format action label in Persian
                    $action_label = self::get_action_label($log->action_type);
                    
                    // Format time
                    $time_obj = new DateTime($log->created_at);
                    $time = $time_obj->format('H:i');
                    
                    $grouped[$key]['actions'][] = array(
                        'file_id' => $log->file_id,
                        'file_name' => $file->file_name,
                        'action' => $log->action_type,
                        'action_label' => $action_label,
                        'time' => $time,
                        'viewer_url' => $viewer_url
                    );
                }
                
                $last_processed_id = max($last_processed_id, $log->id);
            }
            
            // Create digest messages
            foreach ($grouped as $group) {
                if (empty($group['actions'])) {
                    continue;
                }
                
                // Get user display name and sanitize
                $user = get_userdata($group['user_id']);
                if (!$user) {
                    continue;
                }
                $display_name = esc_html($user->display_name);
                
                // Count actions by type
                $action_counts = array();
                foreach ($group['actions'] as $action) {
                    if (!isset($action_counts[$action['action']])) {
                        $action_counts[$action['action']] = 0;
                    }
                    $action_counts[$action['action']]++;
                }
                
                // Generate date label
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $date_label = 'امروز';
                
                if ($group['date'] === $today) {
                    $date_label = 'امروز';
                } elseif ($group['date'] === $yesterday) {
                    $date_label = 'دیروز';
                } else {
                    // Use the actual date
                    $date_label = 'در تاریخ ' . $group['date'];
                }
                
                // Generate summary text
                $summary_parts = array();
                foreach ($action_counts as $action_type => $count) {
                    $label = self::get_action_label($action_type);
                    $summary_parts[] = $count . ' فایل را ' . $label;
                }
                $summary = $display_name . ' ' . $date_label . ' ' . implode(' و ', $summary_parts);
                
                // Create digest data
                $digest_data = array(
                    'user_id' => $group['user_id'],
                    'date' => $group['date'],
                    'summary' => $summary,
                    'actions' => $group['actions']
                );
                
                // Insert digest message
                $wpdb->insert(
                    $messages_table,
                    array(
                        'project_id' => $project_id,
                        'user_id' => $group['user_id'],
                        'message' => wp_json_encode($digest_data, JSON_UNESCAPED_UNICODE),
                        'message_type' => 'system_digest',
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%s', '%s')
                );
            }
            
            // Update last processed ID
            update_option($option_key, $last_processed_id);
        }
    }
    
    /**
     * Get Persian action label
     */
    private static function get_action_label($action_type) {
        switch ($action_type) {
            case 'upload':
                return 'آپلود کرد';
            case 'replace':
                return 'جایگزین کرد';
            case 'delete':
                return 'حذف کرد';
            case 'download':
                return 'دانلود کرد';
            case 'see':
                return 'مشاهده کرد';
            default:
                return $action_type;
        }
    }
    
    /**
     * Process file logs and inject system messages (LEGACY - kept for compatibility)
     * This is still called on fetch but now just updates last_processed_id
     * 
     * @param int $project_id Project ID
     */
    public static function process_file_logs($project_id) {
        // Legacy method - now handled by cron job
        // Just update the tracking option
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $file_logs_table = $table_prefix . 'file_logs';
        
        $option_key = 'hmchat_last_file_log_' . $project_id;
        $last_id = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(id) FROM {$file_logs_table} WHERE project_id = %d",
            $project_id
        ));
        
        if ($last_id) {
            update_option($option_key, $last_id);
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

// Register cron schedule filter globally
add_filter('cron_schedules', array('HMChat_System_Messages', 'add_cron_schedule'));


