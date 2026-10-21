
<?php

require_once __DIR__ . "/seguranca.php";

require_once __DIR__ . "/conexao.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: painel.php");
    exit;
}

$mensagem = "";
$email = "";

$perfisPermitidos = [
    "administrador",
    "gestor",
    "despachante",
    "motorista"
];

try {
    $consulta = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $totalUsuarios = (int) $consulta->fetchColumn();

    if ($totalUsuarios === 0) {
        header("Location: cadastro.php");
        exit;
    }
} catch (PDOException $erro) {
    error_log("Erro ao consultar usuários: " . $erro->getMessage());
    http_response_code(500);
    exit("Não foi possível acessar o banco de dados.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_exigir();
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($email === "" || $senha === "") {
        $mensagem = "Preencha o e-mail e a senha.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um endereço de e-mail válido.";
    } else {
        try {
            $sql = "SELECT id, nome, senha, perfil
                    FROM usuarios
                    WHERE email = :email
                    LIMIT 1";

            $comando = $pdo->prepare($sql);
            $comando->execute([
                ":email" => $email
            ]);

            $usuario = $comando->fetch();

            if (
                $usuario &&
                password_verify($senha, $usuario["senha"]) &&
                in_array($usuario["perfil"], $perfisPermitidos, true)
            ) {
                session_regenerate_id(true);

                $_SESSION["usuario_id"] = (int) $usuario["id"];
                $_SESSION["usuario_nome"] = $usuario["nome"];
                $_SESSION["usuario_nivel"] = $usuario["perfil"];

                header("Location: painel.php");
                exit;
            }

            $mensagem = "E-mail ou senha incorretos.";

        } catch (PDOException $erro) {
            error_log("Erro ao realizar login: " . $erro->getMessage());
            $mensagem = "Não foi possível realizar o login. Tente novamente.";
        }
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
<body class="pagina-login">

    <main class="login-container">
        <h1>SENN AI-X</h1>
        <h2>Gestão Logística</h2>
        <p>Entre na sua conta para continuar.</p>

        <?php if ($mensagem !== ""): ?>
            <p role="alert" class="mensagem-erro">
                <strong>
                    <?= htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") ?>
                </strong>
            </p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_campo() ?>
            <p>
                <label for="email">E-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                    maxlength="100"
                    autocomplete="username"
                    required
                >
            </p>

            <p>
                <label for="senha">Senha</label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    autocomplete="current-password"
                    required
                >
            </p>

            <button type="submit">Entrar</button>
        </form>

        <p class="link-cadastro">
            Ainda não tem uma conta?
            <a href="cadastro.php">Cadastre-se aqui</a>.
        </p>
    </main>

</body>
</html>
