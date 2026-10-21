<?php
session_start();
require_once "conexao.php";

// Quantos usuários já existem no sistema?
$resultado = mysqli_query($conexao, "SELECT COUNT(*) AS total FROM usuarios");
$linha = mysqli_fetch_assoc($resultado);
$total = (int) $linha["total"];

// Se já existe algum usuário, só um administrador logado pode cadastrar outros
if ($total > 0) {
    if (!isset($_SESSION["usuario_id"])) {
        header("Location: login.php");
        exit;
    }
    if ($_SESSION["usuario_nivel"] !== "admin") {
        http_response_code(403);
        die("Acesso negado: só administradores podem cadastrar usuários. <a href=\"painel.php\">Voltar ao painel</a>");
    }
}

$mensagem = "";

// Só executa quando o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome  = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $nivel = $_POST["nivel"] ?? "";

    // O primeiro usuário do sistema sempre será administrador
    if ($total === 0) {
        $nivel = "admin";
    }

    if ($nome === "" || $email === "" || $senha === "") {
        $mensagem = "Preencha todos os campos.";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha precisa ter pelo menos 6 caracteres.";
    } elseif ($nivel !== "admin" && $nivel !== "gestor") {
        $mensagem = "Nível de permissão inválido.";
    } else {
        // 1) Esse e-mail já está cadastrado?
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $comando = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($comando, "s", $email);
        mysqli_stmt_execute($comando);
        mysqli_stmt_store_result($comando);

        if (mysqli_stmt_num_rows($comando) > 0) {
            $mensagem = "Já existe um usuário com esse e-mail.";
        } else {
            // 2) Criptografa a senha e salva no banco
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (nome, email, senha_hash, nivel) VALUES (?, ?, ?, ?)";
            $comando = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($comando, "ssss", $nome, $email, $senha_hash, $nivel);
            mysqli_stmt_execute($comando);

            $mensagem = "Usuário cadastrado com sucesso!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro - SENN AI-X | Gestão Logística</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>SENN AI-X | Gestão Logística</h1>
    <h2>Cadastrar usuário</h2>

    <?php if ($total === 0) { ?>
        <p>Primeiro acesso: o usuário criado aqui será o administrador do sistema.</p>
    <?php } ?>

    <?php if ($mensagem !== "") { ?>
        <p><strong><?php echo $mensagem; ?></strong></p>
    <?php } ?>

    <form method="post" action="cadastro.php">
        <p>
            <label for="nome">Nome</label><br>
            <input type="text" id="nome" name="nome" required>
        </p>
        <p>
            <label for="email">E-mail</label><br>
            <input type="email" id="email" name="email" required>
        </p>
        <p>
            <label for="senha">Senha (mínimo 6 caracteres)</label><br>
            <input type="password" id="senha" name="senha" required>
        </p>

        <?php if ($total > 0) { ?>
            <p>
                <label for="nivel">Nível de permissão</label><br>
                <select id="nivel" name="nivel">
                    <option value="admin">Administrador</option>
                    <option value="gestor">Gestor</option>
                </select>
            </p>
        <?php } ?>

        <button type="submit">Cadastrar</button>
    </form>

    <?php if (isset($_SESSION["usuario_id"])) { ?>
        <p><a href="painel.php">Voltar ao painel</a></p>
    <?php } else { ?>
        <p><a href="login.php">Ir para o login</a></p>
    <?php } ?>
</body>
</html>
