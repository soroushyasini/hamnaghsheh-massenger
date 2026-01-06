# Code Documentation

## Architecture Overview

The Hamnaghsheh Massenger plugin follows a modular architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────┐
│                Main Plugin File                      │
│            (hamnaghsheh-massenger.php)              │
└──────────────────┬──────────────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
   ┌────▼────┐          ┌────▼────┐
   │ Backend │          │Frontend │
   │ Classes │          │ Assets  │
   └────┬────┘          └────┬────┘
        │                    │
   ┌────┴────────────────────┴────┐
   │                              │
┌──▼───────┐              ┌──────▼──────┐
│ Database │              │   Template  │
│  Tables  │              │  (chat-box) │
└──────────┘              └─────────────┘
```

## Class Structure

### 1. Hamnaghsheh_Massenger (Main Class)
**Location**: `hamnaghsheh-massenger.php`

**Responsibilities**:
- Initialize the plugin
- Load dependencies
- Register hooks
- Enqueue assets
- Render chat UI

**Key Methods**:
- `get_instance()`: Singleton pattern
- `enqueue_assets()`: Load CSS/JS
- `render_chat_ui($project_id)`: Render chat template

### 2. Hamnaghsheh_Massenger_Activator
**Location**: `includes/class-activator.php`

**Responsibilities**:
- Create database tables on activation
- Add indexes for performance
- Backfill historical file logs

**Key Methods**:
- `activate()`: Main activation handler
- `create_tables()`: Create database schema
- `backfill_file_logs()`: Import existing logs

**Database Tables Created**:
- `wp_hamnaghsheh_chat_messages`
- `wp_hamnaghsheh_chat_read_status`
- `wp_hamnaghsheh_chat_metadata`

### 3. Hamnaghsheh_Massenger_Deactivator
**Location**: `includes/class-deactivator.php`

**Responsibilities**:
- Clean up on deactivation
- Clear transients
- Flush rewrite rules

**Note**: Does NOT drop tables (data preserved)

### 4. Hamnaghsheh_Massenger_Messages
**Location**: `includes/class-messages.php`

**Responsibilities**:
- CRUD operations for messages
- Pagination logic
- Unread count calculation

**Key Methods**:
- `insert_message($data)`: Create new message
- `update_message($id, $message)`: Edit message (15-min window)
- `get_messages($project_id, $limit, $offset, $before_id)`: Retrieve messages
- `get_messages_with_details($project_id, $limit, $before_id)`: Enhanced retrieval
- `get_unread_count($project_id, $user_id)`: Count unread messages
- `delete_message($id)`: Remove message

### 5. Hamnaghsheh_Massenger_Read_Status
**Location**: `includes/class-read-status.php`

**Responsibilities**:
- Track message read status
- Generate read receipts
- Cache read status (30s transient)

**Key Methods**:
- `mark_as_read($message_id, $user_id)`: Mark single message
- `mark_multiple_as_read($message_ids, $user_id)`: Bulk mark
- `get_read_receipts($message_id)`: Get readers with cache
- `get_formatted_receipts($message_id)`: WhatsApp-style string
- `is_read($message_id, $user_id)`: Check read status

**Caching Strategy**:
- Uses WordPress transients
- 30-second cache lifetime
- Key format: `hamnaghsheh_chat_receipts_{message_id}`

### 6. Hamnaghsheh_Massenger_Permissions
**Location**: `includes/class-permissions.php`

**Responsibilities**:
- Check user access to projects
- Validate permissions for actions
- Integration with main plugin

**Permission Levels**:
- `owner`: Full access (send, read, manage)
- `upload`: Send and read messages
- `view`: Read-only access
- `none`: No access

**Key Methods**:
- `get_user_permission($project_id, $user_id)`: Get permission level
- `can_send_message($project_id, $user_id)`: Check send permission
- `can_read_messages($project_id, $user_id)`: Check read permission
- `can_edit_message($message_id, $user_id)`: Check edit permission
- `verify_permission($project_id, $required)`: Verify and send error

### 7. Hamnaghsheh_Massenger_File_Logger
**Location**: `includes/class-file-logger.php`

**Responsibilities**:
- Listen to file action hooks
- Generate system messages
- Auto-inject file logs into chat

**Hook Integration**:
```php
add_action('hamnaghsheh_file_action', [$this, 'inject_file_log_to_chat'], 10, 4);
```

**Supported Actions**:
- `upload`: 📤 آپلود کرد
- `replace`: 🔄 جایگزین کرد
- `delete`: 🗑️ حذف کرد
- `download`: ⬇️ دانلود کرد
- `see`: 👁️ مشاهده کرد

**Key Methods**:
- `inject_file_log_to_chat($file_id, $project_id, $user_id, $action_type)`: Main handler
- `generate_system_message($user_name, $action_type, $file_name)`: Create Persian message
- `get_action_icon($action_type)`: Get emoji icon

### 8. Hamnaghsheh_Massenger_Heartbeat
**Location**: `includes/class-heartbeat.php`

**Responsibilities**:
- Handle WordPress Heartbeat API
- Send new messages to client
- Update unread counts

**Heartbeat Flow**:
```
Client                    Server
  │                         │
  ├─ heartbeat-send ───────>│
  │  (project_id,           │
  │   last_message_id)      │
  │                         │
  │<─── heartbeat-tick ─────┤
     (new_messages,         │
      unread_count)         │
