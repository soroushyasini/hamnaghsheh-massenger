<?php
/**
 * Plugin Name: Hamnaghsheh Chat
 * Plugin URI: https://github.com/soroushyasini/hamnaghsheh-massenger
 * Description: پلاگین چت و پیام‌رسانی برای سیستم مدیریت پروژه همناقشه - افزودن امکان گفتگوی تیمی در هر پروژه
 * Version: 1.0.0
 * Author: Soroush Yasini
 * Author URI: https://github.com/soroushyasini
 * Text Domain: hamnaghsheh-chat
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HMCHAT_VERSION', '1.0.0');
define('HMCHAT_DIR', plugin_dir_path(__FILE__));
define('HMCHAT_URL', plugin_dir_url(__FILE__));
define('HMCHAT_PREFIX', 'hamnaghsheh_');

/**
 * Check if main Hamnaghsheh plugin is active
 */
function hmchat_check_main_plugin() {
    // Check if main plugin class exists or is active
    $main_plugin_active = false;
    
    // Check for specific main plugin file or function
    if (function_exists('hamnaghsheh_pm_init') || 
        class_exists('Hamnaghsheh_PM') ||
        defined('HAMNAGHSHEH_VERSION')) {
        $main_plugin_active = true;
    }
    
    // Also check active plugins list
    $active_plugins = get_option('active_plugins', array());
    foreach ($active_plugins as $plugin) {
        if (strpos($plugin, 'hamnaghsheh') !== false && strpos($plugin, 'chat') === false) {
            $main_plugin_active = true;
            break;
        }
    }
    
    return $main_plugin_active;
}

/**
 * Show admin notice if main plugin is not active
 */
function hmchat_main_plugin_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <strong>هشدار:</strong> 
            پلاگین <strong>Hamnaghsheh Chat</strong> نیاز به نصب و فعال‌سازی پلاگین اصلی 
            <strong>Hamnaghsheh Project Management</strong> دارد.
        </p>
    </div>
    <?php
}

/**
 * Deactivate plugin if main plugin is not active
 */
function hmchat_activation_check() {
    if (!hmchat_check_main_plugin()) {
        // Deactivate this plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Show admin notice
        add_action('admin_notices', 'hmchat_main_plugin_notice');
        
        // Prevent activation
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
        
        return false;
    }
    return true;
}

// Require core files
require_once HMCHAT_DIR . 'includes/class-chat-activator.php';
require_once HMCHAT_DIR . 'includes/class-chat-deactivator.php';
require_once HMCHAT_DIR . 'includes/class-chat-access.php';
require_once HMCHAT_DIR . 'includes/class-chat-messages.php';
require_once HMCHAT_DIR . 'includes/class-chat-seen.php';
require_once HMCHAT_DIR . 'includes/class-chat-mentions.php';
require_once HMCHAT_DIR . 'includes/class-chat-system-messages.php';
require_once HMCHAT_DIR . 'includes/class-chat-export.php';
require_once HMCHAT_DIR . 'includes/class-chat-renderer.php';

/**
 * Plugin activation hook
 */
function hmchat_activate() {
    if (!hmchat_activation_check()) {
        return;
    }
    
    HMChat_Activator::activate();
}
register_activation_hook(__FILE__, 'hmchat_activate');

/**
 * Plugin deactivation hook
 */
function hmchat_deactivate() {
    HMChat_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'hmchat_deactivate');

/**
 * Initialize plugin
 */
function hmchat_init() {
    // Check main plugin on every admin page load
    if (is_admin() && !hmchat_check_main_plugin()) {
        add_action('admin_notices', 'hmchat_main_plugin_notice');
        return;
    }
    
    // Initialize classes
    HMChat_Access::init();
    HMChat_Messages::init();
    HMChat_Seen::init();
    HMChat_Mentions::init();
    HMChat_System_Messages::init();
    HMChat_Export::init();
    HMChat_Renderer::init();
}
add_action('plugins_loaded', 'hmchat_init');

/**
 * Enqueue frontend assets
 */
function hmchat_enqueue_scripts() {
    // Only enqueue on frontend
    if (is_admin()) {
        return;
    }
    
    // Enqueue CSS
    wp_enqueue_style(
        'hmchat-styles',
        HMCHAT_URL . 'assets/css/chat.css',
        array(),
        HMCHAT_VERSION
    );
    
    // Enqueue main chat JavaScript
    wp_enqueue_script(
        'hmchat-script',
        HMCHAT_URL . 'assets/js/chat.js',
        array('jquery'),
        HMCHAT_VERSION,
        true
    );
    
    // Enqueue mentions JavaScript
    wp_enqueue_script(
        'hmchat-mentions',
        HMCHAT_URL . 'assets/js/mentions.js',
        array('jquery', 'hmchat-script'),
        HMCHAT_VERSION,
        true
    );
    
    // Localize script with AJAX data
    wp_localize_script('hmchat-script', 'hmchat_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hmchat_nonce'),
        'user_id' => get_current_user_id(),
        'max_message_length' => 2000,
        'strings' => array(
            'sending' => 'در حال ارسال...',
            'error' => 'خطا در ارسال پیام',
            'load_more' => 'بارگذاری پیامهای قبلی...',
            'no_more' => 'پیامی وجود ندارد',
            'edited' => 'ویرایش شده',
            'system' => 'سیستم',
            'today' => 'امروز',
            'yesterday' => 'دیروز',
        ),
    ));
}
add_action('wp_enqueue_scripts', 'hmchat_enqueue_scripts');

/**
 * Load plugin text domain
 */
function hmchat_load_textdomain() {
    load_plugin_textdomain(
        'hamnaghsheh-chat',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'hmchat_load_textdomain');
