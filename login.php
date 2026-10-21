<?php
session_start();
require_once "conexao.php";

// Se a pessoa já está logada, vai direto para o painel
if (isset($_SESSION["usuario_id"])) {
    header("Location: painel.php");
    exit;
}

// Se ainda não existe nenhum usuário, vai criar o primeiro (administrador)
$resultado = mysqli_query($conexao, "SELECT COUNT(*) AS total FROM usuarios");
$linha = mysqli_fetch_assoc($resultado);
if ((int) $linha["total"] === 0) {
    header("Location: cadastro.php");
    exit;
}

$mensagem = "";

// Só executa quando o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    // Busca o usuário pelo e-mail
    $sql = "SELECT id, nome, senha_hash, nivel FROM usuarios WHERE email = ?";
    $comando = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($comando, "s", $email);
    mysqli_stmt_execute($comando);
    $resultado = mysqli_stmt_get_result($comando);
    $usuario = mysqli_fetch_assoc($resultado);

    // Achou o usuário E a senha digitada confere com o hash salvo?
    if ($usuario && password_verify($senha, $usuario["senha_hash"])) {
        session_regenerate_id(true); // novo ID de sessão ao entrar (mais seguro)

        // Guarda na sessão quem está logado
        $_SESSION["usuario_id"]    = $usuario["id"];
        $_SESSION["usuario_nome"]  = $usuario["nome"];
        $_SESSION["usuario_nivel"] = $usuario["nivel"];

        header("Location: painel.php");
        exit;
    } else {
        $mensagem = "E-mail ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SENN AI-X | Gestão Logística</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>SENN AI-X | Gestão Logística</h1>
    <h2>Entrar no sistema</h2>

    <?php if ($mensagem !== "") { ?>
        <p><strong><?php echo $mensagem; ?></strong></p>
    <?php } ?>

    <form method="post" action="login.php">
        <p>
            <label for="email">E-mail</label><br>
            <input type="email" id="email" name="email" required>
        </p>
        <p>
            <label for="senha">Senha</label><br>
            <input type="password" id="senha" name="senha" required>
        </p>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
