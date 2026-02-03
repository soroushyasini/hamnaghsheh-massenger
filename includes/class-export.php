<?php
/**
 * Export class - handles chat export to TXT/PDF
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Export class
 */
class Hamnaghsheh_Messenger_Export {
    
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
     * Export chat to TXT
     *
     * @param int $project_id Project ID
     * @return string|false File path or false on failure
     */
    public static function export_to_txt($project_id) {
        global $wpdb;
        
        // Get project
        $project = get_post($project_id);
        if (!$project) {
            return false;
        }
        
        // Get all messages
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name
            FROM {$wpdb->prefix}hamnaghsheh_chat_messages m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.project_id = %d AND m.deleted_at IS NULL
            ORDER BY m.created_at ASC",
            $project_id
        ));
        
        if (empty($messages)) {
            return false;
        }
        
        // Build TXT content
        $content = "========================================\n";
        $content .= sprintf("Chat History: %s\n", $project->post_title);
        $content .= sprintf("Exported: %s\n", current_time('Y-m-d H:i:s'));
        $content .= "========================================\n\n";
        
        foreach ($messages as $message) {
            $sender = $message->display_name ?: __('System', 'hamnaghsheh-messenger');
            $time = date('Y-m-d H:i:s', strtotime($message->created_at));
            $msg_text = wp_strip_all_tags($message->message);
            
            $content .= "[{$time}] {$sender}:\n";
            $content .= "{$msg_text}\n";
            
            if ($message->edited_at) {
                $content .= sprintf("  (Edited at %s)\n", $message->edited_at);
            }
            
            if ($message->message_type === 'file_activity') {
                $content .= "  [File Activity]\n";
            }
            
            $content .= "\n";
        }
        
        // Create temp file
        $upload_dir = wp_upload_dir();
        $file_name = 'chat-' . $project_id . '-' . time() . '.txt';
        $file_path = $upload_dir['basedir'] . '/hamnaghsheh-messenger/' . $file_name;
        
        // Create directory if not exists
        wp_mkdir_p(dirname($file_path));
        
        // Write file
        $result = file_put_contents($file_path, $content);
        
        if ($result === false) {
            return false;
        }
        
        return $file_path;
    }
    
    /**
     * Export chat to PDF (basic implementation)
     * For full PDF support, consider using TCPDF or mPDF library
     *
     * @param int $project_id Project ID
     * @return string|false File path or false on failure
     */
    public static function export_to_pdf($project_id) {
        // For now, return TXT export
        // In production, this would use TCPDF or mPDF
        // Example with TCPDF:
        // require_once 'tcpdf/tcpdf.php';
        // $pdf = new TCPDF();
        // ... add content ...
        // $pdf->Output($file_path, 'F');
        
        return self::export_to_txt($project_id);
    }
    
    /**
     * Generate download link for export
     *
     * @param int $project_id Project ID
     * @param string $format Format (txt or pdf)
     * @return string|false Download URL or false
     */
    public static function generate_download_link($project_id, $format = 'txt') {
        $file_path = null;
        
        if ($format === 'pdf') {
            $file_path = self::export_to_pdf($project_id);
        } else {
            $file_path = self::export_to_txt($project_id);
        }
        
        if (!$file_path) {
            return false;
        }
        
        // Convert to URL
        $upload_dir = wp_upload_dir();
        $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
        
        return $file_url;
    }
    
    /**
     * Cleanup old export files (older than 1 hour)
     */
    public static function cleanup_old_exports() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/hamnaghsheh-messenger/';
        
        if (!is_dir($export_dir)) {
            return;
        }
        
        $files = glob($export_dir . 'chat-*.txt');
        $one_hour_ago = time() - 3600;
        
        foreach ($files as $file) {
            if (filemtime($file) < $one_hour_ago) {
                @unlink($file);
            }
        }
    }
}
