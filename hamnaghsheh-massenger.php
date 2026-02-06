<?php
/**
 * Plugin Name: Hamnaghsheh Messenger
 * Plugin URI: https://github.com/soroushyasini/hamnaghsheh-massenger
 * Description: Real-time chat plugin for project collaboration with Hamnaghsheh PM
 * Version: 1.0.0
 * Author: Hamnaghsheh Team
 * Author URI: https://hamnaghsheh.ir
 * Text Domain: hamnaghsheh-messenger
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HAMNAGHSHEH_MESSENGER_VERSION', '1.0.0');
define('HAMNAGHSHEH_MESSENGER_FILE', __FILE__);
define('HAMNAGHSHEH_MESSENGER_DIR', plugin_dir_path(__FILE__));
define('HAMNAGHSHEH_MESSENGER_URL', plugin_dir_url(__FILE__));
define('HAMNAGHSHEH_MESSENGER_BASENAME', plugin_basename(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    // Only autoload our classes
    if (strpos($class, 'Hamnaghsheh_Messenger_') !== 0) {
        return;
    }
    
    // Convert class name to file name
    $class_file = strtolower(str_replace('_', '-', $class));
    $class_file = str_replace('hamnaghsheh-messenger-', 'class-', $class_file);
    $file_path = HAMNAGHSHEH_MESSENGER_DIR . 'includes/' . $class_file . '.php';
    
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

/**
 * Main plugin class
 */
final class Hamnaghsheh_Messenger {
    
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
        // Check if main plugin is active
        add_action('plugins_loaded', [$this, 'check_dependencies'], 5);
        
        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init'], 10);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Check if main plugin is active
     */
    public function check_dependencies() {
        if (!class_exists('Hamnaghsheh_Projects')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                echo esc_html__('Hamnaghsheh Messenger requires Hamnaghsheh PM plugin to be installed and activated.', 'hamnaghsheh-messenger');
                echo '</p></div>';
            });
            
