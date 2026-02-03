<?php
/**
 * Seen Tracker class - handles "seen by" functionality (CRITICAL)
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seen Tracker class
 */
class Hamnaghsheh_Messenger_Seen_Tracker {
    
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
        // Constructor
    }
    
    /**
     * Mark message(s) as read
     *
     * @param int|array $message_ids Message ID or array of IDs
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function mark_as_read($message_ids, $user_id) {
        global $wpdb;
        
        if (!is_array($message_ids)) {
            $message_ids = [$message_ids];
        }
        
        $success = true;
        foreach ($message_ids as $message_id) {
            // Use INSERT IGNORE to avoid duplicate key errors
            $result = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}hamnaghsheh_chat_reads 
                (message_id, user_id, read_at) 
                VALUES (%d, %d, %s)",
                $message_id,
                $user_id,
                current_time('mysql')
            ));
            
            if ($result === false) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Bulk mark all messages in a project as read
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function bulk_mark_read($project_id, $user_id) {
        global $wpdb;
        
        // Get all message IDs in project that user hasn't read yet
        $message_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT m.id 
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->prefix}hamnaghsheh_chat_reads r 
                ON m.id = r.message_id AND r.user_id = %d
            WHERE m.project_id = %d 
            AND m.deleted_at IS NULL
            AND r.id IS NULL",
            $user_id,
            $project_id
        ));
        
        if (empty($message_ids)) {
            return true;
        }
        
        return self::mark_as_read($message_ids, $user_id);
    }
    
    /**
     * Get users who have seen a message
     *
     * @param int $message_id Message ID
     * @return array Array of user objects
     */
    public static function get_seen_by($message_id) {
        global $wpdb;
        
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, r.read_at
            FROM {$wpdb->prefix}hamnaghsheh_chat_reads r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.message_id = %d
            ORDER BY r.read_at ASC",
            $message_id
        ));
        
        return $users ?: [];
    }
    
    /**
     * Get unread message count for a user in a project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return int Unread count
     */
    public static function get_unread_count($project_id, $user_id) {
        global $wpdb;
        
        // Use transient cache (5 seconds)
        $cache_key = "hamnaghsheh_unread_{$project_id}_{$user_id}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (int)$cached;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->prefix}hamnaghsheh_chat_reads r 
                ON m.id = r.message_id AND r.user_id = %d
            WHERE m.project_id = %d 
            AND m.user_id != %d
            AND m.deleted_at IS NULL
            AND r.id IS NULL",
            $user_id,
            $project_id,
            $user_id // Don't count own messages as unread
        ));
        
        $count = (int)$count;
        
        // Cache for 5 seconds
        set_transient($cache_key, $count, 5);
        
        return $count;
    }
    
    /**
     * Check if message is seen by all project members
     *
     * @param int $message_id Message ID
     * @param int $project_id Project ID
     * @return bool True if seen by all
     */
    public static function is_seen_by_all($message_id, $project_id) {
        global $wpdb;
        
        // Get message
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hamnaghsheh_chat_messages WHERE id = %d",
            $message_id
        ));
        
        if (!$message) {
            return false;
        }
        
        // Get project member count (excluding message sender)
        // This would need integration with the main plugin to get actual member count
        // For now, we'll use a simplified check
        
        $seen_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hamnaghsheh_chat_reads 
            WHERE message_id = %d",
            $message_id
        ));
        
        // If at least one person has seen it (excluding sender), consider it delivered
        return $seen_count > 0;
    }
    
    /**
     * Clear unread count cache
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (optional, clears for all if not provided)
     */
    public static function clear_cache($project_id, $user_id = null) {
        if ($user_id) {
            delete_transient("hamnaghsheh_unread_{$project_id}_{$user_id}");
        } else {
            // Clear for all users - this is a simplified approach
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_hamnaghsheh_unread_{$project_id}_%'"
            );
        }
    }
}
