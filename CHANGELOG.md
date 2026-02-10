# Changelog

All notable changes to the Hamnaghsheh Chat plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-10

### Added
- Initial release of Hamnaghsheh Chat plugin
- Real-time chat system for project-based communication
- Smart polling with adaptive intervals (2s active, 5s idle, 15s dashboard)
- Access control system (project owner, assigned members, admins only)
- @user mention system with autocomplete dropdown
- #file mention system with autocomplete dropdown
- System messages automatically injected from file log activities:
  - File upload notifications
  - File replace notifications
  - File delete notifications
  - File download notifications (deduplicated)
  - File view notifications (deduplicated)
- Message editing functionality (10-minute window)
- Seen status tracking with ✓ (sent) and ✓✓ (seen) indicators
- Click on ✓✓ to view who has seen the message
- Lazy loading of earlier messages (scroll up to load more)
- Chat export feature (owner-only, UTF-8 text file)
- Rate limiting: 30 messages per minute per user
- Message length limit: 2000 characters
- RTL (right-to-left) design for Persian language
- Mobile-first responsive design
- Admin panel to view all project chats
- Unread message count badges (dashboard integration ready)

### Database
- Created `{prefix}_hamnaghsheh_chat_messages` table with optimized indexes
- Created `{prefix}_hamnaghsheh_chat_seen` table with unique constraints
- InnoDB engine for better concurrency and data integrity

### Security
- Nonce verification on all AJAX endpoints
- User capability and access checks on every request
- Input sanitization using WordPress functions
- Output escaping to prevent XSS attacks
- SQL injection prevention with prepared statements
- Rate limiting to prevent abuse
- Message length validation
- Secure file export with proper headers

### Performance
- Optimized database queries with proper indexing
- Batch operations for marking messages as seen
- Smart polling reduces server load
- Lazy loading prevents initial load bloat
- Efficient DOM updates in JavaScript

### User Interface
- Clean, modern design matching main plugin
- Primary color: #09375B (dark navy)
- Accent color: #FFCF00 (golden yellow)
- Vazirmatn font for Persian text
- Smooth animations and transitions
- Touch-friendly controls for mobile
- Proper viewport handling for iPhone notch
- Minimizable chat window

### Developer Features
- Well-documented code with inline comments
- Modular class structure for easy maintenance
- 11 AJAX endpoints for various operations
- Hooks and filters for extensibility
- Clear separation of concerns
- WordPress coding standards compliance

### Known Limitations
- Jalali (Persian) calendar conversion not fully implemented (uses Gregorian dates with Persian numbers)
- System message deduplication window is fixed at 5 minutes
- No real-time push notifications (uses polling)
- No file attachments in chat (mentions only)
- No message search functionality
- No message deletion (only editing within 10 minutes)

### Future Enhancements (Planned)
- WebSocket support for true real-time messaging
- Push notifications for mobile devices
- Full Jalali calendar implementation
- Message search functionality
- File attachment support
- Message reactions (emoji)
- Typing indicators
- Online/offline status
- Message deletion
- Chat archiving
- Export to PDF format
