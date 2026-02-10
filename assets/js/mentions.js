/**
 * Hamnaghsheh Chat - Mentions Autocomplete
 * Handles @user and #file mentions with dropdown autocomplete
 */

(function($) {
    'use strict';
    
    let mentionsState = {
        isActive: false,
        mentionType: null, // 'user' or 'file'
        searchTerm: '',
        selectedIndex: -1,
        cursorPosition: 0,
        mentionStartPos: 0
    };
    
    /**
     * Initialize mentions
     */
    function initMentions() {
        bindEvents();
        createAutocompleteDropdown();
    }
    
    /**
     * Bind events
     */
    function bindEvents() {
        // Monitor input for @ and # triggers
        $(document).on('input', '.hmchat-input', handleInput);
        
        // Keyboard navigation in autocomplete
        $(document).on('keydown', '.hmchat-input', handleKeydown);
        
        // Click on autocomplete item
        $(document).on('click', '.hmchat-autocomplete-item', handleItemClick);
        
        // Click mention buttons
        $(document).on('click', '.hmchat-mention-user-btn', function() {
            insertMentionTrigger('@');
        });
        
        $(document).on('click', '.hmchat-mention-file-btn', function() {
            insertMentionTrigger('#');
        });
        
        // Close autocomplete when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hmchat-input, .hmchat-autocomplete').length) {
                hideAutocomplete();
            }
        });
    }
    
    /**
     * Create autocomplete dropdown
     */
    function createAutocompleteDropdown() {
        if ($('.hmchat-autocomplete').length === 0) {
            const $dropdown = $('<div>').addClass('hmchat-autocomplete');
            $('.hmchat-input-wrapper').append($dropdown);
        }
    }
    
    /**
     * Handle input
     */
    function handleInput(e) {
        const $input = $(e.target);
        const value = $input.val();
        const cursorPos = $input[0].selectionStart;
        
        // Check for mention trigger
        const beforeCursor = value.substring(0, cursorPos);
        const match = beforeCursor.match(/[@#](\w*)$/);
        
        if (match) {
            const trigger = match[0][0];
            const searchTerm = match[1];
            const mentionStartPos = cursorPos - match[0].length;
            
            mentionsState.isActive = true;
            mentionsState.mentionType = trigger === '@' ? 'user' : 'file';
            mentionsState.searchTerm = searchTerm;
            mentionsState.cursorPosition = cursorPos;
            mentionsState.mentionStartPos = mentionStartPos;
            mentionsState.selectedIndex = -1;
            
            showAutocomplete();
        } else {
            hideAutocomplete();
        }
    }
    
    /**
     * Handle keydown
     */
    function handleKeydown(e) {
        if (!mentionsState.isActive) {
            return;
        }
        
        const $items = $('.hmchat-autocomplete-item');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                mentionsState.selectedIndex = Math.min(mentionsState.selectedIndex + 1, $items.length - 1);
                updateSelectedItem();
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                mentionsState.selectedIndex = Math.max(mentionsState.selectedIndex - 1, -1);
                updateSelectedItem();
                break;
                
            case 'Enter':
            case 'Tab':
                if (mentionsState.selectedIndex >= 0) {
                    e.preventDefault();
                    selectItem($items.eq(mentionsState.selectedIndex));
                }
                break;
                
            case 'Escape':
                e.preventDefault();
                hideAutocomplete();
                break;
        }
    }
    
    /**
     * Handle item click
     */
    function handleItemClick(e) {
        e.preventDefault();
        selectItem($(e.currentTarget));
    }
    
    /**
     * Show autocomplete
     */
    function showAutocomplete() {
        const items = getFilteredItems();
        
        if (items.length === 0) {
            hideAutocomplete();
            return;
        }
        
        const $dropdown = $('.hmchat-autocomplete');
        $dropdown.empty();
        
        items.forEach(function(item, index) {
            const $item = $('<div>')
                .addClass('hmchat-autocomplete-item')
                .attr('data-id', item.id)
                .attr('data-name', item.name)
                .html(highlightMatch(item.name, mentionsState.searchTerm));
            
            if (index === mentionsState.selectedIndex) {
                $item.addClass('selected');
            }
            
            $dropdown.append($item);
        });
        
        $dropdown.addClass('active');
    }
    
    /**
     * Hide autocomplete
     */
    function hideAutocomplete() {
        $('.hmchat-autocomplete').removeClass('active');
        mentionsState.isActive = false;
        mentionsState.selectedIndex = -1;
    }
    
    /**
     * Get filtered items
     */
    function getFilteredItems() {
        let items = [];
        
        if (mentionsState.mentionType === 'user') {
            items = window.HMChat && window.HMChat.state && window.HMChat.state.members 
                ? window.HMChat.state.members.map(function(member) {
                    return {
                        id: member.user_id,
                        name: member.display_name
                    };
                })
                : [];
        } else if (mentionsState.mentionType === 'file') {
            items = window.HMChat && window.HMChat.state && window.HMChat.state.files
                ? window.HMChat.state.files.map(function(file) {
                    return {
                        id: file.id,
                        name: file.file_name
                    };
                })
                : [];
        }
        
        // Filter by search term
        if (mentionsState.searchTerm) {
            const searchLower = mentionsState.searchTerm.toLowerCase();
            items = items.filter(function(item) {
                return item.name.toLowerCase().indexOf(searchLower) !== -1;
            });
        }
        
        return items;
    }
    
    /**
     * Update selected item
     */
    function updateSelectedItem() {
        $('.hmchat-autocomplete-item').removeClass('selected');
        
        if (mentionsState.selectedIndex >= 0) {
            $('.hmchat-autocomplete-item').eq(mentionsState.selectedIndex).addClass('selected');
            
            // Scroll into view
            const $selected = $('.hmchat-autocomplete-item.selected');
            if ($selected.length) {
                const $dropdown = $('.hmchat-autocomplete');
                const itemTop = $selected.position().top;
                const itemBottom = itemTop + $selected.outerHeight();
                const dropdownHeight = $dropdown.height();
                
                if (itemBottom > dropdownHeight) {
                    $dropdown.scrollTop($dropdown.scrollTop() + (itemBottom - dropdownHeight));
                } else if (itemTop < 0) {
                    $dropdown.scrollTop($dropdown.scrollTop() + itemTop);
                }
            }
        }
    }
    
    /**
     * Select item
     */
    function selectItem($item) {
        const id = $item.attr('data-id');
        const name = $item.attr('data-name');
        
        if (!id || !name) {
            return;
        }
        
        const $input = $('.hmchat-input');
        const value = $input.val();
        
        // Build mention string
        const trigger = mentionsState.mentionType === 'user' ? '@' : '#';
        const mention = trigger + '[' + id + ':' + name + ']';
        
        // Replace the mention trigger with the mention
        const beforeMention = value.substring(0, mentionsState.mentionStartPos);
        const afterMention = value.substring(mentionsState.cursorPosition);
        const newValue = beforeMention + mention + ' ' + afterMention;
        
        // Update input
        $input.val(newValue);
        
        // Set cursor position after mention
        const newCursorPos = mentionsState.mentionStartPos + mention.length + 1;
        $input[0].setSelectionRange(newCursorPos, newCursorPos);
        
        // Focus input
        $input.focus();
        
        // Hide autocomplete
        hideAutocomplete();
    }
    
    /**
     * Insert mention trigger
     */
    function insertMentionTrigger(trigger) {
        const $input = $('.hmchat-input');
        const value = $input.val();
        const cursorPos = $input[0].selectionStart;
        
        // Insert trigger at cursor
        const beforeCursor = value.substring(0, cursorPos);
        const afterCursor = value.substring(cursorPos);
        
        // Add space before trigger if needed
        const needsSpace = beforeCursor.length > 0 && !beforeCursor.match(/\s$/);
        const newValue = beforeCursor + (needsSpace ? ' ' : '') + trigger + afterCursor;
        
        $input.val(newValue);
        
        // Set cursor after trigger
        const newCursorPos = cursorPos + (needsSpace ? 2 : 1);
        $input[0].setSelectionRange(newCursorPos, newCursorPos);
        
        // Focus input
        $input.focus();
        
        // Trigger input event to show autocomplete
        $input.trigger('input');
    }
    
    /**
     * Highlight match in text
     */
    function highlightMatch(text, search) {
        if (!search) {
            return escapeHtml(text);
        }
        
        const escapedText = escapeHtml(text);
        const escapedSearch = escapeHtml(search);
        const regex = new RegExp('(' + escapedSearch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        
        return escapedText.replace(regex, '<strong>$1</strong>');
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initMentions();
    });
    
})(jQuery);
