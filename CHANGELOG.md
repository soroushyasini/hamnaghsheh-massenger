# Changelog

All notable changes to the Hamnaghsheh Massenger plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-06

### Added
- Initial release of Hamnaghsheh Massenger plugin
- Real-time chat system using WordPress Heartbeat API
- Message CRUD operations (create, read, update, delete)
- Message editing with 15-minute time window
- Edit indicator ("✏️ ویرایش شده") for edited messages
- File mention autocomplete feature (@filename)
- System message auto-injection for file actions
- WhatsApp-style read receipts with timestamps
- Read receipt condensed format: "👁️ دیده شده توسط User (14:23)"
- Permission integration with main plugin (Owner/Upload/View)
- Unread message count badge on toggle button
- Browser notifications for new messages (with user permission)
- Persian/RTL layout throughout the interface
- Mobile responsive design (fullscreen on < 768px)
- Database schema with three tables:
  - `wp_hamnaghsheh_chat_messages`
  - `wp_hamnaghsheh_chat_read_status`
  - `wp_hamnaghsheh_chat_metadata`

### Performance Optimizations
- Composite database indexes for fast queries with 100k+ messages
  - `idx_project_created (project_id, created_at)`
  - `idx_project_id_lookup (project_id, id)`
  - `message_user_unique (message_id, user_id)`
- Conditional heartbeat polling:
  - 15 seconds when chat is open (active)
  - 120 seconds when chat is closed (idle)
- Message pagination:
  - Initial load: last 50 messages
  - "Load More" button for older messages (50 per request)
- Read receipt caching using WordPress transients (30-second cache)
- Per-project chat toggle to disable chat when not needed

### Security
- Nonce validation on all AJAX requests
- Input sanitization using `sanitize_text_field` and `wp_kses_post`
- Output escaping using `esc_html` and `esc_attr`
- SQL injection prevention using `$wpdb->prepare()`
- Permission checks before all operations
- XSS prevention in message display

### Integration
- Hook: `hamnaghsheh_chat_render` - Render chat UI on project page
- Hook: `hamnaghsheh_file_action` - Listen to file actions from main plugin
- Automatic backfill of historical file logs on activation
- Integration with main plugin permission system

### File Actions Supported
- Upload (📤 آپلود کرد)
- Replace (🔄 جایگزین کرد)
- Delete (🗑️ حذف کرد)
- Download (⬇️ دانلود کرد)
- See/View (👁️ مشاهده کرد)

### Documentation
- Comprehensive README.md with installation and usage guide
- DOCUMENTATION.md with technical architecture details
- INTEGRATION.md with code examples and integration patterns
- QUICK-REFERENCE.md with common tasks and API reference
- Inline code comments throughout all classes
- Future migration path documentation (Laravel + WebSockets)

### Assets
- RTL CSS with mobile responsive breakpoints
- JavaScript with heartbeat integration and UI interactions
- Chat box template with Persian text
- File autocomplete dropdown
- Edit mode indicator
- Loading and error states

### Developer Features
- Modular class structure
- Singleton pattern for main classes
- AJAX endpoint classes
- Clean separation of concerns
- Extensible hook system
- Transient caching support

### WordPress Standards
- Follows WordPress Coding Standards
- Uses WordPress API functions
- Compatible with WordPress 5.0+
- PHP 7.2+ compatibility
- MySQL 5.6+ compatibility

## [Unreleased]

### Planned for Version 2.0.0 (Future)
- Migration to Laravel + WebSockets
- Replace WordPress Heartbeat with Laravel Broadcasting
- Pusher or Laravel WebSockets integration
- Real-time WebSocket connections
- Reduced server load with persistent connections

### Potential Future Features
- File attachments in messages
- Message reactions (emoji)
- Message threading/replies
- Full-text message search
- Export chat history (PDF, CSV)
- User typing indicators
- Online/offline status indicators
- Voice messages
- Video messages
- Message pinning
- Message bookmarking
- Chat templates
- Auto-responses
- Chat bot integration
- Analytics dashboard
- Message retention policies
- Scheduled messages
- Message templates
- Rich text formatting
- Code syntax highlighting
- Markdown support
- Giphy integration
- Link previews
- Message translation
- Read aloud (TTS)
- Accessibility improvements

## Version History

### Versioning Scheme
- **Major**: Breaking changes, major new features
- **Minor**: New features, backward compatible
- **Patch**: Bug fixes, minor improvements

### Version Support
- Latest version: Full support
- Previous minor: Security updates only
- Older versions: No support

## Migration Guide

### From 0.x to 1.0.0
Not applicable - initial release.

### Future: From 1.x to 2.0.0
When Laravel + WebSockets version is released:
1. Backup database tables (they will be reused)
2. Deactivate WordPress plugin
3. Install Laravel package
4. Run migration scripts
5. Update frontend integration points
6. Test thoroughly before production deployment

## Security Updates

All security vulnerabilities should be reported privately to the maintainer.
Security patches will be released as soon as possible.

## Deprecation Policy

- Features will be marked deprecated for at least one minor version
- Deprecated features will show warnings in debug mode
- Deprecated features will be removed in next major version

## Credits

### Contributors
- Soroush Yasini - Initial development

### Libraries Used
- WordPress Core (Heartbeat API, AJAX, Database)
- jQuery (bundled with WordPress)

### Inspiration
- WhatsApp Web (read receipts, message bubbles)
- Slack (file mentions, system messages)
- Telegram (edit window, inline editing)

## License

This project is licensed under GPL v2 or later.

## Changelog Notes

- Keep changes organized by type (Added, Changed, Deprecated, Removed, Fixed, Security)
- Include issue/PR references when applicable
- Use present tense ("Add feature" not "Added feature")
- Link to relevant documentation
- Highlight breaking changes
- Note database changes
- Document migration steps
