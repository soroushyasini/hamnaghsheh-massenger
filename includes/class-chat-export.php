<?php
/**
 * Chat Export Class
 * Handles exporting chat history to text file
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Export {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // AJAX handler for logged-in users
        add_action('wp_ajax_hmchat_export_chat', array(__CLASS__, 'ajax_export_chat'));
    }
    
    /**
     * AJAX: Export chat
     */
    public static function ajax_export_chat() {
        check_ajax_referer('hmchat_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$project_id || !$user_id) {
            wp_die('پارامترهای نامعتبر', 400);
        }
        
        // Check if user is project owner
        if (!HMChat_Access::is_project_owner($project_id, $user_id)) {
            wp_die('فقط مالک پروژه می‌تواند چت را دانلود کند', 403);
        }
        
        // Generate export
        self::export_to_file($project_id);
    }
    
    /**
     * Export chat to text file
     * 
     * @param int $project_id Project ID
     */
    private static function export_to_file($project_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
        
        // Get project name
        $projects_table = $table_prefix . 'projects';
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$projects_table} WHERE id = %d",
            $project_id
        ));
        
        if (!$project) {
            wp_die('پروژه یافت نشد', 404);
        }
        
        $project_name = sanitize_file_name($project->name);
        
        // Get all messages
        $messages_table = $table_prefix . 'chat_messages';
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_table} 
             WHERE project_id = %d 
             ORDER BY id ASC",
            $project_id
        ));
        
        // Generate file content
        $content = self::generate_export_content($messages, $project->name);
        
        // Create filename with current date
        $jalali_date = self::get_jalali_date();
        $filename = "chat-export-{$project_name}-{$jalali_date}.txt";
        
        // Set headers for file download
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Output UTF-8 BOM
        echo "\xEF\xBB\xBF";
        
        // Output content
        echo $content;
        
        exit;
    }
    
    /**
     * Generate export file content
     * 
     * @param array $messages Array of message objects
     * @param string $project_name Project name
     * @return string Export content
     */
    private static function generate_export_content($messages, $project_name) {
        $content = '';
        
        // Header
        $content .= "========================================\n";
        $content .= "گفتگوی پروژه: {$project_name}\n";
        $content .= "تاریخ دانلود: " . self::get_jalali_datetime() . "\n";
        $content .= "========================================\n\n";
        
        // Messages
        foreach ($messages as $message) {
            $time = self::format_time($message->created_at);
            
            if ($message->message_type === 'system') {
                // System message
                $text = HMChat_Mentions::strip_mentions($message->message);
                $content .= "[{$time}] 📄 سیستم:\n";
                $content .= "{$text}\n\n";
            } else {
                // User message
                $user = get_userdata($message->user_id);
                $display_name = $user ? $user->display_name : 'کاربر ناشناس';
                
                $text = HMChat_Mentions::strip_mentions($message->message);
                
                $content .= "[{$time}] {$display_name}:\n";
                $content .= "{$text}";
                
                if ($message->is_edited) {
                    $content .= " (ویرایش شده)";
                }
                
                $content .= "\n\n";
            }
        }
        
        // Footer
        $content .= "========================================\n";
        $content .= "تعداد کل پیام‌ها: " . count($messages) . "\n";
        $content .= "========================================\n";
        
        return $content;
    }
    
    /**
     * Format time from datetime
     * 
     * @param string $datetime MySQL datetime
     * @return string Formatted time in Persian
     */
    private static function format_time($datetime) {
        $timestamp = strtotime($datetime);
        $time = date('H:i', $timestamp);
        
        return self::convert_to_persian_numbers($time);
    }
    
    /**
     * Get current Jalali date
     * 
     * @return string Jalali date
     */
    private static function get_jalali_date() {
        // Simple implementation - in production use proper Jalali library
        $timestamp = current_time('timestamp');
        $date = date('Y-m-d', $timestamp);
        
        return $date;
    }
    
    /**
     * Get current Jalali datetime
     * 
     * @return string Jalali datetime
     */
    private static function get_jalali_datetime() {
        // Simple implementation - in production use proper Jalali library
        $timestamp = current_time('timestamp');
        $datetime = date('Y-m-d H:i', $timestamp);
        
        return self::convert_to_persian_numbers($datetime);
    }
    
    /**
     * Convert Latin numbers to Persian numbers
     * 
     * @param string $string String with Latin numbers
     * @return string String with Persian numbers
     */
    private static function convert_to_persian_numbers($string) {
        $persian_numbers = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $latin_numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        
        return str_replace($latin_numbers, $persian_numbers, $string);
    }
}
