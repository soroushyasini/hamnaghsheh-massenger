# Integration Examples

This file provides examples of how to integrate the Hamnaghsheh Massenger plugin with the main Hamnaghsheh PM plugin.

## Basic Integration

### 1. Rendering Chat on Project Page

Add this to your project page template (typically in the main plugin):

```php
<?php
/**
 * Project Page Template
 * File: hamnaghsheh-pm/templates/project-page.php
 */

// Your existing project page code...
$project_id = get_query_var('project_id');
$project = hamnaghsheh_get_project($project_id);

// Display project details
?>
<div class="project-container">
    <h1><?php echo esc_html($project->name); ?></h1>
    
    <!-- Your existing project content -->
    <div class="project-files">
        <!-- File list, etc. -->
    </div>
    
    <!-- Add chat UI here -->
    <?php
    if (function_exists('do_action')) {
        // This will render the chat box if plugin is active
        do_action('hamnaghsheh_chat_render', $project_id);
    }
    ?>
</div>
```

### 2. Triggering File Actions

When a file action occurs in the main plugin, trigger the hook:

```php
<?php
/**
 * Example: File Upload Handler
 * File: hamnaghsheh-pm/includes/class-file-handler.php
 */

class Hamnaghsheh_File_Handler {
    
    public function handle_file_upload($file_id, $project_id) {
        // Your existing file upload logic...
        
        // After successful upload, trigger the action
        do_action(
            'hamnaghsheh_file_action',
            $file_id,      // File ID
            $project_id,   // Project ID
            get_current_user_id(), // User ID
            'upload'       // Action type: upload, replace, delete, download, see
        );
    }
    
    public function handle_file_replace($file_id, $project_id) {
        // Your existing file replace logic...
        
        do_action(
            'hamnaghsheh_file_action',
            $file_id,
            $project_id,
            get_current_user_id(),
            'replace'
        );
    }
    
    public function handle_file_delete($file_id, $project_id) {
        // Your existing file delete logic...
        
        do_action(
            'hamnaghsheh_file_action',
            $file_id,
            $project_id,
            get_current_user_id(),
            'delete'
        );
    }
    
    public function handle_file_download($file_id, $project_id) {
        // Your existing file download logic...
        
        do_action(
            'hamnaghsheh_file_action',
            $file_id,
            $project_id,
            get_current_user_id(),
            'download'
        );
    }
    
    public function handle_file_view($file_id, $project_id) {
        // When user views/previews a file
        
        do_action(
            'hamnaghsheh_file_action',
            $file_id,
            $project_id,
            get_current_user_id(),
            'see'
        );
    }
}
```

## Advanced Integration

### 3. Providing Permission Information

If your main plugin has a function to get project with permissions:

```php
<?php
/**
 * Example: Project Permission Function
 * File: hamnaghsheh-pm/includes/class-project.php
 */

/**
 * Get project with user permission
 *
 * @param int $project_id Project ID
 * @return object|false Project object with user_permission property
 */
function hamnaghsheh_get_project($project_id) {
    global $wpdb;
    
    $projects_table = $wpdb->prefix . 'hamnaghsheh_projects';
    $project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $projects_table WHERE id = %d",
        $project_id
    ));
    
    if (!$project) {
        return false;
    }
    
    // Add user permission to project object
    $user_id = get_current_user_id();
    $project->user_permission = get_user_project_permission($project_id, $user_id);
    
    return $project;
}

/**
 * Get user permission for a project
 *
 * @param int $project_id Project ID
 * @param int $user_id User ID
 * @return string Permission level: owner, upload, view
 */
function get_user_project_permission($project_id, $user_id) {
    global $wpdb;
    
    $projects_table = $wpdb->prefix . 'hamnaghsheh_projects';
    $members_table = $wpdb->prefix . 'hamnaghsheh_project_members';
    
    // Check if user is owner
    $owner_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $projects_table WHERE id = %d",
        $project_id
    ));
    
    if ($owner_id == $user_id) {
        return 'owner';
    }
    
    // Check member permission
    $permission = $wpdb->get_var($wpdb->prepare(
        "SELECT permission FROM $members_table 
        WHERE project_id = %d AND user_id = %d",
        $project_id,
        $user_id
    ));
    
    return $permission ? $permission : false;
}
```

### 4. Adding Chat to Shortcode

If you want to allow chat via shortcode:

