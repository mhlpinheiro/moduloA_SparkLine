
<?php
require_once __DIR__ . '/seguranca.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SENN AI-X - Gestão Logística Last-Mile</title>

    <link rel="stylesheet" href="style.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        defer
    ></script>
</head>
<body>

<header class="navbar">
    <a href="dashboard.php" class="brand">
        <span class="brand-icon">🚚</span>

        <span class="brand-name">
            SENN <strong>AI-X</strong>
            <small>LOGISTICS INTELLIGENCE</small>
        </span>
    </a>

    <?php if (isset($_SESSION['usuario_id'])): ?>

        <nav class="nav-menu" aria-label="Menu principal">
            <a href="dashboard.php">Dashboard</a>
            <a href="frotas.php">Frota</a>
            <a href="motoristas.php">Motoristas</a>
            <a href="pedidos.php">Pedidos / CSV</a>
            <a href="roteirizacao.php">Roteirização IA</a>
            <a href="rastreamento.php">Rastreamento</a>
            <a href="pod.php">Comprovantes</a>
            <a href="alertas.php">Alertas</a>
            <a href="relatorios.php">Relatórios</a>

            <?php if (
                ($_SESSION['usuario_nivel'] ?? '') === 'administrador'
            ): ?>
                <a href="gerar_alertas.php">Gerar alertas</a>
                <a href="backup.php">Backup</a>
            <?php endif; ?>
        </nav>

        <div class="nav-user">
            <span class="user-status" aria-hidden="true"></span>

            <span class="user-name">
                <?= htmlspecialchars(
                    $_SESSION['usuario_nome'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

            <form
                method="POST"
                action="logout.php"
                class="form-logout"
            >
                <?= csrf_campo() ?>

                <button type="submit" class="btn-logout">
                    Sair
                </button>
            </form>
        </div>

    <?php endif; ?>
</header>
</body>
</html>
