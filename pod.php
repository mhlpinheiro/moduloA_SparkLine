<?php
require_once "auth.php";
verificar_autenticacao();
require_once "conexao.php";

$mensagem = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pod'])) {
    $entrega_id = (int)$_POST['entrega_id'];
    $lat = $_POST['latitude'] ?: -23.0264;
    $lng = $_POST['longitude'] ?: -45.5558;
    $tipo = $_POST['evidencia_tipo'];
    $dado = $_POST['evidencia_dado'];
    $obs = $_POST['observacoes'];

    $stmt = $pdo->prepare("INSERT INTO comprovantes_pod (entrega_id, latitude, longitude, evidencia_tipo, evidencia_dado, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$entrega_id, $lat, $lng, $tipo, $dado, $obs])) {
        $pdo->query("UPDATE entregas SET status='entregue' WHERE id=$entrega_id");
        $mensagem = "Comprovante de entrega (POD) registrado com sucesso! Pedido finalizado.";
    }
}

$pedidos_em_rota = $pdo->query("SELECT * FROM entregas WHERE status='em_rota'")->fetchAll();
$comprovantes = $pdo->query("SELECT c.*, e.codigo_pedido, e.cliente_nome FROM comprovantes_pod c JOIN entregas e ON c.entrega_id = e.id ORDER BY c.id DESC")->fetchAll();

require_once "header.php";
?>
<div class="container">
    <h2>Comprovação de Entrega (POD - Proof of Delivery) - RF07</h2>
    <?php if($mensagem): ?><p style="color: green; font-weight: bold;"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 2fr;">
        <div class="card">
            <h3>Registrar Entrega (Motorista)</h3>
            <form method="POST">
                <input type="hidden" name="registrar_pod" value="1">
                <div class="form-group"><label>Selecione a Entrega:</label>
                    <select name="entrega_id" required>
                        <?php foreach($pedidos_em_rota as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['codigo_pedido']) ?> - <?= htmlspecialchars($p['cliente_nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Tipo de Evidência:</label>
                    <select name="evidencia_tipo">
                        <option value="codigo">Código de Confirmação</option>
                        <option value="assinatura">Assinatura Digital</option>
                        <option value="foto">Foto do Comprovante</option>
                    </select>
                </div>
                <div class="form-group"><label>Dado da Evidência (Código/Assinatura):</label>
                    <input type="text" name="evidencia_dado" required placeholder="Ex: COD-88492">
                </div>
                <div class="form-group"><label>Observações:</label>
                    <textarea name="observacoes" rows="3" placeholder="Entregue em mãos ao recepcionista."></textarea>
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;">Finalizar Entrega (POD)</button>
            </form>
        </div>

        <div class="card">
            <h3>Histórico de Comprovantes Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Pedido / Cliente</th>
                        <th>Data / Hora</th>
                        <th>Evidência</th>
                        <th>Geolocalização</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($comprovantes as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['codigo_pedido']) ?></strong><br><small><?= htmlspecialchars($c['cliente_nome']) ?></small></td>
                        <td><?= $c['data_hora'] ?></td>
                        <td><span class="badge badge-disponivel"><?= strtoupper($c['evidencia_tipo']) ?></span>: <?= htmlspecialchars($c['evidencia_dado']) ?></td>
                        <td>Lat: <?= $c['latitude'] ?><br>Lng: <?= $c['longitude'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "footer.php"; ?>