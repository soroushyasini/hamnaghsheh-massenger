<?php
/**
 * AJAX Class
 *
 * Handles all AJAX endpoints for chat operations
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Ajax {

    /**
     * Instance
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
        add_action('wp_ajax_hamnaghsheh_send_message', array($this, 'send_message'));
        add_action('wp_ajax_hamnaghsheh_edit_message', array($this, 'edit_message'));
        add_action('wp_ajax_hamnaghsheh_load_messages', array($this, 'load_messages'));
        add_action('wp_ajax_hamnaghsheh_load_more_messages', array($this, 'load_more_messages'));
        add_action('wp_ajax_hamnaghsheh_mark_as_read', array($this, 'mark_as_read'));
        add_action('wp_ajax_hamnaghsheh_get_unread_count', array($this, 'get_unread_count'));
        add_action('wp_ajax_hamnaghsheh_search_files', array($this, 'search_files'));
    }

    /**
     * Verify nonce for AJAX requests
     */
    private function verify_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hamnaghsheh_chat_nonce')) {
            wp_send_json_error(array('message' => 'امنیتی: درخواست نامعتبر است'));
            exit;
        }
    }

    /**
     * Send a new message
     */
    public function send_message() {
        $this->verify_nonce();

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $mentioned_file_id = isset($_POST['mentioned_file_id']) ? intval($_POST['mentioned_file_id']) : null;

        if (!$project_id || empty($message)) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        // Check permission
        if (!Hamnaghsheh_Massenger_Permissions::can_send_message($project_id)) {
            wp_send_json_error(array('message' => 'شما مجوز ارسال پیام ندارید'));
            return;
        }

        // Insert message
        $message_id = Hamnaghsheh_Massenger_Messages::insert_message(array(
            'project_id' => $project_id,
            'user_id' => get_current_user_id(),
            'message' => $message,
            'message_type' => 'user',
            'mentioned_file_id' => $mentioned_file_id
        ));

        if (!$message_id) {
            wp_send_json_error(array('message' => 'خطا در ارسال پیام'));
            return;
        }

        // Get message details
        $message_obj = Hamnaghsheh_Massenger_Messages::get_message($message_id);
        $user = get_userdata($message_obj->user_id);

        wp_send_json_success(array(
            'message' => array(
                'id' => $message_obj->id,
                'user_id' => $message_obj->user_id,
                'user_name' => $user->display_name,
                'message' => $message_obj->message,
                'message_type' => $message_obj->message_type,
                'created_at' => $message_obj->created_at,
                'formatted_time' => date_i18n('H:i', strtotime($message_obj->created_at)),
                'formatted_date' => date_i18n('Y/m/d', strtotime($message_obj->created_at))
            )
        ));
    }

    /**
     * Edit a message
     */
    public function edit_message() {
        $this->verify_nonce();

        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $new_message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';

        if (!$message_id || empty($new_message)) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        // Update message (permission check is done inside)
        $success = Hamnaghsheh_Massenger_Messages::update_message($message_id, $new_message);

        if (!$success) {
            wp_send_json_error(array('message' => 'خطا در ویرایش پیام یا زمان ویرایش به پایان رسیده است'));
            return;
        }

        wp_send_json_success(array('message' => 'پیام با موفقیت ویرایش شد'));
    }

    /**
     * Load initial messages
     */
    public function load_messages() {
        $this->verify_nonce();

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

        if (!$project_id) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        // Check permission
        if (!Hamnaghsheh_Massenger_Permissions::can_read_messages($project_id)) {
            wp_send_json_error(array('message' => 'شما مجوز مشاهده پیام‌ها ندارید'));
            return;
        }

        // Get last 50 messages
        $messages = Hamnaghsheh_Massenger_Messages::get_messages_with_details($project_id, 50);

        wp_send_json_success(array(
            'messages' => $messages,
            'can_send' => Hamnaghsheh_Massenger_Permissions::can_send_message($project_id)
        ));
    }

    /**
     * Load more older messages (pagination)
     */
    public function load_more_messages() {
        $this->verify_nonce();

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $before_id = isset($_POST['before_id']) ? intval($_POST['before_id']) : 0;

        if (!$project_id || !$before_id) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        // Check permission
        if (!Hamnaghsheh_Massenger_Permissions::can_read_messages($project_id)) {
            wp_send_json_error(array('message' => 'شما مجوز مشاهده پیام‌ها ندارید'));
            return;
        }

        // Get 50 more messages before the given ID
        $messages = Hamnaghsheh_Massenger_Messages::get_messages_with_details($project_id, 50, $before_id);

        wp_send_json_success(array(
            'messages' => $messages,
            'has_more' => count($messages) === 50
        ));
    }

    /**
     * Mark messages as read
     */
    public function mark_as_read() {
        $this->verify_nonce();

        $message_ids = isset($_POST['message_ids']) ? array_map('intval', $_POST['message_ids']) : array();

        if (empty($message_ids)) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        // Mark as read
        Hamnaghsheh_Massenger_Read_Status::mark_multiple_as_read($message_ids);

        wp_send_json_success(array('message' => 'پیام‌ها به عنوان خوانده شده علامت گذاری شدند'));
    }

    /**
     * Get unread count for a project
     */
    public function get_unread_count() {
        $this->verify_nonce();

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

        if (!$project_id) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        $count = Hamnaghsheh_Massenger_Messages::get_unread_count($project_id);

        wp_send_json_success(array('unread_count' => $count));
    }

    /**
     * Search files for autocomplete (file mention feature)
     */
    public function search_files() {
        $this->verify_nonce();

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (!$project_id) {
            wp_send_json_error(array('message' => 'پارامترهای ورودی نامعتبر است'));
            return;
        }

        // Check permission
        if (!Hamnaghsheh_Massenger_Permissions::can_read_messages($project_id)) {
            wp_send_json_error(array('message' => 'شما مجوز دسترسی ندارید'));
            return;
        }

        // Search files in main plugin
        $files = $this->search_project_files($project_id, $query);

        wp_send_json_success(array('files' => $files));
    }

    /**
     * Search project files from main plugin
     *
     * @param int $project_id Project ID
     * @param string $query Search query
     * @return array Files
     */
    private function search_project_files($project_id, $query) {
        global $wpdb;
        
        $files_table = $wpdb->prefix . 'hamnaghsheh_files';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$files_table'") != $files_table) {
            return array();
        }

        $where = $wpdb->prepare("WHERE project_id = %d", $project_id);
        
        if (!empty($query)) {
            $where .= $wpdb->prepare(" AND file_name LIKE %s", '%' . $wpdb->esc_like($query) . '%');
        }

        $files = $wpdb->get_results(
            "SELECT id, file_name, file_size FROM $files_table 
            $where 
            ORDER BY created_at DESC 
            LIMIT 10",
            ARRAY_A
        );

        return $files;
    }
}
