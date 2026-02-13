<?php
/**
 * Chat Messages Class
 * Handles message CRUD operations and AJAX handlers
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Messages {
    
    /**
     * Rate limiting: messages per user
     */
    private static $rate_limit_messages = 30;
    private static $rate_limit_window = 60; // seconds
    
    /**
     * Maximum message length
     */
    private static $max_message_length = 2000;
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_hmchat_send_message', array(__CLASS__, 'ajax_send_message'));
        add_action('wp_ajax_hmchat_fetch_messages', array(__CLASS__, 'ajax_fetch_messages'));
        add_action('wp_ajax_hmchat_load_earlier', array(__CLASS__, 'ajax_load_earlier'));
        add_action('wp_ajax_hmchat_edit_message', array(__CLASS__, 'ajax_edit_message'));
        add_action('wp_ajax_hmchat_get_members', array(__CLASS__, 'ajax_get_members'));
        add_action('wp_ajax_hmchat_get_files', array(__CLASS__, 'ajax_get_files'));
        add_action('wp_ajax_hmchat_get_project_files', array(__CLASS__, 'ajax_get_project_files'));
    }
    
    /**
     * AJAX: Send message
     */
    public static function ajax_send_message() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $message = isset($_POST['message']) ? $_POST['message'] : '';
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        // Rate limiting check
        if (!self::check_rate_limit($user_id)) {
            wp_send_json_error(array('message' => 'تعداد پیام‌های شما از حد مجاز گذشته است. لطفاً کمی صبر کنید.'));
        }
        
        // Sanitize and validate message
        $message = trim($message);
        
        if (empty($message)) {
            wp_send_json_error(array('message' => 'پیام نمی‌تواند خالی باشد'));
        }
        
        if (mb_strlen($message) > self::$max_message_length) {
            wp_send_json_error(array('message' => 'پیام نمی‌تواند بیشتر از ' . self::$max_message_length . ' کاراکتر باشد'));
        }
        
        // Parse mentions before saving
        $message = HMChat_Mentions::parse_input($message);
        
        // Sanitize message (allow some HTML for mentions)
        $message = wp_kses_post($message);
        
        // Insert message
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $inserted = $wpdb->insert(
            $messages_table,
            array(
                'project_id' => $project_id,
                'user_id' => $user_id,
                'message' => $message,
                'message_type' => 'text',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if (!$inserted) {
            wp_send_json_error(array('message' => 'خطا در ذخیره پیام'));
        }
        
        $message_id = $wpdb->insert_id;
        
        // Get message data with user info
        $message_data = self::get_message_data($message_id);
        
        wp_send_json_success(array(
            'message' => $message_data
        ));
    }
    
    /**
     * AJAX: Fetch new messages
     */
    public static function ajax_fetch_messages() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $last_message_id = isset($_POST['last_message_id']) ? intval($_POST['last_message_id']) : 0;
        $last_log_id = isset($_POST['last_log_id']) ? intval($_POST['last_log_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        
        // Fetch user messages
        $user_messages = $wpdb->get_results($wpdb->prepare("
            SELECT 
                m.id,
                m.user_id,
                m.message,
                m.created_at,
                m.is_edited,
                m.edited_at,
                m.message_type,
                u.display_name,
                'user' as msg_type
            FROM {$table_prefix}chat_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.project_id = %d
                AND m.id > %d
                AND m.message_type IN ('text', 'system', 'system_digest')
            ORDER BY m.id ASC
            LIMIT 50
        ", $project_id, $last_message_id));
        
        // Fetch file logs as system messages
        $file_logs = $wpdb->get_results($wpdb->prepare("
            SELECT 
                fl.id as log_id,
                fl.user_id,
                fl.action_type,
                fl.file_id,
                f.file_name,
                f.file_path,
                fl.created_at,
                u.display_name,
                'system' as msg_type
            FROM {$table_prefix}file_logs fl
            LEFT JOIN {$table_prefix}files f ON fl.file_id = f.id
            LEFT JOIN {$wpdb->users} u ON fl.user_id = u.ID
            WHERE fl.project_id = %d
                AND fl.id > %d
            ORDER BY fl.id ASC
            LIMIT 50
        ", $project_id, $last_log_id));
        
        // Format file logs as system messages
        $formatted_file_logs = array();
        foreach ($file_logs as $log) {
            $formatted_file_logs[] = array(
                'id' => 'log_' . $log->log_id,
                'log_id' => $log->log_id,
                'user_id' => $log->user_id,
                'message' => self::format_file_log_message($log),
                'message_type' => 'system',
                'created_at' => $log->created_at,
                'msg_type' => 'system'
            );
        }
        
        // Format user messages
        $formatted_user_messages = self::format_messages($user_messages);
        
        // Merge and sort chronologically
        $all_messages = array_merge($formatted_user_messages, $formatted_file_logs);
        usort($all_messages, function($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });
        
        wp_send_json_success(array(
            'messages' => $all_messages,
            'count' => count($all_messages)
        ));
    }
    
    /**
     * AJAX: Load earlier messages
     */
    public static function ajax_load_earlier() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $before_id = isset($_POST['before_id']) ? intval($_POST['before_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        // Get earlier messages
        $messages = self::get_messages_before($project_id, $before_id, 30);
        
        wp_send_json_success(array(
            'messages' => $messages,
            'count' => count($messages),
            'has_more' => count($messages) === 30
        ));
    }
    
    /**
     * AJAX: Edit message
     */
    public static function ajax_edit_message() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $new_message = isset($_POST['message']) ? $_POST['message'] : '';
        $user_id = get_current_user_id();
        
        if (!$message_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check if user can edit this message
        if (!HMChat_Access::can_edit_message($message_id, $user_id)) {
            wp_send_json_error(array('message' => 'شما مجاز به ویرایش این پیام نیستید'));
        }
        
        // Sanitize and validate message
        $new_message = trim($new_message);
        
        if (empty($new_message)) {
            wp_send_json_error(array('message' => 'پیام نمی‌تواند خالی باشد'));
        }
        
        if (mb_strlen($new_message) > self::$max_message_length) {
            wp_send_json_error(array('message' => 'پیام نمی‌تواند بیشتر از ' . self::$max_message_length . ' کاراکتر باشد'));
        }
        
        // Parse mentions
        $new_message = HMChat_Mentions::parse_input($new_message);
        
        // Sanitize message
        $new_message = wp_kses_post($new_message);
        
        // Update message
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $updated = $wpdb->update(
            $messages_table,
            array(
                'message' => $new_message,
                'is_edited' => 1,
                'edited_at' => current_time('mysql')
            ),
            array('id' => $message_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        // Check for actual database error (false) vs no rows updated (0)
        if ($updated === false) {
            wp_send_json_error(array('message' => 'خطا در ویرایش پیام'));
        } elseif ($updated === 0) {
            // No rows updated - message content was identical
            // This is acceptable, continue to return success
        }
        
        // Get updated message data
        $message_data = self::get_message_data($message_id);
        
        wp_send_json_success(array(
            'message' => $message_data
        ));
    }
    
    /**
     * AJAX: Get project members
     */
    public static function ajax_get_members() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        $members = HMChat_Access::get_project_members($project_id);
        
        wp_send_json_success(array(
            'members' => $members
        ));
    }
    
    /**
     * AJAX: Get project files
     */
    public static function ajax_get_files() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $files_table = $table_prefix . 'files';
        
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_name FROM {$files_table} 
             WHERE project_id = %d 
             ORDER BY uploaded_at DESC 
             LIMIT 100",
            $project_id
        ), ARRAY_A);
        
        wp_send_json_success(array(
            'files' => $files ? $files : array()
        ));
    }
    
    /**
     * Get messages after a specific ID
     */
    private static function get_messages_after($project_id, $after_id, $limit = 50) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_table} 
             WHERE project_id = %d AND id > %d 
             ORDER BY id ASC 
             LIMIT %d",
            $project_id,
            $after_id,
            $limit
        ));
        
        return self::format_messages($messages);
    }
    
    /**
     * Get messages before a specific ID
     */
    private static function get_messages_before($project_id, $before_id, $limit = 30) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_table} 
             WHERE project_id = %d AND id < %d 
             ORDER BY id DESC 
             LIMIT %d",
            $project_id,
            $before_id,
            $limit
        ));
        
        // Reverse to get chronological order
        $messages = array_reverse($messages);
        
        return self::format_messages($messages);
    }
    
    /**
     * Get single message data
     */
    private static function get_message_data($message_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$messages_table} WHERE id = %d",
            $message_id
        ));
        
        if (!$message) {
            return null;
        }
        
        $formatted = self::format_messages(array($message));
        return $formatted[0] ?? null;
    }
    
    /**
     * Format messages with user data and seen status
     */
    private static function format_messages($messages) {
        if (empty($messages)) {
            return array();
        }
        
        $formatted = array();
        
        foreach ($messages as $message) {
            $user_id = $message->user_id;
            $user_data = null;
            
            if ($user_id > 0) {
                $user = get_userdata($user_id);
                if ($user) {
                    $user_data = array(
                        'id' => $user_id,
                        'display_name' => $user->display_name,
                        'avatar_url' => get_avatar_url($user_id, array('size' => 48)),
                        'is_admin' => user_can($user_id, 'manage_options') || user_can($user_id, 'hamnaghsheh_admin')
                    );
                }
            }
            
            // Get seen data
            $seen_data = HMChat_Seen::get_message_seen_status($message->id);
            
            // Parse mentions in message
            $message_text = HMChat_Mentions::render($message->message, $message->project_id);
            
            $formatted[] = array(
                'id' => $message->id,
                'project_id' => $message->project_id,
                'user_id' => $user_id,
                'user' => $user_data,
                'message' => $message_text,
                'message_type' => $message->message_type,
                'is_edited' => (bool)$message->is_edited,
                'edited_at' => $message->edited_at,
                'created_at' => $message->created_at,
                'seen_count' => $seen_data['count'],
                'seen_by' => $seen_data['users']
            );
        }
        
        return $formatted;
    }
    
    /**
     * Check rate limit for user
     */
    private static function check_rate_limit($user_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$messages_table} 
             WHERE user_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)
             AND message_type = 'text'",
            $user_id,
            self::$rate_limit_window
        ));
        
        return $count < self::$rate_limit_messages;
    }
    
    /**
     * Get initial messages for a project
     */
    public static function get_initial_messages($project_id, $limit = 50) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $messages_table = $table_prefix . 'chat_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_table} 
             WHERE project_id = %d 
             ORDER BY id DESC 
             LIMIT %d",
            $project_id,
            $limit
        ));
        
        // Reverse to get chronological order
        $messages = array_reverse($messages);
        
        return self::format_messages($messages);
    }
    
    /**
     * Format file log message
     */
    private static function format_file_log_message($log) {
        $actions = array(
            'upload' => 'آپلود کرد',
            'delete' => 'حذف کرد',
            'replace' => 'جایگزین کرد',
            'download' => 'دانلود کرد',
            'see' => 'مشاهده کرد'
        );
        
        $action_text = isset($actions[$log->action_type]) ? $actions[$log->action_type] : 'عملیات کرد';
        
        // Create file mention
        if ($log->file_id && $log->file_name) {
            $file_mention = "#[{$log->file_id}:{$log->file_name}]";
        } else {
            $file_mention = 'فایل';
        }
        
        return "📄 {$log->display_name} فایل {$file_mention} را {$action_text}";
    }
    
    /**
     * AJAX: Get project files for files tab
     */
    public static function ajax_get_project_files() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(array('message' => 'پارامترهای نامعتبر'));
        }
        
        // Check access
        if (!HMChat_Access::can_access_chat($project_id, $user_id)) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        $files_table = $table_prefix . 'files';
        
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_name, file_path, file_size, uploaded_at
             FROM {$files_table}
             WHERE project_id = %d
             ORDER BY uploaded_at DESC",
            $project_id
        ), ARRAY_A);
        
        wp_send_json_success(array('files' => $files ? $files : array()));
    }
}
