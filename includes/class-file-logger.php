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
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("🔥 Hook fired: file_id=$file_id, project=$project_id, user=$user_id, action=$action_type");
        }
        
        // Get file details
        $file = $this->get_file_by_id($file_id);
        if (!$file) {
            error_log("❌ CRITICAL: File not found for ID $file_id - skipping chat message");
            return; // File not found, skip
        }

        $user = get_userdata($user_id);
        if (!$user) {
            error_log("❌ CRITICAL: User not found for ID $user_id");
            return;
        }

        // Get the file log ID
        $file_log_id = $this->get_last_file_log_id($file_id, $action_type);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("✅ Generating message: User={$user->display_name}, File={$file->file_name}, Action=$action_type");
        }

        // Generate Persian message with actual filename
        $filename = isset($file->file_name) ? esc_html($file->file_name) : 'فایل';
        $message = $this->generate_system_message($user->display_name, $action_type, $filename);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("📝 Message text: $message");
            error_log("📎 mentioned_file_id will be: $file_id");
        }

        // Insert system message
        $message_id = Hamnaghsheh_Massenger_Messages::insert_message(array(
            'project_id' => $project_id,
            'user_id' => $user_id,
            'message' => $message,
            'message_type' => 'system',
            'mentioned_file_id' => intval($file_id), // Ensure integer
            'file_log_id' => $file_log_id ? intval($file_log_id) : null
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($message_id) {
                error_log("✅ Message inserted: ID=$message_id");
            } else {
                global $wpdb;
                error_log("❌ Message insert FAILED: " . $wpdb->last_error);
            }
        }
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
        
        // Ensure filename is not empty
        if (empty($file_name)) {
            $file_name = 'فایل';
        }

        // Format: [Username] فایل [filename] را [action]
        return sprintf('%s فایل %s را %s', $user_name, $file_name, $action_label);
    }

    /**
     * Get file by ID from main plugin
     *
     * @param int $file_id File ID
     * @return object|null File object
     */
    private function get_file_by_id($file_id) {
        global $wpdb;
        
        $files_table = $wpdb->prefix . 'hamnaghsheh_files';
        
        // Direct query without table check (table must exist if hook fired)
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$files_table} WHERE id = %d",
            $file_id
        ));
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($file) {
                error_log("✅ File found: ID=$file_id, Name=" . $file->file_name);
            } else {
                error_log("❌ File NOT found: ID=$file_id, Last error: " . $wpdb->last_error);
            }
        }
        
        return $file;
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
        
        // Direct query without table check
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$logs_table} 
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
