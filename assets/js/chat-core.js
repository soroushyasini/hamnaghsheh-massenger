/**
 * Hamnaghsheh Messenger - Core JavaScript
 * Main orchestrator for chat functionality
 */

(function($) {
    'use strict';
    
    window.HamnaghshehMessenger = window.HamnaghshehMessenger || {};
    
    const Messenger = {
        
        // Configuration
        config: {
            projectId: 0,
            currentUserId: 0,
            lastMessageId: 0,
            pollingInterval: null,
            isOpen: false,
            isLoading: false
        },
        
        // Elements
        elements: {
            fab: null,
            overlay: null,
            messagesContainer: null,
            inputField: null,
            sendButton: null,
            closeButton: null,
            badge: null
        },
        
        /**
         * Initialize messenger
         */
        init: function(projectId) {
            this.config.projectId = projectId;
            this.config.currentUserId = hamnaghshehMessenger.currentUserId;
            
            this.cacheElements();
            this.bindEvents();
            this.updateUnreadCount();
            
            // Initialize other components
            if (window.HamnaghshehMessengerSSE) {
                HamnaghshehMessengerSSE.init(this);
            }
            
            if (window.HamnaghshehMessengerUI) {
                HamnaghshehMessengerUI.init(this);
            }
            
            if (window.HamnaghshehMessengerEditor) {
                HamnaghshehMessengerEditor.init(this);
            }
            
            console.log('Hamnaghsheh Messenger initialized for project', projectId);
        },
        
        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.fab = $('#hamnaghsheh-chat-fab');
            this.elements.overlay = $('#hamnaghsheh-chat-overlay');
            this.elements.messagesContainer = $('#hamnaghsheh-chat-messages');
            this.elements.inputField = $('#hamnaghsheh-chat-input');
            this.elements.sendButton = $('#hamnaghsheh-chat-send');
            this.elements.closeButton = $('#hamnaghsheh-chat-close');
            this.elements.badge = $('#chat-unread-badge');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Open chat
            this.elements.fab.on('click', function() {
                self.openChat();
            });
            
            // Close chat
            this.elements.closeButton.on('click', function() {
                self.closeChat();
            });
            
            // Send message
            this.elements.sendButton.on('click', function() {
                self.sendMessage();
            });
            
            // Send on Enter key
            this.elements.inputField.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
            
            // Typing indicator
            let typingTimeout;
            this.elements.inputField.on('input', function() {
                clearTimeout(typingTimeout);
                self.updateTypingStatus();
                
                typingTimeout = setTimeout(function() {
                    // Stop typing indicator after 2 seconds of inactivity
                }, 2000);
            });
            
            // Close on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.config.isOpen) {
                    self.closeChat();
                }
            });
        },
        
        /**
         * Open chat overlay
         */
        openChat: function() {
            this.elements.overlay.addClass('active');
            this.config.isOpen = true;
            
            // Load messages if not loaded yet
            if (this.config.lastMessageId === 0) {
                this.loadMessages();
            }
            
            // Mark all as read
            this.markAllAsRead();
            
            // Focus input
            this.elements.inputField.focus();
            
            // Clear badge
            this.updateBadge(0);
        },
        
        /**
         * Close chat overlay
         */
        closeChat: function() {
            this.elements.overlay.removeClass('active');
            this.config.isOpen = false;
        },
        
        /**
         * Load messages
         */
        loadMessages: function(lastId = 0, prepend = false) {
            const self = this;
            
            if (this.config.isLoading) {
                return;
            }
            
            this.config.isLoading = true;
            
            $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'GET',
                data: {
                    action: 'hamnaghsheh_load_messages',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId,
                    last_id: lastId,
                    limit: 50
                },
                success: function(response) {
                    if (response.success && response.data.messages) {
                        const messages = response.data.messages;
                        
                        if (messages.length > 0) {
                            if (prepend) {
                                // Prepend older messages
                                HamnaghshehMessengerUI.prependMessages(messages);
                            } else {
                                // Append newer messages
                                HamnaghshehMessengerUI.renderMessages(messages);
                            }
                            
                            // Update last message ID
                            if (!prepend) {
                                const lastMsg = messages[messages.length - 1];
                                self.config.lastMessageId = lastMsg.id;
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load messages:', error);
                },
                complete: function() {
                    self.config.isLoading = false;
                }
            });
        },
        
        /**
         * Send message
         */
        sendMessage: function() {
            const self = this;
            const message = this.elements.inputField.val().trim();
            
            if (!message) {
                return;
            }
            
            // Disable send button
            this.elements.sendButton.prop('disabled', true);
            
            $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'POST',
                data: {
                    action: 'hamnaghsheh_send_message',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        // Clear input
                        self.elements.inputField.val('');
                        
                        // Message will be added via SSE
                        // Or add it immediately for better UX
                        if (response.data.data) {
                            HamnaghshehMessengerUI.appendMessage(response.data.data);
                            self.config.lastMessageId = response.data.data.id;
                        }
                    } else {
                        alert(response.data.message || 'Failed to send message');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to send message:', error);
                    alert('Failed to send message. Please try again.');
                },
                complete: function() {
                    self.elements.sendButton.prop('disabled', false);
                    self.elements.inputField.focus();
                }
            });
        },
        
        /**
         * Mark all messages as read
         */
        markAllAsRead: function() {
            $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'POST',
                data: {
                    action: 'hamnaghsheh_mark_read',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId
                }
            });
        },
        
        /**
         * Update typing status
         */
        updateTypingStatus: function() {
            $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'POST',
                data: {
                    action: 'hamnaghsheh_update_typing',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId
                }
            });
        },
        
        /**
         * Update unread count
         */
        updateUnreadCount: function() {
            const self = this;
            
            $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'GET',
                data: {
                    action: 'hamnaghsheh_get_unread_count',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId
                },
                success: function(response) {
                    if (response.success) {
                        self.updateBadge(response.data.count);
                    }
                }
            });
        },
        
        /**
         * Update badge count
         */
        updateBadge: function(count) {
            if (count > 0) {
                this.elements.badge.text(count > 99 ? '99+' : count).show();
                this.elements.fab.addClass('has-notification');
            } else {
                this.elements.badge.text('').hide();
                this.elements.fab.removeClass('has-notification');
            }
        },
        
        /**
         * Edit message
         */
        editMessage: function(messageId, newMessage) {
            return $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'POST',
                data: {
                    action: 'hamnaghsheh_edit_message',
                    nonce: hamnaghshehMessenger.nonce,
                    message_id: messageId,
                    message: newMessage
                }
            });
        },
        
        /**
         * Delete message
         */
        deleteMessage: function(messageId) {
            if (!confirm(hamnaghshehMessenger.strings.delete_confirm)) {
                return Promise.reject();
            }
            
            return $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'POST',
                data: {
                    action: 'hamnaghsheh_delete_message',
                    nonce: hamnaghshehMessenger.nonce,
                    message_id: messageId
                }
            });
        },
        
        /**
         * Search messages
         */
        searchMessages: function(query) {
            return $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'GET',
                data: {
                    action: 'hamnaghsheh_search_messages',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId,
                    query: query
                }
            });
        },
        
        /**
         * Export chat
         */
        exportChat: function(format = 'txt') {
            const self = this;
            
            $.ajax({
                url: hamnaghshehMessenger.ajaxurl,
                method: 'GET',
                data: {
                    action: 'hamnaghsheh_export_chat',
                    nonce: hamnaghshehMessenger.nonce,
                    project_id: this.config.projectId,
                    format: format
                },
                success: function(response) {
                    if (response.success && response.data.download_url) {
                        window.open(response.data.download_url, '_blank');
                    } else {
                        alert('Failed to export chat');
                    }
                },
                error: function() {
                    alert('Failed to export chat');
                }
            });
        }
    };
    
    // Expose globally
    window.HamnaghshehMessenger = Messenger;
    
    // Auto-initialize on page load
    $(document).ready(function() {
        const $fab = $('#hamnaghsheh-chat-fab');
        if ($fab.length) {
            const projectId = $fab.data('project-id');
            if (projectId) {
                Messenger.init(projectId);
            }
        }
    });
    
})(jQuery);
