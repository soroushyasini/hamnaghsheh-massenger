<?php
/**
 * Access Control Class
 * Handles permission checks for chat access
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Access {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // No specific hooks needed for now
    }
    
    /**
     * Check if user can access project chat
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user has access, false otherwise
     */
    public static function can_access_chat($project_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Not logged in
        if (!$user_id) {
            return false;
        }
        
        // Check if user is WordPress admin
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user has hamnaghsheh_admin capability
        if (user_can($user_id, 'hamnaghsheh_admin')) {
            return true;
        }
        
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        
        // Check if user is project owner
        $project_table = $table_prefix . 'projects';
        $is_owner = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$project_table} WHERE id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
        
        if ($is_owner > 0) {
            return true;
        }
        
        // Check if user is assigned to project
        $assignments_table = $table_prefix . 'project_assignments';
        $is_assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$assignments_table} WHERE project_id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
        
        if ($is_assigned > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user is project owner
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user is owner, false otherwise
     */
    public static function is_project_owner($project_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $project_table = $table_prefix . 'projects';
        
        $is_owner = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$project_table} WHERE id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
        
        return $is_owner > 0;
    }
    
    /**
     * Check if user can edit a message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user can edit, false otherwise
     */
    public static function can_edit_message($message_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        // Get message details
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, created_at, message_type 
             FROM {$messages_table} 
             WHERE id = %d",
            $message_id
        ));
        
        if (!$message) {
            return false;
        }
        
        // Can't edit system messages
        if ($message->message_type === 'system') {
            return false;
        }
        
        // Only owner of message can edit
        if ($message->user_id != $user_id) {
            return false;
        }
        
        // Check if message is within 10 minute edit window
        $created_timestamp = strtotime($message->created_at);
        $current_timestamp = current_time('timestamp');
        $time_diff = $current_timestamp - $created_timestamp;
        
        // 10 minutes = 600 seconds
        if ($time_diff > 600) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get project members (owner + assigned users)
     *
     * @param int $project_id Project ID
     * @return array Array of user objects with id and display_name
     */
    public static function get_project_members($project_id) {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $projects_table = $table_prefix . 'projects';
        $assignments_table = $table_prefix . 'project_assignments';
        
        $members = array();
        
        // Get project owner
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$projects_table} WHERE id = %d",
            $project_id
        ));
        
        if ($owner_id) {
            $owner = get_userdata($owner_id);
            if ($owner) {
                $members[] = array(
                    'user_id' => $owner_id,
                    'display_name' => $owner->display_name,
                    'is_owner' => true
                );
            }
        }
        
        // Get assigned users
        $assigned_user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$assignments_table} WHERE project_id = %d",
            $project_id
        ));
        
        foreach ($assigned_user_ids as $user_id) {
            // Skip if already added as owner
            if ($user_id == $owner_id) {
                continue;
            }
            
            $user = get_userdata($user_id);
            if ($user) {
                $members[] = array(
                    'user_id' => $user_id,
                    'display_name' => $user->display_name,
                    'is_owner' => false
                );
            }
        }
        
        return $members;
    }
}
