<?php
require_once "auth.php";
verificar_autenticacao();
require_once "conexao.php";

$mensagem = "";

// Importação CSV em Lote (RF04)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_csv'])) {
    $file = $_FILES['arquivo_csv']['tmp_name'];
    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        $count = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) >= 5) {
                $codigo = $data[0];
                $cliente = $data[1];
                $endereco = $data[2];
                $peso = floatval($data[3]);
                $volume = floatval($data[4]);
                $prioridade = isset($data[5]) ? $data[5] : 'media';

                $stmt = $pdo->prepare("INSERT INTO entregas (codigo_pedido, cliente_nome, endereco, peso_kg, volume_m3, prioridade) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cliente_nome=VALUES(cliente_nome)");
                $stmt->execute([$codigo, $cliente, $endereco, $peso, $volume, $prioridade]);
                $count++;
            }
        }
        fclose($handle);
        $mensagem = "Sucesso! $count pedidos importados via arquivo CSV.";
    }
}

// Cadastro Manual (RF04)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_manual'])) {
    $codigo = $_POST['codigo_pedido'];
    $cliente = $_POST['cliente_nome'];
    $endereco = $_POST['endereco'];
    $janela_inicio = $_POST['janela_inicio'];
    $janela_fim = $_POST['janela_fim'];
    $peso = $_POST['peso_kg'];
    $volume = $_POST['volume_m3'];
    $prioridade = $_POST['prioridade'];

    $stmt = $pdo->prepare("INSERT INTO entregas (codigo_pedido, cliente_nome, endereco, janela_inicio, janela_fim, peso_kg, volume_m3, prioridade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$codigo, $cliente, $endereco, $janela_inicio, $janela_fim, $peso, $volume, $prioridade])) {
        $mensagem = "Pedido cadastrado manualmente com sucesso!";
    }
}

$pedidos = $pdo->query("SELECT * FROM entregas ORDER BY id DESC")->fetchAll();
require_once "header.php";
?>
<div class="container">
    <h2>Cadastro e Importação de Pedidos / Entregas - RF04</h2>
    <?php if($mensagem): ?><p style="color: green; font-weight: bold;"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 1fr 2fr;">
        <div class="card">
            <h3>📂 Importar Lote (CSV)</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Selecione o arquivo CSV:</label>
                    <input type="file" name="arquivo_csv" accept=".csv" required>
                </div>
                <small style="display:block; margin-bottom: 1rem; color: var(--text-light);">Formato esperado: CODIGO,CLIENTE,ENDERECO,PESO,VOLUME,PRIORIDADE</small>
                <button type="submit" class="btn btn-success" style="width: 100%;">Upload e Importar</button>
            </form>
        </div>

        <div class="card">
            <h3>📝 Novo Pedido Manual</h3>
            <form method="POST">
                <input type="hidden" name="cadastrar_manual" value="1">
                <div class="form-group"><label>Código Pedido:</label><input type="text" name="codigo_pedido" required placeholder="PED-1004"></div>
                <div class="form-group"><label>Cliente:</label><input type="text" name="cliente_nome" required></div>
                <div class="form-group"><label>Endereço Completo:</label><input type="text" name="endereco" required></div>
                <div class="form-group"><label>Janela Início / Fim:</label>
                    <input type="time" name="janela_inicio" required style="width: 48%; display:inline;">
                    <input type="time" name="janela_fim" required style="width: 48%; display:inline;">
                </div>
                <div class="form-group"><label>Peso (kg) / Volume (m³):</label>
                    <input type="number" step="0.1" name="peso_kg" required value="10.0" style="width: 48%; display:inline;">
                    <input type="number" step="0.1" name="volume_m3" required value="0.2" style="width: 48%; display:inline;">
                </div>
                <div class="form-group"><label>Prioridade:</label>
                    <select name="prioridade">
                        <option value="baixa">Baixa</option>
                        <option value="media">Média</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Cadastrar Pedido</button>
            </form>
        </div>

        <div class="card">
            <h3>Lista de Entregas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Código / Cliente</th>
                        <th>Endereço</th>
                        <th>Janela</th>
                        <th>Carga</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pedidos as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['codigo_pedido']) ?></strong><br><small><?= htmlspecialchars($p['cliente_nome']) ?></small></td>
                        <td><?= htmlspecialchars($p['endereco']) ?></td>
                        <td><?= $p['janela_inicio'] ?> - <?= $p['janela_fim'] ?></td>
                        <td><?= $p['peso_kg'] ?> kg<br><small><?= $p['volume_m3'] ?> m³</small></td>
                        <td><span class="badge badge-<?= $p['prioridade'] ?>"><?= ucfirst($p['prioridade']) ?></span></td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "footer.php"; ?>