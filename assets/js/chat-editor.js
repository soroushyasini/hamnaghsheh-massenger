/**
 * Hamnaghsheh Messenger - Editor JavaScript
 * Handles input field functionality, mentions, etc.
 */

(function($) {
    'use strict';
    
    window.HamnaghshehMessengerEditor = {
        
        // Properties
        messenger: null,
        
        /**
         * Initialize editor
         */
        init: function(messengerInstance) {
            this.messenger = messengerInstance;
            this.bindEvents();
        },
        
        /**
         * Bind editor events
         */
        bindEvents: function() {
            const self = this;
            const $input = this.messenger.elements.inputField;
            
            // Auto-resize textarea
            $input.on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
            
            // Handle paste
            $input.on('paste', function(e) {
                // Could handle file paste here in future
            });
            
            // Handle mentions (future feature)
            $input.on('input', function() {
                const text = $(this).val();
                const cursorPos = this.selectionStart;
                
                // Check for @ symbol for mentions
                if (text[cursorPos - 1] === '@') {
                    // Show mention dropdown (future feature)
                }
            });
        },
        
        /**
         * Insert text at cursor
         */
        insertAtCursor: function(text) {
            const $input = this.messenger.elements.inputField;
            const input = $input[0];
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const currentValue = $input.val();
            
            const newValue = currentValue.substring(0, start) + text + currentValue.substring(end);
            $input.val(newValue);
            
            // Set cursor position after inserted text
            input.selectionStart = input.selectionEnd = start + text.length;
            $input.trigger('input');
            $input.focus();
        },
        
        /**
         * Clear input
         */
        clear: function() {
            this.messenger.elements.inputField.val('');
            this.messenger.elements.inputField[0].style.height = 'auto';
        },
        
        /**
         * Focus input
         */
        focus: function() {
            this.messenger.elements.inputField.focus();
        }
    };
    
})(jQuery);
