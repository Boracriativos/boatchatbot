<?php
/*
Plugin Name: Boat Charter AI Chat
Description: AI-powered chat system for boat charter listings with owner handover
Version: 1.0.0
Author: Your Name
Text Domain: boat-chat-ai
*/

if (!defined('ABSPATH')) exit;

// Constants
define('BOAT_CHAT_AI_PATH', plugin_dir_path(__FILE__));
define('BOAT_CHAT_AI_URL', plugin_dir_url(__FILE__));

// Database Setup
require_once BOAT_CHAT_AI_PATH . 'includes/class-database.php';
register_activation_hook(__FILE__, ['BoatChatAI_DB', 'create_tables']);

// Settings
add_action('admin_init', function() {
    register_setting('boat-chat-ai-settings', 'boat_chat_ai_api_key');
    register_setting('boat-chat-ai-settings', 'boat_chat_ai_model');
    register_setting('boat-chat-ai-settings', 'boat_chat_ai_fine_tuning');
});

// Admin Menu
add_action('admin_menu', function() {
    add_menu_page(
        'Boat Chat AI',
        'Boat Chat AI',
        'manage_options',
        'boat-chat-ai',
        'boat_chat_ai_settings_page',
        'dashicons-admin-comments'
    );
});

function boat_chat_ai_settings_page() {
    include BOAT_CHAT_AI_PATH . 'templates/admin-settings.php';
}

// Enqueue Assets
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('boat-chat-css', BOAT_CHAT_AI_URL . 'assets/css/chat-style.css');
    wp_enqueue_script('boat-chat-js', BOAT_CHAT_AI_URL . 'assets/js/chat-script.js', ['jquery'], null, true);
    
    wp_localize_script('boat-chat-js', 'boatChatVars', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('boat_chat_nonce')
    ]);
});

// Shortcodes
add_shortcode('boat_chat_ai', function() {
    ob_start();
    include BOAT_CHAT_AI_PATH . 'templates/chat-interface.php';
    return ob_get_clean();
});

add_shortcode('boat_chat_owner', function() {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        return '<div class="boat-chat-error">'.__('Please login as owner to view chats.', 'boat-chat-ai').'</div>';
    }
    ob_start();
    include BOAT_CHAT_AI_PATH . 'templates/owner-interface.php';
    return ob_get_clean();
});

// Include Components
require_once BOAT_CHAT_AI_PATH . 'includes/openai-handler.php';
require_once BOAT_CHAT_AI_PATH . 'includes/chat-handler.php';
require_once BOAT_CHAT_AI_PATH . 'includes/notifications.php';

// AJAX Handlers
add_action('wp_ajax_nopriv_boat_chat_ai_message', 'handle_chat_message');
add_action('wp_ajax_boat_chat_ai_message', 'handle_chat_message');

function handle_chat_message() {
    check_ajax_referer('boat_chat_nonce', 'nonce');
    
    $message = sanitize_text_field($_POST['message']);
    $session_id = sanitize_text_field($_POST['session_id']);
    
    $chat_handler = new BoatChatAI_Chat();
    $response = $chat_handler->handle_message($message, $session_id);
    
    wp_send_json_success($response);
}