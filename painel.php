<?php
require_once "auth.php";
require_once "conexao.php";

// Verifica se é administrador
$eh_admin = ($_SESSION["usuario_nivel"] === "admin");
$nome_usuario = $_SESSION["usuario_nome"] ?? "Usuário";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel de Controle - SENN AI-X</title>
    <!-- Ligar o ficheiro CSS para o design moderno -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Painel de Controle - SENN AI-X</h1>
        <p>Olá, <strong><?php echo htmlspecialchars($nome_usuario); ?></strong>! Bem-vindo(a) ao sistema de gestão.</p>
        
        <h2>Menu Principal</h2>
        <ul class="menu-painel">
            <!-- Módulos de IA e Relatórios -->
            <li><a href="senn_helper.php">🤖 SENN AI-X Helper (IA)</a></li>
            <li><a href="dashboard.php">📊 Dashboard (IA e Relatórios)</a></li>
            
            <!-- Módulos Base -->
            <li><a href="ativos.php">📦 Gestão de Ativos</a></li>
            <li><a href="movimentacoes.php">🔄 Movimentações</a></li>
            <li><a href="manutencoes.php">🛠️ Registar Manutenções</a></li>
            <li><a href="destinatarios.php">👥 Destinatários (Setores)</a></li>
            <li><a href="gerar_alertas.php">⚠️ Central de Alertas</a></li>
            
            <!-- Módulo Exclusivo Admin -->
            <?php if ($eh_admin) { ?>
                <li><a href="cadastro.php">🛡️ Gestão de Utilizadores</a></li>
            <?php } ?>
        </ul>

        <hr style="margin-top: 30px; border: 0; border-top: 1px solid #eee;">
        <p style="text-align: right;"><a href="logout.php" style="color: #e74c3c;">Sair do sistema</a></p>
    </div>
</body>
</html>