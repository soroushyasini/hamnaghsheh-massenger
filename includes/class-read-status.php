<?php
/**
 * Read Status Class
 *
 * Handles read receipts for messages (WhatsApp-style)
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_Read_Status {

    /**
     * Mark a message as read by a user
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID (defaults to current user)
     * @return bool Success
     */
    public static function mark_as_read($message_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table = $wpdb->prefix . 'hamnaghsheh_chat_read_status';

        // Check if already marked as read
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE message_id = %d AND user_id = %d",
            $message_id,
            $user_id
        ));

        if ($exists) {
            return true; // Already read
        }

        // Insert read status
        $result = $wpdb->insert(
            $table,
            array(
                'message_id' => $message_id,
                'user_id' => $user_id,
                'read_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );

        return $result !== false;
    }

    /**
     * Mark multiple messages as read
     *
     * @param array $message_ids Array of message IDs
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function mark_multiple_as_read($message_ids, $user_id = null) {
        if (empty($message_ids)) {
            return true;
        }

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        foreach ($message_ids as $message_id) {
            self::mark_as_read($message_id, $user_id);
        }

        return true;
    }

    /**
     * Get read receipts for a message with caching
     * Returns array of users who read the message with timestamps
     *
     * @param int $message_id Message ID
     * @return array Read receipts
     */
    public static function get_read_receipts($message_id) {
        // Try to get from cache (30-second transient)
        $cache_key = 'hamnaghsheh_chat_receipts_' . $message_id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_read_status';
        
        $receipts = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, read_at FROM $table WHERE message_id = %d ORDER BY read_at ASC",
            $message_id
        ), ARRAY_A);

        $formatted_receipts = array();
        
        foreach ($receipts as $receipt) {
            $user = get_userdata($receipt['user_id']);
            if ($user) {
                $formatted_receipts[] = array(
                    'user_id' => $receipt['user_id'],
                    'user_name' => $user->display_name,
                    'read_at' => $receipt['read_at'],
                    'formatted_time' => date_i18n('H:i', strtotime($receipt['read_at']))
                );
            }
        }

        // Cache for 30 seconds
        set_transient($cache_key, $formatted_receipts, 30);

        return $formatted_receipts;
    }

    /**
     * Get formatted read receipts string (WhatsApp-style)
     *
     * @param int $message_id Message ID
     * @return string Formatted string
     */
    public static function get_formatted_receipts($message_id) {
        $receipts = self::get_read_receipts($message_id);

        if (empty($receipts)) {
            return '';
        }

        $parts = array();
        foreach ($receipts as $receipt) {
            $parts[] = sprintf(
                '%s (%s)',
                $receipt['user_name'],
                $receipt['formatted_time']
            );
        }

        return '👁️ دیده شده توسط ' . implode(', ', $parts);
    }

    /**
     * Check if a message is read by a user
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID
     * @return bool True if read
     */
    public static function is_read($message_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table = $wpdb->prefix . 'hamnaghsheh_chat_read_status';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE message_id = %d AND user_id = %d",
            $message_id,
            $user_id
        ));

        return (bool) $exists;
    }

    /**
     * Get count of users who read a message
     *
     * @param int $message_id Message ID
     * @return int Count
     */
    public static function get_read_count($message_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hamnaghsheh_chat_read_status';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE message_id = %d",
            $message_id
        ));
    }
}
