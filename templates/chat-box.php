<?php
/**
 * Chat Box Template
 * Main chat UI container
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure variables are set with defaults
$project_id = $project_id ?? 0;
$is_owner = $is_owner ?? false;
?>

<div class="hmchat-container minimized" id="hmchat-container">
    <!-- Header -->
    <div class="hmchat-header">
        <div class="hmchat-header-title">
            <span>💬</span>
            <span>گفتگو</span>
            <span class="hmchat-unread-badge hmchat-hidden" id="hmchat-unread-badge">0</span>
        </div>
        <div class="hmchat-header-actions">
            <?php if ($is_owner): ?>
            <button class="hmchat-header-btn" id="hmchat-export" title="دانلود گفتگو">
                📥
            </button>
            <?php endif; ?>
            <button class="hmchat-header-btn hmchat-minimize" title="بستن/باز کردن">
                <span class="minimize-icon">−</span>
            </button>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="hmchat-tabs hmchat-hidden">
        <button class="hmchat-tab" data-tab="files">
            📁 فایلها
        </button>
        <button class="hmchat-tab active" data-tab="chat">
            💬 گفتگو
        </button>
    </div>
    
    <!-- Messages Area -->
    <div class="hmchat-messages-wrapper" id="hmchat-messages">
        <!-- Load More Button -->
        <button class="hmchat-load-more" id="hmchat-load-more">
            بارگذاری پیامهای قبلی...
        </button>
        
        <!-- Messages will be inserted here by JavaScript -->
    </div>
    
    <!-- Input Area -->
    <div class="hmchat-input-wrapper">
        <!-- Mention Buttons -->
        <div class="hmchat-input-actions">
            <button class="hmchat-mention-btn hmchat-mention-user-btn" title="منشن کاربر">
                @ کاربر
            </button>
            <button class="hmchat-mention-btn hmchat-mention-file-btn" title="منشن فایل">
                # فایل
            </button>
        </div>
        
        <!-- Input Row -->
        <div class="hmchat-input-row">
            <textarea 
                class="hmchat-input" 
                id="hmchat-input" 
                placeholder="پیام خود را بنویسید..."
                rows="1"
            ></textarea>
            <button class="hmchat-send-btn" id="hmchat-send">
                ارسال
            </button>
        </div>
        
        <!-- Autocomplete Dropdown (created by JavaScript) -->
    </div>
</div>

<script>
// Export chat functionality
jQuery(document).ready(function($) {
    $('#hmchat-export').on('click', function() {
        if (!confirm('آیا می‌خواهید گفتگو را دانلود کنید؟')) {
            return;
        }
        
        // Create a form and submit it
        var form = $('<form>', {
            'method': 'POST',
            'action': hmchat_ajax.ajax_url
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'hmchat_export_chat'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': hmchat_ajax.nonce
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'project_id',
            'value': hmchat_project.project_id
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
    
    // Auto-resize textarea
    $('#hmchat-input').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
});
</script>
