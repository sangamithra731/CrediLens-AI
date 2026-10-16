<!-- CrediLens AI — Floating AI Assistant Widget -->
<style>
/* ============ AI Chat Fab ============ */
.ai-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 300;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--gradient-primary);
    border: none;
    color: white;
    cursor: pointer;
    box-shadow: 0 4px 20px var(--primary-glow), 0 2px 8px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    animation: aiFabPulse 3s infinite;
}
.ai-fab:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 28px var(--primary-glow), 0 4px 12px rgba(0,0,0,0.4);
}
.ai-fab.open {
    animation: none;
    transform: rotate(0deg);
}
@keyframes aiFabPulse {
    0%, 100% { box-shadow: 0 4px 20px var(--primary-glow), 0 2px 8px rgba(0,0,0,0.3); }
    50% { box-shadow: 0 4px 30px rgba(99,102,241,0.5), 0 2px 12px rgba(0,0,0,0.3); }
}
.ai-fab-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 16px;
    height: 16px;
    background: var(--success);
    border-radius: 50%;
    border: 2px solid var(--bg-primary);
    animation: pulse 2s infinite;
}

/* ============ AI Chat Panel ============ */
.ai-chat-panel {
    position: fixed;
    bottom: 92px;
    right: 24px;
    z-index: 301;
    width: 400px;
    max-height: 560px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: 0 12px 48px rgba(0,0,0,0.4), 0 0 0 1px var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(16px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}
.ai-chat-panel.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Header */
.ai-chat-header {
    padding: 16px 20px;
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.ai-chat-avatar {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ai-chat-header-info h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: white;
}
.ai-chat-header-info p {
    margin: 2px 0 0;
    font-size: 11px;
    color: rgba(255,255,255,0.7);
}
.ai-chat-close {
    margin-left: auto;
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.ai-chat-close:hover { background: rgba(255,255,255,0.3); }

/* Messages Area */
.ai-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 200px;
    max-height: 340px;
}

.ai-msg {
    max-width: 92%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.6;
    animation: fadeIn 0.3s ease;
    word-break: break-word;
}
.ai-msg.bot {
    align-self: flex-start;
    background: var(--bg-glass);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}
.ai-msg.user {
    align-self: flex-end;
    background: var(--gradient-primary);
    color: white;
    border-bottom-right-radius: 4px;
}
.ai-msg.bot strong { color: var(--primary-light); }

/* Action Buttons */
.ai-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.ai-action-btn {
    padding: 5px 12px;
    background: var(--bg-glass);
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--primary-light);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.ai-action-btn:hover {
    background: rgba(99,102,241,0.15);
    border-color: var(--primary);
}

/* Suggestions */
.ai-suggestions {
    padding: 10px 20px;
    display: flex;
    gap: 6px;
    overflow-x: auto;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}
.ai-suggestion {
    padding: 5px 12px;
    background: var(--bg-glass);
    border: 1px solid var(--border);
    border-radius: 16px;
    color: var(--text-secondary);
    font-size: 11px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    font-family: inherit;
    flex-shrink: 0;
}
.ai-suggestion:hover {
    border-color: var(--primary);
    color: var(--primary-light);
}

/* Input */
.ai-chat-input {
    padding: 12px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.ai-chat-input input {
    flex: 1;
    padding: 10px 14px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 24px;
    color: var(--text-primary);
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}
.ai-chat-input input:focus {
    border-color: var(--primary);
}
.ai-chat-input input::placeholder {
    color: var(--text-dim);
}
.ai-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gradient-primary);
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ai-send-btn:hover {
    transform: scale(1.06);
    box-shadow: 0 2px 10px var(--primary-glow);
}
.ai-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Typing indicator */
.ai-typing {
    display: flex;
    gap: 4px;
    padding: 10px 14px;
    align-self: flex-start;
}
.ai-typing span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--text-muted);
    animation: typingDot 1.2s infinite;
}
.ai-typing span:nth-child(2) { animation-delay: 0.2s; }
.ai-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingDot {
    0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
    30% { opacity: 1; transform: translateY(-4px); }
}

/* Mobile */
@media (max-width: 480px) {
    .ai-chat-panel {
        width: calc(100vw - 32px);
        right: 16px;
        bottom: 80px;
        max-height: 70vh;
    }
    .ai-fab { bottom: 16px; right: 16px; }
}
</style>

<!-- Floating AI Button -->
<button class="ai-fab" id="aiFab" onclick="toggleAiChat()" title="AI Assistant">
    <div class="ai-fab-badge"></div>
    <svg id="aiFabIcon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1.27A7 7 0 0 1 14 22h-4a7 7 0 0 1-6.73-5H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/>
        <circle cx="10" cy="14" r="1.5" fill="currentColor"/>
        <circle cx="14" cy="14" r="1.5" fill="currentColor"/>
    </svg>
