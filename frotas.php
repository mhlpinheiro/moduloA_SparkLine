<?php
require_once __DIR__ . '/seguranca.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { csrf_exigir(); }
require_once __DIR__ . "/auth.php";
verificar_autenticacao();
require_once __DIR__ . "/conexao.php";

$mensagem = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_veiculo'])) {
    $placa = $_POST['placa'];
    $modelo = $_POST['modelo'];
    $tipo = $_POST['tipo'];
    $cap_kg = $_POST['capacidade_kg'];
    $cap_m3 = $_POST['capacidade_m3'];
    $autonomia = $_POST['autonomia_km_l'];
    $status = $_POST['status'];
    $manutencao = $_POST['data_proxima_manutencao'];

    $stmt = $pdo->prepare("INSERT INTO veiculos (placa, modelo, tipo, capacidade_kg, capacidade_m3, autonomia_km_l, status, data_proxima_manutencao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$placa, $modelo, $tipo, $cap_kg, $cap_m3, $autonomia, $status, $manutencao])) {
        $mensagem = "Veículo cadastrado com sucesso!";
    }
}

$veiculos = $pdo->query("SELECT * FROM veiculos ORDER BY id DESC")->fetchAll();
require_once __DIR__ . "/header.php";
?>
<div class="container">
    <h2>Gestão de Frota (Veículos) - RF02</h2>
    
    <?php if($mensagem): ?><p style="color: green; font-weight: bold;"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 2fr;">
        <div class="card">
            <h3>Novo Veículo</h3>
            <form method="POST">
                <?= csrf_campo() ?>
                <input type="hidden" name="cadastrar_veiculo" value="1">
                <div class="form-group"><label>Placa:</label><input type="text" name="placa" required placeholder="ABC-1234"></div>
                <div class="form-group"><label>Modelo:</label><input type="text" name="modelo" required placeholder="Mercedes Sprinter"></div>
                <div class="form-group"><label>Tipo:</label>
                    <select name="tipo">
                        <option value="Van">Van</option>
                        <option value="VUC">VUC</option>
                        <option value="Caminhão Médio">Caminhão Médio</option>
                    </select>
                </div>
                <div class="form-group"><label>Capacidade (kg):</label><input type="number" step="0.01" name="capacidade_kg" required value="1500"></div>
                <div class="form-group"><label>Capacidade (m³):</label><input type="number" step="0.01" name="capacidade_m3" required value="10.0"></div>
                <div class="form-group"><label>Autonomia (km/L):</label><input type="number" step="0.1" name="autonomia_km_l" required value="9.5"></div>
                <div class="form-group"><label>Status:</label>
                    <select name="status">
                        <option value="disponivel">Disponível</option>
                        <option value="em_rota">Em Rota</option>
                        <option value="em_manutencao">Em Manutenção</option>
                    </select>
                </div>
                <div class="form-group"><label>Data Próxima Manutenção:</label><input type="date" name="data_proxima_manutencao" required></div>
                <button type="submit" class="btn" style="width: 100%;">Salvar Veículo</button>
            </form>
        </div>

        <div class="card">
            <h3>Veículos Cadastrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Placa / Modelo</th>
                        <th>Tipo</th>
                        <th>Capacidade</th>
                        <th>Autonomia</th>
                        <th>Status</th>
                        <th>Próx. Manutenção</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($veiculos as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['placa']) ?></strong><br><small><?= htmlspecialchars($v['modelo']) ?></small></td>
                        <td><?= htmlspecialchars($v['tipo']) ?></td>
                        <td><?= $v['capacidade_kg'] ?> kg / <?= $v['capacidade_m3'] ?> m³</td>
                        <td><?= $v['autonomia_km_l'] ?> km/L</td>
                        <td><span class="badge badge-<?= $v['status'] ?>"><?= ucfirst(str_replace('_', ' ', $v['status'])) ?></span></td>
                        <td><?= $v['data_proxima_manutencao'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>