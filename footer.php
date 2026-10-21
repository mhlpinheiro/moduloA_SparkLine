
<?php if (isset($_SESSION['usuario_id'])): ?>

<style>
/* ==========================================
   SENN AI-X HELPER — ESTILO EXCLUSIVO
========================================== */

#chatbot-container,
#chatbot-container * {
    box-sizing: border-box;
}

#chatbot-container {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    width: 370px;
    max-width: calc(100vw - 32px);
    height: 480px;
    max-height: min(650px, calc(100dvh - 48px));
    overflow: hidden;
    border: 1px solid #dce5ed;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 18px 55px rgba(15, 39, 57, .20);
    color: #173247;
    font-family: inherit;
}

#chatbot-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 68px;
    padding: 16px 18px;
    background: linear-gradient(135deg, #117f94, #12677e);
    color: #fff;
    font-size: 15px;
    font-weight: 800;
}

#chatbot-header .chatbot-titulo {
    display: flex;
    align-items: center;
    gap: 10px;
}

#chatbot-header .chatbot-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 12px;
    background: rgba(255,255,255,.15);
    font-size: 20px;
}

#chatbot-header .chatbot-subtitulo {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.8);
    font-size: 11px;
    font-weight: 400;
}

#chatbot-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    border: 0;
    border-radius: 8px;
    background: rgba(255,255,255,.14);
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

#chatbot-toggle:hover {
    background: rgba(255,255,255,.25);
}

#chatbot-messages {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 13px;
    min-height: 0;
    padding: 18px;
    overflow-y: auto;
    background: #f5f8fc;
    scroll-behavior: smooth;
}

#chatbot-messages .msg-bot,
#chatbot-messages .msg-user {
    max-width: 88%;
    padding: 12px 14px;
    border-radius: 13px;
    font-size: 13px;
    line-height: 1.65;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

#chatbot-messages .msg-bot {
    align-self: flex-start;
    border: 1px solid #e1e8ef;
    border-bottom-left-radius: 4px;
    background: #fff;
    color: #354b5d;
    box-shadow: 0 2px 6px rgba(15,39,57,.03);
}

#chatbot-messages .msg-user {
    align-self: flex-end;
    border-bottom-right-radius: 4px;
    background: #117f94;
    color: #fff;
}

#chatbot-messages .msg-erro {
    border-color: #f0caca;
    background: #fff0f0;
    color: #a82e2e;
}

#chatbot-input {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 13px;
    border-top: 1px solid #e5ebf1;
    background: #fff;
}

#chatbot-input input {
    width: 100%;
    min-width: 0;
    height: 43px;
    padding: 0 13px;
    border: 1px solid #dce5ed;
    border-radius: 10px;
    outline: none;
    background: #f8fafc;
    color: #173247;
    font: inherit;
    font-size: 13px;
}

#chatbot-input input:focus {
    border-color: #117f94;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(17,127,148,.10);
}

#chatbot-input button {
    min-width: 76px;
    height: 43px;
    padding: 0 14px;
    border: 0;
    border-radius: 10px;
    background: #117f94;
    color: #fff;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: background .2s;
}

#chatbot-input button:hover {
    background: #0d6d7f;
}

#chatbot-input button:disabled {
    opacity: .6;
    cursor: wait;
}

#chatbot-container.chatbot-minimizado {
    height: auto;
}

#chatbot-container.chatbot-minimizado #chatbot-messages,
#chatbot-container.chatbot-minimizado #chatbot-input {
    display: none;
}

#chatbot-container.chatbot-minimizado #chatbot-toggle {
    font-size: 0;
}

#chatbot-container.chatbot-minimizado #chatbot-toggle::before {
    content: "+";
    font-size: 23px;
}

#chatbot-status {
    display: block;
    margin-top: 2px;
    color: #718096;
    font-size: 11px;
    text-align: center;
}

@media (max-width: 600px) {
    #chatbot-container {
        right: 12px;
        bottom: 12px;
        width: calc(100vw - 24px);
        max-width: none;
        height: min(520px, calc(100dvh - 24px));
        max-height: calc(100dvh - 24px);
        border-radius: 15px;
    }

    #chatbot-header {
        min-height: 60px;
        padding: 12px 14px;
    }

    #chatbot-messages {
        padding: 14px;
    }
}

/* Rodapé institucional */

