<?php
/**
 * Chat Mentions Class
 * Handles @user and #file mentions
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Mentions {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // No specific hooks needed
    }
    
    /**
     * Parse input message and convert mentions to storage format
     * 
     * @param string $message Raw message text
     * @return string Message with mentions in storage format
     */
    public static function parse_input($message) {
        // Message might already contain formatted mentions from frontend
        // Frontend should send: @[user_id:display_name] and #[file_id:file_name]
        // We just sanitize and store as-is
        
        return $message;
    }
    
    /**
     * Render mentions from storage format to HTML
     * 
     * @param string $message Message with mentions in storage format
     * @param int $project_id Project ID for file links
     * @return string Message with rendered HTML mentions
     */
    public static function render($message, $project_id = 0) {
        // Convert @[user_id:display_name] to HTML
        $message = preg_replace_callback(
            '/@\[(\d+):([^\]]+)\]/',
            function($matches) {
                $user_id = $matches[1];
                $display_name = esc_html($matches[2]);
                return '<span class="hmchat-mention hmchat-mention-user" data-user-id="' . esc_attr($user_id) . '">@' . $display_name . '</span>';
            },
            $message
        );
        
        // Convert #[file_id:file_name] to HTML link
        $message = preg_replace_callback(
            '/#\[(\d+):([^\]]+)\]/',
            function($matches) use ($project_id) {
                $file_id = $matches[1];
                $file_name = esc_html($matches[2]);
                
                // Create link to file (assuming main plugin has this structure)
                $file_url = add_query_arg(
                    array(
                        'id' => $project_id,
                        'file' => $file_id
                    ),
                    home_url('/show-project')
                );
                
                return '<a class="hmchat-mention hmchat-mention-file" href="' . esc_url($file_url) . '#file-' . esc_attr($file_id) . '" data-file-id="' . esc_attr($file_id) . '">#' . $file_name . '</a>';
            },
            $message
        );
        
        return $message;
    }
    
    /**
     * Strip mentions from message for plain text export
     * 
     * @param string $message Message with mentions
     * @return string Plain text message
     */
    public static function strip_mentions($message) {
        // Convert @[user_id:display_name] to @display_name
        $message = preg_replace('/@\[\d+:([^\]]+)\]/', '@$1', $message);
        
        // Convert #[file_id:file_name] to #file_name
        $message = preg_replace('/#\[\d+:([^\]]+)\]/', '#$1', $message);
        
        // Remove any HTML tags
        $message = wp_strip_all_tags($message);
        
        return $message;
    }
    
    /**
     * Extract mentioned user IDs from message
     * 
     * @param string $message Message text
     * @return array Array of user IDs
     */
    public static function extract_mentioned_users($message) {
        $user_ids = array();
        
        preg_match_all('/@\[(\d+):[^\]]+\]/', $message, $matches);
        
        if (!empty($matches[1])) {
            $user_ids = array_map('intval', $matches[1]);
            $user_ids = array_unique($user_ids);
        }
        
        return $user_ids;
    }
    
    /**
     * Extract mentioned file IDs from message
     * 
     * @param string $message Message text
     * @return array Array of file IDs
     */
    public static function extract_mentioned_files($message) {
        $file_ids = array();
        
        preg_match_all('/#\[(\d+):[^\]]+\]/', $message, $matches);
        
        if (!empty($matches[1])) {
            $file_ids = array_map('intval', $matches[1]);
            $file_ids = array_unique($file_ids);
        }
        
        return $file_ids;
    }
}
