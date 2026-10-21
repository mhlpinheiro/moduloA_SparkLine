<?php
require_once "auth.php";
require_once "conexao.php";

// Métricas Gerais
$res_ativos = mysqli_query($conexao, "SELECT COUNT(id) as qtd, SUM(valor) as total FROM ativos");
$ativos_dados = mysqli_fetch_assoc($res_ativos);

$res_manu = mysqli_query($conexao, "SELECT SUM(custo) as total_custo FROM manutencoes");
$manu_dados = mysqli_fetch_assoc($res_manu);

// Dados para o Gráfico (Status dos Ativos)
$res_status = mysqli_query($conexao, "SELECT status, COUNT(*) as qtd FROM ativos GROUP BY status");
$status_labels = [];
$status_counts = [];
while ($row = mysqli_fetch_assoc($res_status)) {
    $status_labels[] = ucfirst(str_replace('_', ' ', $row['status']));
    $status_counts[] = $row['qtd'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - SENN AI-X</title>
    <!-- Importa o Chart.js para os gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .cards { display: flex; gap: 20px; margin-bottom: 30px; }
        .card { border: 1px solid #ccc; padding: 20px; border-radius: 8px; background: #f9f9f9; text-align: center; flex: 1; }
        .card h3 { margin: 0 0 10px 0; color: #333; }
        .card p { margin: 0; font-size: 24px; font-weight: bold; color: #007BFF; }
        .chart-container { width: 400px; height: 400px; margin: 0 auto; }
    </style>
</head>
<body>
    <h1>Dashboard Gerencial</h1>
    <p><a href="painel.php">Voltar ao painel</a></p>

    <div class="cards">
        <div class="card">
            <h3>Total de Ativos</h3>
            <p><?php echo $ativos_dados['qtd']; ?></p>
        </div>
        <div class="card">
            <h3>Valor do Patrimônio</h3>
            <p>R$ <?php echo number_format($ativos_dados['total'] ?? 0, 2, ",", "."); ?></p>
        </div>
        <div class="card">
            <h3>Custo de Manutenção</h3>
            <p>R$ <?php echo number_format($manu_dados['total_custo'] ?? 0, 2, ",", "."); ?></p>
        </div>
    </div>

    <div class="chart-container">
        <canvas id="graficoStatus"></canvas>
    </div>

    <script>
        const ctx = document.getElementById('graficoStatus');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    label: 'Ativos por Status',
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: ['#28a745', '#007bff', '#ffc107', '#dc3545']
                }]
            }
        });
    </script>
</body>
</html>