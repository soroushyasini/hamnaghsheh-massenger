/**
 * Hamnaghsheh Messenger - Mobile JavaScript
 * Mobile-specific functionality and touch gestures
 */

(function($) {
    'use strict';
    
    window.HamnaghshehMessengerMobile = {
        
        // Properties
        messenger: null,
        touchStartY: 0,
        touchEndY: 0,
        
        /**
         * Initialize mobile features
         */
        init: function(messengerInstance) {
            this.messenger = messengerInstance;
            
            if (this.isMobile()) {
                this.bindTouchEvents();
                this.preventBodyScroll();
            }
        },
        
        /**
         * Check if device is mobile
         */
        isMobile: function() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        
        /**
         * Bind touch events
         */
        bindTouchEvents: function() {
            const self = this;
            const $overlay = this.messenger.elements.overlay;
            const $header = $overlay.find('.chat-header');
            
            // Swipe down to close (on header)
            $header.on('touchstart', function(e) {
                self.touchStartY = e.touches[0].clientY;
            });
            
            $header.on('touchmove', function(e) {
                self.touchEndY = e.touches[0].clientY;
                
                const diff = self.touchEndY - self.touchStartY;
                
                // Allow swipe down only
                if (diff > 0) {
                    e.preventDefault();
                    
                    // Add visual feedback
                    $overlay.css('transform', `translateY(${Math.min(diff, 100)}px)`);
                    $overlay.css('opacity', 1 - (diff / 400));
                }
            });
            
            $header.on('touchend', function(e) {
                const diff = self.touchEndY - self.touchStartY;
                
                // Reset transform
                $overlay.css('transform', '');
                $overlay.css('opacity', '');
                
                // Close if swiped down enough
                if (diff > 100) {
                    self.messenger.closeChat();
                }
                
                self.touchStartY = 0;
                self.touchEndY = 0;
            });
            
            // Prevent pull-to-refresh when scrolled to top
            this.messenger.elements.messagesContainer.on('touchmove', function(e) {
                const scrollTop = $(this).scrollTop();
                
                if (scrollTop === 0 && e.touches[0].clientY > self.touchStartY) {
                    e.preventDefault();
                }
            });
        },
        
        /**
         * Prevent body scroll when chat is open
         */
        preventBodyScroll: function() {
            const self = this;
            const $overlay = this.messenger.elements.overlay;
            
            // Prevent body scroll when chat is open
            $overlay.on('touchmove', function(e) {
                if (self.messenger.config.isOpen) {
                    const $target = $(e.target);
                    
                    // Allow scroll only in messages container and input
                    if (!$target.closest('.chat-messages').length && 
                        !$target.closest('.chat-input-wrapper').length) {
                        e.preventDefault();
                    }
                }
            });
            
            // Lock body scroll
            $(document).on('touchmove', function(e) {
                if (self.messenger.config.isOpen) {
                    if (!$(e.target).closest('#chat-overlay').length) {
                        e.preventDefault();
                    }
                }
            });
        },
        
        /**
         * Haptic feedback (if supported)
         */
        vibrate: function(pattern = 50) {
            if ('vibrate' in navigator) {
                navigator.vibrate(pattern);
            }
        },
        
        /**
         * Handle keyboard show/hide
         */
        handleKeyboard: function() {
            const self = this;
            
            // iOS specific
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                let initialHeight = window.innerHeight;
                
                $(window).on('resize', function() {
                    const currentHeight = window.innerHeight;
                    
                    // Keyboard showed
                    if (currentHeight < initialHeight) {
                        self.messenger.elements.overlay.addClass('keyboard-visible');
                    } else {
                        self.messenger.elements.overlay.removeClass('keyboard-visible');
                    }
                });
            }
        }
    };
    
    // Auto-initialize mobile features
    $(document).ready(function() {
        if (window.HamnaghshehMessenger && HamnaghshehMessengerMobile.isMobile()) {
            // Initialize after messenger is ready
            setTimeout(function() {
                if (window.HamnaghshehMessenger.config) {
                    HamnaghshehMessengerMobile.init(window.HamnaghshehMessenger);
                }
            }, 500);
        }
    });
    
})(jQuery);