#senn-footer {
    margin-top: 36px;
    padding: 22px 20px;
    border-top: 1px solid #e1e8ef;
    background: #fff;
    color: #718096;
    font-family: inherit;
    font-size: 12px;
    line-height: 1.7;
    text-align: center;
}

#senn-footer strong {
    color: #354b5d;
    font-weight: 700;
}

#senn-footer .senn-footer-sub {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 11px;
}
</style>

<div id="chatbot-container">

    <div id="chatbot-header">
        <div class="chatbot-titulo">
            <span class="chatbot-avatar" aria-hidden="true">🤖</span>
            <span>
                SENN AI-X Helper
                <span class="chatbot-subtitulo">Assistente virtual de logística</span>
            </span>
        </div>

        <button
            id="chatbot-toggle"
            type="button"
            onclick="toggleChat()"
            aria-label="Minimizar ou expandir assistente"
            aria-expanded="true"
        >−</button>
    </div>

    <div
        id="chatbot-messages"
        role="log"
        aria-live="polite"
        aria-label="Conversa com o assistente"
    >
        <div class="msg-bot">Olá! Sou o assistente virtual SENN AI-X. Como posso ajudar com suas entregas ou frota hoje?</div>
    </div>

    <div id="chatbot-input">
        <input
            type="text"
            id="chatMsg"
            placeholder="Pergunte algo ao assistente..."
            aria-label="Digite sua mensagem"
            maxlength="2000"
            autocomplete="off"
        >

        <button id="chatSendButton" type="button" onclick="sendChat()">
            Enviar
        </button>
    </div>

    <span id="chatbot-status" role="status" aria-live="polite"></span>
</div>

<script>
(function () {
    const chatbot = document.getElementById('chatbot-container');
    const mensagens = document.getElementById('chatbot-messages');
    const input = document.getElementById('chatMsg');
    const botaoEnviar = document.getElementById('chatSendButton');
    const botaoToggle = document.getElementById('chatbot-toggle');
    const status = document.getElementById('chatbot-status');

    if (!chatbot || !mensagens || !input || !botaoEnviar || !botaoToggle) {
        return;
    }

    window.toggleChat = function () {
        const minimizado = chatbot.classList.toggle('chatbot-minimizado');

        botaoToggle.textContent = minimizado ? '+' : '−';
        botaoToggle.setAttribute('aria-expanded', String(!minimizado));
        botaoToggle.setAttribute(
            'aria-label',
            minimizado ? 'Expandir assistente' : 'Minimizar assistente'
        );

        if (!minimizado) {
            input.focus();
        }
    };

    function adicionarMensagem(texto, tipo) {
        const elemento = document.createElement('div');

        elemento.className = tipo;
        // Usa textContent para impedir que respostas sejam interpretadas como HTML.
        elemento.textContent = String(texto ?? '');

        mensagens.appendChild(elemento);
        mensagens.scrollTop = mensagens.scrollHeight;

        return elemento;
    }

    window.sendChat = async function () {
        const msg = input.value.trim();

        if (!msg || botaoEnviar.disabled) {
            return;
        }

        adicionarMensagem(msg, 'msg-user');
        input.value = '';
        botaoEnviar.disabled = true;
        status.textContent = 'O assistente está respondendo...';

        try {
            const resposta = await fetch('senn_helper.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ prompt: msg }).toString(),
                credentials: 'same-origin'
            });

            if (!resposta.ok) {
                throw new Error('HTTP ' + resposta.status);
            }

            const data = await resposta.json();

            if (typeof data.resposta !== 'string' || !data.resposta.trim()) {
                throw new Error('Resposta inválida do assistente.');
            }

            adicionarMensagem(data.resposta, 'msg-bot');
            status.textContent = '';

        } catch (erro) {
            console.error('Falha no SENN AI-X Helper:', erro);

            adicionarMensagem(
                'Não foi possível obter uma resposta agora. Verifique sua conexão e tente novamente.',
                'msg-bot msg-erro'
            );

            status.textContent = 'Falha ao conectar com o assistente.';
        } finally {
            botaoEnviar.disabled = false;
            mensagens.scrollTop = mensagens.scrollHeight;
            input.focus();
        }
    };

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            window.sendChat();
        }
    });
})();
</script>

<?php endif; ?>

<footer id="senn-footer">
    <strong>SENAI DEV EXPERIENCE 2026</strong> · Módulo A
    <span class="senn-footer-sub">
        SENN AI-X Logística Last-Mile · Gestão inteligente de operações
    </span>
</footer>

</body>
</html>