```php
<?php
/**
 * Example: Chat Shortcode
 * File: hamnaghsheh-pm/includes/class-shortcodes.php
 */

/**
 * Render project chat via shortcode
 * 
 * Usage: [hamnaghsheh_chat project_id="123"]
 */
function hamnaghsheh_chat_shortcode($atts) {
    $atts = shortcode_atts(array(
        'project_id' => 0,
    ), $atts);
    
    $project_id = intval($atts['project_id']);
    
    if (!$project_id) {
        return '<p>Invalid project ID</p>';
    }
    
    // Check if chat plugin is active
    if (!function_exists('do_action')) {
        return '<p>Chat plugin not active</p>';
    }
    
    // Start output buffering
    ob_start();
    
    // Render chat
    do_action('hamnaghsheh_chat_render', $project_id);
    
    // Return buffered content
    return ob_get_clean();
}

add_shortcode('hamnaghsheh_chat', 'hamnaghsheh_chat_shortcode');
```

### 5. Programmatic Message Creation

Create custom system messages from your main plugin:

```php
<?php
/**
 * Example: Custom System Message
 */

// Check if chat plugin is active
if (class_exists('Hamnaghsheh_Massenger_Messages')) {
    
    // Example 1: Project status change
    $message_id = Hamnaghsheh_Massenger_Messages::insert_message([
        'project_id' => 123,
        'user_id' => 1,
        'message' => 'وضعیت پروژه به "در حال انجام" تغییر کرد',
        'message_type' => 'system'
    ]);
    
    // Example 2: New member added
    $new_member = get_userdata(5);
    Hamnaghsheh_Massenger_Messages::insert_message([
        'project_id' => 123,
        'user_id' => 1,
        'message' => sprintf(
            '%s به پروژه اضافه شد',
            $new_member->display_name
        ),
        'message_type' => 'system'
    ]);
    
    // Example 3: Deadline changed
    Hamnaghsheh_Massenger_Messages::insert_message([
        'project_id' => 123,
        'user_id' => 1,
        'message' => 'مهلت پروژه به 1403/10/15 تغییر کرد',
        'message_type' => 'system'
    ]);
}
```

### 6. Checking Chat Status

Check if chat is available for a project:

```php
<?php
/**
 * Example: Check Chat Availability
 */

function is_chat_available_for_project($project_id) {
    // Check if plugin is active
    if (!class_exists('Hamnaghsheh_Massenger_Permissions')) {
        return false;
    }
    
    // Check if user has permission
    $permission = Hamnaghsheh_Massenger_Permissions::get_user_permission($project_id);
    
    if (!$permission) {
        return false;
    }
    
    // Check if chat is enabled for project
    global $wpdb;
    $table = $wpdb->prefix . 'hamnaghsheh_chat_metadata';
    
    $enabled = $wpdb->get_var($wpdb->prepare(
        "SELECT chat_enabled FROM $table WHERE project_id = %d",
        $project_id
    ));
    
    // Default to enabled if no record
    return $enabled === null ? true : (bool)$enabled;
}
```

### 7. Getting Unread Count for Dashboard

Display unread count in project list or dashboard:

```php
<?php
/**
 * Example: Dashboard Widget
 */

function hamnaghsheh_chat_dashboard_widget() {
    if (!class_exists('Hamnaghsheh_Massenger_Messages')) {
        return;
    }
    
    // Get user's projects (from main plugin)
    $projects = get_user_projects(get_current_user_id());
    
    echo '<div class="chat-unread-summary">';
    echo '<h3>پیام‌های خوانده نشده</h3>';
    echo '<ul>';
    
    foreach ($projects as $project) {
        $unread = Hamnaghsheh_Massenger_Messages::get_unread_count($project->id);
        
        if ($unread > 0) {
            echo '<li>';
            echo esc_html($project->name) . ': ';
            echo '<strong>' . $unread . ' پیام جدید</strong>';
            echo '</li>';
        }
    }
    
    echo '</ul>';
    echo '</div>';
}
```

### 8. Custom Chat Toggle Location

Move chat toggle to a different location:

```php
<?php
/**
 * Example: Custom Toggle Location
 */

// In your template file
?>
<div class="project-header">
    <h1><?php echo esc_html($project->name); ?></h1>
    
    <div class="project-actions">
        <!-- Your existing action buttons -->
        <button class="btn-download">دانلود</button>
        <button class="btn-share">اشتراک‌گذاری</button>
        
        <!-- Add custom chat toggle -->
        <?php if (is_chat_available_for_project($project->id)): ?>
            <button class="btn-chat hamnaghsheh-chat-toggle">
                💬 گفتگو
                <?php
                $unread = Hamnaghsheh_Massenger_Messages::get_unread_count($project->id);
                if ($unread > 0):
                ?>
                    <span class="badge"><?php echo $unread; ?></span>
                <?php endif; ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Still render the chat container (hidden by default) -->
<?php
do_action('hamnaghsheh_chat_render', $project->id);
?>
```

### 9. Disable Chat for Specific Projects

