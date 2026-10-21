
<?php

require_once __DIR__ . "/seguranca.php";

require_once __DIR__ . "/conexao.php";

$perfis = [
    "administrador" => "Administrador",
    "gestor" => "Gestor",
    "despachante" => "Despachante",
    "motorista" => "Motorista"
];

$usuarioLogado = isset($_SESSION["usuario_id"]);
$ehAdmin = $usuarioLogado &&
    ($_SESSION["usuario_nivel"] ?? "") === "administrador";

$mensagem = "";
$tipoMensagem = "erro";
$primeiroUsuario = false;

try {
    $consulta = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $primeiroUsuario = ((int) $consulta->fetchColumn() === 0);
} catch (PDOException $e) {
    http_response_code(500);
    exit("Não foi possível acessar os usuários do sistema.");
}

if ($usuarioLogado && !$ehAdmin) {
    http_response_code(403);
    exit(
        'Acesso negado. Somente administradores podem cadastrar usuários. ' .
        '<a href="painel.php">Voltar ao painel</a>'
    );
}

if ($ehAdmin) {
    $titulo = "Cadastrar usuário";
    $descricao = "Cadastre usuários e escolha o perfil de acesso.";
} elseif ($primeiroUsuario) {
    $titulo = "Criar conta administrativa";
    $descricao = "Crie a primeira conta para administrar o SENN AI-X.";
} else {
    $titulo = "Criar minha conta";
    $descricao = "Cadastre-se para acessar o SENN AI-X. Novas contas recebem o perfil Motorista.";
}

$nome = "";
$email = "";
$perfilSelecionado = "motorista";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_exigir();
    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $perfilSolicitado = $_POST["perfil"] ?? "motorista";
    $perfilSelecionado = $perfilSolicitado;

    try {
        $consulta = $pdo->query("SELECT COUNT(*) FROM usuarios");
        $totalUsuarios = (int) $consulta->fetchColumn();
        $primeiroUsuario = ($totalUsuarios === 0);

        if (
            $nome === "" ||
            mb_strlen($nome) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($senha) < 8
        ) {
            $mensagem = "Preencha os campos corretamente. A senha deve ter pelo menos 8 caracteres.";
        } elseif (!isset($perfis[$perfilSolicitado])) {
            $mensagem = "Selecione um tipo de conta válido.";
        } else {
            if ($primeiroUsuario) {
                $perfilFinal = "administrador";
            } elseif ($ehAdmin) {
                $perfilFinal = $perfilSolicitado;
            } else {
                // Modo de teste solicitado: permite escolher perfil no cadastro público.
                $perfilFinal = $perfilSolicitado;
            }

            $consultaEmail = $pdo->prepare(
                "SELECT id FROM usuarios WHERE email = :email LIMIT 1"
            );

            $consultaEmail->execute([
                ":email" => $email
            ]);

            if ($consultaEmail->fetch()) {
                $mensagem = "Este e-mail já está cadastrado.";
            } else {
                $senhaHash = password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                );

                $pdo->beginTransaction();

                $inserir = $pdo->prepare(
                    "INSERT INTO usuarios (nome, email, senha, perfil)
                     VALUES (:nome, :email, :senha, :perfil)"
                );

                $inserir->execute([
                    ":nome" => $nome,
                    ":email" => $email,
                    ":senha" => $senhaHash,
                    ":perfil" => $perfilFinal
                ]);

                $novoUsuarioId = (int) $pdo->lastInsertId();

                $pdo->commit();

                if ($primeiroUsuario) {
                    session_regenerate_id(true);

                    $_SESSION["usuario_id"] = $novoUsuarioId;
                    $_SESSION["usuario_nome"] = $nome;
                    $_SESSION["usuario_nivel"] = "administrador";

                    header("Location: painel.php");
                    exit;
                }

                $mensagem = "Conta cadastrada com sucesso!";
                $tipoMensagem = "sucesso";

                $nome = "";
                $email = "";
                $perfilSelecionado = "motorista";
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $mensagem = "Não foi possível cadastrar a conta. Verifique os dados e tente novamente.";
        $tipoMensagem = "erro";
    }
}

function escaparCadastro($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= escaparCadastro($titulo) ?> | SENN AI-X</title>

    <link rel="stylesheet" href="style.css">
</head>
<body>

    <main class="painel-container">

        <section class="painel-secao">

            <div class="painel-secao-titulo">
                <p class="painel-identificacao">SENN AI-X</p>

                <h1><?= escaparCadastro($titulo) ?></h1>

                <p><?= escaparCadastro($descricao) ?></p>
            </div>

            <?php if ($mensagem !== ""): ?>
                <p
                    class="<?= escaparCadastro($tipoMensagem) ?>"
                    role="alert"
                >
                    <?= escaparCadastro($mensagem) ?>
                </p>
            <?php endif; ?>

            <form
                method="POST"
                action="cadastro.php"
                class="formulario-cadastro"
            >
                <?= csrf_campo() ?>

                <div class="form-grid">

                    <div>
                        <label for="nome">Nome completo</label>

                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            maxlength="100"
                            value="<?= escaparCadastro($nome) ?>"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div>
                        <label for="email">E-mail</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= escaparCadastro($email) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div>
                        <label for="senha">Senha (mínimo 8 caracteres)</label>

                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div>
                        <label for="perfil">Tipo de conta</label>
                        <select id="perfil" name="perfil" required>
                            <?php foreach ($perfis as $valor => $rotulo): ?>
                                <option value="<?= escaparCadastro($valor) ?>" <?= $perfilSelecionado === $valor ? "selected" : "" ?>>
                                    <?= escaparCadastro($rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <?php if (!$ehAdmin && !$primeiroUsuario): ?>
                    <p>
                        Modo de teste: escolha o perfil para esta conta. Em um sistema publicado,
                        a seleção de Administrador deve ser restrita a usuários autorizados.
                    </p>
                <?php elseif ($primeiroUsuario): ?>
                    <p>
                        Esta é a primeira conta do sistema e será criada como Administrador.
                    </p>
                <?php endif; ?>

                <button type="submit" class="botao-principal">
                    <?= $ehAdmin ? "Cadastrar usuário" : "Criar conta" ?>
                </button>

            </form>

            <div class="painel-secao-titulo">
                <?php if ($ehAdmin): ?>
                    <a href="painel.php">Voltar ao painel</a>
                <?php else: ?>
                    <p>
                        Já tem uma conta?
                        <a href="login.php">Entrar</a>
                    </p>
                <?php endif; ?>
            </div>

        </section>

    </main>

</body>
</html>