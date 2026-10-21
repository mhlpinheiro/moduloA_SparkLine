
<?php

require_once __DIR__ . "/seguranca.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Use o formulário de saída para encerrar a sessão.");
}

csrf_exigir();

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
