jQuery(document).ready(function($) {
    const chatContainer = $('.boat-chat-ai-container');
    const chatMessages = $('.chat-messages');
    const chatInput = $('.chat-input input');
    const sendButton = $('.send-button');
    
    let sessionId = localStorage.getItem('boatChatSessionId') || 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2,9);
    localStorage.setItem('boatChatSessionId', sessionId);

    // Initial message
    if(!localStorage.getItem('boatChatInitialized')) {
        addMessage(boatChatVars.welcomeMessage, false);
        localStorage.setItem('boatChatInitialized', 'true');
    }

    function addMessage(content, isUser) {
        const messageClass = isUser ? 'user-message' : 'ai-message';
        chatMessages.append(`
            <div class="chat-message ${messageClass}">
                <div class="message-content">${content}</div>
            </div>
        `);
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }

    function handleResponse(response) {
        if(response.data.status === 'human') {
            addMessage(boatChatVars.ownerMessage, false);
            chatInput.prop('disabled', true);
            sendButton.prop('disabled', true);
        } else {
            addMessage(response.data.response, false);
        }
    }

    sendButton.click(sendMessage);
    chatInput.keypress(function(e) {
        if(e.which === 13) sendMessage();
    });

    function sendMessage() {
        const message = chatInput.val().trim();
        if(!message) return;

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
            success: handleResponse
        });
    }
});