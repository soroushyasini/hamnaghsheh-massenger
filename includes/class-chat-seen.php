<?php
/**
 * Chat Seen Status Class
 * Handles message seen/read tracking
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Seen {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_hmchat_mark_seen', array(__CLASS__, 'ajax_mark_seen'));
        add_action('wp_ajax_hmchat_get_seen_details', array(__CLASS__, 'ajax_get_seen_details'));
        add_action('wp_ajax_hmchat_get_unread_count', array(__CLASS__, 'ajax_get_unread_count'));
    }
    
    /**
     * AJAX: Mark messages as seen
     */
    public static function ajax_mark_seen() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $message_ids = isset($_POST['message_ids']) ? $_POST['message_ids'] : array();
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (empty($message_ids) || !$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        // Sanitize message IDs
        $message_ids = array_map('intval', $message_ids);
        $message_ids = array_filter($message_ids);
        
        if (empty($message_ids)) {
            wp_send_json_error(array('message' => 'لیست پیام‌ها خالی است'));
        }
        
        // Mark messages as seen
        $marked = self::mark_messages_seen($message_ids, $project_id, $user_id);
        
        wp_send_json_success(array(
            'marked' => $marked
        ));
    }
    
    /**
     * AJAX: Get seen details for a message
     */
    public static function ajax_get_seen_details() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$message_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Get message project ID to check access
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $project_id = $wpdb->get_var($wpdb->prepare(
            "SELECT project_id FROM {$messages_table} WHERE id = %d",
            $message_id
        ));
        
        if (!$project_id) {
            wp_send_json_error(array('message' => 'پیام یافت نشد'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        // Get seen details
        $seen_details = self::get_seen_details($message_id);
        
        wp_send_json_success(array(
            'seen_by' => $seen_details
        ));
    }
    
    /**
     * AJAX: Get unread count for all user projects
     */
    public static function ajax_get_unread_count() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => 'کاربر وارد نشده است'));
        }
        
        // Get all projects user has access to
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $projects_table = $table_prefix . 'projects';
        $assignments_table = $table_prefix . 'project_assignments';
        
        // Get projects where user is owner or assigned
        $project_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.id 
             FROM {$projects_table} p 
             LEFT JOIN {$assignments_table} a ON p.id = a.project_id 
             WHERE p.user_id = %d OR a.user_id = %d",
            $user_id,
            $user_id
        ));
        
        $unread_counts = array();
        
        foreach ($project_ids as $project_id) {
            $count = self::get_unread_count($project_id, $user_id);
            if ($count > 0) {
                $unread_counts[$project_id] = $count;
            }
        }
        
        wp_send_json_success(array(
            'unread_counts' => $unread_counts
        ));
    }
    
    /**
     * Mark messages as seen
     */
    public static function mark_messages_seen($message_ids, $project_id, $user_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        $seen_table = $table_prefix . 'chat_seen';
        
        // Verify all messages belong to the project
        $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
        $valid_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} 
             WHERE project_id = %d AND id IN ($placeholders)",
            array_merge(array($project_id), $message_ids)
        ));
        
        if ($valid_count != count($message_ids)) {
            return false;
        }
        
        // Batch insert seen records
        $values = array();
        $current_time = current_time('mysql');
        
        foreach ($message_ids as $message_id) {
            $values[] = $wpdb->prepare("(%d, %d, %s)", $message_id, $user_id, $current_time);
        }
        
        $values_string = implode(',', $values);
        
        // Use INSERT IGNORE to prevent duplicates
        $wpdb->query(
            "INSERT IGNORE INTO {$seen_table} (message_id, user_id, seen_at) 
             VALUES {$values_string}"
        );
        
        return true;
    }
    
    /**
     * Get seen status for a message
     */
    public static function get_message_seen_status($message_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $seen_table = $table_prefix . 'chat_seen';
        
        $seen_users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, seen_at FROM {$seen_table} WHERE message_id = %d",
            $message_id
        ));
        
        $users = array();
        foreach ($seen_users as $seen) {
            $user = get_userdata($seen->user_id);
            if ($user) {
                $users[] = array(
                    'user_id' => $seen->user_id,
                    'display_name' => $user->display_name,
                    'seen_at' => $seen->seen_at
                );
            }
        }
        
        return array(
            'count' => count($users),
            'users' => $users
        );
    }
    
    /**
     * Get detailed seen info for a message
     */
    public static function get_seen_details($message_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $seen_table = $table_prefix . 'chat_seen';
        
        $seen_users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, seen_at FROM {$seen_table} 
             WHERE message_id = %d 
             ORDER BY seen_at ASC",
            $message_id
        ));
        
        $details = array();
        
        foreach ($seen_users as $seen) {
            $user = get_userdata($seen->user_id);
            if ($user) {
                $details[] = array(
                    'user_id' => $seen->user_id,
                    'display_name' => $user->display_name,
                    'seen_at' => $seen->seen_at,
                    'seen_at_formatted' => self::format_jalali_datetime($seen->seen_at)
                );
            }
        }
        
        return $details;
    }
    
    /**
     * Get unread message count for a project
     */
    public static function get_unread_count($project_id, $user_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        $seen_table = $table_prefix . 'chat_seen';
        
        // Get last seen message ID for this user in this project
        $last_seen_id = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(s.message_id) 
             FROM {$seen_table} s 
             INNER JOIN {$messages_table} m ON s.message_id = m.id 
             WHERE m.project_id = %d AND s.user_id = %d",
            $project_id,
            $user_id
        ));
        
        if (!$last_seen_id) {
            $last_seen_id = 0;
        }
        
        // Count messages after last seen, excluding user's own messages
        $unread_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$messages_table} 
             WHERE project_id = %d 
             AND id > %d 
             AND user_id != %d",
            $project_id,
            $last_seen_id,
            $user_id
        ));
        
        return intval($unread_count);
    }
    
    /**
     * Format datetime to Jalali (Persian) calendar
     */
    private static function format_jalali_datetime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        
        // Simple Jalali conversion (basic implementation)
        // In production, use a proper library like php-jalali
        $gregorian_date = getdate($timestamp);
        
        // For now, return a formatted Persian time
        // TODO: Implement proper Jalali conversion
        $time = date('H:i', $timestamp);
        $persian_time = self::convert_to_persian_numbers($time);
        
        return $persian_time;
    }
    
    /**
     * Convert Latin numbers to Persian numbers
     */
    private static function convert_to_persian_numbers($string) {
        $persian_numbers = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $latin_numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        
        return str_replace($latin_numbers, $persian_numbers, $string);
    }
}
