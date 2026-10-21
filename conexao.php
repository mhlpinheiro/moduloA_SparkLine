<?php
// Configurações de conexão com o banco de dados oficial SENN AI-X
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "senn_logistica_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Modo Fallback (RNF06)
    try {
        $pdo = new PDO("sqlite:" . __DIR__ . "/senn_logistica_fallback.sqlite");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        die("Erro de conexão com o Banco de Dados: " . $e->getMessage());
    }
}
?>