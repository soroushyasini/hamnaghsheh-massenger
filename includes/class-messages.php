<?php
/**
 * Messages Class
 *
 * Handles CRUD operations for chat messages
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Messages {

    /**
     * Insert a new message
     *
     * @param array $data Message data
     * @return int|false Message ID or false on failure
     */
    public static function insert_message($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        
        $defaults = array(
            'project_id' => 0,
            'user_id' => get_current_user_id(),
            'message' => '',
            'message_type' => 'user',
            'mentioned_file_id' => null,
            'file_log_id' => null,
            'is_edited' => 0,
            'edited_at' => null,
            'created_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        // Sanitize message
        $data['message'] = wp_kses_post($data['message']);

        $result = $wpdb->insert(
            $table,
            array(
                'project_id' => intval($data['project_id']),
                'user_id' => intval($data['user_id']),
                'message' => $data['message'],
                'message_type' => $data['message_type'],
                'mentioned_file_id' => !is_null($data['mentioned_file_id']) ? intval($data['mentioned_file_id']) : null,
                'file_log_id' => !is_null($data['file_log_id']) ? intval($data['file_log_id']) : null,
                'is_edited' => intval($data['is_edited']),
                'edited_at' => $data['edited_at'],
                'created_at' => $data['created_at']
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
        );

        if (defined('WP_DEBUG') && WP_DEBUG && $result === false) {
            error_log("❌ Message insert failed: " . $wpdb->last_error);
            error_log("Data: " . print_r($data, true));
        }

        if ($result === false) {
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Update project last activity
        self::update_project_activity($data['project_id']);

        return $message_id;
    }

    /**
     * Update a message
     *
     * @param int $message_id Message ID
     * @param string $new_message New message content
     * @return bool Success
     */
    public static function update_message($message_id, $new_message) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        
        // Get original message
        $message = self::get_message($message_id);
        
        if (!$message) {
            return false;
        }

        // Check if message can be edited (15 minute window)
        $created_time = strtotime($message->created_at);
        $current_time = current_time('timestamp');
        $time_diff = ($current_time - $created_time) / 60; // in minutes

        if ($time_diff > 15) {
            return false; // Edit window expired
        }

        // Check if user owns the message
        if ($message->user_id != get_current_user_id()) {
            return false;
        }

        // Sanitize new message
        $new_message = wp_kses_post($new_message);

        $result = $wpdb->update(
            $table,
            array(
                'message' => $new_message,
                'is_edited' => 1,
                'edited_at' => current_time('mysql')
            ),
            array('id' => $message_id),
            array('%s', '%d', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get a single message by ID
     *
     * @param int $message_id Message ID
     * @return object|null Message object or null
     */
    public static function get_message($message_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $message_id
        ));
    }

    /**
     * Get messages for a project with pagination
     *
     * @param int $project_id Project ID
     * @param int $limit Number of messages to retrieve
     * @param int $offset Offset for pagination
     * @param int $before_id Get messages before this ID (for load more)
     * @return array Messages
     */
    public static function get_messages($project_id, $limit = 50, $offset = 0, $before_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        
        $where = $wpdb->prepare("WHERE project_id = %d", $project_id);
        
        if ($before_id) {
            $where .= $wpdb->prepare(" AND id < %d", $before_id);
        }

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            $where 
            ORDER BY created_at DESC, id DESC 
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        // Reverse order to show oldest first
        return array_reverse($messages);
    }

    /**
     * Get unread message count for a user in a project
     *
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return int Unread count
     */
    public static function get_unread_count($project_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $messages_table = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        $read_table = $wpdb->prefix . 'hamnaghsheh_chat_read_status';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $messages_table m
            LEFT JOIN $read_table r ON m.id = r.message_id AND r.user_id = %d
            WHERE m.project_id = %d 
            AND m.user_id != %d
            AND r.id IS NULL",
            $user_id,
            $project_id,
            $user_id
        ));

        return (int) $count;
    }

    /**
     * Delete a message
     *
     * @param int $message_id Message ID
     * @return bool Success
     */
    public static function delete_message($message_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_messages';
        
        // Only allow deleting own messages
        $message = self::get_message($message_id);
        
        if (!$message || $message->user_id != get_current_user_id()) {
            return false;
        }

        $result = $wpdb->delete(
            $table,
            array('id' => $message_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Update project last activity timestamp
     *
     * @param int $project_id Project ID
     */
    private static function update_project_activity($project_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_metadata';
        
        // Try to update existing record
        $updated = $wpdb->update(
            $table,
            array('last_activity' => current_time('mysql')),
            array('project_id' => $project_id),
            array('%s'),
            array('%d')
        );

        // If no record exists, insert new one
        if ($updated === 0) {
            $wpdb->insert(
                $table,
                array(
                    'project_id' => $project_id,
                    'chat_enabled' => 1,
                    'last_activity' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
        }
    }

    /**
     * Get messages with user and read status info
     *
     * @param int $project_id Project ID
     * @param int $limit Limit
     * @param int $before_id Get messages before this ID
     * @return array Enhanced message data
     */
    public static function get_messages_with_details($project_id, $limit = 50, $before_id = null) {
        $messages = self::get_messages($project_id, $limit, 0, $before_id);
        
        foreach ($messages as &$message) {
            // Get user info
            $user = get_userdata($message->user_id);
            $message->user_name = $user ? $user->display_name : 'Unknown';
            
            // Get read receipts
            $message->read_receipts = Hamnaghsheh_Massenger_Read_Status::get_read_receipts($message->id);
            
            // Format dates (you can use jalaliDate if available from main plugin)
            $message->formatted_time = date_i18n('H:i', strtotime($message->created_at));
            $message->formatted_date = date_i18n('Y/m/d', strtotime($message->created_at));
        }

        return $messages;
    }
}
