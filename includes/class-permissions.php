<?php
/**
 * Permissions Class
 *
 * Handles permission checks for chat access
 * Reuses permissions from main Hamnaghsheh PM plugin
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Permissions {

    /**
     * Permission levels
     */
    const PERMISSION_OWNER = 'owner';
    const PERMISSION_UPLOAD = 'upload';
    const PERMISSION_VIEW = 'view';
    const PERMISSION_NONE = 'none';

    /**
     * Get user permission for a project
     * 
     * Tries to get permission from main plugin's project object
     * Falls back to checking user capabilities
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID (defaults to current user)
     * @return string|false Permission level or false if no access
     */
    public static function get_user_permission($project_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Try to get from main plugin if available
        if (function_exists('hamnaghsheh_get_project')) {
            $project = hamnaghsheh_get_project($project_id);
            if ($project && isset($project->user_permission)) {
                return $project->user_permission;
            }
        }

        // Fallback: Check project ownership and capabilities
        return self::check_project_access($project_id, $user_id);
    }

    /**
     * Check if user can send messages
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool True if can send
     */
    public static function can_send_message($project_id, $user_id = null) {
        $permission = self::get_user_permission($project_id, $user_id);
        
        return in_array($permission, array(
            self::PERMISSION_OWNER,
            self::PERMISSION_UPLOAD
        ));
    }

    /**
     * Check if user can read messages
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool True if can read
     */
    public static function can_read_messages($project_id, $user_id = null) {
        $permission = self::get_user_permission($project_id, $user_id);
        
        return in_array($permission, array(
            self::PERMISSION_OWNER,
            self::PERMISSION_UPLOAD,
            self::PERMISSION_VIEW
        ));
    }

    /**
     * Check if user can edit a message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID
     * @return bool True if can edit
     */
    public static function can_edit_message($message_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Get message
        $message = Hamnaghsheh_Massenger_Messages::get_message($message_id);
        
        if (!$message) {
            return false;
        }

        // Only message owner can edit
        if ($message->user_id != $user_id) {
            return false;
        }

        // Check if within edit window (15 minutes)
        $created_time = strtotime($message->created_at);
        $current_time = current_time('timestamp');
        $time_diff = ($current_time - $created_time) / 60;

        return $time_diff <= 15;
    }

    /**
     * Check if user is project owner
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return bool True if owner
     */
    public static function is_project_owner($project_id, $user_id = null) {
        $permission = self::get_user_permission($project_id, $user_id);
        return $permission === self::PERMISSION_OWNER;
    }

    /**
     * Fallback method to check project access
     * Used when main plugin is not available
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return string Permission level
     */
    private static function check_project_access($project_id, $user_id) {
        global $wpdb;

        // Try to check from main plugin's projects table
        $projects_table = $wpdb->prefix . 'hamnaghsheh_projects';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$projects_table'") == $projects_table) {
            $project = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $projects_table WHERE id = %d",
                $project_id
            ));

            if ($project) {
                // Check if user is owner
                if (isset($project->user_id) && $project->user_id == $user_id) {
                    return self::PERMISSION_OWNER;
                }

                // Check project members table if exists
                $members_table = $wpdb->prefix . 'hamnaghsheh_project_members';
                if ($wpdb->get_var("SHOW TABLES LIKE '$members_table'") == $members_table) {
                    $member = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $members_table WHERE project_id = %d AND user_id = %d",
                        $project_id,
                        $user_id
                    ));

                    if ($member && isset($member->permission)) {
                        return $member->permission;
                    }
                }
            }
        }

        // Default: no access
        return false;
    }

    /**
     * Verify user has permission and return error if not
     *
     * @param int $project_id Project ID
     * @param string $required_permission Required permission level
     * @return bool True if has permission
     */
    public static function verify_permission($project_id, $required_permission = 'view') {
        $user_permission = self::get_user_permission($project_id);

        if (!$user_permission) {
            wp_send_json_error(array('message' => 'شما دسترسی به این پروژه ندارید'));
            return false;
        }

        $permission_levels = array(
            self::PERMISSION_VIEW => 1,
            self::PERMISSION_UPLOAD => 2,
            self::PERMISSION_OWNER => 3
        );

        $user_level = isset($permission_levels[$user_permission]) ? $permission_levels[$user_permission] : 0;
        $required_level = isset($permission_levels[$required_permission]) ? $permission_levels[$required_permission] : 0;

        if ($user_level < $required_level) {
            wp_send_json_error(array('message' => 'شما سطح دسترسی کافی ندارید'));
            return false;
        }

        return true;
    }
}
