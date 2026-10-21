<?php
require_once "auth.php";
require_once "conexao.php";
exigir_admin();

// Exemplo de regra: Verificar ativos com status 'em_manutencao' para gerar alerta
$sql = "SELECT id, nome FROM ativos WHERE status = 'em_manutencao'";
$resultado = mysqli_query($conexao, $sql);
$alertas_gerados = 0;

while ($ativo = mysqli_fetch_assoc($resultado)) {
    // Verifica se já existe um alerta não resolvido para este ativo
    $ativo_id = $ativo['id'];
    $check = mysqli_query($conexao, "SELECT id FROM alertas WHERE ativo_id = $ativo_id AND resolvido = 0");
    
    if (mysqli_num_rows($check) === 0) {
        $msg = "O ativo '{$ativo['nome']}' encontra-se em manutenção. Acompanhar prazo.";
        $tipo = 'manutencao_programada';
        
        $sql_alerta = "INSERT INTO alertas (ativo_id, tipo, mensagem) VALUES (?, ?, ?)";
        $cmd = mysqli_prepare($conexao, $sql_alerta);
        mysqli_stmt_bind_param($cmd, "iss", $ativo_id, $tipo, $msg);
        mysqli_stmt_execute($cmd);
        $alertas_gerados++;
    }
}

// Mostra os alertas pendentes na base de dados
$alertas_pendentes = mysqli_query($conexao, "SELECT a.mensagem, a.criado_em, atv.nome FROM alertas a LEFT JOIN ativos atv ON a.ativo_id = atv.id WHERE a.resolvido = 0");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Central de Alertas</title>
</head>
<body>
    <h1>Central de Alertas Automáticos</h1>
    <p><em>A rotina de verificação rodou. <?php echo $alertas_gerados; ?> novos alertas gerados hoje.</em></p>
    
    <h2>Alertas Pendentes</h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>Data</th>
            <th>Ativo</th>
            <th>Mensagem</th>
        </tr>
        <?php while ($al = mysqli_fetch_assoc($alertas_pendentes)) { ?>
            <tr>
                <td><?php echo date("d/m/Y H:i", strtotime($al["criado_em"])); ?></td>
                <td><?php echo htmlspecialchars($al["nome"] ?? "Geral"); ?></td>
                <td style="color:red; font-weight:bold;"><?php echo htmlspecialchars($al["mensagem"]); ?></td>
            </tr>
        <?php } ?>
    </table>
    <p><a href="painel.php">Voltar ao painel</a></p>
</body>
</html>