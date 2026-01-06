# Quick Reference Guide

## Common Tasks

### Display Chat on Project Page
```php
do_action('hamnaghsheh_chat_render', $project_id);
```

### Trigger File Action
```php
do_action('hamnaghsheh_file_action', $file_id, $project_id, $user_id, $action_type);
// action_type: 'upload', 'replace', 'delete', 'download', 'see'
```

### Send System Message
```php
Hamnaghsheh_Massenger_Messages::insert_message([
    'project_id' => 123,
    'user_id' => 1,
    'message' => 'Custom system message',
    'message_type' => 'system'
]);
```

### Check Permissions
```php
$can_send = Hamnaghsheh_Massenger_Permissions::can_send_message($project_id);
$can_read = Hamnaghsheh_Massenger_Permissions::can_read_messages($project_id);
$permission = Hamnaghsheh_Massenger_Permissions::get_user_permission($project_id);
```

### Get Unread Count
```php
$count = Hamnaghsheh_Massenger_Messages::get_unread_count($project_id);
```

### Enable/Disable Chat for Project
```php
// Disable
global $wpdb;
$wpdb->update(
    $wpdb->prefix . 'hamnaghsheh_chat_metadata',
    ['chat_enabled' => 0],
    ['project_id' => $project_id]
);

// Enable
$wpdb->update(
    $wpdb->prefix . 'hamnaghsheh_chat_metadata',
    ['chat_enabled' => 1],
    ['project_id' => $project_id]
);
```

## AJAX Endpoints

All endpoints require nonce: `hamnaghsheh_chat_nonce`

### Send Message
```javascript
$.post(ajaxurl, {
    action: 'hamnaghsheh_send_message',
    nonce: nonce,
    project_id: 123,
    message: 'Hello world'
});
```

### Edit Message
```javascript
$.post(ajaxurl, {
    action: 'hamnaghsheh_edit_message',
    nonce: nonce,
    message_id: 456,
    message: 'Updated message'
});
```

### Load Messages
```javascript
$.post(ajaxurl, {
    action: 'hamnaghsheh_load_messages',
    nonce: nonce,
    project_id: 123
});
```

### Load More Messages
```javascript
$.post(ajaxurl, {
    action: 'hamnaghsheh_load_more_messages',
    nonce: nonce,
    project_id: 123,
    before_id: 456
});
```

### Mark as Read
```javascript
$.post(ajaxurl, {
    action: 'hamnaghsheh_mark_as_read',
    nonce: nonce,
    message_ids: [1, 2, 3]
});
```

### Search Files
```javascript
$.post(ajaxurl, {
    action: 'hamnaghsheh_search_files',
    nonce: nonce,
    project_id: 123,
    query: 'drawing'
});
```

## Database Tables

### Messages
- Table: `wp_hamnaghsheh_chat_messages`
- Primary Key: `id`
- Indexes: `idx_project_created`, `idx_project_id_lookup`, `file_log_id`

### Read Status
- Table: `wp_hamnaghsheh_chat_read_status`
- Primary Key: `id`
- Unique: `message_user_unique`
- Index: `idx_message_user`

### Metadata
- Table: `wp_hamnaghsheh_chat_metadata`
- Primary Key: `id`
- Unique: `project_id`

## File Structure

```
hamnaghsheh-massenger/
├── hamnaghsheh-massenger.php    # Main plugin file
├── uninstall.php                # Uninstall cleanup
├── README.md                    # Main documentation
├── DOCUMENTATION.md             # Technical docs
├── INTEGRATION.md               # Integration guide
├── includes/
│   ├── class-activator.php      # Activation handler
│   ├── class-deactivator.php    # Deactivation handler
│   ├── class-messages.php       # Message CRUD
│   ├── class-read-status.php    # Read receipts
│   ├── class-permissions.php    # Access control
│   ├── class-file-logger.php    # File action logger
│   ├── class-heartbeat.php      # WordPress Heartbeat
│   └── class-ajax.php           # AJAX handlers
├── assets/
│   ├── css/
│   │   └── chat.css            # RTL styles
│   └── js/
│       └── chat.js             # Frontend logic
└── templates/
    └── chat-box.php            # Chat UI template
```

