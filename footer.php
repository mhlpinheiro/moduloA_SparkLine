<?php if (isset($_SESSION['usuario_id'])): ?>
<!-- Assistente Virtual SENN AI-X Helper (RF13) -->
<div id="chatbot-container">
    <div id="chatbot-header">
        🤖 SENN AI-X Helper
        <span style="cursor:pointer;" onclick="toggleChat()">_</span>
    </div>
    <div id="chatbot-messages">
        <div class="msg-bot">Olá! Sou o assistente virtual SENN AI-X. Como posso ajudar com suas entregas ou frota hoje?</div>
    </div>
    <div id="chatbot-input">
        <input type="text" id="chatMsg" placeholder="Pergunte algo ao assistente..." onkeypress="if(event.key==='Enter') sendChat()">
        <button onclick="sendChat()">Enviar</button>
    </div>
</div>

<script>
function toggleChat() {
    var msgs = document.getElementById('chatbot-messages');
    var input = document.getElementById('chatbot-input');
    msgs.style.display = msgs.style.display === 'none' ? 'block' : 'none';
    input.style.display = input.style.display === 'none' ? 'flex' : 'none';
}

function sendChat() {
    var input = document.getElementById('chatMsg');
    var msg = input.value.trim();
    if (!msg) return;

    var container = document.getElementById('chatbot-messages');
    container.innerHTML += '<div class="msg-user">' + msg + '</div>';
    input.value = '';

    fetch('senn_helper.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prompt=' + encodeURIComponent(msg)
    })
    .then(r => r.json())
    .then(data => {
        container.innerHTML += '<div class="msg-bot">' + data.resposta + '</div>';
        container.scrollTop = container.scrollHeight;
    });
}
</script>
<?php endif; ?>
<footer style="text-align: center; padding: 1.5rem; color: #94a3b8; font-size: 0.85rem;">
    SENAI DEV EXPERIENCE 2026 - Módulo A - SENN AI-X Logística Last-Mile
</footer>
</body>
</html>