<?php
require_once "auth.php";
verificar_autenticacao();
require_once "conexao.php";

$alertas = $pdo->query("SELECT * FROM alertas ORDER BY id DESC")->fetchAll();
require_once "header.php";
?>
<div class="container">
    <h2>Alertas e Notificações Automáticas - RF11</h2>
    <div class="card">
        <h3>Painel de Monitoramento de Eventos Críticos</h3>
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Tipo de Alerta</th>
                    <th>Mensagem</th>
                    <th>Nível Crítico</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($alertas)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-light);">Nenhum alerta crítico pendente no momento. Operação em normalidade.</td>
                </tr>
                <?php else: ?>
                <?php foreach($alertas as $a): ?>
                <tr>
                    <td><?= $a['criado_em'] ?></td>
                    <td><strong><?= strtoupper(str_replace('_', ' ', $a['tipo'])) ?></strong></td>
                    <td><?= htmlspecialchars($a['mensagem']) ?></td>
                    <td><span class="badge badge-falha"><?= strtoupper($a['nivel']) ?></span></td>
                    <td><?= $a['resolvido'] ? 'Resolvido' : 'Pendente' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once "footer.php"; ?>