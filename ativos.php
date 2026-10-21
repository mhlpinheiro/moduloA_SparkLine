
<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";

$nivelUsuario = $_SESSION["usuario_nivel"] ?? "";

$perfisPermitidos = [
    "administrador",
    "gestor"
];

if (!in_array($nivelUsuario, $perfisPermitidos, true)) {
    http_response_code(403);
    exit(
        'Você não tem permissão para cadastrar veículos. ' .
        '<a href="painel.php">Voltar ao painel</a>'
    );
}

$mensagem = "";
$tipoMensagem = "";

$placa = "";
$modelo = "";
$tipo = "";
$capacidadeKg = "";
$capacidadeM3 = "";
$autonomia = "";
$status = "disponivel";
$dataManutencao = "";

$tiposVeiculo = [
    "van" => "Van",
    "caminhao" => "Caminhão",
    "carro" => "Carro",
    "moto" => "Moto",
    "utilitario" => "Utilitário",
    "outro" => "Outro"
];

$statusVeiculo = [
    "disponivel" => "Disponível",
    "em_rota" => "Em rota",
    "em_manutencao" => "Em manutenção"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $placa = strtoupper(trim($_POST["placa"] ?? ""));
    $modelo = trim($_POST["modelo"] ?? "");
    $tipo = $_POST["tipo"] ?? "";
    $capacidadeKg = trim($_POST["capacidade_kg"] ?? "");
    $capacidadeM3 = trim($_POST["capacidade_m3"] ?? "");
    $autonomia = trim($_POST["autonomia_km_l"] ?? "");
    $status = $_POST["status"] ?? "disponivel";
    $dataManutencao = trim($_POST["data_proxima_manutencao"] ?? "");

    if (
        $placa === "" ||
        $modelo === "" ||
        $tipo === "" ||
        $capacidadeKg === "" ||
        $capacidadeM3 === ""
    ) {
        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipoMensagem = "erro";
    } elseif (strlen($placa) > 10 || strlen($modelo) > 100) {
        $mensagem = "Confira a placa e o modelo informados.";
        $tipoMensagem = "erro";
    } elseif (!isset($tiposVeiculo[$tipo])) {
        $mensagem = "Selecione um tipo de veículo válido.";
        $tipoMensagem = "erro";
    } elseif (
        !is_numeric($capacidadeKg) ||
        (float) $capacidadeKg <= 0 ||
        !is_numeric($capacidadeM3) ||
        (float) $capacidadeM3 <= 0
    ) {
        $mensagem = "As capacidades devem ser números maiores que zero.";
        $tipoMensagem = "erro";
    } elseif (
        $autonomia !== "" &&
        (!is_numeric($autonomia) || (float) $autonomia < 0)
    ) {
        $mensagem = "Informe uma autonomia válida.";
        $tipoMensagem = "erro";
    } elseif (!isset($statusVeiculo[$status])) {
        $mensagem = "Selecione um status válido.";
        $tipoMensagem = "erro";
    } elseif (
        $dataManutencao !== "" &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataManutencao)
    ) {
        $mensagem = "Informe uma data de manutenção válida.";
        $tipoMensagem = "erro";
    } else {
        try {
            $consulta = $pdo->prepare(
                "SELECT id FROM veiculos WHERE placa = :placa LIMIT 1"
            );

            $consulta->execute([
                ":placa" => $placa
            ]);

            if ($consulta->fetch()) {
                $mensagem = "Já existe um veículo cadastrado com essa placa.";
                $tipoMensagem = "erro";
            } else {
                $sql = "INSERT INTO veiculos (
                            placa,
                            modelo,
                            tipo,
                            capacidade_kg,
                            capacidade_m3,
                            autonomia_km_l,
                            status,
                            data_proxima_manutencao
                        ) VALUES (
                            :placa,
                            :modelo,
                            :tipo,
                            :capacidade_kg,
                            :capacidade_m3,
                            :autonomia,
                            :status,
                            :data_manutencao
                        )";

                $comando = $pdo->prepare($sql);

                $comando->execute([
                    ":placa" => $placa,
                    ":modelo" => $modelo,
                    ":tipo" => $tipo,
                    ":capacidade_kg" => $capacidadeKg,
                    ":capacidade_m3" => $capacidadeM3,
                    ":autonomia" => $autonomia !== "" ? $autonomia : null,
                    ":status" => $status,
                    ":data_manutencao" => $dataManutencao !== ""
                        ? $dataManutencao
                        : null
                ]);

                $mensagem = "Veículo cadastrado com sucesso!";
                $tipoMensagem = "sucesso";

                $placa = "";
                $modelo = "";
                $tipo = "";
                $capacidadeKg = "";
                $capacidadeM3 = "";
                $autonomia = "";
                $status = "disponivel";
                $dataManutencao = "";
            }
        } catch (PDOException $erro) {
            error_log("Erro ao cadastrar veículo: " . $erro->getMessage());

            $mensagem = "Não foi possível cadastrar o veículo.";
            $tipoMensagem = "erro";
        }
    }
}

