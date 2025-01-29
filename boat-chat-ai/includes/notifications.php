<?php
class BoatChatAI_Notifications {
    /**
     * Create new chat session when user submits info
     */
    public function create_chat_session($session_id, $user_data) {
        global $wpdb;
        
        $listing_id = get_queried_object_id();
        $owner_id = get_post_meta($listing_id, 'owner_id', true);

        $wpdb->insert(
            "{$wpdb->prefix}boat_chat_users",
            [
                'session_id' => $session_id,
                'name' => sanitize_text_field($user_data['name']),
                'email' => sanitize_email($user_data['email']),
                'phone' => sanitize_text_field($user_data['phone']),
                'listing_id' => $listing_id,
                'owner_id' => $owner_id,
                'chat_history' => json_encode([]),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        $this->notify_owner($session_id);
    }

    /**
     * Send notification to boat owner
     */
    private function notify_owner($session_id) {
        global $wpdb;
        
        // Get chat details
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}boat_chat_users 
            WHERE session_id = %s",
            $session_id
        ));

        // Get owner email
        $owner = get_userdata($chat->owner_id);
        $listing = get_post($chat->listing_id);

        // Build message
        $subject = sprintf(__('New Charter Inquiry: %s', 'boat-chat-ai'), $listing->post_title);
        
        $message = sprintf(__(
            "New customer inquiry:\n\n
            Name: %s\n
            Email: %s\n
            Phone: %s\n\n
            Boat: %s\n
            Listing URL: %s\n\n
            Manage Chat: %s",
            'boat-chat-ai'
        ),
        $chat->name,
        $chat->email,
        $chat->phone,
        $listing->post_title,
        get_permalink($listing->ID),
        admin_url("admin.php?page=boat-chat-ai&session=$session_id"));

        // Send email
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <no-reply@%s>', get_bloginfo('name'), $_SERVER['SERVER_NAME'])
        ];

        wp_mail($owner->user_email, $subject, $message, $headers);
    }

    /**
     * Notify user when owner takes over
     */
    public function notify_user_takeover($session_id) {
        global $wpdb;
        
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}boat_chat_users 
            WHERE session_id = %s",
            $session_id
        ));

        $subject = __('Owner Has Joined the Chat', 'boat-chat-ai');
        $message = __("The boat owner has joined the conversation. You can now chat directly.\n\n", 'boat-chat-ai');
        
        wp_mail($chat->email, $subject, $message);
    }
}