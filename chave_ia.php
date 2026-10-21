<?php
// Configure HF_API_TOKEN e HF_MODEL no ambiente do PHP/XAMPP.
// Não coloque tokens reais neste arquivo nem os envie ao GitHub.
$CHAVE_IA = getenv('HF_API_TOKEN') ?: '';
$MODELO_HF = getenv('HF_MODEL') ?: 'meta-llama/Llama-3.2-3B-Instruct';
