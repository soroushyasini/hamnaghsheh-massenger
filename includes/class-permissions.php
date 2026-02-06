<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Messenger_Permissions {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get project owner ID
     * 
     * @param int $project_id
     * @return int|null Owner user ID or null if project not found
     */
    private static function get_project_owner_id($project_id) {
        global $wpdb;
        
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}hamnaghsheh_projects WHERE id = %d",
            $project_id
        ));
        
        return $owner_id ? (int)$owner_id : null;
    }
    
    /**
     * Get user's assignment permission for a project
     * 
     * @param int $project_id
     * @param int $user_id
     * @return string|null Permission level ('upload' or 'view') or null if not assigned
     */
    private static function get_user_assignment_permission($project_id, $user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT permission FROM {$wpdb->prefix}hamnaghsheh_project_assignments 
             WHERE project_id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
    }
    
    /**
     * Check if user can access chat in a project
     * 
     * @param int $project_id
     * @param int $user_id
     * @return bool
     */
    public static function can_user_chat($project_id, $user_id) {
        // Must be logged in
        if (!$user_id) {
            error_log('🔐 Chat permission: User not logged in');
            return false;
        }
        
        // Check if user is the project owner
        $owner_id = self::get_project_owner_id($project_id);
        
        if (!$owner_id) {
            error_log('🔐 Chat permission: Project not found');
            return false;
        }
        
        // Owner has full access
        if ($owner_id === (int)$user_id) {
            error_log("🔐 Chat permission: User $user_id is OWNER of project $project_id - ALLOWED");
            return true;
        }
        
        // Check if user is assigned to the project
        $permission = self::get_user_assignment_permission($project_id, $user_id);
        
        if ($permission) {
            error_log("🔐 Chat permission: User $user_id assigned to project $project_id with permission '$permission' - ALLOWED");
            // Assigned users can access chat (both 'upload' and 'view')
            // Note: 'view' users have read-only access (enforced in can_send_message)
            return true;
        }
        
        error_log("🔐 Chat permission: User $user_id has NO ACCESS to project $project_id - DENIED");
        return false;
    }
    
    /**
     * Check if user can send messages (not just read)
     * 
     * @param int $project_id
     * @param int $user_id
     * @return bool
     */
    public static function can_send_message($project_id, $user_id) {
        if (!self::can_user_chat($project_id, $user_id)) {
            return false;
        }
        
        // Check if owner
        $owner_id = self::get_project_owner_id($project_id);
        
        if ($owner_id && $owner_id === (int)$user_id) {
            return true; // Owner can always send
        }
        
        // Check assignment permission
        $permission = self::get_user_assignment_permission($project_id, $user_id);
        
        // Only 'upload' permission can send messages, 'view' is read-only
        return $permission === 'upload';
    }
    
    /**
     * Check if user can edit a message
     * 
     * @param int $message_id
     * @param int $user_id
     * @return bool
     */
    public static function can_edit_message($message_id, $user_id) {
        global $wpdb;
        
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, created_at, message_type FROM {$wpdb->prefix}hamnaghsheh_chat_messages 
             WHERE id = %d AND deleted_at IS NULL",
            $message_id
        ));
        
        if (!$message) {
            return false;
        }
        
        // Must be message owner
        if ((int)$message->user_id !== (int)$user_id) {
            return false;
        }
        
        // System messages can't be edited (only 'text' type can be edited)
        if ($message->message_type !== 'text') {
            return false;
        }
        
        // Can only edit within 15 minutes
        $time_diff = time() - strtotime($message->created_at);
        return $time_diff < (15 * 60);
    }
    
    /**
     * Check if user can delete a message
     * 
     * @param int $message_id
     * @param int $user_id
     * @return bool
     */
    public static function can_delete_message($message_id, $user_id) {
        // Same rules as edit for now
        return self::can_edit_message($message_id, $user_id);
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
