class BoatChatAI_Chat {
    public function handle_message($message, $session_id) {
        global $wpdb;
        
        // Get current chat state
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}boat_chat_users WHERE session_id = %s",
            $session_id
        ));

        // If owner has taken over, stop AI
        if ($chat->status === 'human') {
            return ['status' => 'human'];
        }

        // Data collection flow
        switch ($chat->state) {
            case 'name':
                return $this->handle_name($message, $session_id);
                
            case 'email':
                return $this->handle_email($message, $session_id);
                
            case 'phone':
                return $this->handle_phone($message, $session_id);
                
            default:
                return $this->handle_normal_chat($message, $session_id);
        }
    }

    private function handle_name($message, $session_id) {
        global $wpdb;
        
        $wpdb->update(
            "{$wpdb->prefix}boat_chat_users",
            [
                'name' => sanitize_text_field($message),
                'state' => 'email'
            ],
            ['session_id' => $session_id]
        );
        
        return [
            'response' => __('Great! Could you share your email address?', 'boat-chat-ai'),
            'status' => 'ai'
        ];
    }

    private function handle_email($message, $session_id) {
        global $wpdb;
        
        if (!filter_var($message, FILTER_VALIDATE_EMAIL)) {
            return [
                'response' => __('Please enter a valid email address.', 'boat-chat-ai'),
                'status' => 'ai'
            ];
        }

        $wpdb->update(
            "{$wpdb->prefix}boat_chat_users",
            [
                'email' => sanitize_email($message),
                'state' => 'phone'
            ],
            ['session_id' => $session_id]
        );
        
        return [
            'response' => __('Thank you! Finally, what\'s your phone number?', 'boat-chat-ai'),
            'status' => 'ai'
        ];
    }

    private function handle_phone($message, $session_id) {
        global $wpdb;
        
        $wpdb->update(
            "{$wpdb->prefix}boat_chat_users",
            [
                'phone' => sanitize_text_field($message),
                'state' => 'ready'
            ],
            ['session_id' => $session_id]
        );
        
        // Create full chat session and notify owner
        $notifications = new BoatChatAI_Notifications();
        $notifications->create_chat_session($session_id);
        
        return [
            'response' => __('Thank you! How can I assist you with this boat charter?', 'boat-chat-ai'),
            'status' => 'ai'
        ];
    }

    private function handle_normal_chat($message, $session_id) {
        // Get listing context
        $listing = get_queried_object();
        $context = "Boat: {$listing->post_title}, " .
                   "Price: " . get_post_meta($listing->ID, 'price', true) . ", " .
                   "Capacity: " . get_post_meta($listing->ID, 'capacity', true);

        // Generate AI response
        $openai = new BoatChatAI_OpenAI();
        $response = $openai->generate_response($message, $context);

        return [
            'response' => $response['choices'][0]['message']['content'],
            'status' => 'ai'
        ];
    }
}