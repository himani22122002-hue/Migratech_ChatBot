/**
 * Migratech Chatbot Frontend Logic (Step 3)
 * Modular design ready for backend API integration in Step 4.
 */

document.addEventListener('DOMContentLoaded', () => {
    const chatForm = document.getElementById('chat-form');
    const userInput = document.getElementById('user-input');
    const chatMessages = document.getElementById('chat-messages');
    const clearChatBtn = document.getElementById('clear-chat');
    const promptChips = document.querySelectorAll('.prompt-chip');

    // Event Listeners
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

    function submitUserMessage(text) {
        // Append user message to UI
        appendMessage(text, 'user');

        // Show typing indicator or pending state
        const loadingId = showTypingIndicator();

        // Call the chat service module (modularly prepared for fetch("api/chat.php"))
        ChatService.sendMessage(text)
            .then(response => {
                removeTypingIndicator(loadingId);
                appendMessage(response, 'bot');
            })
            .catch(error => {
                removeTypingIndicator(loadingId);
                appendMessage('Sorry, something went wrong. Please try again.', 'bot');
                console.error('Chat error:', error);
            });
    }

    function appendMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', sender === 'user' ? 'user-message' : 'bot-message');

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
        contentDiv.innerHTML = '<p><em>Migratech Assistant is typing...</em></p>';

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
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
});

/**
 * ChatService Module
 * Encapsulates communication logic. 
 * Designed so that in Step 4, the placeholder can be replaced with fetch("api/chat.php").
 */
const ChatService = {
    async sendMessage(message) {
        // Simulate network delay for realistic feel
        await new Promise(resolve => setTimeout(resolve, 600));

        // Placeholder response logic (Ready to be replaced by fetch("api/chat.php") in Step 4)
        const lowerMsg = message.toLowerCase();
        
        if (lowerMsg.includes('service')) {
            return "Migratech offers Web Development, Mobile App Development, Custom Software (CRM/ERP), Digital Marketing (SEO, SMO, PPC), Graphic Design & Branding, Biometric Systems, and Software Testing.";
        } else if (lowerMsg.includes('product')) {
            return "Our primary products include Interactive Flat Panels (IFPD), SmartClass education technology platforms, and Biometric & Access Control systems.";
        } else if (lowerMsg.includes('contact') || lowerMsg.includes('phone') || lowerMsg.includes('email')) {
            return "You can reach Migratech at +91-8859907771 or +91-8171333362, or email us at migratech03@gmail.com.";
        } else if (lowerMsg.includes('hour') || lowerMsg.includes('time')) {
            return "Our working hours are Monday through Saturday, 10:00 AM – 06:00 PM. We are closed on Sundays.";
        } else {
            return `Thank you for your message: "${message}". Migratech Softwares provides 360-degree digital and software solutions. Feel free to ask about our services, products, working hours, or contact details!`;
        }
    }
};
