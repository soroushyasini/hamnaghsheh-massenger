<?php
/**
 * Auto Messages class - handles automatic messages from file activities
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto Messages class
 */
class Hamnaghsheh_Messenger_Auto_Messages {
    
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
        // Listen to file actions from main plugin
        add_action('hamnaghsheh_file_action', [$this, 'create_file_message'], 10, 4);
    }
    
    /**
     * Create auto-message from file action
     *
     * @param int $file_id File ID
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @param string $action_type Action type (upload, replace, delete, download, see)
     */
    public function create_file_message($file_id, $project_id, $user_id, $action_type) {
        // Skip 'see' and 'download' actions (too noisy)
        if (in_array($action_type, ['see', 'download'], true)) {
            return;
        }
        
        // Get file details
        $file = $this->get_file_details($file_id);
        if (!$file) {
            return;
        }
        
        // Format message
        $message = $this->format_file_activity_message($file, $action_type);
        
        // Create metadata
        $metadata = [
            'action' => $action_type,
            'file_id' => $file_id,
            'file_name' => $file->file_name ?? 'unknown',
            'file_size' => $file->file_size ?? 0,
            'file_path' => $file->file_path ?? ''
        ];
        
        // Send message
        Hamnaghsheh_Messenger_Messages::send_message(
            $project_id,
            $user_id,
            $message,
            'file_activity',
            $metadata
        );
    }
    
    /**
     * Format file activity message
     *
     * @param object $file File object
     * @param string $action Action type
     * @return string Formatted message
     */
    private function format_file_activity_message($file, $action) {
        $file_name = $file->file_name ?? __('Unknown file', 'hamnaghsheh-messenger');
        $file_size = $this->format_file_size($file->file_size ?? 0);
        
        $messages = [
            'upload' => sprintf(
                /* translators: 1: file name, 2: file size */
                __('فایل %1$s را آپلود کرد (%2$s)', 'hamnaghsheh-messenger'),
                '<strong>' . esc_html($file_name) . '</strong>',
                $file_size
            ),
            'replace' => sprintf(
                /* translators: 1: file name */
                __('فایل %s را جایگزین کرد', 'hamnaghsheh-messenger'),
                '<strong>' . esc_html($file_name) . '</strong>'
            ),
            'delete' => sprintf(
                /* translators: 1: file name */
                __('فایل %s را حذف کرد', 'hamnaghsheh-messenger'),
                '<strong>' . esc_html($file_name) . '</strong>'
            )
        ];
        
        return $messages[$action] ?? sprintf(
            /* translators: 1: action, 2: file name */
            __('عملیات %1$s روی فایل %2$s انجام شد', 'hamnaghsheh-messenger'),
            $action,
            esc_html($file_name)
        );
    }
    
    /**
     * Get file details
     *
     * @param int $file_id File ID
     * @return object|null File object
     */
    private function get_file_details($file_id) {
        // Try to use main plugin's file function
        if (class_exists('Hamnaghsheh_File_Upload') && method_exists('Hamnaghsheh_File_Upload', 'get_file_by_id')) {
            return Hamnaghsheh_File_Upload::get_file_by_id($file_id);
        }
        
        // Fallback: basic query
        global $wpdb;
        
        // Try common table name patterns
        $possible_tables = [
            $wpdb->prefix . 'hamnaghsheh_files',
            $wpdb->prefix . 'hamnaghsheh_uploads',
            $wpdb->prefix . 'hamnaghsheh_pm_files'
        ];
        
        foreach ($possible_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($table_exists) {
                $file = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d",
                    $file_id
                ));
                if ($file) {
                    return $file;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Format file size
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
