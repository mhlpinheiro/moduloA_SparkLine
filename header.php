<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENN AI-X - Gestão Logística Last-Mile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
<div class="navbar">
    <div class="brand">
        🚚 SENN AI-X <span style="font-size: 0.8rem; color:#94a3b8;">Logistics</span>
    </div>
    <?php if (isset($_SESSION['usuario_id'])): ?>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="frotas.php">Frota</a></li>
        <li><a href="motoristas.php">Motoristas</a></li>
        <li><a href="pedidos.php">Pedidos / CSV</a></li>
        <li><a href="roteirizacao.php">Roteirização (IA)</a></li>
        <li><a href="rastreamento.php">Rastreamento</a></li>
        <li><a href="pod.php">POD (Comprovantes)</a></li>
        <li><a href="alertas.php">Alertas</a></li>
        <li><a href="logout.php" style="color: #f87171;">Sair (<?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?>)</a></li>
    </ul>
    <?php endif; ?>
</div>