```

**Key Methods**:
- `heartbeat_received($response, $data)`: Handle tick
- `heartbeat_settings($settings)`: Modify settings
- `get_new_messages($project_id, $last_message_id)`: Fetch new messages

### 9. Hamnaghsheh_Massenger_Ajax
**Location**: `includes/class-ajax.php`

**Responsibilities**:
- Handle AJAX endpoints
- Validate nonces
- Return JSON responses

**AJAX Endpoints**:
- `hamnaghsheh_send_message`: Send new message
- `hamnaghsheh_edit_message`: Edit existing message
- `hamnaghsheh_load_messages`: Load initial messages
- `hamnaghsheh_load_more_messages`: Pagination
- `hamnaghsheh_mark_as_read`: Mark as read
- `hamnaghsheh_get_unread_count`: Get unread count
- `hamnaghsheh_search_files`: File autocomplete

**Security**:
- Nonce verification on all requests
- Input sanitization
- Permission checks
- SQL injection prevention

## Frontend Architecture

### JavaScript (chat.js)

**Main Functions**:
- `initChat()`: Initialize chat system
- `setupEventListeners()`: Bind UI events
- `setupHeartbeat()`: Configure WordPress Heartbeat
- `updateHeartbeatInterval()`: Conditional polling (15s/120s)
- `openChat()`: Open chat UI
- `closeChat()`: Close chat UI
- `loadMessages()`: Load initial messages
- `loadMoreMessages()`: Pagination
- `sendMessage()`: Send new message
- `editMessage()`: Edit existing message
- `markMessagesAsRead()`: Mark as read
- `handleFileAutocomplete()`: File mention feature
- `showNotification()`: Browser notifications

**Heartbeat Integration**:
```javascript
// Send data to server
$(document).on('heartbeat-send', function(e, data) {
    data.hamnaghsheh_chat = {
        project_id: currentProjectId,
        last_message_id: lastMessageId
    };
});

// Receive data from server
$(document).on('heartbeat-tick', function(e, data) {
    if (data.hamnaghsheh_chat) {
        handleHeartbeatResponse(data.hamnaghsheh_chat);
    }
});
```

**Conditional Heartbeat**:
```javascript
if (chatOpen) {
    wp.heartbeat.interval(15);  // Active: 15 seconds
} else {
    wp.heartbeat.interval(120); // Idle: 2 minutes
}
```

### CSS (chat.css)

**Key Features**:
- RTL layout
- Mobile responsive (< 768px = fullscreen)
- Persian font support
- Smooth animations
- WhatsApp-style message bubbles

**Breakpoints**:
- Desktop: `> 768px` - 400px sidebar
- Mobile: `<= 768px` - Fullscreen

## Database Schema Details

### Message Indexing Strategy

**Indexes**:
1. `idx_project_created (project_id, created_at)`: Fast chronological queries
2. `idx_project_id_lookup (project_id, id)`: Fast ID-based lookups
3. `file_log_id (file_log_id)`: Fast file log joins

**Query Optimization**:
```sql
-- Get recent messages (uses idx_project_created)
SELECT * FROM wp_hamnaghsheh_chat_messages
WHERE project_id = 123
ORDER BY created_at DESC, id DESC
LIMIT 50;

