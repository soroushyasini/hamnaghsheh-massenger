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
        
        // Convert #[file_id:file_name] to HTML link with direct viewer URL
        $message = preg_replace_callback(
            '/#\[(\d+):([^\]]+)\]/',
            function($matches) use ($project_id) {
                $file_id = $matches[1];
                $file_name = esc_html($matches[2]);
                
                // Get file details from database
                global $wpdb;
                $table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
                $files_table = $table_prefix . 'files';
                
                $file = $wpdb->get_row($wpdb->prepare(
                    "SELECT file_path FROM {$files_table} WHERE id = %d",
                    $file_id
                ));
                
                if (!$file || !$file->file_path) {
                    // Fallback to simple anchor link if file not found
                    return '<span class="hmchat-mention hmchat-mention-file" data-file-id="' . esc_attr($file_id) . '">#' . $file_name . '</span>';
                }
                
                // Get file extension
                $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $file_url = '';
                
                // Generate viewer URL based on extension
                switch ($extension) {
                    case 'dwg':
                    case 'dxf':
                        $file_url = 'https://hamnaghsheh.ir/dwg-viewer/?file=' . urlencode($file->file_path);
                        break;
                    
                    case 'kml':
                    case 'kmz':
                    case 'geojson':
                        $file_url = 'https://hamnaghsheh.ir/gis-viewer/?file=' . urlencode($file->file_path) . '&type=' . $extension;
                        break;
                    
                    case 'pdf':
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        $file_url = 'https://hamnaghsheh.ir/document-viewer/?file=' . urlencode($file->file_path) . '&type=' . $extension;
                        break;
                    
                    case 'txt':
                        $file_url = 'https://hamnaghsheh.ir/txt-viewer/?file=' . urlencode($file->file_path);
                        break;
                    
                    default:
                        // For unsupported file types, use generic viewer or download
                        $file_url = 'https://hamnaghsheh.ir/document-viewer/?file=' . urlencode($file->file_path) . '&type=' . $extension;
                        break;
                }
                
                return '<a class="hmchat-mention hmchat-mention-file" href="' . esc_url($file_url) . '" target="_blank" rel="noopener noreferrer" data-file-id="' . esc_attr($file_id) . '">#' . $file_name . '</a>';
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
