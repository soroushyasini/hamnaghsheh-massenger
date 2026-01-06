/**
 * Hamnaghsheh Massenger Chat JavaScript
 * Handles real-time updates, UI interactions, and AJAX requests
 */

(function($) {
    'use strict';

    let chatOpen = false;
    let currentProjectId = null;
    let lastMessageId = 0;
    let editingMessageId = null;
    let notificationsEnabled = false;

    /**
     * Initialize chat
     */
    function initChat() {
        // Get project ID from container
        currentProjectId = $('.hamnaghsheh-chat-container').data('project-id');
        
        if (!currentProjectId) {
            return;
        }

        // Setup event listeners
        setupEventListeners();

        // Setup heartbeat
        setupHeartbeat();

        // Request notification permission
        requestNotificationPermission();
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Toggle chat
        $(document).on('click', '.hamnaghsheh-chat-toggle', function() {
            toggleChat();
        });

        // Close chat
        $(document).on('click', '.hamnaghsheh-chat-close', function() {
            closeChat();
        });

        // Send message
        $(document).on('click', '.hamnaghsheh-send-btn', function() {
            sendMessage();
        });

        // Send on Enter (without Shift)
        $(document).on('keydown', '.hamnaghsheh-message-input', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Edit message
        $(document).on('click', '.hamnaghsheh-edit-btn', function() {
            const messageId = $(this).data('message-id');
            const messageText = $(this).closest('.hamnaghsheh-message').find('.hamnaghsheh-message-text').text();
            startEditing(messageId, messageText);
        });

        // Cancel edit
        $(document).on('click', '.hamnaghsheh-cancel-edit', function() {
            cancelEditing();
        });

        // Load more messages
        $(document).on('click', '.hamnaghsheh-load-more', function() {
            loadMoreMessages();
        });

        // File mention autocomplete
        $(document).on('input', '.hamnaghsheh-message-input', function() {
            handleFileAutocomplete($(this).val());
        });

        // Select file from autocomplete
        $(document).on('click', '.hamnaghsheh-file-item', function() {
            const fileName = $(this).find('.hamnaghsheh-file-name').text();
            const fileId = $(this).data('file-id');
            insertFileMention(fileName, fileId);
        });
    }

    /**
     * Setup WordPress Heartbeat for real-time updates
     */
    function setupHeartbeat() {
        // Listen to heartbeat send
        $(document).on('heartbeat-send', function(e, data) {
            if (currentProjectId) {
                data.hamnaghsheh_chat = {
                    project_id: currentProjectId,
                    last_message_id: lastMessageId
                };
            }
        });

        // Listen to heartbeat tick
        $(document).on('heartbeat-tick', function(e, data) {
            if (data.hamnaghsheh_chat) {
                handleHeartbeatResponse(data.hamnaghsheh_chat);
            }
        });

        // Set initial heartbeat interval
        updateHeartbeatInterval();
    }

    /**
     * Update heartbeat interval based on chat state
     * Conditional Heartbeat Optimization: 15s when open, 120s when closed
     */
    function updateHeartbeatInterval() {
        if (typeof wp !== 'undefined' && wp.heartbeat) {
            if (chatOpen) {
                wp.heartbeat.interval(15); // Active polling when chat is open
            } else {
                wp.heartbeat.interval(120); // Slow down to 2 minutes when closed
            }
        }
    }

    /**
     * Handle heartbeat response with new messages
     */
    function handleHeartbeatResponse(data) {
        // Update unread count
        if (data.unread_count !== undefined) {
            updateUnreadCount(data.unread_count);
        }

        // Add new messages
        if (data.new_messages && data.new_messages.length > 0) {
            data.new_messages.forEach(function(message) {
                appendMessage(message);
                
                // Show notification if chat is closed
                if (!chatOpen) {
                    showNotification(message);
                }
            });

            // Mark as read if chat is open
            if (chatOpen) {
                markMessagesAsRead();
            }
        }
    }

    /**
     * Toggle chat open/closed
     */
    function toggleChat() {
        if (chatOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    /**
     * Open chat
     */
    function openChat() {
        chatOpen = true;
        $('.hamnaghsheh-chat-container').addClass('open');
        $('.hamnaghsheh-chat-toggle').hide();
        
        // Update heartbeat interval
        updateHeartbeatInterval();

        // Load messages if not loaded
        if (lastMessageId === 0) {
            loadMessages();
        } else {
            // Mark existing messages as read
            markMessagesAsRead();
            scrollToBottom();
        }

        // Focus input
        $('.hamnaghsheh-message-input').focus();
    }

    /**
     * Close chat
     */
    function closeChat() {
        chatOpen = false;
        $('.hamnaghsheh-chat-container').removeClass('open');
        $('.hamnaghsheh-chat-toggle').show();
        
        // Update heartbeat interval
        updateHeartbeatInterval();
    }

    /**
     * Load initial messages
     */
    function loadMessages() {
        $.ajax({
            url: hamnaghshehChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hamnaghsheh_load_messages',
                nonce: hamnaghshehChat.nonce,
                project_id: currentProjectId
            },
            success: function(response) {
                if (response.success) {
                    $('.hamnaghsheh-chat-messages').empty();
                    
                    // Add load more button if we have messages
                    if (response.data.messages.length === 50) {
                        $('.hamnaghsheh-chat-messages').append(
                            '<button class="hamnaghsheh-load-more">' + 
                            hamnaghshehChat.strings.loadMore + 
                            '</button>'
                        );
                    }

                    response.data.messages.forEach(function(message) {
                        appendMessage(message);
                    });

                    // Disable input if user can't send
                    if (!response.data.can_send) {
                        $('.hamnaghsheh-message-input').prop('disabled', true);
                        $('.hamnaghsheh-send-btn').prop('disabled', true);
                    }

                    scrollToBottom();
                    markMessagesAsRead();
                }
            },
            error: function() {
                showError(hamnaghshehChat.strings.errorOccurred);
            }
        });
    }

    /**
     * Load more older messages (pagination)
     */
    function loadMoreMessages() {
        const firstMessageId = $('.hamnaghsheh-message:not(.system)').first().data('message-id');
        
        if (!firstMessageId) {
            return;
        }

        $('.hamnaghsheh-load-more').prop('disabled', true).text('در حال بارگذاری...');

        $.ajax({
            url: hamnaghshehChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hamnaghsheh_load_more_messages',
                nonce: hamnaghshehChat.nonce,
                project_id: currentProjectId,
                before_id: firstMessageId
            },
            success: function(response) {
                if (response.success) {
                    const messages = response.data.messages;
                    
                    // Remove load more button
                    $('.hamnaghsheh-load-more').remove();

                    // Add new load more button if has more
                    if (response.data.has_more) {
                        $('.hamnaghsheh-chat-messages').prepend(
                            '<button class="hamnaghsheh-load-more">' + 
                            hamnaghshehChat.strings.loadMore + 
                            '</button>'
                        );
                    }

                    // Prepend messages
                    messages.reverse().forEach(function(message) {
                        prependMessage(message);
                    });
                }
            },
            error: function() {
                $('.hamnaghsheh-load-more').prop('disabled', false).text(hamnaghshehChat.strings.loadMore);
                showError(hamnaghshehChat.strings.errorOccurred);
            }
        });
    }

    /**
     * Send a message
     */
    function sendMessage() {
        const input = $('.hamnaghsheh-message-input');
        const message = input.val().trim();

        if (!message) {
            return;
        }

        // Check if editing
        if (editingMessageId) {
            editMessage(editingMessageId, message);
            return;
        }

        // Disable input
        input.prop('disabled', true);
        $('.hamnaghsheh-send-btn').prop('disabled', true);

        $.ajax({
            url: hamnaghshehChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hamnaghsheh_send_message',
                nonce: hamnaghshehChat.nonce,
                project_id: currentProjectId,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    input.val('').prop('disabled', false);
                    $('.hamnaghsheh-send-btn').prop('disabled', false);
                    input.focus();
                    
                    // Message will be added via heartbeat
                } else {
                    showError(response.data.message);
                    input.prop('disabled', false);
                    $('.hamnaghsheh-send-btn').prop('disabled', false);
                }
            },
            error: function() {
                showError(hamnaghshehChat.strings.errorOccurred);
                input.prop('disabled', false);
                $('.hamnaghsheh-send-btn').prop('disabled', false);
            }
        });
    }

    /**
     * Start editing a message
     */
    function startEditing(messageId, messageText) {
        editingMessageId = messageId;
        $('.hamnaghsheh-message-input').val(messageText);
        $('.hamnaghsheh-editing-mode').addClass('active').find('span').text(hamnaghshehChat.strings.editing);
        $('.hamnaghsheh-message-input').focus();
    }

    /**
     * Cancel editing
     */
    function cancelEditing() {
        editingMessageId = null;
        $('.hamnaghsheh-message-input').val('');
        $('.hamnaghsheh-editing-mode').removeClass('active');
    }

    /**
     * Edit a message
     */
    function editMessage(messageId, newMessage) {
        $.ajax({
            url: hamnaghshehChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hamnaghsheh_edit_message',
                nonce: hamnaghshehChat.nonce,
                message_id: messageId,
                message: newMessage
            },
            success: function(response) {
                if (response.success) {
                    // Update message in UI
                    const messageEl = $('.hamnaghsheh-message[data-message-id="' + messageId + '"]');
                    messageEl.find('.hamnaghsheh-message-text').text(newMessage);
                    
                    // Add edited label if not exists
                    if (!messageEl.find('.hamnaghsheh-edited-label').length) {
                        messageEl.find('.hamnaghsheh-message-content').append(
                            '<div class="hamnaghsheh-edited-label">' + 
                            hamnaghshehChat.strings.edited + 
                            '</div>'
                        );
                    }

                    cancelEditing();
                } else {
                    showError(response.data.message);
                }
            },
            error: function() {
                showError(hamnaghshehChat.strings.errorOccurred);
            }
        });
    }

    /**
     * Append a message to the chat
     */
    function appendMessage(message) {
        const messageHtml = buildMessageHtml(message);
        $('.hamnaghsheh-chat-messages').append(messageHtml);
        
        if (message.id > lastMessageId) {
            lastMessageId = message.id;
        }

        scrollToBottom();
    }

    /**
     * Prepend a message to the chat
     */
    function prependMessage(message) {
        const messageHtml = buildMessageHtml(message);
        
        // Insert after load more button if exists
        if ($('.hamnaghsheh-load-more').length) {
            $('.hamnaghsheh-load-more').after(messageHtml);
        } else {
            $('.hamnaghsheh-chat-messages').prepend(messageHtml);
        }
    }

    /**
     * Build message HTML
     */
    function buildMessageHtml(message) {
        const isSystem = message.message_type === 'system';
        const canEdit = message.can_edit && !isSystem;
        
        let html = '<div class="hamnaghsheh-message' + (isSystem ? ' system' : '') + '" data-message-id="' + message.id + '">';
        
        html += '<div class="hamnaghsheh-message-header">';
        html += '<span class="hamnaghsheh-message-user">';
        if (isSystem) {
            html += '<span class="hamnaghsheh-system-icon">📄</span>';
        }
        html += message.user_name + '</span>';
        html += '<span class="hamnaghsheh-message-time">' + message.formatted_time + '</span>';
        html += '</div>';
        
        html += '<div class="hamnaghsheh-message-content">';
        html += '<div class="hamnaghsheh-message-text">' + escapeHtml(message.message) + '</div>';
        
        if (message.is_edited) {
            html += '<div class="hamnaghsheh-edited-label">' + hamnaghshehChat.strings.edited + '</div>';
        }
        
        if (message.read_receipts && !isSystem) {
            html += '<div class="hamnaghsheh-read-receipts">' + message.read_receipts + '</div>';
        }
        
        html += '</div>';
        
        if (canEdit) {
            html += '<div class="hamnaghsheh-message-actions">';
            html += '<button class="hamnaghsheh-edit-btn" data-message-id="' + message.id + '">ویرایش</button>';
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }

    /**
     * Mark visible messages as read
     */
    function markMessagesAsRead() {
        const messageIds = [];
        
        $('.hamnaghsheh-message').each(function() {
            const messageId = $(this).data('message-id');
            if (messageId) {
                messageIds.push(messageId);
            }
        });

        if (messageIds.length === 0) {
            return;
        }

        $.ajax({
            url: hamnaghshehChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hamnaghsheh_mark_as_read',
                nonce: hamnaghshehChat.nonce,
                message_ids: messageIds
            }
        });

        // Update unread count
        updateUnreadCount(0);
    }

    /**
     * Update unread count badge
     */
    function updateUnreadCount(count) {
        const badge = $('.hamnaghsheh-chat-toggle .unread-badge');
        
        if (count > 0) {
            if (badge.length) {
                badge.text(count);
            } else {
                $('.hamnaghsheh-chat-toggle').append(
                    '<span class="unread-badge">' + count + '</span>'
                );
            }
        } else {
            badge.remove();
        }
    }

    /**
     * Handle file mention autocomplete
     */
    function handleFileAutocomplete(text) {
        const atIndex = text.lastIndexOf('@');
        
        if (atIndex === -1) {
            $('.hamnaghsheh-file-autocomplete').removeClass('active');
            return;
        }

        const query = text.substring(atIndex + 1);
        
        if (query.length < 2) {
            $('.hamnaghsheh-file-autocomplete').removeClass('active');
            return;
        }

        // Search files
        $.ajax({
            url: hamnaghshehChat.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hamnaghsheh_search_files',
                nonce: hamnaghshehChat.nonce,
                project_id: currentProjectId,
                query: query
            },
            success: function(response) {
                if (response.success && response.data.files.length > 0) {
                    showFileAutocomplete(response.data.files);
                } else {
                    $('.hamnaghsheh-file-autocomplete').removeClass('active');
                }
            }
        });
    }

    /**
     * Show file autocomplete dropdown
     */
    function showFileAutocomplete(files) {
        let html = '';
        
        files.forEach(function(file) {
            html += '<div class="hamnaghsheh-file-item" data-file-id="' + file.id + '">';
            html += '<span class="hamnaghsheh-file-name">' + escapeHtml(file.file_name) + '</span>';
            html += '<span class="hamnaghsheh-file-size">(' + formatFileSize(file.file_size) + ')</span>';
            html += '</div>';
        });

        $('.hamnaghsheh-file-autocomplete').html(html).addClass('active');
    }

    /**
     * Insert file mention into input
     */
    function insertFileMention(fileName, fileId) {
        const input = $('.hamnaghsheh-message-input');
        const text = input.val();
        const atIndex = text.lastIndexOf('@');
        
        const newText = text.substring(0, atIndex) + '@' + fileName + ' ';
        input.val(newText);
        
        $('.hamnaghsheh-file-autocomplete').removeClass('active');
        input.focus();
    }

    /**
     * Request notification permission
     */
    function requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            // Show prompt after a delay
            setTimeout(function() {
                if (confirm('آیا می‌خواهید اعلان‌های پیام جدید را دریافت کنید؟')) {
                    Notification.requestPermission().then(function(permission) {
                        notificationsEnabled = permission === 'granted';
                    });
                }
            }, 3000);
        } else if (Notification.permission === 'granted') {
            notificationsEnabled = true;
        }
    }

    /**
     * Show browser notification
     */
    function showNotification(message) {
        if (!notificationsEnabled || message.message_type === 'system') {
            return;
        }

        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(hamnaghshehChat.strings.notificationTitle, {
                body: message.user_name + ': ' + message.message,
                icon: '/wp-content/plugins/hamnaghsheh-massenger/assets/icon.png',
                tag: 'hamnaghsheh-chat-' + currentProjectId
            });

            notification.onclick = function() {
                window.focus();
                openChat();
                notification.close();
            };
        }
    }

    /**
     * Scroll to bottom of messages
     */
    function scrollToBottom() {
        const messagesContainer = $('.hamnaghsheh-chat-messages');
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }

    /**
     * Show error message
     */
    function showError(message) {
        const errorHtml = '<div class="hamnaghsheh-error">' + message + '</div>';
        $('.hamnaghsheh-chat-messages').prepend(errorHtml);
        
        setTimeout(function() {
            $('.hamnaghsheh-error').fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Initialize on document ready
    $(document).ready(function() {
        initChat();
    });

})(jQuery);
