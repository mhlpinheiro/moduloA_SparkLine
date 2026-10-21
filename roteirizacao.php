<?php
require_once __DIR__ . '/seguranca.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { csrf_exigir(); }
require_once __DIR__ . "/auth.php";
verificar_autenticacao();
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/ia_conexao.php";

$mensagem = "";
$rota_otimizada = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otimizar_rotas'])) {
    $veiculo_id = (int)$_POST['veiculo_id'];
    $motorista_id = (int)$_POST['motorista_id'];

    $pedidos_pendentes = $pdo->query("SELECT * FROM entregas WHERE status='pendente'")->fetchAll();
    
    $prompt = "Otimize a rota para " . count($pedidos_pendentes) . " entregas minimizando tempo e combustivel.";
    $resposta_ia = consultar_ia($prompt);

    $pdo->query("UPDATE entregas SET status='em_rota', motorista_id=$motorista_id, veiculo_id=$veiculo_id WHERE status='pendente'");
    $mensagem = "Rotas otimizadas com sucesso! Veículo e motorista alocados.";
    $rota_otimizada = $resposta_ia;
}

$pedidos_em_rota = $pdo->query("SELECT p.*, m.nome as motorista_nome, v.placa FROM entregas p LEFT JOIN motoristas m ON p.motorista_id = m.id LEFT JOIN veiculos v ON p.veiculo_id = v.id WHERE p.status='em_rota'")->fetchAll();
$veiculos = $pdo->query("SELECT * FROM veiculos WHERE status='disponivel'")->fetchAll();
$motoristas = $pdo->query("SELECT * FROM motoristas WHERE status='disponivel'")->fetchAll();

require_once __DIR__ . "/header.php";
?>
<div class="container">
    <h2>Roteirização e Otimização de Rotas (IA & TSP) - RF05</h2>
    <?php if($mensagem): ?><p style="color: green; font-weight: bold;"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 2fr;">
        <div class="card">
            <h3>Executar Roteirização Inteligente</h3>
            <form method="POST">
                <?= csrf_campo() ?>
                <input type="hidden" name="otimizar_rotas" value="1">
                <div class="form-group"><label>Selecione o Veículo:</label>
                    <select name="veiculo_id" required>
                        <?php foreach($veiculos as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa']) ?> (Cap: <?= $v['capacidade_kg'] ?>kg)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Selecione o Motorista:</label>
                    <select name="motorista_id" required>
                        <?php foreach($motoristas as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;">🤖 Gerar Rota Otimizada via IA</button>
            </form>

            <?php if($rota_otimizada): ?>
            <div style="margin-top: 1.5rem; background: #e0f2fe; padding: 1rem; border-radius: 6px;">
                <h4>Resultado do Algoritmo IA:</h4>
                <p style="font-size: 0.9rem; color: #0369a1;"><?= htmlspecialchars($rota_otimizada) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Entregas em Rota Planejada</h3>
            <table>
                <thead>
                    <tr>
                        <th>Código Pedido</th>
                        <th>Cliente / Endereço</th>
                        <th>Motorista / Veículo</th>
                        <th>Status Rota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pedidos_em_rota as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['codigo_pedido']) ?></strong></td>
                        <td><?= htmlspecialchars($p['cliente_nome']) ?><br><small><?= htmlspecialchars($p['endereco']) ?></small></td>
                        <td><?= htmlspecialchars($p['motorista_nome'] ?: 'Não atribuído') ?><br><small>Placa: <?= htmlspecialchars($p['placa'] ?? '') ?></small></td>
                        <td><span class="badge badge-em_rota">Em Rota de Entrega</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>