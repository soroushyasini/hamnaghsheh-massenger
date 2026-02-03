<?php
/**
 * Typing Indicator class - handles typing status
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Typing Indicator class
 */
class Hamnaghsheh_Messenger_Typing_Indicator {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register cleanup cron
        add_action('hamnaghsheh_messenger_cleanup_typing', [$this, 'cleanup_old_typing']);
    }
    
    /**
     * Update typing status for a user
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function update_typing($project_id, $user_id) {
        global $wpdb;
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for MySQL
        // Or use REPLACE for simplicity
        $result = $wpdb->replace(
            $wpdb->prefix . 'hamnaghsheh_chat_typing',
            [
                'project_id' => $project_id,
                'user_id' => $user_id,
                'last_typed_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get users currently typing in a project
     *
     * @param int $project_id Project ID
     * @param int $exclude_user_id User ID to exclude (current user)
     * @return array Array of user objects
     */
    public static function get_typing_users($project_id, $exclude_user_id = 0) {
        global $wpdb;
        
        // Get users who typed in last 5 seconds
        $five_seconds_ago = date('Y-m-d H:i:s', time() - 5);
        
        $where = $wpdb->prepare(
            "t.project_id = %d AND t.last_typed_at > %s",
            $project_id,
            $five_seconds_ago
        );
        
        if ($exclude_user_id > 0) {
            $where .= $wpdb->prepare(" AND t.user_id != %d", $exclude_user_id);
        }
        
        $users = $wpdb->get_results(
            "SELECT u.ID, u.display_name, u.user_email, t.last_typed_at
            FROM {$wpdb->prefix}hamnaghsheh_chat_typing t
            JOIN {$wpdb->users} u ON t.user_id = u.ID
            WHERE {$where}
            ORDER BY t.last_typed_at DESC"
        );
        
        return $users ?: [];
    }
    
    /**
     * Clear typing status for a user
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function clear_typing($project_id, $user_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'hamnaghsheh_chat_typing',
            [
                'project_id' => $project_id,
                'user_id' => $user_id
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Cleanup old typing indicators (cron job)
     * Remove entries older than 1 minute
     */
    public function cleanup_old_typing() {
        global $wpdb;
        
        $one_minute_ago = date('Y-m-d H:i:s', time() - 60);
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hamnaghsheh_chat_typing 
            WHERE last_typed_at < %s",
            $one_minute_ago
        ));
    }
}
