<?php
// Coloque este arquivo no topo de TODA página que exige login:
//     require_once "auth.php";
session_start();

// Se não existe usuário na sessão, manda para o login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Chame exigir_admin() no topo das páginas que só o administrador pode usar
function exigir_admin() {
    if ($_SESSION["usuario_nivel"] !== "admin") {
        http_response_code(403);
        die("Acesso negado: esta página é só para administradores. <a href=\"painel.php\">Voltar ao painel</a>");
    }
}
