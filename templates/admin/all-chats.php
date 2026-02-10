<?php
/**
 * Admin All Chats Template
 * Lists all project chats for admin review
 *
 * @package Hamnaghsheh_Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options') && !current_user_can('hamnaghsheh_admin')) {
    wp_die('دسترسی غیرمجاز');
}

global $wpdb;
$table_prefix = $wpdb->prefix . HMCHAT_PREFIX;
$projects_table = $table_prefix . 'projects';
$messages_table = $table_prefix . 'chat_messages';

// Get all projects with chat activity
$projects = $wpdb->get_results("
    SELECT 
        p.id,
        p.name,
        p.user_id,
        COUNT(m.id) as message_count,
        MAX(m.created_at) as last_activity
    FROM {$projects_table} p
    LEFT JOIN {$messages_table} m ON p.id = m.project_id
    GROUP BY p.id
    HAVING message_count > 0
    ORDER BY last_activity DESC
");
?>

<div class="wrap" dir="rtl">
    <h1>💬 گفتگوهای پروژه‌ها</h1>
    
    <?php if (empty($projects)): ?>
        <div class="notice notice-info">
            <p>هیچ گفتگویی یافت نشد.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>نام پروژه</th>
                    <th>مالک</th>
                    <th>تعداد پیام‌ها</th>
                    <th>آخرین فعالیت</th>
                    <th>پیام آخر</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php
                    // Get project owner
                    $owner = get_userdata($project->user_id);
                    $owner_name = $owner ? $owner->display_name : 'نامشخص';
                    
                    // Get last message
                    $last_message = $wpdb->get_row($wpdb->prepare("
                        SELECT message, user_id, created_at 
                        FROM {$messages_table} 
                        WHERE project_id = %d 
                        ORDER BY id DESC 
                        LIMIT 1
                    ", $project->id));
                    
                    $last_message_preview = '';
                    if ($last_message) {
                        $msg_user = get_userdata($last_message->user_id);
                        $msg_user_name = $msg_user ? $msg_user->display_name : 'سیستم';
                        $message_text = HMChat_Mentions::strip_mentions($last_message->message);
                        $message_text = wp_trim_words($message_text, 10, '...');
                        $last_message_preview = '<strong>' . esc_html($msg_user_name) . ':</strong> ' . esc_html($message_text);
                    }
                    
                    // Format last activity
                    $last_activity_formatted = '';
                    if ($project->last_activity) {
                        $timestamp = strtotime($project->last_activity);
                        $diff = time() - $timestamp;
                        
                        if ($diff < 60) {
                            $last_activity_formatted = 'همین الان';
                        } elseif ($diff < 3600) {
                            $minutes = floor($diff / 60);
                            $last_activity_formatted = $minutes . ' دقیقه پیش';
                        } elseif ($diff < 86400) {
                            $hours = floor($diff / 3600);
                            $last_activity_formatted = $hours . ' ساعت پیش';
                        } else {
                            $days = floor($diff / 86400);
                            $last_activity_formatted = $days . ' روز پیش';
                        }
                    }
                    
                    // Project URL
                    $project_url = add_query_arg('id', $project->id, home_url('/show-project'));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($project->name); ?></strong>
                        </td>
                        <td>
                            <?php echo esc_html($owner_name); ?>
                        </td>
                        <td>
                            <span class="badge" style="background: #09375B; color: white; padding: 4px 8px; border-radius: 4px;">
                                <?php echo intval($project->message_count); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($last_activity_formatted); ?>
                        </td>
                        <td>
                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo $last_message_preview; ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($project_url); ?>" 
                               class="button button-primary" 
                               target="_blank">
                                مشاهده
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
            .wrap {
                font-family: 'Vazirmatn', sans-serif;
            }
            .wp-list-table th,
            .wp-list-table td {
                text-align: right;
            }
        </style>
    <?php endif; ?>
</div>
