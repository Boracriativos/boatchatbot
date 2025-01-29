<?php
// templates/admin-settings.php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

$api_key = get_option('boat_chat_ai_api_key');
$model = get_option('boat_chat_ai_model', 'gpt-4');
$fine_tuning = get_option('boat_chat_ai_fine_tuning');
?>
<div class="wrap boat-chat-settings">
    <h1><?php esc_html_e('Boat Charter AI Settings', 'boat-chat-ai'); ?></h1>

    <div class="boat-chat-settings-container">
        <form method="post" action="options.php" class="boat-chat-settings-form">
            <?php settings_fields('boat-chat-ai-settings'); ?>
            
            <div class="settings-section">
                <h2><?php esc_html_e('OpenAI Configuration', 'boat-chat-ai'); ?></h2>
                
                <div class="form-field">
                    <label for="boat_chat_ai_api_key">
                        <?php esc_html_e('API Key', 'boat-chat-ai'); ?>
                    </label>
                    <input type="password" 
                           id="boat_chat_ai_api_key" 
                           name="boat_chat_ai_api_key" 
                           value="<?php echo esc_attr($api_key); ?>"
                           class="regular-text"
                           autocomplete="off">
                    <p class="description">
                        <?php printf(
                            esc_html__('Get your API key from %sOpenAI%s', 'boat-chat-ai'),
                            '<a href="https://platform.openai.com/account/api-keys" target="_blank">',
                            '</a>'
                        ); ?>
                    </p>
                </div>

                <div class="form-field">
                    <label for="boat_chat_ai_model">
                        <?php esc_html_e('Model Version', 'boat-chat-ai'); ?>
                    </label>
                    <select id="boat_chat_ai_model" name="boat_chat_ai_model" class="regular-text">
                        <option value="gpt-4" <?php selected($model, 'gpt-4'); ?>>
                            GPT-4
                        </option>
                        <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>
                            GPT-3.5 Turbo
                        </option>
                    </select>
                </div>
            </div>

            <div class="settings-section">
                <h2><?php esc_html_e('Chat Configuration', 'boat-chat-ai'); ?></h2>
                
                <div class="form-field">
                    <label for="boat_chat_ai_fine_tuning">
                        <?php esc_html_e('Custom Instructions', 'boat-chat-ai'); ?>
                    </label>
                    <textarea id="boat_chat_ai_fine_tuning" 
                              name="boat_chat_ai_fine_tuning" 
                              rows="8" 
                              class="large-text code"><?php echo esc_textarea($fine_tuning); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Example instructions:', 'boat-chat-ai'); ?><br>
                        "You are a boat charter specialist. Always consider:<br>
                        - Nautical terminology<br>
                        - Safety protocols<br>
                        - Local maritime regulations<br>
                        - Weather impact on sailing<br>
                        - Boat maintenance requirements"
                    </p>
                </div>
            </div>

            <?php submit_button(__('Save Settings', 'boat-chat-ai')); ?>
        </form>

        <div class="settings-sidebar">
            <div class="shortcode-box">
                <h3><?php esc_html_e('Shortcodes', 'boat-chat-ai'); ?></h3>
                <p><strong><?php esc_html_e('User Chat:', 'boat-chat-ai'); ?></strong></p>
                <code>[boat_chat_ai]</code>
                
                <p><strong><?php esc_html_e('Owner Dashboard:', 'boat-chat-ai'); ?></strong></p>
                <code>[boat_chat_owner]</code>
            </div>

            <div class="system-status">
                <h3><?php esc_html_e('System Status', 'boat-chat-ai'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Database Version:', 'boat-chat-ai'); ?> 1.0.0</li>
                    <li><?php esc_html_e('API Connection:', 'boat-chat-ai'); ?> 
                        <?php echo ($api_key) ? '<span class="connected">✅ '.__('Connected', 'boat-chat-ai').'</span>' : '<span class="disconnected">❌ '.__('Disconnected', 'boat-chat-ai').'</span>'; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>