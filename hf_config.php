
<?php

$configPath = __DIR__ . '/config.local.php';

$config = file_exists($configPath)
    ? require $configPath
    : [];

$CHAVE_IA = $config['HF_API_TOKEN'] ?? '';
$MODELO_HF = $config['HF_MODEL']
    ?? 'meta-llama/Llama-3.2-3B-Instruct';

if ($CHAVE_IA === '') {
    error_log('SENN AI-X: token da Hugging Face não configurado.');
}