-- Get messages before ID (uses idx_project_id_lookup)
SELECT * FROM wp_hamnaghsheh_chat_messages
WHERE project_id = 123 AND id < 1000
ORDER BY created_at DESC, id DESC
LIMIT 50;
```

### Read Status Optimization

**Unique Index**:
- `message_user_unique (message_id, user_id)`: Prevents duplicate reads

**Query Pattern**:
```sql
-- Get unread messages
SELECT COUNT(*) FROM wp_hamnaghsheh_chat_messages m
LEFT JOIN wp_hamnaghsheh_chat_read_status r 
  ON m.id = r.message_id AND r.user_id = 1
WHERE m.project_id = 123 
  AND m.user_id != 1
  AND r.id IS NULL;
```

## Performance Optimizations

### 1. Database Indexes
- Composite indexes for multi-column queries
- Covering indexes for common queries
- Unique constraints for data integrity

### 2. Conditional Heartbeat
- Active: 15 seconds (chat open)
- Idle: 120 seconds (chat closed)
- Reduces server load by 87.5% when idle

### 3. Message Pagination
- Initial load: 50 messages
- Load more: 50 messages per request
- Prevents memory exhaustion

### 4. Read Receipt Caching
- 30-second transient cache
- Reduces queries for popular messages
- Auto-expiration

### 5. Per-Project Toggle
- Check `chat_enabled` before rendering
- Skip heartbeat for disabled projects
- Reduce unnecessary overhead

## Security Measures

### Input Validation
```php
// Sanitize user input
$message = wp_kses_post($_POST['message']);

// Validate integers
$project_id = intval($_POST['project_id']);
```

### Output Escaping
```php
// Escape for HTML
echo esc_html($message);

// Escape for attributes
echo esc_attr($project_id);
```

### SQL Injection Prevention
```php
// Always use prepare
$wpdb->prepare(
    "SELECT * FROM $table WHERE project_id = %d AND id < %d",
    $project_id,
    $before_id
);
```

### Nonce Verification
```php
// Verify nonce on AJAX
if (!wp_verify_nonce($_POST['nonce'], 'hamnaghsheh_chat_nonce')) {
    wp_send_json_error(['message' => 'Invalid request']);
}
```

### Permission Checks
```php
// Always verify permissions
if (!Hamnaghsheh_Massenger_Permissions::can_send_message($project_id)) {
    wp_send_json_error(['message' => 'No permission']);
}
```

## Integration Points

### Main Plugin Hooks

**Render Chat**:
```php
do_action('hamnaghsheh_chat_render', $project_id);
```

**File Actions**:
```php
do_action('hamnaghsheh_file_action', $file_id, $project_id, $user_id, $action_type);
```

### Permission Integration
```php
// Get from main plugin if available
if (function_exists('hamnaghsheh_get_project')) {
    $project = hamnaghsheh_get_project($project_id);
    $permission = $project->user_permission;
}
```

## Testing Checklist

- [ ] Plugin activates without errors
- [ ] Tables created with correct indexes
- [ ] Historical logs backfilled
- [ ] Chat UI renders
- [ ] Send message works
- [ ] Edit message (15 min window)
- [ ] File autocomplete
- [ ] System messages auto-inject
- [ ] Read receipts display
- [ ] Heartbeat updates (15s)
- [ ] Unread count badge
- [ ] Browser notifications
- [ ] Permissions enforced
- [ ] Mobile fullscreen
- [ ] Pagination works
- [ ] Performance: < 50ms impact

## Future Enhancements

### Phase 2: Laravel + WebSockets
- Replace Heartbeat with Laravel Broadcasting
- Use Pusher or Laravel WebSockets
- Keep same database schema
- Minimal frontend changes

### Phase 3: Advanced Features
- File attachments in messages
- Message reactions (emoji)
- Threading/replies
- Message search
- Export chat history
- User typing indicators
- Online/offline status
