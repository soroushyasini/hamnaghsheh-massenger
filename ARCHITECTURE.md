# Hamnaghsheh Chat Plugin - Technical Architecture

## Overview

The Hamnaghsheh Chat plugin is a comprehensive real-time chat system built as a companion plugin for the Hamnaghsheh Project Management WordPress plugin. It provides project-based team communication with advanced features like mentions, system messages, and seen tracking.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress Frontend                          │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │                    Project Page                            │ │
│  │  ┌─────────────────────────────────────────────────────┐  │ │
│  │  │           Chat Box UI (chat-box.php)                │  │ │
│  │  │  ┌─────────────────────────────────────────────┐    │  │ │
│  │  │  │  Header: Title, Export Btn, Minimize        │    │  │ │
│  │  │  ├─────────────────────────────────────────────┤    │  │ │
│  │  │  │  Tabs: Files | Chat                         │    │  │ │
│  │  │  ├─────────────────────────────────────────────┤    │  │ │
│  │  │  │  Messages Area (scrollable)                 │    │  │ │
│  │  │  │  - System messages (📄)                     │    │  │ │
│  │  │  │  - User messages (bubbles)                  │    │  │ │
│  │  │  │  - Own messages (left, blue)                │    │  │ │
│  │  │  │  - Others (right, white)                    │    │  │ │
│  │  │  │  - Load more button                         │    │  │ │
│  │  │  ├─────────────────────────────────────────────┤    │  │ │
│  │  │  │  Input Area                                 │    │  │ │
│  │  │  │  - [@] [#] mention buttons                  │    │  │ │
│  │  │  │  - Textarea input                           │    │  │ │
│  │  │  │  - Send button                              │    │  │ │
│  │  │  │  - Autocomplete dropdown                    │    │  │ │
│  │  │  └─────────────────────────────────────────────┘    │  │ │
│  │  └─────────────────────────────────────────────────────┘  │ │
│  └───────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              ↕ AJAX Calls
┌─────────────────────────────────────────────────────────────────┐
│                    WordPress Backend (PHP)                      │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Main Plugin File (hamnaghsheh-chat.php)                │   │
│  │  - Constants definition                                 │   │
│  │  - Class loading                                        │   │
│  │  - Hook registration                                    │   │
│  │  - Asset enqueuing                                      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              ↓                                  │
│  ┌──────────────┬──────────────┬────────────────────────────┐  │
│  │ Activator    │ Deactivator  │ Renderer                   │  │
│  │ - DB Tables  │ - Cleanup    │ - Inject UI                │  │
│  │ - Indexes    │              │ - Localize JS              │  │
│  └──────────────┴──────────────┴────────────────────────────┘  │
│                              ↓                                  │
│  ┌──────────────┬──────────────┬────────────────────────────┐  │
│  │ Access       │ Messages     │ Seen                       │  │
│  │ - can_access │ - send       │ - mark_seen                │  │
│  │ - is_owner   │ - fetch      │ - get_details              │  │
│  │ - can_edit   │ - edit       │ - unread_count             │  │
│  │ - members    │ - load_more  │                            │  │
│  └──────────────┴──────────────┴────────────────────────────┘  │
│                              ↓                                  │
│  ┌──────────────┬──────────────┬────────────────────────────┐  │
│  │ Mentions     │ System Msgs  │ Export                     │  │
│  │ - parse      │ - process    │ - to_file                  │  │
│  │ - render     │ - inject     │ - format                   │  │
│  │ - strip      │ - dedup      │                            │  │
│  └──────────────┴──────────────┴────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              ↕
┌─────────────────────────────────────────────────────────────────┐
│                       Database (MySQL)                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  chat_messages (InnoDB)                                 │   │
│  │  - id, project_id, user_id, message                     │   │
│  │  - message_type (text|system)                           │   │
│  │  - is_edited, edited_at, created_at                     │   │
│  │  Indexes: project_id+id DESC, project_id+created_at     │   │
│  └─────────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  chat_seen (InnoDB)                                     │   │
│  │  - id, message_id, user_id, seen_at                     │   │
│  │  Indexes: UNIQUE(message_id,user_id), user_id+seen_at   │   │
│  └─────────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Main Plugin Tables (read-only for chat)                │   │
│  │  - projects, project_assignments, files, file_logs      │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              ↕
┌─────────────────────────────────────────────────────────────────┐
│                    JavaScript (Frontend)                        │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  chat.js - Main Chat Logic                              │   │
│  │  ┌───────────────────────────────────────────────────┐  │   │
│  │  │ State Management                                  │  │   │
│  │  │ - projectId, lastMessageId, isMinimized          │  │   │
│  │  │ - pollInterval, isUserActive, members, files     │  │   │
│  │  └───────────────────────────────────────────────────┘  │   │
│  │  ┌───────────────────────────────────────────────────┐  │   │
│  │  │ Smart Polling                                     │  │   │
│  │  │ - 2s active, 5s idle, 15s dashboard              │  │   │
│  │  │ - Pause on tab hidden, resume on visible         │  │   │
│  │  │ - Activity tracking (mouse, keyboard)            │  │   │
│  │  └───────────────────────────────────────────────────┘  │   │
│  │  ┌───────────────────────────────────────────────────┐  │   │
│  │  │ Message Operations                                │  │   │
│  │  │ - Send (optimistic UI)                           │  │   │
│  │  │ - Fetch new (incremental)                        │  │   │
│  │  │ - Load earlier (lazy)                            │  │   │
│  │  │ - Edit (10 min window)                           │  │   │
│  │  │ - Mark seen (visibility detection)               │  │   │
│  │  └───────────────────────────────────────────────────┘  │   │
│  └─────────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  mentions.js - Autocomplete                             │   │
│  │  - @ user mention with dropdown                         │   │
│  │  - # file mention with dropdown                         │   │
│  │  - Keyboard navigation (arrows, enter, tab)             │   │
│  │  - Filter as you type                                   │   │
│  │  - Insert formatted mention: @[id:name] or #[id:name]   │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Component Breakdown

### PHP Backend Classes (9)

| Class | File | Purpose | Lines |
|-------|------|---------|-------|
| HMChat_Activator | class-chat-activator.php | Create database tables and indexes | ~55 |
| HMChat_Deactivator | class-chat-deactivator.php | Cleanup on deactivation | ~23 |
| HMChat_Access | class-chat-access.php | Permission checking and member management | ~213 |
| HMChat_Messages | class-chat-messages.php | Message CRUD and AJAX handlers | ~515 |
| HMChat_Seen | class-chat-seen.php | Seen status tracking | ~331 |
| HMChat_Mentions | class-chat-mentions.php | Parse and render mentions | ~132 |
| HMChat_System_Messages | class-chat-system-messages.php | File log integration | ~205 |
| HMChat_Export | class-chat-export.php | Export to text file | ~208 |
| HMChat_Renderer | class-chat-renderer.php | Render UI and admin page | ~117 |

### JavaScript Files (2)

| File | Purpose | Lines |
|------|---------|-------|
| chat.js | Main chat logic, polling, UI updates | ~664 |
| mentions.js | Autocomplete for @user and #file | ~293 |

### CSS Files (1)

| File | Purpose | Lines |
|------|---------|-------|
| chat.css | RTL-first, mobile-first styles | ~595 |

### Templates (2)

| File | Purpose | Lines |
|------|---------|-------|
| chat-box.php | Main chat UI container | ~123 |
| admin/all-chats.php | Admin panel for all chats | ~183 |

## AJAX Endpoints (11)

| Endpoint | Method | Purpose | Access |
|----------|--------|---------|--------|
| hmchat_send_message | POST | Send new message | Logged in |
| hmchat_fetch_messages | POST | Get new messages | Logged in |
| hmchat_load_earlier | POST | Load older messages | Logged in |
| hmchat_edit_message | POST | Edit existing message | Owner only |
| hmchat_mark_seen | POST | Mark messages as seen | Logged in |
| hmchat_get_seen_details | POST | Get who saw message | Logged in |
| hmchat_get_unread_count | POST | Get unread count | Logged in |
| hmchat_get_members | POST | Get project members | Logged in |
| hmchat_get_files | POST | Get project files | Logged in |
| hmchat_export_chat | POST | Export chat history | Owner only |

## Database Schema

### chat_messages Table

```sql
CREATE TABLE {prefix}_hamnaghsheh_chat_messages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','system') DEFAULT 'text',
    is_edited TINYINT(1) DEFAULT 0,
    edited_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_id_desc (project_id, id DESC),
    INDEX idx_project_created (project_id, created_at),
    INDEX idx_user_messages (user_id, created_at)
) ENGINE=InnoDB;
```

### chat_seen Table

```sql
CREATE TABLE {prefix}_hamnaghsheh_chat_seen (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_message_user (message_id, user_id),
    INDEX idx_user_seen (user_id, seen_at)
) ENGINE=InnoDB;
```

## Data Flow

### Message Send Flow

```
User types message → JS validates → AJAX send → PHP checks access
→ PHP sanitizes → PHP parses mentions → Insert to DB → Return formatted message
→ JS renders → Scroll to bottom → Mark as sent (✓)
```

### Message Fetch Flow (Polling)

```
JS timer triggers → AJAX fetch (last_message_id) → PHP checks access
→ PHP gets new messages → PHP processes system messages from file_logs
→ PHP formats with user data & seen status → Return to JS
→ JS renders new messages → Mark visible as seen
```

### Mention Flow

```
User types @ or # → JS shows dropdown → Filters as typing
→ User selects → Insert @[id:name] or #[id:name] → Send to server
→ Server stores as-is → On fetch, server renders to HTML
→ <span class="hmchat-mention-user">@name</span>
→ <a class="hmchat-mention-file" href="...">name</a>
```

### System Message Flow

```
User uploads file → Main plugin logs to file_logs table
→ Chat plugin polls file_logs on each fetch
→ Finds new logs → Checks deduplication (5 min window)
→ Creates system message → Inserts to chat_messages
→ Returns in next fetch → Displayed with 📄 icon
```

## Performance Optimizations

1. **Smart Polling**: Adaptive intervals reduce server load
2. **Incremental Fetch**: Only new messages since last ID
3. **Lazy Loading**: Earlier messages loaded on demand
4. **Batch Operations**: Mark multiple messages as seen in one query
5. **Database Indexes**: Optimized for common queries
6. **InnoDB Engine**: Row-level locking for concurrent writes
7. **Visibility Detection**: Only mark visible messages as seen
8. **DOM Caching**: jQuery objects cached where possible

## Security Measures

1. **Nonce Verification**: All AJAX calls verified
2. **Capability Checks**: Access control on every endpoint
3. **Input Sanitization**: `sanitize_text_field`, `wp_kses_post`
4. **Output Escaping**: `esc_html`, `esc_attr`, `esc_url`
5. **Prepared Statements**: SQL injection prevention
6. **Rate Limiting**: 30 messages per minute
7. **Length Validation**: Max 2000 characters
8. **Edit Window**: 10 minutes only
9. **Owner-only Export**: Export restricted to project owner
10. **DOM Sanitization**: Proper HTML entity decoding

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## WordPress Compatibility

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.6+ or MariaDB 10.0+

## Future Enhancements

See CHANGELOG.md for planned features including:
- WebSocket real-time messaging
- Push notifications
- Full Jalali calendar
- Message search
- File attachments
- Message reactions
- Typing indicators
- Online/offline status

---

**Total Lines of Code**: ~4,000
**Total Files**: 17
**Development Time**: Complete implementation in single session
**Status**: Production ready ✅
