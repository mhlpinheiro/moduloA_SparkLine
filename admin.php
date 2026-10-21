
<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";

exigir_admin();

$nomeUsuario = htmlspecialchars(
    $_SESSION["usuario_nome"] ?? "Administrador",
    ENT_QUOTES,
    "UTF-8"
);

$mensagem = "";
$totalUsuarios = null;
$usuarios = [];

$usuariosPorPerfil = [
    "administrador" => 0,
    "gestor" => 0,
    "despachante" => 0,
    "motorista" => 0
];

$nomesPerfis = [
    "administrador" => "Administrador",
    "gestor" => "Gestor de frota",
    "despachante" => "Despachante",
    "motorista" => "Motorista"
];

try {
    $consulta = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $totalUsuarios = (int) $consulta->fetchColumn();

    $sql = "SELECT perfil, COUNT(*) AS total
            FROM usuarios
            GROUP BY perfil";

    $consulta = $pdo->query($sql);

    while ($usuario = $consulta->fetch()) {
        $perfil = $usuario["perfil"];

        if (array_key_exists($perfil, $usuariosPorPerfil)) {
            $usuariosPorPerfil[$perfil] = (int) $usuario["total"];
        }
    }

    $sql = "SELECT id, nome, email, perfil, criado_em
            FROM usuarios
            ORDER BY id DESC";

    $consulta = $pdo->query($sql);
    $usuarios = $consulta->fetchAll();

} catch (PDOException $erro) {
    error_log("Erro ao consultar usuários: " . $erro->getMessage());

    $totalUsuarios = null;
    $usuarios = [];
    $mensagem = "Não foi possível carregar os dados dos usuários.";
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Administração | SENN AI-X</title>

    <link rel="stylesheet" href="style.css">
</head>
<body>

    <main class="painel-container">

        <header class="painel-header">
            <div>
                <p class="painel-identificacao">SENN AI-X</p>
                <h1>Administração do Sistema</h1>
                <p class="painel-subtitulo">
                    Área restrita para administradores
                </p>
            </div>

            <form method="POST" action="logout.php" class="form-logout">
                <?= csrf_campo() ?>
                <button class="painel-sair" type="submit">Sair do sistema</button>
            </form>
        </header>

        <section class="painel-boas-vindas">
            <div>
                <h2>Bem-vindo(a), <?= $nomeUsuario ?>!</h2>
                <p>Gerencie usuários e acompanhe os acessos do sistema.</p>
            </div>

            <div class="painel-perfil">
                <span>Seu perfil</span>
                <strong>Administrador</strong>
            </div>
        </section>

        <?php if ($mensagem !== ""): ?>
            <p role="alert">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <section class="painel-secao">
            <div class="painel-secao-titulo">
                <h2>Visão geral</h2>
                <p>Resumo das contas registradas no sistema.</p>
            </div>

            <div class="painel-grid">

                <article class="painel-card">
                    <span class="painel-card-categoria">Total de usuários</span>
                    <h3>
                        <?= $totalUsuarios === null ? "Indisponível" : $totalUsuarios ?>
                    </h3>
                    <p>Total de contas cadastradas no sistema.</p>
                </article>

                <article class="painel-card">
                    <span class="painel-card-categoria">Administradores</span>
                    <h3><?= $usuariosPorPerfil["administrador"] ?></h3>
                    <p>Contas com acesso administrativo.</p>
                </article>

                <article class="painel-card">
                    <span class="painel-card-categoria">Gestores</span>
                    <h3><?= $usuariosPorPerfil["gestor"] ?></h3>
                    <p>Contas com perfil de gestão.</p>
                </article>

                <article class="painel-card">
                    <span class="painel-card-categoria">Despachantes</span>
                    <h3><?= $usuariosPorPerfil["despachante"] ?></h3>
                    <p>Contas do setor de despacho.</p>
                </article>

                <article class="painel-card">
                    <span class="painel-card-categoria">Motoristas</span>
                    <h3><?= $usuariosPorPerfil["motorista"] ?></h3>
                    <p>Contas com perfil de motorista.</p>
                </article>

            </div>
        </section>

        <section class="painel-secao">
            <div class="painel-secao-titulo">
                <h2>Usuários cadastrados</h2>
                <p>Consulte as contas e seus respectivos perfis de acesso.</p>
            </div>

            <?php if (count($usuarios) > 0): ?>
                <div class="tabela-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Perfil</th>
                                <th>Cadastro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <?= (int) $usuario["id"] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($usuario["nome"], ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($usuario["email"], ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $nomesPerfis[$usuario["perfil"]] ?? "Não definido",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario["criado_em"] ?? "Não informado",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum usuário encontrado.</p>
            <?php endif; ?>
        </section>

        <section class="painel-secao">
            <div class="painel-secao-titulo">
                <h2>Gerenciamento</h2>
                <p>Acesse as funcionalidades administrativas.</p>
            </div>

            <div class="painel-grid">

                <a class="painel-card" href="cadastro.php">
                    <span class="painel-card-categoria">Usuários</span>
                    <h3>Gestão de usuários</h3>
                    <p>Cadastre novas contas e defina seus perfis de acesso.</p>
                    <span class="painel-card-link">Acessar módulo</span>
                </a>

                <a class="painel-card" href="painel.php">
                    <span class="painel-card-categoria">Sistema</span>
                    <h3>Painel principal</h3>
                    <p>Volte à página inicial e acesse os módulos logísticos.</p>
                    <span class="painel-card-link">Voltar ao painel</span>
                </a>

            </div>
        </section>

        <footer class="painel-footer">
            <p>SENN AI-X — Área administrativa</p>
        </footer>

    </main>

</body>
</html>
