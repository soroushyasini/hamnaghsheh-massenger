<?php
/**
 * Plugin Name: Hamnaghsheh Massenger
 * Plugin URI: https://github.com/soroushyasini/hamnaghsheh-massenger
 * Description: Project-based chat system for Hamnaghsheh PM plugin with real-time updates, file action logging, and read receipts.
 * Version: 1.0.0
 * Author: Soroush Yasini
 * Text Domain: hamnaghsheh-massenger
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HAMNAGHSHEH_MASSENGER_VERSION', '1.0.0');
define('HAMNAGHSHEH_MASSENGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HAMNAGHSHEH_MASSENGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HAMNAGHSHEH_MASSENGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook
 */
function activate_hamnaghsheh_massenger() {
    require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-activator.php';
    Hamnaghsheh_Massenger_Activator::activate();
}

/**
 * Plugin deactivation hook
 */
function deactivate_hamnaghsheh_massenger() {
    require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-deactivator.php';
    Hamnaghsheh_Massenger_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hamnaghsheh_massenger');
register_deactivation_hook(__FILE__, 'deactivate_hamnaghsheh_massenger');

/**
 * Main plugin class
 */
class Hamnaghsheh_Massenger {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-messages.php';
        require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-read-status.php';
        require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-file-logger.php';
        require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-permissions.php';
        require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-heartbeat.php';
        require_once HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'includes/class-ajax.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Render chat UI
        add_action('hamnaghsheh_chat_render', array($this, 'render_chat_ui'), 10, 1);
        
        // Initialize components
        Hamnaghsheh_Massenger_File_Logger::get_instance();
        Hamnaghsheh_Massenger_Heartbeat::get_instance();
        Hamnaghsheh_Massenger_Ajax::get_instance();
    }

    /**
     * Enqueue plugin assets
     */
    public function enqueue_assets() {
        // Only enqueue on pages where chat is needed
        global $post;
        
        // Enqueue CSS
        wp_enqueue_style(
            'hamnaghsheh-massenger-chat',
            HAMNAGHSHEH_MASSENGER_PLUGIN_URL . 'assets/css/chat.css',
            array(),
            HAMNAGHSHEH_MASSENGER_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'hamnaghsheh-massenger-chat',
            HAMNAGHSHEH_MASSENGER_PLUGIN_URL . 'assets/js/chat.js',
            array('jquery', 'heartbeat'),
            HAMNAGHSHEH_MASSENGER_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script(
            'hamnaghsheh-massenger-chat',
            'hamnaghshehChat',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hamnaghsheh_chat_nonce'),
                'strings' => array(
                    'sendButton' => 'ارسال',
                    'loadMore' => 'بارگذاری پیام‌های قدیمی‌تر',
                    'editing' => 'در حال ویرایش...',
                    'edited' => '✏️ ویرایش شده',
                    'typeMessage' => 'پیام خود را بنویسید...',
                    'seenBy' => '👁️ دیده شده توسط',
                    'notificationTitle' => 'پیام جدید',
                    'errorOccurred' => 'خطایی رخ داد. لطفا دوباره تلاش کنید.',
                )
            )
        );
    }

    /**
     * Render chat UI for a project
     */
    public function render_chat_ui($project_id) {
        // Check if chat is enabled for this project
        if (!$this->is_chat_enabled($project_id)) {
            return;
        }

        // Check user permission
        $permission = Hamnaghsheh_Massenger_Permissions::get_user_permission($project_id);
        if (!$permission) {
            return; // No access
        }

        // Load template
        include HAMNAGHSHEH_MASSENGER_PLUGIN_DIR . 'templates/chat-box.php';
    }

    /**
     * Check if chat is enabled for a project
     */
    private function is_chat_enabled($project_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hamnaghsheh_chat_metadata';
        
        $enabled = $wpdb->get_var($wpdb->prepare(
            "SELECT chat_enabled FROM $table WHERE project_id = %d",
            $project_id
        ));

        // Default to enabled if no record exists
        return $enabled === null ? true : (bool)$enabled;
    }
}

/**
 * Initialize the plugin
 */
function hamnaghsheh_massenger_init() {
    return Hamnaghsheh_Massenger::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'hamnaghsheh_massenger_init');
