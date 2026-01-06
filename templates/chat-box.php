<?php
/**
 * Chat Box Template
 *
 * This template renders the chat UI for a project
 *
 * @package Hamnaghsheh_Massenger
 * @var int $project_id Project ID from render_chat_ui()
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current user permission
$permission = Hamnaghsheh_Massenger_Permissions::get_user_permission($project_id);

if (!$permission) {
    return;
}

// Get unread count
$unread_count = Hamnaghsheh_Massenger_Messages::get_unread_count($project_id);
?>

<!-- Chat Toggle Button -->
<button class="hamnaghsheh-chat-toggle">
    💬 گفتگو
    <?php if ($unread_count > 0): ?>
        <span class="unread-badge"><?php echo esc_html($unread_count); ?></span>
    <?php endif; ?>
</button>

<!-- Chat Container -->
<div class="hamnaghsheh-chat-container" data-project-id="<?php echo esc_attr($project_id); ?>">
    <!-- Chat Header -->
    <div class="hamnaghsheh-chat-header">
        <h3>گفتگوی پروژه</h3>
        <button class="hamnaghsheh-chat-close">×</button>
    </div>

    <!-- Chat Messages -->
    <div class="hamnaghsheh-chat-messages">
        <div class="hamnaghsheh-loading">در حال بارگذاری پیام‌ها</div>
    </div>

    <!-- Editing Mode Indicator -->
    <div class="hamnaghsheh-editing-mode">
        <span>در حال ویرایش...</span>
        <button class="hamnaghsheh-cancel-edit">انصراف</button>
    </div>

    <!-- Chat Input -->
    <div class="hamnaghsheh-chat-input">
        <!-- File Autocomplete Dropdown -->
        <div class="hamnaghsheh-file-autocomplete"></div>
        
        <textarea 
            class="hamnaghsheh-message-input" 
            placeholder="<?php echo esc_attr('پیام خود را بنویسید... (@ برای اشاره به فایل)'); ?>"
            rows="1"
            <?php echo !Hamnaghsheh_Massenger_Permissions::can_send_message($project_id) ? 'disabled' : ''; ?>
        ></textarea>
        
        <button 
            class="hamnaghsheh-send-btn"
            <?php echo !Hamnaghsheh_Massenger_Permissions::can_send_message($project_id) ? 'disabled' : ''; ?>
        >
            ➤
        </button>
    </div>
</div>
