/**
 * QC-ALERTO AI Assistant Bubble v2.0
 * Advanced Conversational UI with Gemini API Support
 */

(function() {
    // 1. Enhanced Styles
    const styles = `
        .qca-bubble-wrap { position: fixed; bottom: 25px; right: 25px; z-index: 9999; font-family: 'Inter', sans-serif; }
        .qca-btn { 
            width: 60px; height: 60px; border-radius: 50%; background: #111827; 
            border: 3px solid #F5A623; color: #fff; display: flex; align-items: center; 
            justify-content: center; font-size: 24px; cursor: pointer; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.3); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .qca-btn:hover { transform: scale(1.1) rotate(5deg); background: #1f2937; }
        .qca-btn i { transition: transform 0.3s; }
        .qca-btn.open i { transform: rotate(180deg); }
        
        .qca-pulse { position: absolute; inset: -5px; border-radius: 50%; border: 2px solid #F5A623; opacity: 0; animation: qcapulse 2s infinite; }
        @keyframes qcapulse { 0% { transform: scale(0.8); opacity: 0; } 50% { opacity: 0.5; } 100% { transform: scale(1.3); opacity: 0; } }

        .qca-window { 
            position: absolute; bottom: 80px; right: 0; width: 360px; 
            background: #fff; border-radius: 16px; overflow: hidden; 
            box-shadow: 0 12px 48px rgba(0,0,0,0.25); display: none; flex-direction: column;
            border: 1px solid #e5e7eb; transform-origin: bottom right; 
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-height: 550px;
        }
        .qca-window.show { display: flex; }
        
        .qca-header { background: #111827; color: #fff; padding: 16px; display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #F5A623; }
        .qca-logo { width: 32px; height: 32px; border-radius: 50%; background: #F5A623; color: #111827; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px; }
        
        .qca-body { padding: 16px; height: 320px; overflow-y: auto; background: #f9fafb; scroll-behavior: smooth; }
        .qca-msg { margin-bottom: 12px; font-size: 13.5px; line-height: 1.5; padding: 10px 14px; border-radius: 12px; max-width: 85%; position: relative; word-wrap: break-word; }
        .qca-msg-ai { background: #fff; color: #111827; border: 1px solid #e5e7eb; border-top-left-radius: 2px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .qca-msg-user { background: #111827; color: #fff; margin-left: auto; border-top-right-radius: 2px; }
        
        /* Typing indicator */
        .typing { display: flex; gap: 4px; padding: 10px 14px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; width: fit-content; margin-bottom: 12px; }
        .dot { width: 6px; height: 6px; background: #9ca3af; border-radius: 50%; animation: dot-elastic 1.5s infinite linear; }
        .dot:nth-child(2) { animation-delay: 0.1s; }
        .dot:nth-child(3) { animation-delay: 0.2s; }
        @keyframes dot-elastic { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.4); } }

        .qca-footer { padding: 12px; background: #fff; border-top: 1px solid #e5e7eb; }
        .qca-input-wrap { display: flex; gap: 8px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 10px; padding: 4px 8px; }
        .qca-input { flex: 1; border: none; background: transparent; padding: 8px; font-size: 13.5px; outline: none; }
        .qca-send { border: none; background: transparent; color: #111827; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: color 0.2s; }
        .qca-send:hover { color: #F5A623; }
        .qca-send:disabled { color: #d1d5db; cursor: not-allowed; }

        .qca-quick { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 12px; background: #fff; border-top: 1px solid #e5e7eb; }
        .quick-btn { background: #f3f4f6; border: 1px solid #e5e7eb; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #4b5563; cursor: pointer; transition: all 0.2s; }
        .quick-btn:hover { background: #111827; color: #fff; border-color: #111827; }

        @media (max-width: 480px) {
            .qca-window { width: calc(100vw - 40px); bottom: 70px; right: -5px; }
        }
    `;

    const styleSheet = document.createElement("style");
    styleSheet.innerText = styles;
    document.head.appendChild(styleSheet);

    // 2. Create HTML
    const wrap = document.createElement('div');
    wrap.className = 'qca-bubble-wrap';
    wrap.innerHTML = `
        <div class="qca-window" id="qcaWindow">
            <div class="qca-header">
                <div class="qca-logo">AI</div>
                <div style="flex:1;">
                    <div style="font-weight: 700; font-size: 14px;">QC-ALERTO Assistant</div>
                    <div style="font-size: 10px; color: #9ca3af; display: flex; align-items: center; gap: 4px;">
                        <span style="width:6px;height:6px;background:#4ade80;border-radius:50%;"></span> AI Online
                    </div>
                </div>
                <button id="qcaClose" style="background:none; border:none; color:#fff; cursor:pointer;"><i class="bi bi-dash-lg"></i></button>
            </div>
            <div class="qca-body" id="qcaBody">
                <div class="qca-msg qca-msg-ai">
                    👋 Mabuhay, Kabayan! Ako ang inyong QC AI Assistant. Handa na akong makipag-chat sa inyo tungkol sa kahit anong concern sa Quezon City.
                </div>
            </div>
            <div class="qca-quick" id="qcaQuick">
                <button class="quick-btn" onclick="sendQuick('Paano mag-report?')">Paano mag-report?</button>
                <button class="quick-btn" onclick="sendQuick('I-track ang report')">I-track ang report</button>
                <button class="quick-btn" onclick="sendQuick('Emergency Hotlines')">Emergency Hotlines</button>
            </div>
            <div class="qca-footer">
                <div class="qca-input-wrap">
                    <input type="text" class="qca-input" id="qcaInput" placeholder="Mag-type ng mensahe dito..." autocomplete="off">
                    <button class="qca-send" id="qcaSend"><i class="bi bi-send-fill"></i></button>
                </div>
                <div style="font-size:9px; color:#9ca3af; text-align:center; margin-top:8px;">Powered by QC-ALERTO Gemini AI</div>
            </div>
        </div>
        <div class="qca-btn" id="qcaBtn">
            <div class="qca-pulse"></div>
            <i class="bi bi-robot"></i>
        </div>
    `;
    document.body.appendChild(wrap);

    // 3. Logic
    const btn = document.getElementById('qcaBtn');
    const win = document.getElementById('qcaWindow');
    const closeBtn = document.getElementById('qcaClose');
    const body = document.getElementById('qcaBody');
    const input = document.getElementById('qcaInput');
    const sendBtn = document.getElementById('qcaSend');

    // Make sendQuick globally accessible
    window.sendQuick = function(txt) {
        processUserMsg(txt);
    };

    btn.addEventListener('click', () => {
        win.classList.toggle('show');
        btn.classList.toggle('open');
        if (win.classList.contains('show')) input.focus();
    });

    closeBtn.addEventListener('click', () => {
        win.classList.remove('show');
        btn.classList.remove('open');
    });

    sendBtn.addEventListener('click', () => sendMessage());
    input.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });

    async function sendMessage() {
        const txt = input.value.trim();
        if (!txt) return;
        input.value = '';
        await processUserMsg(txt);
    }

    async function processUserMsg(txt) {
        addMessage(txt, 'user');
        
        // Add typing indicator
        const typing = document.createElement('div');
        typing.className = 'typing';
        typing.id = 'qc-typing';
        typing.innerHTML = '<div class="dot"></div><div class="dot"></div><div class="dot"></div>';
        body.appendChild(typing);
        scrollToBottom();

        try {
            const formData = new FormData();
            formData.append('message', txt);

            const response = await fetch('/irms/ajax/ai_chat_proxy.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();
            document.getElementById('qc-typing').remove();
            
            if (data.response) {
                addMessage(data.response, 'ai');
            } else {
                addMessage("Pasensya na, Kabayan. Nagkaroon ng error. Pakisubukang muli.", 'ai');
            }
        } catch (error) {
            console.error(error);
            document.getElementById('qc-typing').remove();
            addMessage("Hindi ko maabot ang aking system. Pakicheck ang inyong internet connection.", 'ai');
        }
    }

    function addMessage(txt, type) {
        const msg = document.createElement('div');
        msg.className = `qca-msg qca-msg-${type}`;
        // Convert URLs to clickable links and handle line breaks
        let processedTxt = txt.replace(/\n/g, '<br>');
        processedTxt = processedTxt.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" style="color:inherit; text-decoration:underline;">$1</a>');
        
        msg.innerHTML = processedTxt;
        body.appendChild(msg);
        scrollToBottom();
    }

    function scrollToBottom() {
        body.scrollTop = body.scrollHeight;
    }
})();
