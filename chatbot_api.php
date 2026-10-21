
<?php
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/chave_ia.php';

header('Content-Type: application/json; charset=utf-8');

function respostaJson(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Aceita somente requisições POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respostaJson(405, ['erro' => 'Use POST.']);
}

// Verifica se o usuário está autenticado.
if (empty($_SESSION['usuario_id'])) {
    respostaJson(401, ['erro' => 'Faça login primeiro.']);
}

// Valida o token CSRF conforme a implementação de seguranca.php.
csrf_exigir();

// Confere a configuração da IA.
if (
    !isset($CHAVE_IA, $MODELO_HF) ||
    trim($CHAVE_IA) === '' ||
    trim($MODELO_HF) === ''
) {
    error_log('SENN AI-X: configuração da IA incompleta.');
    respostaJson(500, [
        'erro' => 'A IA não está configurada corretamente no servidor.'
    ]);
}

// Lê e valida o JSON recebido.
$entrada = json_decode(file_get_contents('php://input'), true);

if (!is_array($entrada)) {
    respostaJson(400, ['erro' => 'Requisição JSON inválida.']);
}

$mensagem = $entrada['mensagem'] ?? '';

if (!is_string($mensagem)) {
    respostaJson(422, ['erro' => 'A mensagem deve ser um texto.']);
}

$mensagem = trim($mensagem);

if ($mensagem === '' || mb_strlen($mensagem, 'UTF-8') > 2000) {
    respostaJson(422, [
        'erro' => 'A mensagem não pode estar vazia nem ultrapassar 2000 caracteres.'
    ]);
}

// Monta a solicitação para a Hugging Face.
$payload = [
    'model' => $MODELO_HF,
    'messages' => [
        [
            'role' => 'system',
            'content' =>
                'Você é o assistente virtual do SENN AI-X, especializado em logística last-mile. ' .
                'Responda em português brasileiro, com clareza e objetividade. ' .
                'Não invente dados de pedidos, frotas ou motoristas. ' .
                'Você não tem acesso automático ao banco de dados do sistema.'
        ],
        [
            'role' => 'user',
            'content' => $mensagem
        ]
    ],
    'max_tokens' => 400,
    'stream' => false
];

$ch = curl_init(
    'https://router.huggingface.co/v1/chat/completions'
);

if ($ch === false) {
    respostaJson(500, ['erro' => 'Não foi possível iniciar a conexão com a IA.']);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $CHAVE_IA,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    )
]);

$corpo = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($corpo === false) {
    error_log('SENN AI-X: falha cURL: ' . curl_error($ch));
    curl_close($ch);

    respostaJson(502, [
        'erro' => 'Falha de conexão com o serviço de inteligência artificial.'
    ]);
}

curl_close($ch);

$dados = json_decode($corpo, true);

// Trata erros retornados pelo serviço.
if ($status < 200 || $status >= 300) {
    error_log('SENN AI-X: Hugging Face retornou HTTP ' . $status);

    respostaJson(502, [
        'erro' => 'A IA recusou a solicitação. Verifique o token, o modelo e a disponibilidade do serviço.'
    ]);
}

if (!is_array($dados)) {
    respostaJson(502, ['erro' => 'A IA retornou uma resposta inválida.']);
}

$texto = $dados['choices'][0]['message']['content'] ?? null;

if (!is_string($texto) || trim($texto) === '') {
    respostaJson(502, [
        'erro' => 'O serviço de IA não retornou uma resposta utilizável.'
    ]);
}

respostaJson(200, [
    'resposta' => trim($texto)
]);
a