<?php
/**
 * Chat Container Template
 * Main chat UI structure
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$project_id = isset($project_id) ? intval($project_id) : 0;
$unread_count = Hamnaghsheh_Messenger_Notifications::get_project_unread($project_id, get_current_user_id());
?>

<!-- Floating Action Button -->
<div id="chat-floating-button" class="chat-fab" data-project-id="<?php echo esc_attr($project_id); ?>">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
    </svg>
    <span class="badge" id="chat-unread-badge"><?php echo $unread_count > 0 ? esc_html($unread_count) : ''; ?></span>
</div>

<!-- Chat Overlay -->
<div id="chat-overlay" class="chat-overlay" dir="rtl" style="display: none;">
    
    <!-- Header -->
    <div class="chat-header">
        <h3>💬 <?php esc_html_e('Chat', 'hamnaghsheh-messenger'); ?></h3>
        <button type="button" id="chat-close" class="chat-close" aria-label="<?php esc_attr_e('Close chat', 'hamnaghsheh-messenger'); ?>">
            ×
        </button>
    </div>
    
    <!-- Messages Container -->
    <div class="chat-messages" id="chat-messages-container">
        <!-- Messages will be inserted here by JavaScript -->
        <div class="chat-loading">
            <div class="chat-loading-spinner"></div>
        </div>
    </div>
    
    <!-- Typing Indicator -->
    <div class="chat-typing-indicator" id="typing-indicator" style="display: none;">
        <!-- Typing status will be inserted here -->
    </div>
    
    <!-- Input Wrapper -->
    <div class="chat-input-wrapper">
        <textarea 
            id="chat-input" 
            placeholder="<?php esc_attr_e('Type a message...', 'hamnaghsheh-messenger'); ?>"
            rows="1"
            aria-label="<?php esc_attr_e('Message input', 'hamnaghsheh-messenger'); ?>"
        ></textarea>
        <button type="button" id="chat-send" aria-label="<?php esc_attr_e('Send message', 'hamnaghsheh-messenger'); ?>">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
        </button>
    </div>
    
</div>
