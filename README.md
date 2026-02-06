# Hamnaghsheh Messenger

Real-time chat plugin for WordPress that integrates with Hamnaghsheh PM to provide project-based communication with Server-Sent Events (SSE), auto-messages from file activities, and "seen by" functionality.

## 🎯 Features

### Phase 1 - MVP (Implemented)
- ✅ Real-time messaging with SSE (<1 second latency)
- ✅ **"Seen by" feature** (like Telegram) - Shows who has read each message
- ✅ Auto-messages from file upload/delete/replace actions
- ✅ Mobile-first floating button UI (60% mobile priority)
- ✅ Permission-based access (project members only)
- ✅ Message timestamps and user avatars
- ✅ Unread message badges
- ✅ Typing indicators ("X is typing...")
- ✅ Edit/delete own messages (within 15 minutes)
- ✅ Search chat history
- ✅ Load older messages (lazy loading)
- ✅ Export chat log to TXT

### Phase 2 - Future Enhancements
- 📝 Export to PDF (requires TCPDF library)
- 📝 Mention users (@username)
- 📝 File references in messages
- 📝 Email notifications
- 📝 Dark mode

## 📋 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- **Hamnaghsheh PM** plugin (required dependency)
- MySQL 5.7 or higher
- Modern web browser with EventSource support (SSE)

## 📦 Installation

1. **Upload the plugin:**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/soroushyasini/hamnaghsheh-massenger.git
   ```

2. **Activate the plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Hamnaghsheh Messenger"
   - Click "Activate"

3. **Database tables will be created automatically:**
   - `wp_hamnaghsheh_chat_messages`
   - `wp_hamnaghsheh_chat_reads`
   - `wp_hamnaghsheh_chat_typing`

## 🔧 Integration with Hamnaghsheh PM

### 1. Render Chat UI on Project Page

Add this hook to your project template:

```php
// In your project page template (e.g., single-project.php)
do_action('hamnaghsheh_chat_render', $project_id, $project);
```

### 2. Auto-Messages from File Activities

The plugin automatically listens to file actions:

```php
// The main plugin should trigger this action
do_action('hamnaghsheh_file_action', $file_id, $project_id, $user_id, $action_type);

// Supported actions:
// - 'upload'  → Creates "فایل X را آپلود کرد"
// - 'replace' → Creates "فایل X را جایگزین کرد"
// - 'delete'  → Creates "فایل X را حذف کرد"
// - 'download' and 'see' are ignored (too noisy)
```

### 3. Cleanup on Project Delete

```php
// The main plugin should trigger this action
do_action('hamnaghsheh_project_deleted', $project_id);
```

## 🗄️ Database Schema

### Table 1: Messages
```sql
wp_hamnaghsheh_chat_messages
- id (primary key)
- project_id (foreign key)
- user_id (NULL for system messages)
- message_type (text, system, file_activity)
- message (TEXT)
- metadata (JSON) - stores file refs, mentions, edit history
- edited_at (DATETIME)
- deleted_at (DATETIME) - soft delete
- created_at (DATETIME)
```

### Table 2: Read Status (CRITICAL)
```sql
wp_hamnaghsheh_chat_reads
- id (primary key)
- message_id (foreign key)
- user_id (foreign key)
- read_at (DATETIME)
- UNIQUE(message_id, user_id)
```

### Table 3: Typing Indicators
```sql
wp_hamnaghsheh_chat_typing
- project_id (primary key)
- user_id (primary key)
- last_typed_at (DATETIME)
```

## ⚡ Server-Sent Events (SSE)

### How SSE Works

1. Client opens EventSource connection to WordPress
2. Server streams events for 30 seconds
3. Connection closes, browser auto-reconnects
4. Events: `new_message`, `user_typing`, heartbeat

### SSE Endpoint

```
GET /wp-admin/admin-ajax.php?action=hamnaghsheh_sse_stream&project_id=1&last_message_id=0&nonce=xxx
```

### Fallback to Polling

If EventSource is not supported (IE11), the plugin falls back to AJAX polling every 3 seconds.

### Server Configuration

**For Nginx:**
```nginx
# Disable buffering for SSE
location ~ /wp-admin/admin-ajax\.php {
    proxy_buffering off;
    proxy_cache off;
}
```

**For Apache:**
No special configuration needed. Ensure `mod_headers` is enabled.

## 🔒 Security Features

- ✅ Nonce verification on all AJAX requests
- ✅ `$wpdb->prepare()` for all SQL queries
- ✅ `esc_html()`, `esc_attr()` for all output
- ✅ Permission checks before every action
- ✅ Rate limiting (max 10 messages/minute per user)
- ✅ Soft delete (no permanent deletion)
- ✅ XSS prevention with `wp_kses_post()`

## 🎨 UI/UX

### Mobile (60% Priority)
- Fullscreen overlay
- Floating action button (bottom-right)
- Swipe down to close
- Touch-optimized inputs

### Desktop (40% Priority)
- Windowed chat (400x600px)
- Bottom-right corner
- Hover effects
- Keyboard shortcuts (ESC to close)

### RTL Support
All text is RTL-friendly for Persian (Farsi) language:
- `dir="rtl"` on chat container
- Time stamps on left side
- Checkmarks on left of messages

## 📱 Browser Support

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ iOS Safari (latest)
- ✅ Chrome Android (latest)
- ⚠️ IE11 (polling fallback)

## 🛠️ Developer API

### JavaScript

```javascript
// Initialize messenger
HamnaghshehMessenger.init(projectId);

// Send message
HamnaghshehMessenger.sendMessage();

// Edit message
HamnaghshehMessenger.editMessage(messageId, newMessage);

// Delete message
HamnaghshehMessenger.deleteMessage(messageId);

// Search messages
HamnaghshehMessenger.searchMessages(query);

// Export chat
HamnaghshehMessenger.exportChat('txt');
```

### PHP

```php
// Send message
Hamnaghsheh_Messenger_Messages::send_message($project_id, $user_id, $message);

// Mark as read
Hamnaghsheh_Messenger_Seen_Tracker::mark_as_read($message_id, $user_id);

// Get unread count
Hamnaghsheh_Messenger_Notifications::get_project_unread($project_id, $user_id);

// Check permission
Hamnaghsheh_Messenger_Permissions::can_user_chat($project_id, $user_id);
```

## 🔄 Cron Jobs

The plugin schedules a cron job to cleanup old typing indicators:

```php
// Runs hourly
wp_schedule_event(time(), 'hourly', 'hamnaghsheh_messenger_cleanup_typing');
```

## 🐛 Troubleshooting

### SSE not working?

1. Check server supports SSE (PHP 7.4+)
2. Check Nginx buffering is disabled
3. Check browser console for errors
4. Try polling fallback (automatic)

### Messages not appearing?

1. Check permissions (user must be project member)
2. Check browser console for JavaScript errors
3. Clear WordPress transient cache
4. Check database tables exist

### "Access denied" error?

1. Verify user is assigned to project
2. Check user has 'upload' or 'owner' permission
3. View-only users can see chat but cannot send

## 📄 License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## 👥 Credits

Developed for the Hamnaghsheh PM ecosystem.

## 🆘 Support

For issues and feature requests, please use the GitHub repository:
https://github.com/soroushyasini/hamnaghsheh-massenger/issues