try {
    $consulta = $pdo->query(
        "SELECT placa, modelo, tipo, capacidade_kg, capacidade_m3,
                autonomia_km_l, status, data_proxima_manutencao
         FROM veiculos
         ORDER BY placa ASC"
    );

    $veiculos = $consulta->fetchAll();
} catch (PDOException $erro) {
    error_log("Erro ao consultar veículos: " . $erro->getMessage());

    $veiculos = [];
    $mensagem = "Não foi possível carregar a lista de veículos.";
    $tipoMensagem = "erro";
}

function escapar($valor): string
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestão de Ativos | SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="painel-container">

    <header class="painel-header">
        <div>
            <p class="painel-identificacao">SENN AI-X</p>
            <h1>Gestão de Ativos</h1>
            <p class="painel-subtitulo">Cadastro e consulta de veículos da frota</p>
        </div>

        <a class="painel-sair" href="painel.php">Voltar ao painel</a>
    </header>

    <?php if ($mensagem !== ""): ?>
        <p class="<?= escapar($tipoMensagem) ?>" role="alert">
            <?= escapar($mensagem) ?>
        </p>
    <?php endif; ?>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Cadastrar veículo</h2>
            <p>Preencha os dados do veículo para adicioná-lo à frota.</p>
        </div>

        <form method="POST" action="ativos.php" class="formulario-cadastro">
                <?= csrf_campo() ?>

            <div class="form-grid">
                <p>
                    <label for="placa">Placa *</label>
                    <input
                        type="text"
                        id="placa"
                        name="placa"
                        value="<?= escapar($placa) ?>"
                        maxlength="10"
                        placeholder="ABC1D23"
                        required
                    >
                </p>

                <p>
                    <label for="modelo">Modelo *</label>
                    <input
                        type="text"
                        id="modelo"
                        name="modelo"
                        value="<?= escapar($modelo) ?>"
                        maxlength="100"
                        placeholder="Ex.: Fiat Ducato"
                        required
                    >
                </p>

                <p>
                    <label for="tipo">Tipo de veículo *</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">Selecione</option>
                        <?php foreach ($tiposVeiculo as $valor => $rotulo): ?>
                            <option
                                value="<?= escapar($valor) ?>"
                                <?= $tipo === $valor ? "selected" : "" ?>
                            >
                                <?= escapar($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="capacidade_kg">Capacidade em kg *</label>
                    <input
                        type="number"
                        id="capacidade_kg"
                        name="capacidade_kg"
                        min="0.01"
                        step="0.01"
                        value="<?= escapar($capacidadeKg) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="capacidade_m3">Capacidade em m³ *</label>
                    <input
                        type="number"
                        id="capacidade_m3"
                        name="capacidade_m3"
                        min="0.01"
                        step="0.01"
                        value="<?= escapar($capacidadeM3) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="autonomia_km_l">Autonomia (km/l)</label>
                    <input
                        type="number"
                        id="autonomia_km_l"
                        name="autonomia_km_l"
                        min="0"
                        step="0.01"
                        value="<?= escapar($autonomia) ?>"
                    >
                </p>

                <p>
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <?php foreach ($statusVeiculo as $valor => $rotulo): ?>
                            <option
                                value="<?= escapar($valor) ?>"
                                <?= $status === $valor ? "selected" : "" ?>
                            >
                                <?= escapar($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="data_proxima_manutencao">Próxima manutenção</label>
                    <input
                        type="date"
                        id="data_proxima_manutencao"
                        name="data_proxima_manutencao"
                        value="<?= escapar($dataManutencao) ?>"
                    >
                </p>
            </div>

            <button type="submit">Cadastrar veículo</button>
        </form>
    </section>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Veículos cadastrados</h2>
            <p>Total de veículos listados: <?= count($veiculos) ?></p>
        </div>

        <?php if (count($veiculos) > 0): ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Modelo</th>
                            <th>Tipo</th>
                            <th>Capacidade (kg)</th>
                            <th>Capacidade (m³)</th>
                            <th>Autonomia (km/l)</th>
                            <th>Status</th>
                            <th>Próxima manutenção</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <tr>
                                <td><?= escapar($veiculo["placa"]) ?></td>
                                <td><?= escapar($veiculo["modelo"]) ?></td>
                                <td><?= escapar($tiposVeiculo[$veiculo["tipo"]] ?? $veiculo["tipo"]) ?></td>
                                <td><?= escapar($veiculo["capacidade_kg"]) ?></td>
                                <td><?= escapar($veiculo["capacidade_m3"]) ?></td>
                                <td><?= escapar($veiculo["autonomia_km_l"] ?? "Não informada") ?></td>
                                <td><?= escapar($statusVeiculo[$veiculo["status"]] ?? $veiculo["status"]) ?></td>
                                <td>
                                    <?= $veiculo["data_proxima_manutencao"]
                                        ? escapar(date("d/m/Y", strtotime($veiculo["data_proxima_manutencao"])))
                                        : "Não informada" ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhum veículo cadastrado até o momento.</p>
        <?php endif; ?>
    </section>

    <footer class="painel-footer">
        <p>SENN AI-X — Gestão de Ativos</p>
    </footer>

</main>

</body>
</html>