</button>

<!-- Chat Panel -->
<div class="ai-chat-panel" id="aiChatPanel">
    <div class="ai-chat-header">
        <div class="ai-chat-avatar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1.27A7 7 0 0 1 14 22h-4a7 7 0 0 1-6.73-5H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/>
                <circle cx="10" cy="14" r="1.5" fill="white"/>
                <circle cx="14" cy="14" r="1.5" fill="white"/>
            </svg>
        </div>
        <div class="ai-chat-header-info">
            <h4>CrediLens AI Assistant</h4>
            <p>Loan guidance · EMI calculator · Help</p>
        </div>
        <button class="ai-chat-close" onclick="toggleAiChat()" title="Close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <div class="ai-chat-messages" id="aiChatMessages"></div>

    <div class="ai-suggestions" id="aiSuggestions"></div>

    <div class="ai-chat-input">
        <input type="text" id="aiInput" placeholder="Ask me anything about loans..." onkeypress="if(event.key==='Enter')sendAiMessage()">
        <button class="ai-send-btn" id="aiSendBtn" onclick="sendAiMessage()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/>
                <polygon points="22,2 15,22 11,13 2,9 22,2"/>
            </svg>
        </button>
    </div>
</div>

<script>
let aiChatOpen = false;
let aiInitialized = false;

function toggleAiChat() {
    aiChatOpen = !aiChatOpen;
    document.getElementById('aiChatPanel').classList.toggle('open', aiChatOpen);
    document.getElementById('aiFab').classList.toggle('open', aiChatOpen);

    if (aiChatOpen && !aiInitialized) {
        aiInitialized = true;
        initAiChat();
    }

    if (aiChatOpen) {
        setTimeout(() => document.getElementById('aiInput').focus(), 300);
    }
}

function initAiChat() {
    // Send initial greeting
    addBotMessage("Hello! I'm your <strong>CrediLens AI Assistant</strong>. I can help you with loan applications, EMI calculations, eligibility checks, and more.\n\nType your question or pick a suggestion below! 🚀");

    // Load suggestions
    fetch('api/ai_assistant.php?action=suggestions')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.suggestions) {
                const el = document.getElementById('aiSuggestions');
                el.innerHTML = data.suggestions.slice(0, 6).map(s =>
                    `<button class="ai-suggestion" onclick="askSuggestion(this, '${s.replace(/'/g, "\\'")}')">${s}</button>`
                ).join('');
            }
        }).catch(() => {});
}

function addBotMessage(html, actions) {
    const container = document.getElementById('aiChatMessages');
    const msgEl = document.createElement('div');
    msgEl.className = 'ai-msg bot';
    let content = html.replace(/\n/g, '<br>');
    if (actions && actions.length > 0) {
        content += '<div class="ai-actions">' +
            actions.map(a => `<button class="ai-action-btn" onclick="navigateTo('${a.page}');toggleAiChat()">${a.label}</button>`).join('') +
            '</div>';
    }
    msgEl.innerHTML = content;
    container.appendChild(msgEl);
    container.scrollTop = container.scrollHeight;
}

function addUserMessage(text) {
    const container = document.getElementById('aiChatMessages');
    const msgEl = document.createElement('div');
    msgEl.className = 'ai-msg user';
    msgEl.textContent = text;
    container.appendChild(msgEl);
    container.scrollTop = container.scrollHeight;
}

function showTyping() {
    const container = document.getElementById('aiChatMessages');
    const typing = document.createElement('div');
    typing.className = 'ai-typing';
    typing.id = 'aiTyping';
    typing.innerHTML = '<span></span><span></span><span></span>';
    container.appendChild(typing);
    container.scrollTop = container.scrollHeight;
}

function hideTyping() {
    const el = document.getElementById('aiTyping');
    if (el) el.remove();
}

async function sendAiMessage() {
    const input = document.getElementById('aiInput');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    addUserMessage(msg);

    const btn = document.getElementById('aiSendBtn');
    btn.disabled = true;
    showTyping();

    try {
        const fd = new FormData();
        fd.append('action', 'chat');
        fd.append('message', msg);
        const res = await fetch('api/ai_assistant.php', { method: 'POST', body: fd });
        const data = await res.json();

        hideTyping();
        if (data.success) {
            addBotMessage(data.response, data.actions);
        } else {
            addBotMessage("Sorry, I encountered an error. Please try again.");
        }
    } catch (e) {
        hideTyping();
        addBotMessage("I'm having trouble connecting. Please check your connection and try again.");
    }

    btn.disabled = false;
    input.focus();
}

function askSuggestion(el, text) {
    document.getElementById('aiInput').value = text;
    sendAiMessage();
    // Remove used suggestion
    el.style.display = 'none';
}
</script>
