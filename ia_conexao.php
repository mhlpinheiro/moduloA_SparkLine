<?php
// Assistente de IA do SENN AI-X Helper (Hugging Face, endereço novo).
// A chave e o modelo ficam em chave_ia.php, que NÃO vai para o GitHub.
require_once __DIR__ . "/chave_ia.php";
 
// Pergunta à IA. Se a IA falhar por qualquer motivo, responde em "modo offline" (RNF05).
function perguntarIA($prompt) {
    // Variáveis criadas em chave_ia.php precisam ser "puxadas" para dentro da função
    global $CHAVE_IA, $MODELO_HF;
 
    $modelo = !empty($MODELO_HF) ? $MODELO_HF : "meta-llama/Llama-3.2-3B-Instruct";
 
    if (empty($CHAVE_IA) || $CHAVE_IA === "COLE_SUA_CHAVE_AQUI") {
        return resposta_offline($prompt, "a chave da IA não está configurada em chave_ia.php");
    }
 
    // Endereço novo da Hugging Face (o api-inference.huggingface.co foi desativado)
    $url = "https://router.huggingface.co/v1/chat/completions";
 
    $dados = [
        "model"       => $modelo,
        "messages"    => [["role" => "user", "content" => $prompt]],
        "max_tokens"  => 400,
        "temperature" => 0.7
    ];
 
    // Deixe true. Se aparecer "SSL certificate problem", troque para false só para testar
    // e depois corrija o certificado (cacert.pem) no php.ini.
    $verificar_ssl = true;
 
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $CHAVE_IA,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verificar_ssl);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verificar_ssl ? 2 : 0);
 
    // Mantidos do seu código: ajudam em computadores com DNS bloqueado
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    @curl_setopt($ch, CURLOPT_DNS_SERVERS, "8.8.8.8,8.8.4.4");
 
    $resposta  = curl_exec($ch);
    $codigo    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro_curl = curl_error($ch);
    curl_close($ch);
 
    // Sem internet, DNS, certificado, tempo esgotado...
    if ($resposta === false) {
        return resposta_offline($prompt, "sem conexão com a IA: " . $erro_curl);
    }
 
    $resultado = json_decode($resposta, true);
 
    // 200 = deu certo. Outro número = problema (chave, modelo, limite de uso...)
    if ($codigo !== 200) {
        $erro = $resultado["error"] ?? "erro desconhecido";
        if (is_array($erro)) {
            $erro = $erro["message"] ?? json_encode($erro);
        }
        return resposta_offline($prompt, "a IA respondeu com erro " . $codigo . ": " . $erro);
    }
 
    // O texto da resposta fica dentro dessa "gaveta" do JSON
    $texto = $resultado["choices"][0]["message"]["content"] ?? "";
    if (trim($texto) === "") {
        return resposta_offline($prompt, "a IA não devolveu texto");
    }
 
    return trim($texto);
}
 
// Modo offline: responde por palavras-chave quando a IA não está disponível
function resposta_offline($pergunta, $motivo) {
    $p = strtolower($pergunta);
 
    $base = [
        ["manuten",   "Para registrar uma manutenção, abra o módulo de Manutenções e escolha o ativo, o tipo (preventiva ou corretiva), a data, o técnico responsável, o custo e a descrição. Ela fica salva no histórico do ativo."],
        ["cadastr",   "Para cadastrar um ativo, abra Gestão de Ativos e preencha o formulário de novo ativo. Colaboradores e setores responsáveis são cadastrados na tela de Destinatários."],
        ["ativo",     "Na Gestão de Ativos você consulta, cadastra, edita e exclui ativos. Cada ativo tem categoria, valor, data de aquisição, número de série, status e localização."],
        ["movimen",   "Para movimentar um ativo, abra Movimentações, escolha o ativo, o novo responsável e a nova localização. O histórico da movimentação é registrado."],
        ["transfer",  "Para transferir um ativo, abra Movimentações, escolha o ativo, o novo responsável e a nova localização."],
        ["alerta",    "A Central de Alertas mostra os avisos pendentes, como ativos em manutenção, prazos e devoluções."],
        ["relat",     "Os relatórios e indicadores ficam no Dashboard. A exportação em CSV abre no Excel."],
        ["dashboard", "O Dashboard mostra o total de ativos, o valor do patrimônio, o custo de manutenção e um gráfico por status."]
    ];
 
    foreach ($base as $item) {
        if (strpos($p, $item[0]) !== false) {
            return "[Modo offline] " . $item[1] . " (IA indisponível: " . $motivo . ")";
        }
    }
 
    return "[Modo offline] Posso ajudar com cadastro de ativos, manutenções, movimentações, alertas e relatórios. "
         . "Tente perguntar sobre um desses temas. (IA indisponível: " . $motivo . ")";
}
 