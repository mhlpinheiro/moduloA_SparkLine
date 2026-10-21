<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';

$permitidos = ['administrador', 'gestor', 'despachante'];
if (!in_array($_SESSION['usuario_nivel'] ?? '', $permitidos, true)) {
    http_response_code(403);
    exit('Acesso negado. <a href="painel.php">Voltar ao painel</a>');
}

if (isset($_GET['exportar']) && $_GET['exportar'] === 'entregas') {
    $stmt = $pdo->query('SELECT codigo_pedido, cliente_nome, endereco, janela_inicio, janela_fim, peso_kg, volume_m3, prioridade, status, risco_atraso_porcentagem, data_criacao FROM entregas ORDER BY data_criacao DESC');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="senn_relatorio_entregas.csv"');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Código', 'Cliente', 'Endereço', 'Janela início', 'Janela fim', 'Peso kg', 'Volume m3', 'Prioridade', 'Status', 'Risco de atraso %', 'Criado em'], ';');
    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($saida, $linha, ';');
    fclose($saida);
    exit;
}

$indicadores = $pdo->query("SELECT COUNT(*) total, SUM(status = 'pendente') pendentes, SUM(status = 'em_rota') em_rota, SUM(status = 'entregue') entregues, SUM(status = 'falha') falhas, AVG(risco_atraso_porcentagem) risco_medio FROM entregas")->fetch() ?: [];
$veiculos = $pdo->query('SELECT COUNT(*) total, SUM(status = \'disponivel\') disponiveis, SUM(status = \'em_rota\') em_rota, SUM(status = \'em_manutencao\') manutencao FROM veiculos')->fetch() ?: [];
require_once __DIR__ . '/header.php';
?>
<main class="container">
    <h2>Relatórios operacionais</h2>
    <p>Indicadores calculados diretamente a partir dos registros atuais do banco.</p>
    <div class="cards">
        <article class="card"><h3>Entregas</h3><p><?= (int)($indicadores['total'] ?? 0) ?></p></article>
        <article class="card"><h3>Pendentes</h3><p><?= (int)($indicadores['pendentes'] ?? 0) ?></p></article>
        <article class="card"><h3>Em rota</h3><p><?= (int)($indicadores['em_rota'] ?? 0) ?></p></article>
        <article class="card"><h3>Entregues</h3><p><?= (int)($indicadores['entregues'] ?? 0) ?></p></article>
        <article class="card"><h3>Falhas</h3><p><?= (int)($indicadores['falhas'] ?? 0) ?></p></article>
        <article class="card"><h3>Risco médio</h3><p><?= number_format((float)($indicadores['risco_medio'] ?? 0), 1, ',', '.') ?>%</p></article>
    </div>
    <h3>Frota</h3>
    <p>Total: <?= (int)($veiculos['total'] ?? 0) ?> · Disponíveis: <?= (int)($veiculos['disponiveis'] ?? 0) ?> · Em rota: <?= (int)($veiculos['em_rota'] ?? 0) ?> · Em manutenção: <?= (int)($veiculos['manutencao'] ?? 0) ?></p>
    <p><a class="btn" href="relatorios.php?exportar=entregas">Exportar entregas para CSV</a></p>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
