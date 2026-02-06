<?php
/**
 * SSE Handler class - handles Server-Sent Events for real-time updates
 *
 * @package Hamnaghsheh_Messenger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SSE Handler class
 */
class Hamnaghsheh_Messenger_SSE_Handler {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Constructor
    }
    
    /**
     * Stream events to client
     *
     * @param int $project_id Project ID
     * @param int $last_message_id Last message ID client has
     * @param int $user_id Current user ID
     */
    public static function stream_events($project_id, $last_message_id, $user_id) {
        // Disable caching
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // For Nginx
        
        // Disable PHP output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set execution time limit
        set_time_limit(30);
        
        // Track start time
        $start_time = time();
        $heartbeat_interval = 10; // Send heartbeat every 10 seconds
        $last_heartbeat = time();
        
        // Stream for 30 seconds
        while ((time() - $start_time) < 30) {
            // Check for new messages
            $new_messages = Hamnaghsheh_Messenger_Messages::get_messages_after(
                $project_id,
                $last_message_id
            );
            
            if (!empty($new_messages)) {
                foreach ($new_messages as $msg) {
                    // Prepare message data
                    $message_data = self::prepare_message_data($msg);
                    
                    // Send SSE event
                    self::send_event('new_message', $message_data);
                    
                    $last_message_id = $msg->id;
                }
            }
            
            // Check for typing indicators
            $typing_users = Hamnaghsheh_Messenger_Typing_Indicator::get_typing_users(
                $project_id,
                $user_id
            );
            
            if (!empty($typing_users)) {
                $typing_data = array_map(function($user) {
                    return [
                        'user_id' => $user->ID,
                        'display_name' => $user->display_name
                    ];
                }, $typing_users);
                
                self::send_event('user_typing', $typing_data);
            }
            
            // Send heartbeat
            if ((time() - $last_heartbeat) >= $heartbeat_interval) {
                echo ": heartbeat\n\n";
                self::flush_output();
                $last_heartbeat = time();
            }
            
            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }
            
            // Sleep for 1 second
            sleep(1);
        }
        
        // Connection closes, browser will auto-reconnect
        exit;
    }
    
    /**
     * Send SSE event
     *
     * @param string $event_type Event type
     * @param mixed $data Event data
     */
    public static function send_event($event_type, $data) {
        echo "event: {$event_type}\n";
        echo 'data: ' . wp_json_encode($data) . "\n\n";
        self::flush_output();
    }
    
    /**
     * Flush output buffers
     */
    private static function flush_output() {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Prepare message data for SSE
     *
     * @param object $message Message object
     * @return array Prepared data
     */
    private static function prepare_message_data($message) {
        $metadata = null;
        if ($message->metadata) {
            $metadata = json_decode($message->metadata, true);
        }
        
        return [
            'id' => $message->id,
            'project_id' => $message->project_id,
            'user_id' => $message->user_id,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'metadata' => $metadata,
            'display_name' => $message->display_name,
            'user_email' => $message->user_email,
            'avatar_url' => get_avatar_url($message->user_id),
            'created_at' => $message->created_at,
            'edited_at' => $message->edited_at,
            'seen_by' => $message->seen_by ?? [],
            'timestamp' => strtotime($message->created_at)
        ];
    }
    
    /**
     * Check if connection is still alive
     *
     * @return bool
     */
    public static function check_connection_alive() {
        return !connection_aborted();
    }
}