            // Deactivate this plugin
            deactivate_plugins(HAMNAGHSHEH_MESSENGER_BASENAME);
            return false;
        }
        return true;
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('hamnaghsheh-messenger', false, dirname(HAMNAGHSHEH_MESSENGER_BASENAME) . '/languages');
        
        // Initialize components
        $this->init_components();
        
        // Register hooks
        $this->register_hooks();
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Core classes
        Hamnaghsheh_Messenger_Permissions::get_instance();
        Hamnaghsheh_Messenger_Messages::get_instance();
        Hamnaghsheh_Messenger_Seen_Tracker::get_instance();
        Hamnaghsheh_Messenger_Typing_Indicator::get_instance();
        Hamnaghsheh_Messenger_Auto_Messages::get_instance();
        Hamnaghsheh_Messenger_Notifications::get_instance();
        Hamnaghsheh_Messenger_SSE_Handler::get_instance();
        Hamnaghsheh_Messenger_API::get_instance();
        Hamnaghsheh_Messenger_Export::get_instance();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Render chat UI on project page
        add_action('hamnaghsheh_chat_render', [$this, 'render_chat_ui'], 10, 2);
        
        // Cleanup when project is deleted
        add_action('hamnaghsheh_project_deleted', [$this, 'cleanup_project_data'], 10, 1);
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Only load on project pages
        if (!is_singular() && !is_page()) {
            return;
        }
        
        // CSS files
        wp_enqueue_style(
            'hamnaghsheh-messenger-mobile',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/css/chat-mobile.css',
            [],
            HAMNAGHSHEH_MESSENGER_VERSION
        );
        
        wp_enqueue_style(
            'hamnaghsheh-messenger-desktop',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/css/chat-desktop.css',
            ['hamnaghsheh-messenger-mobile'],
            HAMNAGHSHEH_MESSENGER_VERSION
        );
        
        wp_enqueue_style(
            'hamnaghsheh-messenger-floating',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/css/chat-floating.css',
            ['hamnaghsheh-messenger-mobile'],
            HAMNAGHSHEH_MESSENGER_VERSION
        );
        
        // JavaScript files
        wp_enqueue_script(
            'hamnaghsheh-messenger-core',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/js/chat-core.js',
            ['jquery'],
            HAMNAGHSHEH_MESSENGER_VERSION,
            true
        );
        
        wp_enqueue_script(
            'hamnaghsheh-messenger-sse',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/js/chat-sse.js',
            ['hamnaghsheh-messenger-core'],
            HAMNAGHSHEH_MESSENGER_VERSION,
            true
        );
        
        wp_enqueue_script(
            'hamnaghsheh-messenger-ui',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/js/chat-ui.js',
            ['hamnaghsheh-messenger-core'],
            HAMNAGHSHEH_MESSENGER_VERSION,
            true
        );
        
        wp_enqueue_script(
            'hamnaghsheh-messenger-editor',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/js/chat-editor.js',
            ['hamnaghsheh-messenger-core'],
            HAMNAGHSHEH_MESSENGER_VERSION,
            true
        );
        
        wp_enqueue_script(
            'hamnaghsheh-messenger-mobile',
            HAMNAGHSHEH_MESSENGER_URL . 'assets/js/chat-mobile.js',
            ['hamnaghsheh-messenger-core'],
            HAMNAGHSHEH_MESSENGER_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('hamnaghsheh-messenger-core', 'hamnaghshehMessenger', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hamnaghsheh_messenger_nonce'),
            'currentUserId' => get_current_user_id(),
            'strings' => [
                'typing' => __('%s is typing...', 'hamnaghsheh-messenger'),
                'seen_by' => __('Seen by %s', 'hamnaghsheh-messenger'),
                'seen_by_all' => __('Seen by all', 'hamnaghsheh-messenger'),
                'sent' => __('Sent', 'hamnaghsheh-messenger'),
                'edit_time_expired' => __('Can only edit within 15 minutes', 'hamnaghsheh-messenger'),
                'delete_confirm' => __('Are you sure you want to delete this message?', 'hamnaghsheh-messenger'),
                'load_more' => __('Load more messages', 'hamnaghsheh-messenger'),
            ]
        ]);
    }
    
    /**
     * Show debug message (only when WP_DEBUG is enabled)
     * 
     * @param string $message Message text
     * @param string $type Type: 'error', 'warning', 'info'
     */
    private function show_debug_message($message, $type = 'error') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $colors = [
            'error' => '#d63638',
            'warning' => '#dba617',
            'info' => '#00a0d2',
        ];
        
        $icons = [
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
        ];
        
        $bg_color = isset($colors[$type]) ? $colors[$type] : $colors['error'];
        $icon = isset($icons[$type]) ? $icons[$type] : $icons['error'];
        
        echo '<div role="alert" style="position:fixed;bottom:20px;right:20px;background:' . esc_attr($bg_color) . ';color:white;padding:10px;z-index:9999;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">';
        echo '<strong>' . esc_html($icon) . ' Chat Debug:</strong> ' . esc_html($message);
        echo '</div>';
    }
    
    /**
     * Render chat UI
     */
    public function render_chat_ui($project_id, $project = null) {
        // Debug logging
        error_log('🎯 Chat render called for project: ' . $project_id);
        
        $user_id = get_current_user_id();
        error_log('👤 Current user: ' . $user_id);
        
        // Check permission
        $can_chat = Hamnaghsheh_Messenger_Permissions::can_user_chat($project_id, $user_id);
        error_log('🔐 Can user chat: ' . ($can_chat ? 'YES' : 'NO'));
        
        if (!$can_chat) {
            error_log('❌ Permission denied for user ' . $user_id . ' on project ' . $project_id);
            $this->show_debug_message('Permission Denied', 'error');
            return;
        }
        
        $template_path = HAMNAGHSHEH_MESSENGER_DIR . 'templates/chat-container.php';
        error_log('📄 Template path: ' . $template_path);
        error_log('📂 Template exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));
        
        if (!file_exists($template_path)) {
            error_log('❌ Template file missing!');
            $this->show_debug_message('Template Missing', 'warning');
            return;
        }
        
        error_log('✅ Rendering chat template');
        include $template_path;
    }
    
    /**
     * Cleanup project data when project is deleted
     */
    public function cleanup_project_data($project_id) {
        global $wpdb;
        
        // Delete messages
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}hamnaghsheh_chat_messages 
            SET deleted_at = NOW() 
            WHERE project_id = %d AND deleted_at IS NULL",
            $project_id
        ));
        
        // Delete read status
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hamnaghsheh_chat_reads 
            WHERE message_id IN (
                SELECT id FROM {$wpdb->prefix}hamnaghsheh_chat_messages WHERE project_id = %d
            )",
            $project_id
        ));
        
        // Delete typing indicators
        $wpdb->delete(
            $wpdb->prefix . 'hamnaghsheh_chat_typing',
            ['project_id' => $project_id],
            ['%d']
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        require_once HAMNAGHSHEH_MESSENGER_DIR . 'includes/class-installer.php';
        Hamnaghsheh_Messenger_Installer::activate();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        require_once HAMNAGHSHEH_MESSENGER_DIR . 'includes/class-installer.php';
        Hamnaghsheh_Messenger_Installer::deactivate();
    }
}

// Initialize plugin
function hamnaghsheh_messenger() {
    return Hamnaghsheh_Messenger::get_instance();
}

// Start the plugin
hamnaghsheh_messenger();
