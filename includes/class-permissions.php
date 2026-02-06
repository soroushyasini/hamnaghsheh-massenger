<?php
/**
 * Permissions class - handles access control
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permissions class
 */
class Hamnaghsheh_Messenger_Permissions {
    
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
     * Check if user can access chat (view only)
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool
     */
    public static function can_user_chat($project_id, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Check if main plugin function exists
        if (!class_exists('Hamnaghsheh_Projects')) {
            return false;
        }
        
        // Check if user is owner or assigned
        $permission = self::get_user_permission($project_id, $user_id);
        
        return $permission !== false;
    }
    
    /**
     * Check if user can send messages
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool
     */
    public static function can_send_message($project_id, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        $permission = self::get_user_permission($project_id, $user_id);
        
        // Only owner or upload permission can send
        return in_array($permission, ['owner', 'upload'], true);
    }
    
    /**
     * Check if user can edit message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID
     * @return bool
     */
    public static function can_edit_message($message_id, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        global $wpdb;
        
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hamnaghsheh_chat_messages 
            WHERE id = %d AND deleted_at IS NULL",
            $message_id
        ));
        
        if (!$message || (int)$message->user_id !== (int)$user_id) {
            return false; // Not message owner
        }
        
        // System messages can't be edited
        if ($message->message_type !== 'text') {
            return false;
        }
        
        // Can edit within 15 minutes
        $time_diff = time() - strtotime($message->created_at);
        return $time_diff < (15 * 60);
    }
    
    /**
     * Check if user can delete message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID
     * @return bool
     */
    public static function can_delete_message($message_id, $user_id) {
        // Same rules as edit
        return self::can_edit_message($message_id, $user_id);
    }
    
    /**
     * Get user permission for project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return string|bool Permission level or false
     */
    private static function get_user_permission($project_id, $user_id) {
        // Try to use main plugin's permission function
        if (class_exists('Hamnaghsheh_Projects') && method_exists('Hamnaghsheh_Projects', 'get_user_project_permission')) {
            return Hamnaghsheh_Projects::get_user_project_permission($project_id, $user_id);
        }
        
        // Fallback: check if user is the project owner
        // This is a basic fallback - the main plugin should provide the proper method
        $project = get_post($project_id);
        if (!$project) {
            return false;
        }
        
        if ((int)$project->post_author === (int)$user_id) {
            return 'owner';
        }
        
        return false;
    }
    
    /**
     * Rate limiting check
     *
     * @param int $user_id User ID
     * @param int $limit Messages per minute
     * @return bool True if within limit
     */
    public static function check_rate_limit($user_id, $limit = 10) {
        global $wpdb;
        
        $one_minute_ago = date('Y-m-d H:i:s', time() - 60);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hamnaghsheh_chat_messages 
            WHERE user_id = %d AND created_at > %s",
            $user_id,
            $one_minute_ago
        ));
        
        return (int)$count < $limit;
    }
}
