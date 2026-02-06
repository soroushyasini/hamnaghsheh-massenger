/**
 * Hamnaghsheh Messenger - SSE (Server-Sent Events) Client
 * Handles real-time updates via Server-Sent Events
 */

(function($) {
    'use strict';
    
    window.HamnaghshehMessengerSSE = {
        
        // Properties
        eventSource: null,
        messenger: null,
        reconnectAttempts: 0,
        maxReconnectAttempts: 10,
        reconnectDelay: 3000,
        isConnected: false,
        
        /**
         * Initialize SSE connection
         */
        init: function(messengerInstance) {
            this.messenger = messengerInstance;
            this.connect();
        },
        
        /**
         * Connect to SSE stream
         */
        connect: function() {
            const self = this;
            
            if (!this.messenger || !this.messenger.config.projectId) {
                console.error('Messenger not initialized');
                return;
            }
            
            // Close existing connection
            this.disconnect();
            
            // Build SSE URL
            const url = new URL(hamnaghshehMessenger.ajaxurl);
            url.searchParams.set('action', 'hamnaghsheh_sse_stream');
            url.searchParams.set('project_id', this.messenger.config.projectId);
            url.searchParams.set('last_message_id', this.messenger.config.lastMessageId);
            url.searchParams.set('nonce', hamnaghshehMessenger.nonce);
            
            console.log('Connecting to SSE stream...');
            
            // Create EventSource
            this.eventSource = new EventSource(url.toString());
            
            // On connection open
            this.eventSource.addEventListener('open', function(e) {
                console.log('SSE connection opened');
                self.isConnected = true;
                self.reconnectAttempts = 0;
                self.showConnectionStatus('connected');
            });
            
            // On new message event
            this.eventSource.addEventListener('new_message', function(e) {
                try {
                    const message = JSON.parse(e.data);
                    console.log('New message received:', message);
                    
                    // Add message to UI
                    if (window.HamnaghshehMessengerUI) {
                        HamnaghshehMessengerUI.appendMessage(message);
                    }
                    
                    // Update last message ID
                    self.messenger.config.lastMessageId = message.id;
                    
                    // Play notification sound if not from current user
                    if (message.user_id != hamnaghshehMessenger.currentUserId) {
                        self.playNotificationSound();
                        
                        // Update badge if chat is closed
                        if (!self.messenger.config.isOpen) {
                            self.messenger.updateUnreadCount();
                        }
                    }
                    
                } catch (error) {
                    console.error('Error parsing new message:', error);
                }
            });
            
            // On user typing event
            this.eventSource.addEventListener('user_typing', function(e) {
                try {
                    const users = JSON.parse(e.data);
                    console.log('Users typing:', users);
                    
                    if (window.HamnaghshehMessengerUI) {
                        HamnaghshehMessengerUI.updateTypingIndicator(users);
                    }
                } catch (error) {
                    console.error('Error parsing typing event:', error);
                }
            });
            
            // On connection error
            this.eventSource.addEventListener('error', function(e) {
                console.error('SSE connection error:', e);
                self.isConnected = false;
                self.handleConnectionError();
            });
        },
        
        /**
         * Disconnect from SSE stream
         */
        disconnect: function() {
            if (this.eventSource) {
                console.log('Disconnecting SSE stream...');
                this.eventSource.close();
                this.eventSource = null;
                this.isConnected = false;
            }
        },
        
        /**
         * Handle connection error and reconnect
         */
        handleConnectionError: function() {
            const self = this;
            
            this.disconnect();
            
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                
                console.log(`Reconnecting... (Attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
                this.showConnectionStatus('reconnecting');
                
                // Exponential backoff
                const delay = this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1);
                
                setTimeout(function() {
                    self.connect();
                }, Math.min(delay, 30000)); // Max 30 seconds
                
            } else {
                console.error('Max reconnection attempts reached');
                this.showConnectionStatus('disconnected');
                
                // Show error message to user
                this.showReconnectPrompt();
            }
        },
        
        /**
         * Show connection status
         */
        showConnectionStatus: function(status) {
            // Remove existing status
            $('.chat-connection-status').remove();
            
            let message = '';
            let className = status;
            
            switch (status) {
                case 'connected':
                    // Don't show anything for connected state
                    return;
                    
                case 'reconnecting':
                    message = 'Reconnecting...';
                    break;
                    
                case 'disconnected':
                    message = 'Connection lost';
                    break;
            }
            
            if (message && this.messenger.config.isOpen) {
                const $status = $('<div>')
                    .addClass('chat-connection-status')
                    .addClass(className)
                    .text(message);
                
                this.messenger.elements.overlay.prepend($status);
                
                // Auto-hide connected status after 2 seconds
                if (status === 'connected') {
                    setTimeout(function() {
                        $status.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 2000);
                }
            }
        },
        
        /**
         * Show reconnect prompt
         */
        showReconnectPrompt: function() {
            const self = this;
            
            if (confirm('Connection to chat server lost. Retry?')) {
                this.reconnectAttempts = 0;
                this.connect();
            }
        },
        
        /**
         * Play notification sound
         */
        playNotificationSound: function() {
            // Simple beep using Web Audio API
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.value = 0.1;
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.1);
                
                // Add visual indicator
                if (this.messenger.elements.fab) {
                    this.messenger.elements.fab.addClass('sound-playing');
                    setTimeout(function() {
                        this.messenger.elements.fab.removeClass('sound-playing');
                    }.bind(this), 1000);
                }
                
            } catch (error) {
                console.log('Audio notification not supported:', error);
            }
        },
        
        /**
         * Check if browser supports SSE
         */
        isSupported: function() {
            return typeof EventSource !== 'undefined';
        },
        
        /**
         * Fallback to polling if SSE not supported
         */
        startPolling: function() {
            const self = this;
            
            console.log('SSE not supported, using polling fallback');
            
            // Poll every 3 seconds
            this.messenger.config.pollingInterval = setInterval(function() {
                self.messenger.loadMessages(self.messenger.config.lastMessageId);
            }, 3000);
        },
        
        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.messenger.config.pollingInterval) {
                clearInterval(this.messenger.config.pollingInterval);
                this.messenger.config.pollingInterval = null;
            }
        }
    };
    
    // Check SSE support on load
    $(document).ready(function() {
        if (!HamnaghshehMessengerSSE.isSupported()) {
            console.warn('EventSource (SSE) not supported in this browser');
        }
    });
    
})(jQuery);
