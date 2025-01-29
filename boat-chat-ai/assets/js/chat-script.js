jQuery(document).ready(function($) {
    const chatContainer = $('.boat-chat-ai-container');
    const chatMessages = $('.chat-messages');
    const chatInput = $('.chat-input input');
    const sendButton = $('.send-button');
    
    let sessionId = localStorage.getItem('boatChatSessionId') || generateSessionId();
    
    function generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    function addMessage(content, isUser = false) {
        const messageClass = isUser ? 'user-message' : 'ai-message';
        chatMessages.append(`
            <div class="chat-message ${messageClass}">
                ${content}
            </div>
        `);
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }
    
    function sendMessage() {
        const message = chatInput.val().trim();
        if (!message) return;
        
        addMessage(message, true);
        chatInput.val('');
        
        $.ajax({
            url: boatChatVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'boat_chat_ai_message',
                nonce: boatChatVars.nonce,
                message: message,
                session_id: sessionId
            },
            success: function(response) {
                if (response.success) {
                    addMessage(response.data.response);
                }
            }
        });
    }
    
    sendButton.click(sendMessage);
    chatInput.keypress(function(e) {
        if (e.which === 13) sendMessage();
    });
    
    // Initial message
    addMessage("Hello! I'm your boat charter assistant. Could you please start by providing your name, email, and phone number?");
});