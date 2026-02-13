/**
 * Hamnaghsheh Chat - Main JavaScript
 * Handles chat functionality with smart polling, message sending, and UI updates
 */

(function($) {
    'use strict';
    
    // Polling intervals
    const POLL_INTERVALS = {
        active: 2000,      // Chat open & user focused
        idle: 5000,        // Chat open, user idle
        dashboard: 15000   // Dashboard page
    };
    
    // Chat state
    let chatState = {
        projectId: null,
        lastMessageId: 0,
        lastLogId: 0,
        isMinimized: true,
        pollInterval: null,
        currentPollDelay: POLL_INTERVALS.active,
        isUserActive: true,
        lastActivityTime: Date.now(),
        isSending: false,
        editingMessageId: null,
        members: [],
        files: []
    };
    
    // Activity tracking
    let activityTimeout = null;
    
    /**
     * Initialize chat
     */
    function initChat() {
        if (typeof hmchat_project === 'undefined') {
            return;
        }
        
        chatState.projectId = hmchat_project.project_id;
        chatState.members = hmchat_project.members || [];
        
        // Load lastLogId from localStorage
        const storageKey = 'hmchat_last_log_' + chatState.projectId;
        chatState.lastLogId = parseInt(localStorage.getItem(storageKey)) || 0;
        
        // Load initial messages
        if (hmchat_project.initial_messages && hmchat_project.initial_messages.length > 0) {
            renderMessages(hmchat_project.initial_messages, false);
            chatState.lastMessageId = hmchat_project.initial_messages[hmchat_project.initial_messages.length - 1].id;
        }
        
        // Bind events
        bindEvents();
        
        // Start polling
        startPolling();
        
        // Track user activity
        trackActivity();
        
        // Handle page visibility
        handleVisibilityChange();
        
        // Scroll to bottom
        scrollToBottom();
        
        // Load members and files
        loadMembers();
        loadFiles();
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Minimize/maximize chat
        $(document).on('click', '.hmchat-minimize', toggleChat);
        
        // Send message
        $(document).on('click', '.hmchat-send-btn', sendMessage);
        
        // Enter to send (Shift+Enter for new line)
        $(document).on('keydown', '.hmchat-input', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Load earlier messages
        $(document).on('click', '.hmchat-load-more', loadEarlierMessages);
        
        // Double-click to edit own message
        $(document).on('dblclick', '.hmchat-message.own', function() {
            const messageId = $(this).data('message-id');
            startEditMessage(messageId);
        });
        
        // Edit actions
        $(document).on('click', '.hmchat-edit-save', saveEditMessage);
        $(document).on('click', '.hmchat-edit-cancel', cancelEditMessage);
        
        // Click seen status to show details
        $(document).on('click', '.hmchat-message-status.seen', function() {
            const messageId = $(this).closest('.hmchat-message').data('message-id');
            showSeenDetails(messageId);
        });
        
        // Close seen modal
        $(document).on('click', '.hmchat-seen-close, .hmchat-seen-modal', function(e) {
            if (e.target === this) {
                $('.hmchat-seen-modal').remove();
            }
        });
        
        // Toggle digest message details
        $(document).on('click', '.hmchat-digest-header', function() {
            const $header = $(this);
            const $details = $header.next('.hmchat-digest-details');
            
            if ($details.is(':visible')) {
                $details.slideUp(300);
                $header.removeClass('expanded');
            } else {
                $details.slideDown(300);
                $header.addClass('expanded');
            }
        });
        
        // Tab switching
        $(document).on('click', '.hmchat-tab', handleTabSwitch);
        
        // Mark messages as seen when scrolling
        $(document).on('scroll', '.hmchat-messages-wrapper', debounce(markVisibleMessagesAsSeen, 2000));
    }
    
    /**
     * Toggle chat minimized state
     */
    function toggleChat() {
        chatState.isMinimized = !chatState.isMinimized;
        $('.hmchat-container').toggleClass('minimized', chatState.isMinimized);
        
        if (!chatState.isMinimized) {
            scrollToBottom();
            markVisibleMessagesAsSeen();
        }
    }
    
    /**
     * Send message
     */
    function sendMessage() {
        if (chatState.isSending) {
            return;
        }
        
        const $input = $('.hmchat-input');
        const message = $input.val().trim();
        
        if (!message) {
            return;
        }
        
        if (message.length > hmchat_ajax.max_message_length) {
            showError('پیام نمی‌تواند بیشتر از ' + hmchat_ajax.max_message_length + ' کاراکتر باشد');
            return;
        }
        
        chatState.isSending = true;
        $('.hmchat-send-btn').prop('disabled', true).text('در حال ارسال...');
        
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_send_message',
                nonce: hmchat_ajax.nonce,
                project_id: chatState.projectId,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    $input.val('');
                    renderMessages([response.data.message], true);
                    scrollToBottom();
                    chatState.lastMessageId = response.data.message.id;
                } else {
                    showError(response.data.message || 'خطا در ارسال پیام');
                }
            },
            error: function() {
                showError('خطا در ارسال پیام');
            },
            complete: function() {
                chatState.isSending = false;
                $('.hmchat-send-btn').prop('disabled', false).text('ارسال');
            }
        });
    }
    
    /**
     * Fetch new messages
     */
    function fetchNewMessages() {
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_fetch_messages',
                nonce: hmchat_ajax.nonce,
                project_id: chatState.projectId,
                last_message_id: chatState.lastMessageId,
                last_log_id: chatState.lastLogId
            },
            success: function(response) {
                if (response.success && response.data.messages.length > 0) {
                    // Update tracking IDs
                    response.data.messages.forEach(function(msg) {
                        if (msg.msg_type === 'system' && typeof msg.id === 'string' && msg.id.startsWith('log_')) {
                            const logId = parseInt(msg.id.replace('log_', ''));
                            chatState.lastLogId = Math.max(chatState.lastLogId, logId);
                        } else if (msg.id && typeof msg.id === 'number') {
                            chatState.lastMessageId = Math.max(chatState.lastMessageId, msg.id);
                        }
                    });
                    
                    // Save lastLogId to localStorage
                    const storageKey = 'hmchat_last_log_' + chatState.projectId;
                    localStorage.setItem(storageKey, chatState.lastLogId);
                    
                    renderMessages(response.data.messages, true);
                    
                    // Scroll to bottom if chat is open
                    if (!chatState.isMinimized) {
                        scrollToBottom();
                    }
                    
                    // Mark as seen if visible
                    if (!chatState.isMinimized) {
                        markVisibleMessagesAsSeen();
                    }
                }
            }
        });
    }
    
    /**
     * Load earlier messages
     */
    function loadEarlierMessages() {
        const $btn = $('.hmchat-load-more');
        const firstMessageId = $('.hmchat-message').first().data('message-id');
        
        if (!firstMessageId) {
            return;
        }
        
        $btn.prop('disabled', true).html('<div class="hmchat-loading-spinner"></div> بارگذاری...');
        
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_load_earlier',
                nonce: hmchat_ajax.nonce,
                project_id: chatState.projectId,
                before_id: firstMessageId
            },
            success: function(response) {
                if (response.success && response.data.messages.length > 0) {
                    // Save scroll position
                    const $wrapper = $('.hmchat-messages-wrapper');
                    const oldScrollHeight = $wrapper[0].scrollHeight;
                    
                    // Prepend messages
                    renderMessages(response.data.messages, false, true);
                    
                    // Restore scroll position
                    const newScrollHeight = $wrapper[0].scrollHeight;
                    $wrapper.scrollTop(newScrollHeight - oldScrollHeight);
                    
                    // Hide button if no more messages
                    if (!response.data.has_more) {
                        $btn.hide();
                    }
                } else {
                    $btn.hide();
                }
            },
            error: function() {
                showError('خطا در بارگذاری پیام‌ها');
            },
            complete: function() {
                $btn.prop('disabled', false).text('بارگذاری پیامهای قبلی');
            }
        });
    }
    
    /**
     * Render messages
     */
    function renderMessages(messages, append = true, prepend = false) {
        const $wrapper = $('.hmchat-messages-wrapper');
        const currentUserId = hmchat_ajax.user_id;
        let lastDateKey = null;
        
        messages.forEach(function(msg) {
            // Check if message already exists
            if ($('.hmchat-message[data-message-id="' + msg.id + '"]').length > 0) {
                return;
            }
            
            // Check if we need to add a date divider
            const msgDateKey = getDateKey(msg.created_at);
            
            if (append) {
                // Get the last message's date
                const $lastMessage = $('.hmchat-message').last();
                if ($lastMessage.length > 0) {
                    const lastMsgId = $lastMessage.data('message-id');
                    const lastMsgTimestamp = $lastMessage.attr('data-timestamp');
                    if (lastMsgTimestamp) {
                        lastDateKey = getDateKey(lastMsgTimestamp);
                    }
                }
                
                // Add date divider if date changed
                if (lastDateKey !== msgDateKey) {
                    const dateLabel = getJalaliDateLabel(msg.created_at);
                    const $divider = $('<div>')
                        .addClass('hmchat-date-divider')
                        .html('<span>' + dateLabel + '</span>');
                    $wrapper.append($divider);
                }
            } else if (prepend) {
                // For prepending, check the first existing message
                const $firstMessage = $('.hmchat-message').first();
                if ($firstMessage.length > 0) {
                    const firstMsgTimestamp = $firstMessage.attr('data-timestamp');
                    if (firstMsgTimestamp) {
                        const firstDateKey = getDateKey(firstMsgTimestamp);
                        if (msgDateKey !== firstDateKey) {
                            const dateLabel = getJalaliDateLabel(firstMsgTimestamp);
                            const $divider = $('<div>')
                                .addClass('hmchat-date-divider')
                                .html('<span>' + dateLabel + '</span>');
                            $firstMessage.before($divider);
                        }
                    }
                }
            }
            
            const $message = createMessageElement(msg, currentUserId);
            // Add timestamp attribute for date grouping
            $message.attr('data-timestamp', msg.created_at);
            
            if (prepend) {
                // Insert after load more button if it exists
                const $loadMore = $('.hmchat-load-more');
                if ($loadMore.length) {
                    $loadMore.after($message);
                } else {
                    $wrapper.prepend($message);
                }
            } else if (append) {
                $wrapper.append($message);
            } else {
                $wrapper.append($message);
            }
            
            lastDateKey = msgDateKey;
        });
    }
    
    /**
     * Create message element
     */
    function createMessageElement(msg, currentUserId) {
        const isOwn = msg.user_id == currentUserId;
        const isSystem = msg.message_type === 'system';
        
        let messageClass = 'hmchat-message';
        if (isSystem) {
            messageClass += ' system';
        } else if (isOwn) {
            messageClass += ' own';
        } else {
            messageClass += ' other';
        }
        
        const $message = $('<div>')
            .addClass(messageClass)
            .attr('data-message-id', msg.id);
        
        // Store raw message for editing (without HTML rendering)
        if (!isSystem && msg.message) {
            // Use DOM parser for proper HTML decoding to avoid XSS
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = msg.message;
            const rawText = tempDiv.textContent || tempDiv.innerText || '';
            $message.attr('data-message-raw', rawText);
        }
        
        if (isSystem) {
            // Check if it's a digest message (JSON format)
            const isDigest = msg.message_type === 'system_digest';
            
            if (isDigest) {
                messageClass += ' system-digest';
            }
            
            const $bubble = $('<div>').addClass('hmchat-message-bubble');
            
            if (isDigest) {
                // Render digest message
                try {
                    const digestData = JSON.parse(msg.message);
                    const $digestHeader = $('<div>')
                        .addClass('hmchat-digest-header');
                    
                    $digestHeader.append($('<span>').addClass('hmchat-digest-icon').text('📊'));
                    $digestHeader.append($('<span>').addClass('hmchat-digest-summary').text(digestData.summary || 'خلاصه فعالیت'));
                    $digestHeader.append($('<span>').addClass('hmchat-digest-toggle').text('◀'));
                    
                    const $digestDetails = $('<div>')
                        .addClass('hmchat-digest-details')
                        .css('display', 'none');
                    
                    if (digestData.actions && digestData.actions.length > 0) {
                        const $table = $('<table>').addClass('hmchat-digest-table');
                        digestData.actions.forEach(function(action) {
                            const $row = $('<tr>');
                            const $cell = $('<td>');
                            
                            if (action.viewer_url && action.viewer_url !== '#') {
                                const $link = $('<a>')
                                    .attr('href', action.viewer_url)
                                    .attr('target', '_blank')
                                    .attr('rel', 'noopener noreferrer')
                                    .text('#' + action.file_name);
                                $cell.append($link);
                            } else {
                                $cell.text('#' + action.file_name);
                            }
                            
                            $row.append($cell);
                            $row.append($('<td>').text(action.action_label || action.action));
                            $row.append($('<td>').text(convertToPersianNumbers(action.time)));
                            $table.append($row);
                        });
                        $digestDetails.append($table);
                    }
                    
                    $bubble.append($digestHeader);
                    $bubble.append($digestDetails);
                } catch (e) {
                    // Fallback if JSON parsing fails
                    const $text = $('<p>').addClass('hmchat-message-text').html('📄 ' + msg.message);
                    $bubble.append($text);
                }
            } else {
                // Regular system message
                const $text = $('<p>').addClass('hmchat-message-text').html('📄 ' + msg.message);
                $bubble.append($text);
            }
            
            $message.append($bubble);
        } else {
            // Avatar
            if (msg.user && msg.user.avatar_url) {
                const $avatar = $('<div>').addClass('hmchat-message-avatar');
                const $img = $('<img>').attr('src', msg.user.avatar_url).attr('alt', msg.user.display_name);
                $avatar.append($img);
                $message.append($avatar);
            }
            
            // Content
            const $content = $('<div>').addClass('hmchat-message-content');
            
            // Header
            if (!isOwn) {
                const $header = $('<div>').addClass('hmchat-message-header');
                const $name = $('<span>').addClass('hmchat-message-name').text(msg.user.display_name);
                $header.append($name);
                
                if (msg.user.is_admin) {
                    const $badge = $('<span>').addClass('hmchat-admin-badge').text('🛡️ مدیر');
                    $header.append($badge);
                }
                
                $content.append($header);
            }
            
            // Bubble
            const $bubble = $('<div>').addClass('hmchat-message-bubble');
            const $text = $('<p>').addClass('hmchat-message-text').html(msg.message);
            $bubble.append($text);
            
            // Meta
            const $meta = $('<div>').addClass('hmchat-message-meta');
            const $time = $('<span>').addClass('hmchat-message-time').text(formatTime(msg.created_at));
            $meta.append($time);
            
            if (msg.is_edited) {
                const $edited = $('<span>').addClass('hmchat-edited-label').text('(ویرایش شده)');
                $meta.append($edited);
            }
            
            if (isOwn) {
                const $status = $('<span>').addClass('hmchat-message-status');
                if (msg.seen_count > 0) {
                    $status.addClass('seen').html('✓✓');
                } else {
                    $status.html('✓');
                }
                $meta.append($status);
            }
            
            $bubble.append($meta);
            $content.append($bubble);
            $message.append($content);
        }
        
        return $message;
    }
    
    /**
     * Start edit message
     */
    function startEditMessage(messageId) {
        const $message = $('.hmchat-message[data-message-id="' + messageId + '"]');
        
        // Get original message text from data attribute (if available) or from text content
        let currentText = $message.data('message-raw');
        if (!currentText) {
            currentText = $message.find('.hmchat-message-text').text().trim();
        }
        
        const $editBox = $('<div>').addClass('hmchat-message-edit');
        const $input = $('<textarea>')
            .addClass('hmchat-edit-input')
            .val(currentText);
        
        const $actions = $('<div>').addClass('hmchat-edit-actions');
        const $save = $('<button>').addClass('hmchat-edit-btn hmchat-edit-save').text('ذخیره');
        const $cancel = $('<button>').addClass('hmchat-edit-btn hmchat-edit-cancel').text('لغو');
        $actions.append($save, $cancel);
        
        $editBox.append($input, $actions);
        $message.find('.hmchat-message-content').append($editBox);
        
        chatState.editingMessageId = messageId;
        $input.focus();
    }
    
    /**
     * Save edit message
     */
    function saveEditMessage() {
        const messageId = chatState.editingMessageId;
        const $message = $('.hmchat-message[data-message-id="' + messageId + '"]');
        const newText = $message.find('.hmchat-edit-input').val().trim();
        
        if (!newText) {
            showError('پیام نمی‌تواند خالی باشد');
            return;
        }
        
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_edit_message',
                nonce: hmchat_ajax.nonce,
                message_id: messageId,
                message: newText
            },
            success: function(response) {
                if (response.success) {
                    // Update message
                    $message.find('.hmchat-message-text').html(response.data.message.message);
                    
                    // Add edited label if not exists
                    if (!$message.find('.hmchat-edited-label').length) {
                        const $edited = $('<span>').addClass('hmchat-edited-label').text('(ویرایش شده)');
                        $message.find('.hmchat-message-meta').append($edited);
                    }
                    
                    cancelEditMessage();
                } else {
                    showError(response.data.message || 'خطا در ویرایش پیام');
                }
            },
            error: function() {
                showError('خطا در ویرایش پیام');
            }
        });
    }
    
    /**
     * Cancel edit message
     */
    function cancelEditMessage() {
        $('.hmchat-message-edit').remove();
        chatState.editingMessageId = null;
    }
    
    /**
     * Mark visible messages as seen
     */
    function markVisibleMessagesAsSeen() {
        if (chatState.isMinimized) {
            return;
        }
        
        const messageIds = [];
        const $wrapper = $('.hmchat-messages-wrapper');
        const wrapperTop = $wrapper.scrollTop();
        const wrapperBottom = wrapperTop + $wrapper.height();
        
        $('.hmchat-message:not(.own):not(.system)').each(function() {
            const $msg = $(this);
            const msgTop = $msg.position().top;
            const msgBottom = msgTop + $msg.height();
            
            // Check if message is visible
            if (msgBottom >= wrapperTop && msgTop <= wrapperBottom) {
                const messageId = $msg.data('message-id');
                if (messageId) {
                    messageIds.push(messageId);
                }
            }
        });
        
        if (messageIds.length > 0) {
            $.ajax({
                url: hmchat_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hmchat_mark_seen',
                    nonce: hmchat_ajax.nonce,
                    project_id: chatState.projectId,
                    message_ids: messageIds
                }
            });
        }
    }
    
    /**
     * Show seen details
     */
    function showSeenDetails(messageId) {
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_get_seen_details',
                nonce: hmchat_ajax.nonce,
                message_id: messageId
            },
            success: function(response) {
                if (response.success) {
                    renderSeenModal(response.data.seen_by);
                }
            }
        });
    }
    
    /**
     * Render seen modal
     */
    function renderSeenModal(seenBy) {
        const $modal = $('<div>').addClass('hmchat-seen-modal');
        const $content = $('<div>').addClass('hmchat-seen-content');
        
        const $title = $('<h3>').addClass('hmchat-seen-title').text('مشاهده شده توسط:');
        $content.append($title);
        
        if (seenBy.length === 0) {
            $content.append($('<p>').text('هنوز کسی این پیام را ندیده است'));
        } else {
            const $list = $('<div>').addClass('hmchat-seen-list');
            
            seenBy.forEach(function(user) {
                const $item = $('<div>').addClass('hmchat-seen-item');
                const $user = $('<div>').addClass('hmchat-seen-user').text(user.display_name);
                const $time = $('<div>').addClass('hmchat-seen-time').text(user.seen_at_formatted);
                $item.append($user, $time);
                $list.append($item);
            });
            
            $content.append($list);
        }
        
        const $close = $('<button>').addClass('hmchat-seen-close').text('بستن');
        $content.append($close);
        
        $modal.append($content);
        $('body').append($modal);
    }
    
    /**
     * Load members
     */
    function loadMembers() {
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_get_members',
                nonce: hmchat_ajax.nonce,
                project_id: chatState.projectId
            },
            success: function(response) {
                if (response.success) {
                    chatState.members = response.data.members;
                }
            }
        });
    }
    
    /**
     * Load files
     */
    function loadFiles() {
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_get_files',
                nonce: hmchat_ajax.nonce,
                project_id: chatState.projectId
            },
            success: function(response) {
                if (response.success) {
                    chatState.files = response.data.files;
                }
            }
        });
    }
    
    /**
     * Start polling
     */
    function startPolling() {
        stopPolling();
        
        chatState.pollInterval = setInterval(function() {
            fetchNewMessages();
        }, chatState.currentPollDelay);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (chatState.pollInterval) {
            clearInterval(chatState.pollInterval);
            chatState.pollInterval = null;
        }
    }
    
    /**
     * Track user activity
     */
    function trackActivity() {
        $(document).on('mousemove keydown touchstart', function() {
            chatState.lastActivityTime = Date.now();
            
            if (!chatState.isUserActive) {
                chatState.isUserActive = true;
                updatePollInterval();
            }
            
            clearTimeout(activityTimeout);
            activityTimeout = setTimeout(function() {
                chatState.isUserActive = false;
                updatePollInterval();
            }, 30000); // 30 seconds
        });
    }
    
    /**
     * Update poll interval
     */
    function updatePollInterval() {
        const newInterval = chatState.isUserActive ? POLL_INTERVALS.active : POLL_INTERVALS.idle;
        
        if (newInterval !== chatState.currentPollDelay) {
            chatState.currentPollDelay = newInterval;
            startPolling();
        }
    }
    
    /**
     * Handle visibility change
     */
    function handleVisibilityChange() {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                // Immediate fetch on tab focus
                fetchNewMessages();
                startPolling();
            }
        });
    }
    
    /**
     * Scroll to bottom
     */
    function scrollToBottom() {
        const $wrapper = $('.hmchat-messages-wrapper');
        $wrapper.scrollTop($wrapper[0].scrollHeight);
    }
    
    /**
     * Format time
     */
    function formatTime(datetime) {
        const date = new Date(datetime);
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const time = hours + ':' + minutes;
        
        return convertToPersianNumbers(time);
    }
    
    /**
     * Convert to Persian numbers
     */
    function convertToPersianNumbers(str) {
        const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return String(str).replace(/\d/g, function(digit) {
            return persianNumbers[digit];
        });
    }
    
    /**
     * Convert Gregorian date to Jalali (Persian/Solar Hijri calendar)
     * 
     * This algorithm converts Gregorian calendar dates to the Iranian/Persian calendar (Jalali).
     * The Jalali calendar is a solar calendar used in Iran and Afghanistan.
     * 
     * Algorithm source: Based on the formula by Kazimierz M. Borkowski
     * Reference: https://www.astro.uni.torun.pl/~kb/Papers/EMP/PersianC-EMP.htm
     * 
     * Accuracy: Valid for dates between 1800-2200 CE (approximately 1178-1578 Persian calendar)
     * 
     * @param Date date JavaScript Date object to convert
     * @return Array [year, month, day] in Jalali calendar
     */
    function gregorianToJalali(date) {
        const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        let gy = date.getFullYear();
        let gm = date.getMonth() + 1;
        let gd = date.getDate();
        
        let g_day_no = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400);
        
        for (let i = 0; i < gm - 1; i++) {
            g_day_no += g_d_m[i];
        }
        
        if (gm > 2 && ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0))) {
            g_day_no++;
        }
        
        g_day_no += gd;
        
        let j_day_no = g_day_no - 79;
        
        let j_np = Math.floor(j_day_no / 12053);
        j_day_no = j_day_no % 12053;
        
        let jy = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
        
        j_day_no %= 1461;
        
        if (j_day_no >= 366) {
            jy += Math.floor((j_day_no - 1) / 365);
            j_day_no = (j_day_no - 1) % 365;
        }
        
        let j_d_m = [0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336];
        let jm = 0;
        for (let i = 0; i < 12; i++) {
            if (j_day_no < j_d_m[i]) {
                jm = i;
                break;
            }
        }
        
        // Ensure jm is at least 1 to avoid negative index
        if (jm === 0) {
            jm = 12;
        }
        
        let jd = j_day_no - j_d_m[jm - 1] + 1;
        
        return [jy, jm, jd];
    }
    
    /**
     * Get Jalali date label for message grouping
     */
    function getJalaliDateLabel(timestamp) {
        const now = new Date();
        const msgDate = new Date(timestamp);
        
        // Reset time to midnight for comparison
        const nowMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const msgMidnight = new Date(msgDate.getFullYear(), msgDate.getMonth(), msgDate.getDate());
        
        const diffTime = nowMidnight - msgMidnight;
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 0) {
            return 'امروز';
        } else if (diffDays === 1) {
            return 'دیروز';
        } else {
            const jalali = gregorianToJalali(msgDate);
            const year = jalali[0];
            const month = String(jalali[1]).padStart(2, '0');
            const day = String(jalali[2]).padStart(2, '0');
            return convertToPersianNumbers(year + '/' + month + '/' + day);
        }
    }
    
    /**
     * Get date key for grouping (YYYY-MM-DD format)
     */
    function getDateKey(timestamp) {
        const date = new Date(timestamp);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    /**
     * Show error
     */
    function showError(message) {
        // Simple alert for now - could be improved with a toast notification
        alert(message);
    }
    
    /**
     * Handle tab switching
     */
    function handleTabSwitch(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $tab = $(this);
        const tabType = $tab.data('tab');
        
        // Update active tab
        $('.hmchat-tab').removeClass('active');
        $tab.addClass('active');
        
        const $messagesWrapper = $('.hmchat-messages-wrapper');
        const $inputWrapper = $('.hmchat-input-wrapper');
        let $filesWrapper = $('.hmchat-files-wrapper');
        
        if (tabType === 'files') {
            // Show files tab
            $messagesWrapper.hide();
            $inputWrapper.hide();
            
            if ($filesWrapper.length === 0) {
                $filesWrapper = createFilesTab();
                $('.hmchat-container').find('.hmchat-tabs').after($filesWrapper);
            } else {
                $filesWrapper.show();
            }
            
            loadProjectFiles($filesWrapper);
        } else {
            // Show chat tab
            $messagesWrapper.show();
            $inputWrapper.show();
            if ($filesWrapper.length > 0) {
                $filesWrapper.hide();
            }
        }
    }
    
    /**
     * Create files tab wrapper
     */
    function createFilesTab() {
        const $wrapper = $('<div>')
            .addClass('hmchat-files-wrapper')
            .css({
                'flex': '1',
                'overflow-y': 'auto',
                'padding': '15px',
                'background': '#f8f9fa'
            })
            .html('<p class="text-center">در حال بارگذاری...</p>');
        
        return $wrapper;
    }
    
    /**
     * Load project files
     */
    function loadProjectFiles($wrapper) {
        $.ajax({
            url: hmchat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hmchat_get_project_files',
                nonce: hmchat_ajax.nonce,
                project_id: chatState.projectId
            },
            success: function(response) {
                if (response.success && response.data.files) {
                    $wrapper.html(renderFilesTable(response.data.files));
                } else {
                    $wrapper.html('<p class="text-center" style="color: #999;">هیچ فایلی یافت نشد</p>');
                }
            },
            error: function() {
                $wrapper.html('<p class="text-center" style="color: #999;">خطا در بارگذاری فایلها</p>');
            }
        });
    }
    
    /**
     * Render files table
     */
    function renderFilesTable(files) {
        if (!files || files.length === 0) {
            return '<p class="text-center" style="color: #999;">هیچ فایلی یافت نشد</p>';
        }
        
        let html = '<div class="hmchat-files-list">';
        
        files.forEach(function(file) {
            const ext = file.file_name.split('.').pop().toLowerCase();
            const icon = getFileIcon(ext);
            const size = formatFileSize(file.file_size || 0);
            
            html += '<div class="hmchat-file-item">';
            html += '<span class="hmchat-file-icon">' + icon + '</span>';
            html += '<div class="hmchat-file-info">';
            html += '<div class="hmchat-file-name">' + escapeHtml(file.file_name) + '</div>';
            html += '<div class="hmchat-file-meta">' + size + '</div>';
            html += '</div>';
            html += '<a href="' + escapeHtml(file.file_path) + '" target="_blank" class="hmchat-file-btn">مشاهده</a>';
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    }
    
    /**
     * Get file icon by extension
     */
    function getFileIcon(ext) {
        const icons = {
            'dwg': '📐',
            'dxf': '📐',
            'pdf': '📄',
            'jpg': '🖼️',
            'jpeg': '🖼️',
            'png': '🖼️',
            'kml': '🗺️',
            'kmz': '🗺️',
            'txt': '📝'
        };
        return icons[ext] || '📎';
    }
    
    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initChat();
    });
    
    // Expose to global scope for debugging
    window.HMChat = {
        state: chatState,
        fetchNewMessages: fetchNewMessages,
        sendMessage: sendMessage,
        scrollToBottom: scrollToBottom
    };
    
})(jQuery);
