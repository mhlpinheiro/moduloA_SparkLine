
<?php
require_once __DIR__ . '/ia_conexao.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderJson(405, [
        'erro' => 'Método não permitido. Utilize POST.'
    ]);
}

// Aceita tanto JSON (mensagem) quanto formulário POST (prompt).
$tipoConteudo = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($tipoConteudo, 'application/json') !== false) {
    $entrada = json_decode(file_get_contents('php://input'), true);

    if (!is_array($entrada)) {
        responderJson(400, ['erro' => 'JSON inválido.']);
    }

    $prompt = $entrada['mensagem'] ?? $entrada['prompt'] ?? '';
} else {
    $prompt = $_POST['prompt'] ?? $_POST['mensagem'] ?? '';
}

if (!is_string($prompt)) {
    responderJson(422, ['erro' => 'A mensagem deve ser um texto.']);
}

$prompt = trim($prompt);

if ($prompt === '' || mb_strlen($prompt, 'UTF-8') > 2000) {
    responderJson(422, [
        'erro' => 'Envie uma mensagem válida de até 2000 caracteres.'
    ]);
}

try {
    $resposta = consultar_ia($prompt);

    if (!is_string($resposta) || trim($resposta) === '') {
        responderJson(502, [
            'erro' => 'A inteligência artificial não retornou uma resposta válida.'
        ]);
    }

    responderJson(200, [
        'resposta' => trim($resposta)
    ]);
} catch (Throwable $e) {
    error_log('SENN AI-X - erro no senn_helper.php: ' . $e->getMessage());

    responderJson(500, [
        'erro' => 'Não foi possível processar a mensagem. Tente novamente.'
    ]);
}
