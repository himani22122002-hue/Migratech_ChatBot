/**
 * Migratech Chatbot Frontend Logic
 * Connects frontend to api/chat.php
 */

document.addEventListener('DOMContentLoaded', () => {
    const chatForm = document.getElementById('chat-form');
    const userInput = document.getElementById('user-input');
    const chatMessages = document.getElementById('chat-messages');
    const clearChatBtn = document.getElementById('clear-chat');
    const promptChips = document.querySelectorAll('.prompt-chip');

    chatForm.addEventListener('submit', handleFormSubmit);
    clearChatBtn.addEventListener('click', clearChatHistory);

    promptChips.forEach(chip => {
        chip.addEventListener('click', () => {
            const query = chip.getAttribute('data-query');
            if (query) {
                submitUserMessage(query);
            }
        });
    });

    function handleFormSubmit(e) {
        e.preventDefault();

        const text = userInput.value.trim();
        if (!text) return;

        submitUserMessage(text);
        userInput.value = '';
    }

    async function submitUserMessage(text) {
        appendMessage(text, 'user');

        const loadingId = showTypingIndicator();

        try {
            const response = await ChatService.sendMessage(text);

            removeTypingIndicator(loadingId);

            if (!response.success) {
                appendMessage(
                    response.reply || 'Sorry, something went wrong. Please try again.',
                    'bot'
                );
                return;
            }

            appendMessage(response.reply, 'bot');

            if (response.needs_human === true) {
                showCallNowButton();
            }

        } catch (error) {
            removeTypingIndicator(loadingId);

            appendMessage(
                'Sorry, something went wrong. Please try again.',
                'bot'
            );

            console.error('Chat error:', error);
        }
    }

    function appendMessage(text, sender) {
        const messageDiv = document.createElement('div');

        messageDiv.classList.add(
            'message',
            sender === 'user' ? 'user-message' : 'bot-message'
        );

        const avatarDiv = document.createElement('div');
        avatarDiv.classList.add('message-avatar');
        avatarDiv.textContent = sender === 'user' ? 'U' : 'M';

        const contentDiv = document.createElement('div');
        contentDiv.classList.add('message-content');

        const p = document.createElement('p');
        p.textContent = text;

        const timeSpan = document.createElement('span');
        timeSpan.classList.add('message-time');
        timeSpan.textContent = formatTime(new Date());

        contentDiv.appendChild(p);
        contentDiv.appendChild(timeSpan);

        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);

        chatMessages.appendChild(messageDiv);

        scrollToBottom();
    }

    function showCallNowButton() {
        // Avoid showing duplicate buttons
        if (document.getElementById('call-now-button')) {
            return;
        }

        const callContainer = document.createElement('div');
        callContainer.classList.add('call-now-container');
        callContainer.id = 'call-now-button';

        const callButton = document.createElement('a');

        callButton.href = 'tel:+918859907771';
        callButton.classList.add('call-now-button');
        callButton.textContent = '📞 Call Now';

        callContainer.appendChild(callButton);
        chatMessages.appendChild(callContainer);

        scrollToBottom();
    }

    function showTypingIndicator() {
        const id = 'typing-' + Date.now();

        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', 'bot-message');
        messageDiv.id = id;

        const avatarDiv = document.createElement('div');
        avatarDiv.classList.add('message-avatar');
        avatarDiv.textContent = 'M';

        const contentDiv = document.createElement('div');
        contentDiv.classList.add('message-content');

        contentDiv.innerHTML =
            '<p><em>Migratech Assistant is typing...</em></p>';

        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);

        chatMessages.appendChild(messageDiv);

        scrollToBottom();

        return id;
    }

    function removeTypingIndicator(id) {
        const element = document.getElementById(id);

        if (element) {
            element.remove();
        }
    }

    function clearChatHistory() {
        chatMessages.innerHTML = `
            <div class="message bot-message">
                <div class="message-avatar">M</div>
                <div class="message-content">
                    <p>Chat cleared. How else can I assist you with Migratech Softwares today?</p>
                    <span class="message-time">${formatTime(new Date())}</span>
                </div>
            </div>
        `;

        const callButton = document.getElementById('call-now-button');

        if (callButton) {
            callButton.remove();
        }
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function formatTime(date) {
        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
});


/**
 * ChatService
 * Communicates with the PHP backend.
 */
const ChatService = {

    async sendMessage(message) {

        const response = await fetch('api/chat.php', {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify({
                message: message
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }

        return await response.json();
    }
};