
<?php

require_once __DIR__ . "/auth.php";

$nome_usuario = $_SESSION["usuario_nome"] ?? "Usuário";
$nivel_usuario = $_SESSION["usuario_nivel"] ?? "";

$perfis = [
    "administrador" => "Administrador",
    "gestor" => "Gestor",
    "despachante" => "Despachante",
    "motorista" => "Motorista"
];

$nome_perfil = $perfis[$nivel_usuario] ?? "Usuário";
$eh_admin = ($nivel_usuario === "administrador");

$modulos = [
    [
        "titulo" => "Gestão de Ativos",
        "descricao" => "Cadastre veículos e acompanhe capacidade, disponibilidade e situação dos ativos.",
        "arquivo" => "ativos.php",
        "categoria" => "Operações",
        "perfis" => ["administrador", "gestor"]
    ],
    [
        "titulo" => "Movimentações",
        "descricao" => "Cadastre entregas e acompanhe seus status e responsáveis.",
        "arquivo" => "movimentacoes.php",
        "categoria" => "Operações",
        "perfis" => ["administrador", "gestor", "despachante", "motorista"]
    ],
    [
        "titulo" => "Otimização de Rotas",
        "descricao" => "Organize entregas considerando prioridades e capacidade dos veículos.",
        "arquivo" => "otimizar_rotas.php",
        "categoria" => "Operações",
        "perfis" => ["administrador", "gestor", "despachante"]
    ],
    [
        "titulo" => "Manutenções",
        "descricao" => "Agende manutenções e acompanhe a disponibilidade dos veículos.",
        "arquivo" => "manutencoes.php",
        "categoria" => "Operações",
        "perfis" => ["administrador", "gestor"]
    ],
    [
        "titulo" => "Destinatários",
        "descricao" => "Consulte e gerencie os setores destinatários.",
        "arquivo" => "destinatarios.php",
        "categoria" => "Operações",
        "perfis" => ["administrador", "gestor"]
    ],
    [
        "titulo" => "Central de Alertas",
        "descricao" => "Consulte alertas relacionados às operações logísticas.",
        "arquivo" => "gerar_alertas.php",
        "categoria" => "Monitoramento",
        "perfis" => ["administrador", "gestor", "despachante"]
    ],
    [
        "titulo" => "Dashboard e Relatórios",
        "descricao" => "Acompanhe indicadores e informações para a gestão logística.",
        "arquivo" => "dashboard.php",
        "categoria" => "Monitoramento",
        "perfis" => ["administrador", "gestor"]
    ],
    [
        "titulo" => "SENN AI-X Helper",
        "descricao" => "Acesse o assistente do sistema para obter suporte às atividades logísticas.",
        "arquivo" => "senn_helper.php",
        "categoria" => "Inteligência Artificial",
        "perfis" => ["administrador", "gestor", "despachante", "motorista"]
    ]
];

$categorias = [
    "Operações" => [
        "titulo" => "Operações logísticas",
        "descricao" => "Gerencie entregas, veículos e recursos da operação."
    ],
    "Monitoramento" => [
        "titulo" => "Monitoramento e relatórios",
        "descricao" => "Acompanhe alertas e indicadores da operação."
    ],
    "Inteligência Artificial" => [
        "titulo" => "Inteligência do sistema",
        "descricao" => "Ferramentas de suporte inteligente do SENN AI-X."
    ]
];

$nome_usuario = htmlspecialchars(
    $nome_usuario,
    ENT_QUOTES,
    "UTF-8"
);

$nome_perfil = htmlspecialchars(
    $nome_perfil,
    ENT_QUOTES,
    "UTF-8"
);

function escaparPainel($valor): string
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

    <title>Painel de Controle | SENN AI-X</title>

    <link rel="stylesheet" href="style.css">
</head>
<body>

    <main class="painel-container">

        <header class="painel-header">
            <div>
                <p class="painel-identificacao">SENN AI-X</p>
                <h1>Painel de Controle</h1>
                <p class="painel-subtitulo">
                    Sistema de gestão logística inteligente
                </p>
            </div>

            <form method="POST" action="logout.php" class="form-logout">
                <?= csrf_campo() ?>
                <button class="painel-sair" type="submit">Sair do sistema</button>
            </form>
        </header>

        <section class="painel-boas-vindas">
            <div>
                <h2>Olá, <?= $nome_usuario ?>!</h2>
                <p>Bem-vindo(a) ao sistema de gestão logística.</p>
            </div>

            <div class="painel-perfil">
                <span>Perfil de acesso</span>
                <strong><?= $nome_perfil ?></strong>
            </div>
        </section>

        <?php foreach ($categorias as $categoria => $informacoes): ?>

            <?php
            $modulosCategoria = array_filter(
                $modulos,
                function ($modulo) use ($categoria, $nivel_usuario) {
                    return
                        $modulo["categoria"] === $categoria &&
                        in_array(
                            $nivel_usuario,
                            $modulo["perfis"],
                            true
                        );
                }
            );
            ?>

            <?php if (!empty($modulosCategoria)): ?>

                <section class="painel-secao">

                    <div class="painel-secao-titulo">
                        <h2><?= escaparPainel($informacoes["titulo"]) ?></h2>
                        <p><?= escaparPainel($informacoes["descricao"]) ?></p>
                    </div>

                    <div class="painel-grid">

                        <?php foreach ($modulosCategoria as $modulo): ?>

                            <a
                                class="painel-card"
                                href="<?= escaparPainel($modulo["arquivo"]) ?>"
                            >
                                <span class="painel-card-categoria">
                                    <?= escaparPainel($modulo["categoria"]) ?>
                                </span>

                                <h3>
                                    <?= escaparPainel($modulo["titulo"]) ?>
                                </h3>

                                <p>
                                    <?= escaparPainel($modulo["descricao"]) ?>
                                </p>

                                <span class="painel-card-link">
                                    Acessar módulo
                                </span>
                            </a>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

        <?php endforeach; ?>

        <?php if ($eh_admin): ?>

            <section class="painel-secao painel-admin">

                <div class="painel-secao-titulo">
                    <h2>Administração do sistema</h2>
                    <p>
                        Gerencie as contas e os perfis de acesso dos usuários.
                    </p>
                </div>

                <div class="painel-grid">

                    <a class="painel-card" href="admin.php">
                        <span class="painel-card-categoria">
                            Acesso restrito
                        </span>

                        <h3>Área administrativa</h3>

                        <p>
                            Consulte os usuários cadastrados e seus perfis.
                        </p>

                        <span class="painel-card-link">
                            Acessar administração
                        </span>
                    </a>

                    <a class="painel-card" href="cadastro.php">
                        <span class="painel-card-categoria">
                            Usuários
                        </span>

                        <h3>Gestão de usuários</h3>

                        <p>
                            Cadastre usuários e defina seus perfis de acesso.
                        </p>

                        <span class="painel-card-link">
                            Gerenciar usuários
                        </span>
                    </a>

                </div>

            </section>

        <?php endif; ?>

        <footer class="painel-footer">
            <p>SENN AI-X — Sistema de Gestão Logística</p>
        </footer>

    </main>

</body>
</html>
