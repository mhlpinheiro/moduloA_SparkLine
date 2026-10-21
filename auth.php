<?php

require_once __DIR__ . "/seguranca.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$perfisPermitidos = [
    "administrador",
    "gestor",
    "despachante",
    "motorista"
];

if (
    !isset($_SESSION["usuario_nivel"]) ||
    !in_array($_SESSION["usuario_nivel"], $perfisPermitidos, true)
) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $parametros = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            [
                "expires" => time() - 42000,
                "path" => $parametros["path"],
                "domain" => $parametros["domain"],
                "secure" => $parametros["secure"],
                "httponly" => $parametros["httponly"],
                "samesite" => "Lax"
            ]
        );
    }

    session_destroy();

    header("Location: login.php");
    exit;
}

function exigir_admin(): void
{
    if (($_SESSION["usuario_nivel"] ?? "") !== "administrador") {
        http_response_code(403);

        exit(
            'Acesso negado: esta página é exclusiva para administradores. ' .
            '<a href="painel.php">Voltar ao painel</a>'
        );
    }
}