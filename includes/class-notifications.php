<?php
/**
 * Notifications class - handles unread badges and notifications
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notifications class
 */
class Hamnaghsheh_Messenger_Notifications {
    
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
     * Get total unread badge count for a user (across all projects)
     *
     * @param int $user_id User ID
     * @return int Total unread count
     */
    public static function get_badge_count($user_id) {
        global $wpdb;
        
        // Use transient cache
        $cache_key = "hamnaghsheh_total_unread_{$user_id}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (int)$cached;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->prefix}hamnaghsheh_chat_reads r 
                ON m.id = r.message_id AND r.user_id = %d
            WHERE m.user_id != %d
            AND m.deleted_at IS NULL
            AND r.id IS NULL",
            $user_id,
            $user_id
        ));
        
        $count = (int)$count;
        
        // Cache for 5 seconds
        set_transient($cache_key, $count, 5);
        
        return $count;
    }
    
    /**
     * Get unread count for a specific project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return int Unread count
     */
    public static function get_project_unread($project_id, $user_id) {
        return Hamnaghsheh_Messenger_Seen_Tracker::get_unread_count($project_id, $user_id);
    }
    
    /**
     * Send email notification for new message (optional feature)
     *
     * @param int $user_id Recipient user ID
     * @param object $message Message object
     * @param object $sender Sender user object
     * @return bool Success
     */
    public static function send_email_notification($user_id, $message, $sender) {
        // Get user
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Check if user wants email notifications (could be a user meta setting)
        $wants_email = get_user_meta($user_id, 'hamnaghsheh_messenger_email_notifications', true);
        if ($wants_email === 'no') {
            return false;
        }
        
        // Get project
        $project = get_post($message->project_id);
        if (!$project) {
            return false;
        }
        
        // Prepare email
        $to = $user->user_email;
        $subject = sprintf(
            /* translators: 1: sender name, 2: project title */
            __('New message from %1$s in %2$s', 'hamnaghsheh-messenger'),
            $sender->display_name,
            $project->post_title
        );
        
        $message_content = wp_strip_all_tags($message->message);
        if (strlen($message_content) > 100) {
            $message_content = substr($message_content, 0, 100) . '...';
        }
        
        $body = sprintf(
            /* translators: 1: sender name, 2: message content, 3: project title */
            __("%1$s sent you a message:\n\n%2$s\n\nView in project: %3$s", 'hamnaghsheh-messenger'),
            $sender->display_name,
            $message_content,
            get_permalink($project->ID)
        );
        
        // Send email
        return wp_mail($to, $subject, $body);
    }
    
    /**
     * Clear notification cache for user
     *
     * @param int $user_id User ID
     */
    public static function clear_cache($user_id) {
        delete_transient("hamnaghsheh_total_unread_{$user_id}");
    }
}