## Permission Levels

- **owner**: Full access (send, read, edit, manage)
- **upload**: Send and read messages
- **view**: Read-only access
- **none**: No access

## Message Types

- **user**: Regular user message (editable within 15 minutes)
- **system**: Auto-generated system message (not editable)

## Action Types (File Logger)

- **upload**: 📤 آپلود کرد
- **replace**: 🔄 جایگزین کرد
- **delete**: 🗑️ حذف کرد
- **download**: ⬇️ دانلود کرد
- **see**: 👁️ مشاهده کرد

## Performance Settings

- **Heartbeat Interval**: 15s (active) / 120s (idle)
- **Initial Load**: 50 messages
- **Pagination**: 50 messages per request
- **Read Receipt Cache**: 30 seconds
- **Edit Window**: 15 minutes

## CSS Classes

- `.hamnaghsheh-chat-toggle`: Toggle button
- `.hamnaghsheh-chat-container`: Main container
- `.hamnaghsheh-chat-messages`: Messages area
- `.hamnaghsheh-message`: Single message
- `.hamnaghsheh-message.system`: System message
- `.hamnaghsheh-chat-input`: Input area
- `.unread-badge`: Unread count badge

## JavaScript Events

```javascript
// Heartbeat send
$(document).on('heartbeat-send', function(e, data) {
    data.hamnaghsheh_chat = { ... };
});

// Heartbeat tick
$(document).on('heartbeat-tick', function(e, data) {
    if (data.hamnaghsheh_chat) { ... }
});
```

## Security Checklist

- [x] Nonce validation on AJAX
- [x] Input sanitization
- [x] Output escaping
- [x] SQL injection prevention
- [x] Permission checks
- [x] XSS prevention

## Debugging

Enable WordPress debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

## Common SQL Queries

### Get all messages for project
```sql
SELECT * FROM wp_hamnaghsheh_chat_messages
WHERE project_id = 123
ORDER BY created_at DESC
LIMIT 50;
```

### Get unread count for user
```sql
SELECT COUNT(*) FROM wp_hamnaghsheh_chat_messages m
LEFT JOIN wp_hamnaghsheh_chat_read_status r 
  ON m.id = r.message_id AND r.user_id = 1
WHERE m.project_id = 123 
  AND m.user_id != 1
  AND r.id IS NULL;
```

### Get read receipts for message
```sql
SELECT user_id, read_at 
FROM wp_hamnaghsheh_chat_read_status
WHERE message_id = 456
ORDER BY read_at ASC;
```

### Check if chat is enabled
```sql
SELECT chat_enabled 
FROM wp_hamnaghsheh_chat_metadata
WHERE project_id = 123;
```

## Browser Compatibility

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+
- Mobile browsers (iOS Safari, Chrome Mobile)

## WordPress Requirements

- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+
- jQuery (bundled with WordPress)
- Heartbeat API (enabled by default)

## Localization

All text strings are in Persian (فارسی).
To add translations, use WordPress i18n functions:

```php
__('Text', 'hamnaghsheh-massenger')
_e('Text', 'hamnaghsheh-massenger')
esc_html__('Text', 'hamnaghsheh-massenger')
```

## Troubleshooting

### Chat not appearing?
1. Check if action is called: `do_action('hamnaghsheh_chat_render', $project_id);`
2. Verify user has permission
3. Check browser console for errors

### Messages not updating?
1. Verify WordPress Heartbeat is enabled
2. Check network tab for AJAX requests
3. Verify nonce is valid

### Performance issues?
1. Check database indexes are created
2. Monitor query count with Query Monitor plugin
3. Verify heartbeat interval is adjusting

### Read receipts not showing?
1. Clear transient cache: delete from `wp_options` where `option_name` like `_transient_hamnaghsheh_chat_%`
2. Check if users have read permissions
3. Verify messages are being marked as read

## Support

- GitHub Issues: https://github.com/soroushyasini/hamnaghsheh-massenger/issues
- Documentation: README.md, DOCUMENTATION.md, INTEGRATION.md
