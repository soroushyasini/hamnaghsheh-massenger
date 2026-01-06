<?php
/**
 * File Logger Class
 *
 * Listens to file action hooks from main plugin and injects system messages into chat
 *
 * @package Hamnaghsheh_Massenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hamnaghsheh_Massenger_File_Logger {

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
        // Hook into file action from main plugin
        add_action('hamnaghsheh_file_action', array($this, 'inject_file_log_to_chat'), 10, 4);
    }

    /**
     * Inject file action into chat as system message
     *
     * @param int $file_id File ID
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @param string $action_type Action type (upload, replace, delete, download, see)
     */
    public function inject_file_log_to_chat($file_id, $project_id, $user_id, $action_type) {
        // Get file details
        $file = $this->get_file_by_id($file_id);
        $user = get_userdata($user_id);

        if (!$user) {
            return;
        }

        $file_name = $file ? $file->file_name : 'فایل';

        // Generate Persian message
        $message = $this->generate_system_message($user->display_name, $action_type, $file_name);

        // Get the file log ID
        $file_log_id = $this->get_last_file_log_id($file_id, $action_type);

        // Insert system message
        Hamnaghsheh_Massenger_Messages::insert_message(array(
            'project_id' => $project_id,
            'user_id' => $user_id,
            'message' => $message,
            'message_type' => 'system',
            'mentioned_file_id' => $file_id,
            'file_log_id' => $file_log_id
        ));
    }

    /**
     * Generate Persian system message
     *
     * @param string $user_name User display name
     * @param string $action_type Action type
     * @param string $file_name File name
     * @return string System message
     */
    private function generate_system_message($user_name, $action_type, $file_name) {
        $actions_fa = array(
            'upload'   => 'آپلود کرد',
            'replace'  => 'جایگزین کرد',
            'delete'   => 'حذف کرد',
            'download' => 'دانلود کرد',
            'see'      => 'مشاهده کرد'
        );

        $action_label = isset($actions_fa[$action_type]) ? $actions_fa[$action_type] : $action_type;

        return sprintf('%s %s %s', $user_name, $action_label, $file_name);
    }

    /**
     * Get file by ID from main plugin
     *
     * @param int $file_id File ID
     * @return object|null File object
     */
    private function get_file_by_id($file_id) {
        global $wpdb;
        
        // Try to get from main plugin's files table
        $files_table = $wpdb->prefix . 'hamnaghsheh_files';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$files_table'") != $files_table) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d",
            $file_id
        ));
    }

    /**
     * Get last file log ID for a file and action
     *
     * @param int $file_id File ID
     * @param string $action_type Action type
     * @return int|null Log ID
     */
    private function get_last_file_log_id($file_id, $action_type) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'hamnaghsheh_file_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") != $logs_table) {
            return null;
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $logs_table 
            WHERE file_id = %d AND action_type = %s 
            ORDER BY created_at DESC 
            LIMIT 1",
            $file_id,
            $action_type
        ));
    }

    /**
     * Get action icon based on action type
     *
     * @param string $action_type Action type
     * @return string Icon
     */
    public static function get_action_icon($action_type) {
        $icons = array(
            'upload'   => '📤',
            'replace'  => '🔄',
            'delete'   => '🗑️',
            'download' => '⬇️',
            'see'      => '👁️'
        );

        return isset($icons[$action_type]) ? $icons[$action_type] : '📄';
    }
}
