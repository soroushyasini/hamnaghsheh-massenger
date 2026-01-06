<?php
/**
 * Heartbeat Class
 *
 * Handles WordPress Heartbeat API integration for real-time updates
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Heartbeat {

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
        // Hook into WordPress Heartbeat
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 2);
        add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
    }

    /**
     * Modify heartbeat settings
     *
     * @param array $settings Heartbeat settings
     * @return array Modified settings
     */
    public function heartbeat_settings($settings) {
        // Default interval is 15 seconds (will be overridden by JavaScript based on chat state)
        $settings['interval'] = 15;
        return $settings;
    }

    /**
     * Handle heartbeat tick - send new messages to client
     *
     * @param array $response Response data
     * @param array $data Data from client
     * @return array Modified response
     */
    public function heartbeat_received($response, $data) {
        // Check if this is a chat heartbeat request
        if (!isset($data['hamnaghsheh_chat'])) {
            return $response;
        }

        $chat_data = $data['hamnaghsheh_chat'];
        
        if (!isset($chat_data['project_id']) || !isset($chat_data['last_message_id'])) {
            return $response;
        }

        $project_id = intval($chat_data['project_id']);
        $last_message_id = intval($chat_data['last_message_id']);

        // Check permissions
        if (!Hamnaghsheh_Massenger_Permissions::can_read_messages($project_id)) {
            return $response;
        }

        // Get new messages since last_message_id
        $new_messages = $this->get_new_messages($project_id, $last_message_id);

        // Get unread count
        $unread_count = Hamnaghsheh_Massenger_Messages::get_unread_count($project_id);

        // Prepare response
        $response['hamnaghsheh_chat'] = array(
            'new_messages' => $new_messages,
            'unread_count' => $unread_count,
            'timestamp' => current_time('timestamp')
        );

        return $response;
    }

    /**
     * Get new messages since a specific message ID
     *
     * @param int $project_id Project ID
     * @param int $last_message_id Last message ID client has
     * @return array New messages
     */
    private function get_new_messages($project_id, $last_message_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_messages';

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE project_id = %d AND id > %d 
            ORDER BY created_at ASC, id ASC",
            $project_id,
            $last_message_id
        ));

        $formatted_messages = array();

        foreach ($messages as $message) {
            $user = get_userdata($message->user_id);
            
            $formatted_messages[] = array(
                'id' => $message->id,
                'project_id' => $message->project_id,
                'user_id' => $message->user_id,
                'user_name' => $user ? $user->display_name : 'Unknown',
                'message' => $message->message,
                'message_type' => $message->message_type,
                'is_edited' => (bool) $message->is_edited,
                'created_at' => $message->created_at,
                'formatted_time' => date_i18n('H:i', strtotime($message->created_at)),
                'formatted_date' => date_i18n('Y/m/d', strtotime($message->created_at)),
                'can_edit' => Hamnaghsheh_Massenger_Permissions::can_edit_message($message->id),
                'read_receipts' => Hamnaghsheh_Massenger_Read_Status::get_formatted_receipts($message->id)
            );
        }

        return $formatted_messages;
    }
}
