<?php
class BoatChatAI_OpenAI {
    public function generate_response($prompt, $context) {
        $api_key = get_option('boat_chat_ai_api_key');
        $model = get_option('boat_chat_ai_model', 'gpt-4');
        
        $messages = [
            [
                'role' => 'system',
                'content' => "You are a boat charter expert assistant. Specialize in yacht rentals, sailing conditions, and marine services. Context: $context"
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500
            ])
        ]);

        if (is_wp_error($response)) {
            error_log('OpenAI Error: ' . $response->get_error_message());
            return false;
        }

        return json_decode($response['body'], true);
    }
}