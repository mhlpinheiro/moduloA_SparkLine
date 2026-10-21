<?php
require_once "auth.php";
verificar_autenticacao();
require_once "conexao.php";

$mensagem = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_motorista'])) {
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $cnh = $_POST['cnh'];
    $categoria = $_POST['categoria_cnh'];
    $validade = $_POST['validade_cnh'];
    $telefone = $_POST['telefone'];
    $veiculo_id = $_POST['veiculo_id'] ?: NULL;

    $stmt = $pdo->prepare("INSERT INTO motoristas (nome, cpf, cnh, categoria_cnh, validade_cnh, telefone, veiculo_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$nome, $cpf, $cnh, $categoria, $validade, $telefone, $veiculo_id])) {
        $mensagem = "Motorista cadastrado com sucesso!";
    }
}

$motoristas = $pdo->query("SELECT m.*, v.placa, v.modelo FROM motoristas m LEFT JOIN veiculos v ON m.veiculo_id = v.id ORDER BY m.id DESC")->fetchAll();
$veiculos_disponiveis = $pdo->query("SELECT * FROM veiculos")->fetchAll();
require_once "header.php";
?>
<div class="container">
    <h2>Cadastro de Motoristas - RF03</h2>
    <?php if($mensagem): ?><p style="color: green; font-weight: bold;"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 2fr;">
        <div class="card">
            <h3>Novo Motorista</h3>
            <form method="POST">
                <input type="hidden" name="cadastrar_motorista" value="1">
                <div class="form-group"><label>Nome Completo:</label><input type="text" name="nome" required></div>
                <div class="form-group"><label>CPF:</label><input type="text" name="cpf" required placeholder="000.000.000-00"></div>
                <div class="form-group"><label>Número da CNH:</label><input type="text" name="cnh" required></div>
                <div class="form-group"><label>Categoria CNH:</label>
                    <select name="categoria_cnh">
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
                <div class="form-group"><label>Validade CNH:</label><input type="date" name="validade_cnh" required></div>
                <div class="form-group"><label>Telefone Contato:</label><input type="text" name="telefone" required></div>
                <div class="form-group"><label>Vincular a Veículo:</label>
                    <select name="veiculo_id">
                        <option value="">Sem veículo no momento</option>
                        <?php foreach($veiculos_disponiveis as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa']) ?> - <?= htmlspecialchars($v['modelo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Salvar Motorista</button>
            </form>
        </div>

        <div class="card">
            <h3>Motoristas Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nome / CPF</th>
                        <th>CNH (Cat. / Validade)</th>
                        <th>Contato</th>
                        <th>Veículo Vinculado</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($motoristas as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['nome']) ?></strong><br><small><?= htmlspecialchars($m['cpf']) ?></small></td>
                        <td>Cat. <?= htmlspecialchars($m['categoria_cnh']) ?><br><small>Venc: <?= $m['validade_cnh'] ?></small></td>
                        <td><?= htmlspecialchars($m['telefone']) ?></td>
                        <td><?= $m['placa'] ? htmlspecialchars($m['placa'] . " (" . $m['modelo'] . ")") : "<em>Nenhum</em>" ?></td>
                        <td><span class="badge badge-<?= $m['status'] ?>"><?= ucfirst(str_replace('_', ' ', $m['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "footer.php"; ?>