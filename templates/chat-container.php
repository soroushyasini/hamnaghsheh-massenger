<?php
/**
 * Chat Container Template
 * 
 * Renders the floating chat button and chat overlay
 * 
 * @package Hamnaghsheh_Messenger
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get project ID from passed variables
$project_id = isset($project_id) ? $project_id : (isset($project->id) ? $project->id : 0);
$current_user = wp_get_current_user();

if (!$project_id) {
    error_log('❌ Chat template: No project ID provided');
    return;
}

error_log("✅ Chat template rendering for project $project_id, user {$current_user->ID}");
?>

<!-- Floating Action Button -->
<div id="hamnaghsheh-chat-fab" class="chat-fab" data-project-id="<?php echo esc_attr($project_id); ?>">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
    <span class="badge" id="chat-unread-badge" style="display: none;">0</span>
</div>

<!-- Chat Overlay (Hidden by default) -->
<div id="hamnaghsheh-chat-overlay" class="chat-overlay" style="display: none;">
    <div class="chat-container" data-project-id="<?php echo esc_attr($project_id); ?>">
        
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="chat-header-content">
                <h3 class="chat-title">💬 گفتگوی پروژه</h3>
                <p class="chat-subtitle" id="chat-online-users">در حال بارگذاری...</p>
            </div>
            <button type="button" class="chat-close" id="hamnaghsheh-chat-close" aria-label="Close chat">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <!-- Messages Container -->
        <div class="chat-messages-wrapper">
            <div class="chat-messages" id="hamnaghsheh-chat-messages" data-project-id="<?php echo esc_attr($project_id); ?>">
                <!-- Load more button -->
                <div class="load-more-container" style="text-align: center; padding: 10px; display: none;">
                    <button type="button" class="load-more-btn" id="chat-load-more">
                        بارگذاری پیامهای قبلی
                    </button>
                </div>
                
                <!-- Messages will be dynamically inserted here -->
                <div class="chat-loading" id="chat-initial-loading">
                    <div class="loading-spinner"></div>
                    <p>در حال بارگذاری پیامها...</p>
                </div>
            </div>
        </div>
        
        <!-- Typing Indicator -->
        <div class="chat-typing-indicator" id="hamnaghsheh-typing-indicator" style="display: none;">
            <div class="typing-avatar"></div>
            <div class="typing-content">
                <span class="typing-user"></span>
                <span class="typing-dots">
                    <span>.</span><span>.</span><span>.</span>
                </span>
            </div>
        </div>
        
        <!-- Message Input Area -->
        <div class="chat-input-wrapper">
            <div class="chat-input-container">
                <textarea 
                    id="hamnaghsheh-chat-input" 
                    class="chat-input" 
                    placeholder="پیام خود را بنویسید..."
                    rows="1"
                    maxlength="2000"
                    data-project-id="<?php echo esc_attr($project_id); ?>"
                ></textarea>
                <button 
                    type="button" 
                    class="chat-send-btn" 
                    id="hamnaghsheh-chat-send"
                    aria-label="Send message"
                    disabled
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
            <div class="chat-input-footer">
                <span class="char-count"><span id="char-counter">0</span> / 2000</span>
            </div>
        </div>
    </div>
</div>

<!-- Message Context Menu (for edit/delete) -->
<div id="chat-context-menu" class="chat-context-menu" style="display: none;">
    <button type="button" class="context-menu-item" data-action="edit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
        </svg>
        ویرایش
    </button>
    <button type="button" class="context-menu-item" data-action="delete">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
        حذف
    </button>
</div>

<script>
console.log('✅ Chat template loaded for project <?php echo $project_id; ?>');
console.log('✅ Chat overlay element:', document.getElementById('hamnaghsheh-chat-overlay') ? 'EXISTS' : 'MISSING');
console.log('✅ Chat FAB element:', document.getElementById('hamnaghsheh-chat-fab') ? 'EXISTS' : 'MISSING');
</script>
