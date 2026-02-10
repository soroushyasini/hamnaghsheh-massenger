/**
 * Hamnaghsheh Messenger - UI JavaScript
 * Handles message rendering and UI updates
 */

(function($) {
    'use strict';
    
    window.HamnaghshehMessengerUI = {
        
        // Properties
        messenger: null,
        
        /**
         * Initialize UI
         */
        init: function(messengerInstance) {
            this.messenger = messengerInstance;
            this.bindScrollEvents();
        },
        
        /**
         * Bind scroll events
         */
        bindScrollEvents: function() {
            const self = this;
            const $container = this.messenger.elements.messagesContainer;
            
            // Load more on scroll to top
            $container.on('scroll', function() {
                if ($(this).scrollTop() === 0 && !self.messenger.config.isLoading) {
                    self.loadOlderMessages();
                }
            });
        },
        
        /**
         * Render messages
         */
        renderMessages: function(messages) {
            const $container = this.messenger.elements.messagesContainer;
            
            // Clear loading state
            $container.find('.chat-loading').remove();
            
            if (!messages || messages.length === 0) {
                if ($container.children('.message-bubble').length === 0) {
                    this.showEmptyState();
                }
                return;
            }
            
            // Remove empty state
            $container.find('.chat-empty').remove();
            
            // Render each message
            messages.forEach((message) => {
                this.appendMessage(message, false);
            });
            
            // Scroll to bottom
            this.scrollToBottom();
        },
        
        /**
         * Append a single message
         */
        appendMessage: function(message, animate = true) {
            const $container = this.messenger.elements.messagesContainer;
            const $message = this.createMessageElement(message);
            
            if (animate) {
                $message.hide().appendTo($container).fadeIn(300);
                this.scrollToBottom();
            } else {
                $message.appendTo($container);
            }
            
            return $message;
        },
        
        /**
         * Prepend older messages
         */
        prependMessages: function(messages) {
            const $container = this.messenger.elements.messagesContainer;
            const scrollHeight = $container[0].scrollHeight;
            
            messages.reverse().forEach((message) => {
                const $message = this.createMessageElement(message);
                $container.prepend($message);
            });
            
            // Maintain scroll position
            const newScrollHeight = $container[0].scrollHeight;
            $container.scrollTop(newScrollHeight - scrollHeight);
        },
        
        /**
         * Create message element
         */
        createMessageElement: function(message) {
            const isOwnMessage = message.user_id == hamnaghshehMessenger.currentUserId;
            const messageClass = isOwnMessage ? 'message-sent' : 'message-received';
            const messageType = message.message_type || 'text';
            
            let $message;
            
            if (messageType === 'file_activity' || messageType === 'system') {
                $message = this.createSystemMessage(message);
            } else {
                $message = this.createTextMessage(message, messageClass);
            }
            
            $message.attr('data-message-id', message.id);
            
            return $message;
        },
        
        /**
         * Create text message element
         */
        createTextMessage: function(message, messageClass) {
            const isOwnMessage = message.user_id == hamnaghshehMessenger.currentUserId;
            
            const $bubble = $('<div>')
                .addClass('message-bubble')
                .addClass(messageClass);
            
            // Header
            const $header = $('<div>').addClass('message-header');
            
            // Avatar
            $('<img>')
                .addClass('avatar')
                .attr('src', message.avatar_url)
                .attr('alt', message.display_name)
                .appendTo($header);
            
            // Name
            $('<strong>').text(message.display_name || 'Unknown').appendTo($header);
            
            // Time
            const time = this.formatTime(message.created_at);
            $('<span>').addClass('time').text(time).appendTo($header);
            
            $header.appendTo($bubble);
            
            // Content
            const $content = $('<div>')
                .addClass('message-content')
                .html(this.sanitizeMessage(message.message));
            
            if (message.edited_at) {
                $content.append(' <em>(edited)</em>');
            }
            
            $content.appendTo($bubble);
            
            // Footer
            const $footer = $('<div>').addClass('message-footer');
            
            // Seen status
            const $seenStatus = this.createSeenStatus(message);
            $seenStatus.appendTo($footer);
            
            // Actions (for own messages)
            if (isOwnMessage && message.can_edit) {
                const $actions = $('<div>').addClass('message-actions');
                
                $('<button>')
                    .addClass('edit-btn')
                    .text('Edit')
                    .on('click', () => this.editMessage(message.id))
                    .appendTo($actions);
                
                $('<button>')
                    .addClass('delete-btn')
                    .text('Delete')
                    .on('click', () => this.deleteMessage(message.id))
                    .appendTo($actions);
                
                $actions.appendTo($footer);
            }
            
            $footer.appendTo($bubble);
            
            return $bubble;
        },
        
        /**
         * Create system message element
         */
        createSystemMessage: function(message) {
            const $bubble = $('<div>')
                .addClass('message-bubble')
                .addClass('message-system');
            
            const $content = $('<div>')
                .addClass('message-content')
                .html('🔵 <strong>' + (message.display_name || 'System') + '</strong> ' + this.sanitizeMessage(message.message));
            
            // Add file link if metadata exists
            if (message.metadata && message.metadata.file_id) {
                const $fileLink = $('<a>')
                    .addClass('view-file-btn')
                    .attr('href', '#file-' + message.metadata.file_id)
                    .text('View File');
                
                $content.append('<br>').append($fileLink);
            }
            
            $content.appendTo($bubble);
            
            // Time
            const time = this.formatTime(message.created_at);
            $('<span>').addClass('time').text(time).appendTo($bubble);
            
            return $bubble;
        },
        
        /**
         * Create seen status element
         */
        createSeenStatus: function(message) {
            const $status = $('<span>').addClass('seen-status');
            
            if (!message.seen_by || message.seen_by.length === 0) {
                // Sent only
                $status.html('✓ ' + hamnaghshehMessenger.strings.sent);
            } else {
                // Seen by users
                const names = message.seen_by.map(u => u.display_name).join('، ');
                
                if (message.seen_by.length >= 3) { // Assuming 3+ means "all"
                    $status.html('✓✓ ' + hamnaghshehMessenger.strings.seen_by_all);
                    $status.addClass('seen-by-all');
                } else {
                    $status.html('✓✓ ' + hamnaghshehMessenger.strings.seen_by.replace('%s', names));
                }
            }
            
            return $status;
        },
        
        /**
         * Update typing indicator
         */
        updateTypingIndicator: function(users) {
            const $indicator = $('#hamnaghsheh-typing-indicator');
            
            if (!users || users.length === 0) {
                $indicator.removeClass('active').hide();
                return;
            }
            
            const names = users.map(u => u.display_name).join('، ');
            const text = hamnaghshehMessenger.strings.typing.replace('%s', names);
            
            $indicator
                .html(text + ' <span class="typing-dots"><span></span><span></span><span></span></span>')
                .addClass('active')
                .show();
        },
        
        /**
         * Scroll to bottom
         */
        scrollToBottom: function(smooth = false) {
            const $container = this.messenger.elements.messagesContainer;
            
            if (smooth) {
                $container.addClass('scrolling');
            }
            
            $container[0].scrollTop = $container[0].scrollHeight;
            
            if (smooth) {
                setTimeout(() => {
                    $container.removeClass('scrolling');
                }, 300);
            }
        },
        
        /**
         * Load older messages
         */
        loadOlderMessages: function() {
            const $container = this.messenger.elements.messagesContainer;
            const $firstMessage = $container.find('.message-bubble').first();
            
            if (!$firstMessage.length) {
                return;
            }
            
            const firstId = $firstMessage.data('message-id');
            
            // Show loading indicator
            const $loading = $('<div>').addClass('chat-loading');
            $('<div>').addClass('chat-loading-spinner').appendTo($loading);
            $container.prepend($loading);
            
            this.messenger.loadMessages(firstId - 50, true);
        },
        
        /**
         * Show empty state
         */
        showEmptyState: function() {
            const $container = this.messenger.elements.messagesContainer;
            
            const $empty = $('<div>').addClass('chat-empty');
            $empty.html('<p>No messages yet. Start the conversation!</p>');
            
            $container.append($empty);
        },
        
        /**
         * Edit message
         */
        editMessage: function(messageId) {
            const $message = $(`.message-bubble[data-message-id="${messageId}"]`);
            const $content = $message.find('.message-content');
            const currentText = $content.text().replace(' (edited)', '').trim();
            
            const newText = prompt('Edit message:', currentText);
            
            if (newText && newText !== currentText) {
                this.messenger.editMessage(messageId, newText).then((response) => {
                    if (response.success) {
                        $content.html(this.sanitizeMessage(newText) + ' <em>(edited)</em>');
                    } else {
                        alert(response.data.message || hamnaghshehMessenger.strings.edit_time_expired);
                    }
                }).catch(() => {
                    alert('Failed to edit message');
                });
            }
        },
        
        /**
         * Delete message
         */
        deleteMessage: function(messageId) {
            const $message = $(`.message-bubble[data-message-id="${messageId}"]`);
            
            this.messenger.deleteMessage(messageId).then((response) => {
                if (response.success) {
                    $message.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || 'Failed to delete message');
                }
            }).catch(() => {
                // User cancelled
            });
        },
        
        /**
         * Sanitize message HTML
         */
        sanitizeMessage: function(message) {
            // Basic sanitization - WordPress already does this server-side
            return message;
        },
        
        /**
         * Format timestamp
         */
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            
            const isToday = date.toDateString() === now.toDateString();
            
            if (isToday) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else {
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
        }
    };
    
})(jQuery);
