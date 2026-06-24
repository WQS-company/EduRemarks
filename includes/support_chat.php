<?php
// includes/support_chat.php - Final Premium Support Hub v4 (Responsive & Optimized)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) return;

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];
$root_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/super_admin/') !== false) ? '../' : '';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Poppins:wght@400;600;800&display=swap');

    :root {
        --chat-primary: #1a4da1;
        --chat-accent: #F4B400;
        --chat-bg: #ffffff;
        --chat-text: #1e293b;
        --chat-muted: #64748b;
        --chat-bot-bg: #f8fafc;
        --chat-radius: 28px;
    }

    #eduremarksChat {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 10001;
        font-family: 'Inter', 'Poppins', sans-serif;
    }

    /* Floating Triggers */
    .chat-bubble-btn {
        width: 62px;
        height: 62px;
        border-radius: 20px;
        background: var(--chat-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 12px 30px rgba(26, 77, 161, 0.35);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 2px solid rgba(255,255,255,0.1);
        position: relative;
    }
    .chat-bubble-btn:hover { transform: scale(1.1) rotate(-5deg); filter: brightness(1.1); }

    .chat-notif-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #ff4757;
        color: white;
        font-size: 0.75rem;
        width: 24px;
        height: 24px;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 3px solid #fff;
        font-weight: 800;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* Responsive Window */
    .chat-window {
        position: fixed;
        bottom: 100px;
        right: 25px;
        width: 400px;
        height: 640px;
        max-height: calc(100vh - 130px);
        background: var(--chat-bg);
        border-radius: var(--chat-radius);
        box-shadow: 0 30px 100px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        visibility: hidden;
        opacity: 0;
        transform: translateY(30px) scale(0.95);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .chat-window.active { visibility: visible; opacity: 1; transform: translateY(0) scale(1); pointer-events: all; }

    /* Header */
    .chat-header {
        background: var(--chat-primary);
        padding: 20px 24px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chat-header-info { display: flex; align-items: center; gap: 12px; }
    .header-ctrls { display: flex; gap: 15px; font-size: 1.1rem; }
    .header-ctrls i { cursor: pointer; opacity: 0.7; transition: 0.3s; }
    .header-ctrls i:hover { opacity: 1; transform: scale(1.1); }

    /* Main Body */
    .chat-body {
        flex-grow: 1;
        background: #fff;
        padding: 24px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 16px;
        scrollbar-width: thin;
    }

    /* Message Aesthetics */
    .msg-node { display: flex; flex-direction: column; max-width: 85%; }
    .msg-content { 
        padding: 14px 20px; 
        border-radius: 20px; 
        font-size: 0.94rem; 
        line-height: 1.5; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        word-wrap: break-word;
    }
    .msg-in .msg-content { background: var(--chat-bot-bg); color: var(--chat-text); align-self: flex-start; border-bottom-left-radius: 4px; margin-left: 10px; }
    .msg-out .msg-node { align-self: flex-end; }
    .msg-out .msg-content { background: var(--chat-primary); color: #fff; border-bottom-right-radius: 4px; }

    .chat-msg-avatar { width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0; background: #fff; padding: 3px; border: 1px solid rgba(0,0,0,0.05); }

    .attachment-node { margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); max-width: 240px; }
    .attachment-node img, .attachment-node video { width: 100%; display: block; cursor: pointer; background: #eee; }

    /* FAQ System */
    .faq-list { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 15px; }
    .faq-btn { 
        background: var(--chat-accent); 
        color: #fff; 
        padding: 10px 18px; 
        border-radius: 15px; 
        font-size: 0.82rem; 
        font-weight: 600; 
        border: none;
        transition: 0.3s;
        box-shadow: 0 4px 10px rgba(244, 180, 0, 0.2);
    }
    .faq-btn:hover { background: #e2a600; transform: translateY(-2px); }

    /* Footer & Optimized Input Node */
    .chat-footer {
        padding: 15px 20px 20px;
        background: #fff;
        border-top: 1px solid #f1f5f9;
        position: relative;
    }

    /* Compact Preview (Matches UX for non-blocking input) */
    #pendingUpload {
        background: #f1f5f9;
        border-radius: 18px;
        padding: 8px 12px;
        margin-bottom: 12px;
        display: none;
        position: relative;
        border: 2px solid #e2e8f0;
        align-items: center;
        gap: 12px;
    }
    .preview-node { width: 45px; height: 45px; border-radius: 10px; object-fit: cover; }
    .preview-info { flex: 1; overflow: hidden; }
    .preview-info div { font-size: 0.75rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--chat-text); }
    .preview-info span { font-size: 0.65rem; color: var(--chat-muted); }
    
    .remove-preview {
        width: 22px;
        height: 22px;
        background: #ff4757;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.6rem;
        transition: 0.2s;
    }
    .remove-preview:hover { transform: scale(1.1); background: #eb3b5a; }

    /* The Unified Input Hub */
    .chat-input-hub {
        background: #f1f5f9;
        border-radius: 25px;
        padding: 12px 18px;
        position: relative;
        display: flex;
        flex-direction: column;
        transition: 0.3s;
    }
    .chat-input-hub:focus-within { background: #fff; box-shadow: 0 0 0 2px rgba(26, 77, 161, 0.1), 0 10px 25px rgba(0,0,0,0.05); }

    .chat-input-hub textarea {
        width: 100%;
        border: none;
        background: transparent;
        outline: none;
        font-size: 0.94rem;
        color: var(--chat-text);
        resize: none;
        padding-bottom: 40px;
        max-height: 100px;
        min-height: 24px;
    }

    .hub-actions {
        position: absolute;
        bottom: 12px;
        left: 18px;
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .hub-btn { font-size: 1.25rem; color: #8a99af; cursor: pointer; transition: 0.2s; }
    .hub-btn:hover { color: var(--chat-primary); transform: translateY(-2px); }

    .hub-send {
        position: absolute;
        bottom: 8px;
        right: 8px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--chat-accent);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(244, 180, 0, 0.3);
        transition: 0.3s;
    }
    .hub-send:hover { background: #e2a600; transform: scale(1.08); }

    /* Emoji Utility */
    .emoji-hub {
        position: absolute;
        bottom: 120px;
        left: 20px;
        right: 20px;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.12);
        border: 1px solid #f1f5f9;
        display: none;
        z-index: 100;
        overflow: hidden;
    }
    .emoji-hub.active { display: block; animation: hubBounce 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes hubBounce { from { opacity:0; transform: translateY(15px) scale(0.9); } to { opacity:1; transform: translateY(0) scale(1); } }
    .emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); padding: 12px; gap: 8px; max-height: 180px; overflow-y: auto; }
    .emoji-pick { font-size: 1.4rem; cursor: pointer; text-align: center; border-radius: 10px; transition: 0.2s; padding: 4px; }
    .emoji-pick:hover { background: #f1f5f9; transform: scale(1.2); }

    .typing-node { font-size: 0.72rem; color: var(--chat-muted); font-style: italic; margin-bottom: 10px; display: none; padding-left: 5px; }

    /* Responsive Excellence */
    @media (max-width: 991px) {
        #eduremarksChat { bottom: 85px; right: 20px; }
    }
    @media (max-width: 540px) {
        .chat-window { bottom: 0; right: 0; width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
        .chat-bubble-btn { width: 52px; height: 52px; border-radius: 16px; font-size: 1.3rem; }
    }
</style>

<div id="eduremarksChat">
    <button class="chat-bubble-btn" id="chatToggle" title="Support Sync">
        <i class="fas fa-comment-dots"></i>
        <span class="chat-notif-badge" id="chatBadge"></span>
    </button>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="chat-header-info">
                <img src="<?php echo $root_path; ?>img/logo.png" style="width: 24px; height: 24px; object-fit: contain; background: white; border-radius: 6px; padding: 2px;">
                <div class="fw-800" style="font-size: 1rem;">EduRemarks <span style="font-weight: 300; opacity: 0.8;">Support</span></div>
            </div>
            <div class="header-ctrls">
                <i class="fas fa-circle-half-stroke opacity-75"></i>
                <i class="fas fa-times" id="closeChat"></i>
            </div>
        </div>

        <div class="chat-body" id="chatBody">
            <div class="msg-node msg-in" style="flex-direction: row; gap: 8px;">
                <img src="<?php echo $root_path; ?>img/logo.png" class="chat-msg-avatar shadow-sm">
                <div class="msg-content">
                    Greetings! I'm <b>EduRemarks</b> Assistant. How can I facilitate your session orchestration today?
                    <div class="faq-list">
                        <button class="faq-btn" onclick="sendQuick('Payment Query')">Payment Issue</button>
                        <button class="faq-btn" onclick="sendQuick('Account Config')">Change Email</button>
                        <button class="faq-btn" onclick="sendQuick('Live Rep')">Contact Support</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-footer">
            <div class="typing-node" id="typingIndicator">EduRemarks is synchronizing and typing...</div>
            
            <!-- Compact File Component (Non-blocking) -->
            <div id="pendingUpload">
                <div id="filePreviewSlot"></div>
                <div class="preview-info">
                    <div id="fileTitleNode">Uploading File...</div>
                    <span id="fileSizeNode">Calculating...</span>
                </div>
                <div class="remove-preview" id="killUpload"><i class="fas fa-times"></i></div>
            </div>

            <!-- Emoji Hub -->
            <div class="emoji-hub" id="emojiHub">
                <div class="emoji-grid" id="emojiGrid"></div>
            </div>

            <!-- Consolidated Input Hub -->
            <div class="chat-input-hub">
                <textarea id="chatInput" placeholder="Describe your request..." rows="1"></textarea>
                <div class="hub-actions">
                    <div class="hub-btn" id="emojiOpener"><i class="far fa-smile"></i></div>
                    <label class="hub-btn" title="Add Attachment">
                        <i class="fas fa-paperclip"></i>
                        <input type="file" id="fileBuffer" accept="image/*,video/mp4,application/pdf" style="display:none">
                    </label>
                </div>
                <button class="hub-send" id="triggerSend">
                    <i class="fas fa-paper-plane" id="sendIcon"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const chatToggle = document.getElementById('chatToggle');
    const chatWindow = document.getElementById('chatWindow');
    const closeChat = document.getElementById('closeChat');
    const chatBody = document.getElementById('chatBody');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('triggerSend');
    const fileBuffer = document.getElementById('fileBuffer');
    const pendingUI = document.getElementById('pendingUpload');
    const previewSlot = document.getElementById('filePreviewSlot');
    const killUpload = document.getElementById('killUpload');
    const emojiOpener = document.getElementById('emojiOpener');
    const emojiHub = document.getElementById('emojiHub');
    const emojiGrid = document.getElementById('emojiGrid');
    
    let lastMsgId = 0;
    let ticketRef = null;
    let isSyncTyping = false;
    let typingTimer = null;
    let activeFile = null;

    // Emoji Engine
    const emojis = ["😀","😃","😄","😁","😆","😅","😂","🤣","😊","😇","🙂","🙃","😉","😌","😍","🥰","😘","😗","😙","😚","😋","😛","😝","😜","🤪","🤨","🧐","😎","🤩","😏","😒","😞","😔","😟","😕","☹️","😮","😳","🥺","😭","😱","😡","🎉","👍","🔥","✨","❤️"];
    emojis.forEach(e => {
        const span = document.createElement('span');
        span.className = 'emoji-pick';
        span.innerText = e;
        span.onclick = () => { chatInput.value += e; emojiHub.classList.remove('active'); chatInput.focus(); };
        emojiGrid.appendChild(span);
    });

    chatToggle.onclick = () => {
        chatWindow.classList.toggle('active');
        if(chatWindow.classList.contains('active')) {
            loadStream();
            document.getElementById('chatBadge').style.display = 'none';
        }
    };

    closeChat.onclick = () => chatWindow.classList.remove('active');
    emojiOpener.onclick = () => emojiHub.classList.toggle('active');

    function scrollEnd() { chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: 'smooth' }); }

    window.sendQuick = (text) => { chatInput.value = text; sendBtn.click(); };

    // Elastic Input
    chatInput.oninput = function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        handleTypingProtocol();
    };

    // Buffer File Logic (Compact Preview)
    fileBuffer.onchange = function() {
        if(this.files && this.files[0]) {
            activeFile = this.files[0];
            const reader = new FileReader();
            
            previewSlot.innerHTML = '';
            if(activeFile.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = 'preview-node';
                reader.onload = (e) => img.src = e.target.result;
                reader.readAsDataURL(activeFile);
                previewSlot.appendChild(img);
            } else if(activeFile.type.startsWith('video/')) {
                previewSlot.innerHTML = `<div class="preview-node bg-dark d-flex align-items-center justify-content-center text-white"><i class="fas fa-play"></i></div>`;
            } else {
                previewSlot.innerHTML = `<div class="preview-node bg-danger bg-opacity-10 d-flex align-items-center justify-content-center text-danger"><i class="fas fa-file-pdf"></i></div>`;
            }

            document.getElementById('fileTitleNode').innerText = activeFile.name;
            document.getElementById('fileSizeNode').innerText = (activeFile.size / 1024).toFixed(1) + ' KB';
            pendingUI.style.display = 'flex';
        }
    };

    killUpload.onclick = () => {
        activeFile = null;
        fileBuffer.value = '';
        pendingUI.style.display = 'none';
    };

    // Protocol: Dispatch Message
    sendBtn.onclick = async () => {
        const payload = chatInput.value.trim();
        if(!payload && !activeFile) return;

        const syncData = new FormData();
        syncData.append('action', 'send');
        syncData.append('message', payload);
        syncData.append('ticket_id', ticketRef || '');
        if(activeFile) syncData.append('attachment', activeFile);

        // UI Clearing Post-Dispatch
        chatInput.value = '';
        chatInput.style.height = 'auto';
        killUpload.click();
        
        // Optimistic UI Component
        const opId = 'op_' + Date.now();
        pushNode({ message: payload || 'Syncing attachment node...', sender_id: <?php echo $current_user_id; ?>, created_at: new Date() }, opId);

        try {
            const res = await fetch('<?php echo $root_path; ?>ajax/support_chat_handler.php', { method: 'POST', body: syncData });
            const data = await res.json();
            if(data.success) {
                ticketRef = data.ticket_id;
                const opNode = document.getElementById(opId);
                if(opNode) opNode.remove();
                loadStream();
            }
        } catch(e) { console.error("Communication error:", e); }
    };

    chatInput.onkeydown = (e) => { if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendBtn.click(); } };

    function handleTypingProtocol() {
        if(!isSyncTyping) { isSyncTyping = true; broadcastStatus(true); }
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => { isSyncTyping = false; broadcastStatus(false); }, 3000);
    }

    function broadcastStatus(status) {
        fetch('<?php echo $root_path; ?>ajax/support_chat_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=typing&status=${status ? 1 : 0}&ticket_id=${ticketRef || ''}`
        });
    }

    function parseAttachment(m) {
        if(!m.file_path) return '';
        const uri = '<?php echo $root_path; ?>' + m.file_path;
        if(m.attachment_type === 'image') return `<div class="attachment-node shadow-sm"><img src="${uri}" onclick="window.open('${uri}')"></div>`;
        if(m.attachment_type === 'video') return `<div class="attachment-node shadow-sm"><video controls src="${uri}"></video></div>`;
        return `<a href="${uri}" target="_blank" class="d-block mt-2 p-3 bg-light rounded text-dark text-decoration-none fw-600 shadow-sm border"><i class="fas fa-file-pdf me-2 text-danger"></i> Open Attachment Node</a>`;
    }

    function pushNode(m, id = null) {
        const isMe = (m.sender_id == <?php echo $current_user_id; ?>);
        const side = isMe ? 'out' : 'in';
        
        const wrap = document.createElement('div');
        wrap.className = `msg-node msg-${side}`;
        if(id) wrap.id = id;
        
        if(!isMe) {
            wrap.style.flexDirection = 'row';
            wrap.style.gap = '8px';
            const ava = document.createElement('img');
            ava.src = '<?php echo $root_path; ?>img/logo.png';
            ava.className = 'chat-msg-avatar shadow-sm';
            wrap.appendChild(ava);
        }

        const box = document.createElement('div');
        box.className = `msg-content`;
        box.innerHTML = `<div>${m.message || ''}</div>${parseAttachment(m)}`;
        
        wrap.appendChild(box);
        chatBody.appendChild(wrap);
        scrollEnd();
    }

    function loadStream() {
        fetch('<?php echo $root_path; ?>ajax/support_chat_handler.php?action=history')
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                chatBody.innerHTML = '';
                ticketRef = data.ticket_id;
                data.messages.forEach(m => {
                    pushNode(m);
                    lastMsgId = m.id;
                });
                scrollEnd();
            }
        });
    }

    // Real-time Sync Engine
    setInterval(() => {
        fetch(`<?php echo $root_path; ?>ajax/support_chat_handler.php?action=poll&last_id=${lastMsgId}&ticket_id=${ticketRef || ''}`)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Online Signal
                if(data.admin_online) document.getElementById('statusIndicator')?.classList.add('online');
                
                // Typing Signal
                document.getElementById('typingIndicator').style.display = data.is_typing ? 'block' : 'none';

                // Data Stream Ingestion
                if(data.messages.length > 0) {
                    data.messages.forEach(m => {
                        pushNode(m);
                        lastMsgId = Math.max(lastMsgId, m.id);
                        if(!chatWindow.classList.contains('active')) {
                            const badge = document.getElementById('chatBadge');
                            const current = parseInt(badge.innerText || 0) + 1;
                            badge.innerText = current;
                            badge.style.display = 'flex';
                        }
                    });
                }
            }
        });
    }, 2500);

})();
</script>
