<?php
require_once __DIR__ . "/ia_conexao.php";

header('Content-Type: application/json');

$prompt = $_POST['prompt'] ?? '';

if (!empty($prompt)) {
    $resposta = consultar_ia($prompt);
    echo json_encode(["resposta" => $resposta]);
} else {
    echo json_encode(["resposta" => "Por favor, envie uma mensagem válida."]);
}
?>