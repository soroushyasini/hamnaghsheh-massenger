<?php
/**
 * Chat Renderer Class
 * Handles rendering chat UI on project pages
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class HMChat_Renderer {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Hook into WordPress footer to inject chat UI
        add_action('wp_footer', array(__CLASS__, 'render_chat_container'));
        
        // Add admin menu for all chats
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 20);
    }
    
    /**
     * Render chat container in footer
     */
    public static function render_chat_container() {
        // Only render on frontend
        if (is_admin()) {
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return;
        }
        
        // Get project ID from query string (main plugin uses ?id=X for project page)
        $project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$project_id) {
            return;
        }
        
        // Check if user has access to this project's chat
        if (!HMChat_Access::can_access_chat($project_id)) {
            return;
        }
        
        // Get project members
        $members = HMChat_Access::get_project_members($project_id);
        
        // Get initial messages
        $initial_messages = HMChat_Messages::get_initial_messages($project_id);
        
        // Pass data to JavaScript
        wp_localize_script('hmchat-script', 'hmchat_project', array(
            'project_id' => $project_id,
            'members' => $members,
            'initial_messages' => $initial_messages,
            'is_owner' => HMChat_Access::is_project_owner($project_id)
        ));
        
        // Include chat template
        $template_file = HMCHAT_DIR . 'templates/chat-box.php';
        if (file_exists($template_file)) {
            include $template_file;
        }
    }
    
    /**
     * Add admin menu for all chats
     */
    public static function add_admin_menu() {
        // Check if user has admin capabilities
        if (!current_user_can('manage_options') && !current_user_can('hamnaghsheh_admin')) {
            return;
        }
        
        // Try to add as submenu under main Hamnaghsheh menu
        // The main plugin likely uses 'hamnaghsheh' as menu slug
        add_submenu_page(
            'hamnaghsheh',
            'گفتگوها',
            '💬 گفتگوها',
            'manage_options',
            'hamnaghsheh-chats',
            array(__CLASS__, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page for all chats
     */
    public static function render_admin_page() {
        include HMCHAT_DIR . 'templates/admin/all-chats.php';
    }
    
    /**
     * Get unread badge HTML
     * 
     * @param int $project_id Project ID
     * @param int $user_id User ID
     * @return string Badge HTML or empty string
     */
    public static function get_unread_badge($project_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return '';
        }
        
        $unread_count = HMChat_Seen::get_unread_count($project_id, $user_id);
        
        if ($unread_count > 0) {
            return '<span class="hmchat-unread-badge">' . $unread_count . '</span>';
        }
        
        return '';
    }
}
