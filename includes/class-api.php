<?php
/**
 * API class - handles AJAX endpoints
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API class
 */
class Hamnaghsheh_Messenger_API {
    
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
        // Register AJAX endpoints
        add_action('wp_ajax_hamnaghsheh_send_message', [$this, 'ajax_send_message']);
        add_action('wp_ajax_hamnaghsheh_edit_message', [$this, 'ajax_edit_message']);
        add_action('wp_ajax_hamnaghsheh_delete_message', [$this, 'ajax_delete_message']);
        add_action('wp_ajax_hamnaghsheh_load_messages', [$this, 'ajax_load_messages']);
        add_action('wp_ajax_hamnaghsheh_search_messages', [$this, 'ajax_search_messages']);
        add_action('wp_ajax_hamnaghsheh_mark_read', [$this, 'ajax_mark_read']);
        add_action('wp_ajax_hamnaghsheh_update_typing', [$this, 'ajax_update_typing']);
        add_action('wp_ajax_hamnaghsheh_sse_stream', [$this, 'ajax_sse_stream']);
        add_action('wp_ajax_hamnaghsheh_export_chat', [$this, 'ajax_export_chat']);
        add_action('wp_ajax_hamnaghsheh_get_unread_count', [$this, 'ajax_get_unread_count']);
    }
    
    /**
     * Send message
     */
    public function ajax_send_message() {
        // Verify nonce
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $user_id = get_current_user_id();
        
        // Validate
        if (!$project_id || !$message || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_send_message($project_id, $user_id)) {
            wp_send_json_error(['message' => __('You do not have permission to send messages', 'hamnaghsheh-messenger')]);
        }
        
        // Check rate limit
        if (!Hamnaghsheh_Messenger_Permissions::check_rate_limit($user_id)) {
            wp_send_json_error(['message' => __('Too many messages. Please slow down.', 'hamnaghsheh-messenger')]);
        }
        
        // Send message
        $message_id = Hamnaghsheh_Messenger_Messages::send_message($project_id, $user_id, $message);
        
        if (!$message_id) {
            wp_send_json_error(['message' => __('Failed to send message', 'hamnaghsheh-messenger')]);
        }
        
        // Clear typing status
        Hamnaghsheh_Messenger_Typing_Indicator::clear_typing($project_id, $user_id);
        
        // Get full message data
        $msg = Hamnaghsheh_Messenger_Messages::get_message($message_id);
        
        wp_send_json_success([
            'message' => __('Message sent', 'hamnaghsheh-messenger'),
            'data' => $this->format_message_response($msg)
        ]);
    }
    
    /**
     * Edit message
     */
    public function ajax_edit_message() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $new_message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $user_id = get_current_user_id();
        
        if (!$message_id || !$new_message || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_edit_message($message_id, $user_id)) {
            wp_send_json_error(['message' => __('You cannot edit this message', 'hamnaghsheh-messenger')]);
        }
        
        // Edit message
        $result = Hamnaghsheh_Messenger_Messages::edit_message($message_id, $new_message);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to edit message', 'hamnaghsheh-messenger')]);
        }
        
        wp_send_json_success(['message' => __('Message edited', 'hamnaghsheh-messenger')]);
    }
    
    /**
     * Delete message
     */
    public function ajax_delete_message() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$message_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_delete_message($message_id, $user_id)) {
            wp_send_json_error(['message' => __('You cannot delete this message', 'hamnaghsheh-messenger')]);
        }
        
        // Delete message
        $result = Hamnaghsheh_Messenger_Messages::delete_message($message_id);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete message', 'hamnaghsheh-messenger')]);
        }
        
        wp_send_json_success(['message' => __('Message deleted', 'hamnaghsheh-messenger')]);
    }
    
    /**
     * Load messages
     */
    public function ajax_load_messages() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_user_chat($project_id, $user_id)) {
            wp_send_json_error(['message' => __('Access denied', 'hamnaghsheh-messenger')]);
        }
        
        // Get messages
        $messages = Hamnaghsheh_Messenger_Messages::get_messages($project_id, $last_id, $limit);
        
        // Format messages
        $formatted = array_map([$this, 'format_message_response'], $messages);
        
        wp_send_json_success(['messages' => $formatted]);
    }
    
    /**
     * Search messages
     */
    public function ajax_search_messages() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $query = isset($_GET['query']) ? sanitize_text_field($_GET['query']) : '';
        $user_id = get_current_user_id();
        
        if (!$project_id || !$query || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_user_chat($project_id, $user_id)) {
            wp_send_json_error(['message' => __('Access denied', 'hamnaghsheh-messenger')]);
        }
        
        // Search messages
        $messages = Hamnaghsheh_Messenger_Messages::search_messages($project_id, $query);
        
        // Format messages
        $formatted = array_map([$this, 'format_message_response'], $messages);
        
        wp_send_json_success(['messages' => $formatted]);
    }
    
    /**
     * Mark messages as read
     */
    public function ajax_mark_read() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Mark all as read
        $result = Hamnaghsheh_Messenger_Seen_Tracker::bulk_mark_read($project_id, $user_id);
        
        // Clear caches
        Hamnaghsheh_Messenger_Seen_Tracker::clear_cache($project_id, $user_id);
        Hamnaghsheh_Messenger_Notifications::clear_cache($user_id);
        
        wp_send_json_success(['message' => __('Messages marked as read', 'hamnaghsheh-messenger')]);
    }
    
    /**
     * Update typing status
     */
    public function ajax_update_typing() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Update typing
        Hamnaghsheh_Messenger_Typing_Indicator::update_typing($project_id, $user_id);
        
        wp_send_json_success();
    }
    
    /**
     * SSE stream endpoint
     */
    public function ajax_sse_stream() {
        // Verify nonce from query string
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'hamnaghsheh_messenger_nonce')) {
            wp_die('Invalid nonce');
        }
        
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_die('Invalid request');
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_user_chat($project_id, $user_id)) {
            wp_die('Access denied');
        }
        
        // Stream events
        Hamnaghsheh_Messenger_SSE_Handler::stream_events($project_id, $last_message_id, $user_id);
    }
    
    /**
     * Export chat
     */
    public function ajax_export_chat() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'txt';
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        // Check permission
        if (!Hamnaghsheh_Messenger_Permissions::can_user_chat($project_id, $user_id)) {
            wp_send_json_error(['message' => __('Access denied', 'hamnaghsheh-messenger')]);
        }
        
        // Generate download link
        $download_url = Hamnaghsheh_Messenger_Export::generate_download_link($project_id, $format);
        
        if (!$download_url) {
            wp_send_json_error(['message' => __('Failed to export chat', 'hamnaghsheh-messenger')]);
        }
        
        wp_send_json_success(['download_url' => $download_url]);
    }
    
    /**
     * Get unread count
     */
    public function ajax_get_unread_count() {
        check_ajax_referer('hamnaghsheh_messenger_nonce', 'nonce');
        
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'hamnaghsheh-messenger')]);
        }
        
        $count = Hamnaghsheh_Messenger_Notifications::get_project_unread($project_id, $user_id);
        
        wp_send_json_success(['count' => $count]);
    }
    
    /**
     * Format message for API response
     *
     * @param object $message Message object
     * @return array Formatted message
     */
    private function format_message_response($message) {
        $metadata = null;
        if ($message->metadata) {
            $metadata = json_decode($message->metadata, true);
        }
        
        return [
            'id' => $message->id,
            'project_id' => $message->project_id,
            'user_id' => $message->user_id,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'metadata' => $metadata,
            'display_name' => $message->display_name,
            'avatar_url' => get_avatar_url($message->user_id),
            'created_at' => $message->created_at,
            'edited_at' => $message->edited_at,
            'seen_by' => $message->seen_by ?? [],
            'timestamp' => strtotime($message->created_at),
            'can_edit' => Hamnaghsheh_Messenger_Permissions::can_edit_message($message->id, get_current_user_id()),
            'can_delete' => Hamnaghsheh_Messenger_Permissions::can_delete_message($message->id, get_current_user_id())
        ];
    }
}
