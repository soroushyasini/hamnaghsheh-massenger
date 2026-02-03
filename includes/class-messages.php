<?php
/**
 * Messages class - handles message CRUD operations
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Messages class
 */
class Hamnaghsheh_Messenger_Messages {
    
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
     * Send a message
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @param string $message Message content
     * @param string $message_type Message type (text, system, file_activity)
     * @param array $metadata Additional metadata
     * @return int|false Message ID or false on failure
     */
    public static function send_message($project_id, $user_id, $message, $message_type = 'text', $metadata = null) {
        global $wpdb;
        
        // Sanitize input
        $message = wp_kses_post($message);
        
        // Prepare metadata
        $metadata_json = null;
        if ($metadata && is_array($metadata)) {
            $metadata_json = wp_json_encode($metadata);
        }
        
        // Insert message
        $result = $wpdb->insert(
            $wpdb->prefix . 'hamnaghsheh_chat_messages',
            [
                'project_id' => $project_id,
                'user_id' => $user_id,
                'message_type' => $message_type,
                'message' => $message,
                'metadata' => $metadata_json,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Edit a message
     *
     * @param int $message_id Message ID
     * @param string $new_message New message content
     * @return bool Success
     */
    public static function edit_message($message_id, $new_message) {
        global $wpdb;
        
        // Get original message
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hamnaghsheh_chat_messages WHERE id = %d",
            $message_id
        ));
        
        if (!$message) {
            return false;
        }
        
        // Sanitize new message
        $new_message = wp_kses_post($new_message);
        
        // Update metadata to store original message
        $metadata = $message->metadata ? json_decode($message->metadata, true) : [];
        if (!isset($metadata['original'])) {
            $metadata['original'] = $message->message;
        }
        
        // Update message
        $result = $wpdb->update(
            $wpdb->prefix . 'hamnaghsheh_chat_messages',
            [
                'message' => $new_message,
                'metadata' => wp_json_encode($metadata),
                'edited_at' => current_time('mysql')
            ],
            ['id' => $message_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a message (soft delete)
     *
     * @param int $message_id Message ID
     * @return bool Success
     */
    public static function delete_message($message_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hamnaghsheh_chat_messages',
            ['deleted_at' => current_time('mysql')],
            ['id' => $message_id],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get messages for a project
     *
     * @param int $project_id Project ID
     * @param int $last_id Last message ID (for pagination)
     * @param int $limit Number of messages to retrieve
     * @return array Messages
     */
    public static function get_messages($project_id, $last_id = 0, $limit = 50) {
        global $wpdb;
        
        $where = $wpdb->prepare(
            "project_id = %d AND deleted_at IS NULL",
            $project_id
        );
        
        if ($last_id > 0) {
            $where .= $wpdb->prepare(" AND id > %d", $last_id);
        }
        
        $messages = $wpdb->get_results(
            "SELECT m.*, u.display_name, u.user_email
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE {$where}
            ORDER BY m.created_at ASC
            LIMIT %d",
            $limit
        );
        
        // Add seen_by data to each message
        foreach ($messages as &$message) {
            $message->seen_by = Hamnaghsheh_Messenger_Seen_Tracker::get_seen_by($message->id);
        }
        
        return $messages;
    }
    
    /**
     * Get messages after a specific ID (for SSE)
     *
     * @param int $project_id Project ID
     * @param int $last_message_id Last message ID
     * @return array New messages
     */
    public static function get_messages_after($project_id, $last_message_id) {
        global $wpdb;
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.project_id = %d AND m.id > %d AND m.deleted_at IS NULL
            ORDER BY m.created_at ASC",
            $project_id,
            $last_message_id
        ));
        
        // Add seen_by data
        foreach ($messages as &$message) {
            $message->seen_by = Hamnaghsheh_Messenger_Seen_Tracker::get_seen_by($message->id);
        }
        
        return $messages;
    }
    
    /**
     * Search messages in a project
     *
     * @param int $project_id Project ID
     * @param string $query Search query
     * @param int $limit Number of results
     * @return array Messages
     */
    public static function search_messages($project_id, $query, $limit = 50) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.project_id = %d 
            AND m.message LIKE %s 
            AND m.deleted_at IS NULL
            ORDER BY m.created_at DESC
            LIMIT %d",
            $project_id,
            $search_term,
            $limit
        ));
        
        return $messages;
    }
    
    /**
     * Get single message by ID
     *
     * @param int $message_id Message ID
     * @return object|null Message object
     */
    public static function get_message($message_id) {
        global $wpdb;
        
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.id = %d AND m.deleted_at IS NULL",
            $message_id
        ));
        
        if ($message) {
            $message->seen_by = Hamnaghsheh_Messenger_Seen_Tracker::get_seen_by($message->id);
        }
        
        return $message;
    }
}