Disable chat for a specific project:

```php
<?php
/**
 * Example: Disable Chat for Project
 */

function disable_chat_for_project($project_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'hamnaghsheh_chat_metadata';
    
    // Try to update
    $updated = $wpdb->update(
        $table,
        ['chat_enabled' => 0],
        ['project_id' => $project_id],
        ['%d'],
        ['%d']
    );
    
    // If no row exists, insert
    if ($updated === 0) {
        $wpdb->insert(
            $table,
            [
                'project_id' => $project_id,
                'chat_enabled' => 0
            ],
            ['%d', '%d']
        );
    }
}

function enable_chat_for_project($project_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'hamnaghsheh_chat_metadata';
    
    $wpdb->update(
        $table,
        ['chat_enabled' => 1],
        ['project_id' => $project_id],
        ['%d'],
        ['%d']
    );
}
```

### 10. Email Notifications for New Messages

Send email when user receives a message:

```php
<?php
/**
 * Example: Email Notification Hook
 * Add to main plugin or functions.php
 */

add_action('hamnaghsheh_message_sent', 'send_chat_email_notification', 10, 2);

function send_chat_email_notification($message_id, $project_id) {
    // Get project members
    $members = get_project_members($project_id);
    
    // Get message details
    $message = Hamnaghsheh_Massenger_Messages::get_message($message_id);
    $sender = get_userdata($message->user_id);
    
    // Get project
    $project = hamnaghsheh_get_project($project_id);
    
    foreach ($members as $member) {
        // Skip sender
        if ($member->ID == $message->user_id) {
            continue;
        }
        
        // Prepare email
        $to = $member->user_email;
        $subject = sprintf('پیام جدید در پروژه %s', $project->name);
        $body = sprintf(
            "سلام %s،\n\n" .
            "%s در پروژه %s پیام جدیدی ارسال کرد:\n\n" .
            "%s\n\n" .
            "برای مشاهده و پاسخ، به لینک زیر مراجعه کنید:\n" .
            "%s",
            $member->display_name,
            $sender->display_name,
            $project->name,
            $message->message,
            get_permalink($project_id)
        );
        
        // Send email
        wp_mail($to, $subject, $body);
    }
}

// Trigger this action when message is sent
// In your AJAX handler or message insert function
do_action('hamnaghsheh_message_sent', $message_id, $project_id);
```

## Testing Integration

### Test File Action Integration

```php
<?php
/**
 * Test file action integration
 */

// Trigger a test file action
do_action('hamnaghsheh_file_action', 1, 123, 1, 'upload');

// Check if system message was created
$messages = Hamnaghsheh_Massenger_Messages::get_messages(123, 1);
var_dump($messages);
```

### Test Permissions

```php
<?php
/**
 * Test permission integration
 */

$project_id = 123;
$user_id = 1;

$permission = Hamnaghsheh_Massenger_Permissions::get_user_permission($project_id, $user_id);
echo "Permission: " . $permission . "\n";

$can_send = Hamnaghsheh_Massenger_Permissions::can_send_message($project_id, $user_id);
echo "Can send: " . ($can_send ? 'Yes' : 'No') . "\n";

$can_read = Hamnaghsheh_Massenger_Permissions::can_read_messages($project_id, $user_id);
echo "Can read: " . ($can_read ? 'Yes' : 'No') . "\n";
```

## WordPress Filters and Actions

### Available Hooks

The plugin provides these hooks for customization:

```php
<?php
/**
 * Filter message before insertion
 */
add_filter('hamnaghsheh_chat_message_before_insert', function($data) {
    // Modify message data before saving
    // $data contains: project_id, user_id, message, message_type, etc.
    return $data;
});

/**
 * Action after message is inserted
 */
add_action('hamnaghsheh_chat_message_inserted', function($message_id, $message_data) {
    // Do something after message is saved
    // E.g., send notifications, update analytics, etc.
}, 10, 2);

/**
 * Filter read receipts display
 */
add_filter('hamnaghsheh_chat_read_receipts', function($receipts, $message_id) {
    // Modify how read receipts are displayed
    return $receipts;
}, 10, 2);
```

## Common Issues and Solutions

### Issue 1: Chat Not Appearing

**Solution**: Make sure you're calling the render action:
```php
do_action('hamnaghsheh_chat_render', $project_id);
```

### Issue 2: System Messages Not Appearing

**Solution**: Verify the file action hook is triggered:
```php
do_action('hamnaghsheh_file_action', $file_id, $project_id, $user_id, $action_type);
```

### Issue 3: Permission Errors

**Solution**: Ensure your main plugin provides the permission function:
```php
function hamnaghsheh_get_project($project_id) {
    // Return project with user_permission property
}
```
