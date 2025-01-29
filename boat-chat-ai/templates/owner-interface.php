<div class="boat-chat-owner-dashboard">
    <h2><?php _e('Active Chats', 'boat-chat-ai'); ?></h2>
    <div class="chats-list">
        <?php
        global $wpdb;
        $owner_id = get_current_user_id();
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}boat_chat_users 
            WHERE owner_id = %d 
            ORDER BY created_at DESC",
            $owner_id
        ));

        foreach ($chats as $chat) {
            echo '<div class="chat-item" data-session="'.esc_attr($chat->session_id).'">';
            echo '<h4>'.esc_html(get_the_title($chat->listing_id)).'</h4>';
            echo '<p>'.esc_html($chat->name).' - '.esc_html($chat->email).'</p>';
            echo '</div>';
        }
        ?>
    </div>
    <div class="chat-detail"></div>
</div>