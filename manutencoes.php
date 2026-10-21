
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
        'Você não tem permissão para gerenciar manutenções. ' .
        '<a href="painel.php">Voltar ao painel</a>'
    );
}

$mensagem = "";
$tipoMensagem = "";

function escaparManutencao($valor): string
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $placa = strtoupper(trim($_POST["placa"] ?? ""));
    $acao = $_POST["acao"] ?? "";

    if ($placa === "") {
        $mensagem = "Selecione um veículo.";
        $tipoMensagem = "erro";
    } elseif (!in_array($acao, ["agendar", "concluir"], true)) {
        $mensagem = "Ação inválida.";
        $tipoMensagem = "erro";
    } else {
        try {
            if ($acao === "agendar") {
                $dataManutencao = trim(
                    $_POST["data_proxima_manutencao"] ?? ""
                );

                if ($dataManutencao === "") {
                    $mensagem = "Informe a data prevista para a manutenção.";
                    $tipoMensagem = "erro";
                } elseif (
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataManutencao)
                    || !checkdate(
                        (int) substr($dataManutencao, 5, 2),
                        (int) substr($dataManutencao, 8, 2),
                        (int) substr($dataManutencao, 0, 4)
                    )
                ) {
                    $mensagem = "Informe uma data válida.";
                    $tipoMensagem = "erro";
                } else {
                    $consulta = $pdo->prepare(
                        "UPDATE veiculos
                         SET data_proxima_manutencao = :data,
                             status = 'em_manutencao'
                         WHERE placa = :placa"
                    );

                    $consulta->execute([
                        ":data" => $dataManutencao,
                        ":placa" => $placa
                    ]);

                    if ($consulta->rowCount() > 0) {
                        $mensagem = "Manutenção agendada e veículo marcado como em manutenção.";
                        $tipoMensagem = "sucesso";
                    } else {
                        $consulta = $pdo->prepare(
                            "SELECT placa FROM veiculos WHERE placa = :placa"
                        );
                        $consulta->execute([
                            ":placa" => $placa
                        ]);

                        if ($consulta->fetch()) {
                            $mensagem = "Os dados do veículo já estavam atualizados.";
                            $tipoMensagem = "sucesso";
                        } else {
                            $mensagem = "Veículo não encontrado.";
                            $tipoMensagem = "erro";
                        }
                    }
                }
            }

            if ($acao === "concluir") {
                $consulta = $pdo->prepare(
                    "UPDATE veiculos
                     SET status = 'disponivel',
                         data_proxima_manutencao = NULL
                     WHERE placa = :placa
                     AND status = 'em_manutencao'"
                );

                $consulta->execute([
                    ":placa" => $placa
                ]);

                if ($consulta->rowCount() > 0) {
                    $mensagem = "Manutenção concluída. Veículo marcado como disponível.";
                    $tipoMensagem = "sucesso";
                } else {
                    $mensagem = "Não foi possível concluir a manutenção. Verifique o veículo e seu status.";
                    $tipoMensagem = "erro";
                }
            }
        } catch (PDOException $erro) {
            error_log("Erro ao gerenciar manutenção: " . $erro->getMessage());
            $mensagem = "Não foi possível atualizar a manutenção.";
            $tipoMensagem = "erro";
        }
    }
}

try {
    $consulta = $pdo->query(
        "SELECT placa, modelo, tipo, status, data_proxima_manutencao
         FROM veiculos
         ORDER BY
             CASE
                 WHEN status = 'em_manutencao' THEN 0
                 WHEN data_proxima_manutencao IS NOT NULL THEN 1
                 ELSE 2
             END,
             data_proxima_manutencao ASC,
             placa ASC"
    );

    $veiculos = $consulta->fetchAll();
} catch (PDOException $erro) {
    error_log("Erro ao consultar manutenções: " . $erro->getMessage());
    $veiculos = [];
    $mensagem = "Não foi possível carregar os veículos.";
    $tipoMensagem = "erro";
}

$totalVeiculos = count($veiculos);
$totalEmManutencao = 0;
$totalAgendadas = 0;
$totalVencidas = 0;
$hoje = date("Y-m-d");

foreach ($veiculos as $veiculo) {
    if ($veiculo["status"] === "em_manutencao") {
        $totalEmManutencao++;
    }

    if (!empty($veiculo["data_proxima_manutencao"])) {
        $data = $veiculo["data_proxima_manutencao"];

        if ($data >= $hoje && $veiculo["status"] !== "em_manutencao") {
            $totalAgendadas++;
        }

        if ($data < $hoje && $veiculo["status"] !== "em_manutencao") {
            $totalVencidas++;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manutenções | SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="painel-container">

    <header class="painel-header">
        <div>
            <p class="painel-identificacao">SENN AI-X</p>
            <h1>Manutenções da frota</h1>
            <p class="painel-subtitulo">
                Planejamento e acompanhamento da manutenção dos veículos
            </p>
        </div>

        <a class="painel-sair" href="painel.php">Voltar ao painel</a>
    </header>

    <?php if ($mensagem !== ""): ?>
        <p class="<?= escaparManutencao($tipoMensagem) ?>" role="alert">
            <?= escaparManutencao($mensagem) ?>
        </p>
    <?php endif; ?>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Resumo da frota</h2>
            <p>Indicadores baseados nos dados registrados dos veículos.</p>
        </div>

        <div class="painel-grid">
            <article class="painel-card">
                <span class="painel-card-categoria">Veículos cadastrados</span>
                <h3><?= $totalVeiculos ?></h3>
                <p>Total de veículos consultados.</p>
            </article>

            <article class="painel-card">
                <span class="painel-card-categoria">Em manutenção</span>
                <h3><?= $totalEmManutencao ?></h3>
                <p>Veículos atualmente indisponíveis para operação.</p>
            </article>

            <article class="painel-card">
                <span class="painel-card-categoria">Manutenções previstas</span>
                <h3><?= $totalAgendadas ?></h3>
                <p>Veículos com manutenção futura registrada.</p>
            </article>

            <article class="painel-card">
                <span class="painel-card-categoria">Datas vencidas</span>
                <h3><?= $totalVencidas ?></h3>
                <p>Veículos cuja data prevista já passou.</p>
            </article>
        </div>
    </section>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Agendar manutenção</h2>
            <p>
                Selecione um veículo e informe a data prevista.
                O veículo será marcado como em manutenção.
            </p>
        </div>

        <form method="POST" action="manutencoes.php" class="formulario-cadastro">
                <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="agendar">

            <div class="form-grid">
                <p>
                    <label for="placa_agendar">Veículo</label>
                    <select id="placa_agendar" name="placa" required>
                        <option value="">Selecione um veículo</option>

                        <?php foreach ($veiculos as $veiculo): ?>
                            <option value="<?= escaparManutencao($veiculo["placa"]) ?>">
                                <?= escaparManutencao($veiculo["placa"]) ?>
                                — <?= escaparManutencao($veiculo["modelo"]) ?>
                                (<?= escaparManutencao($veiculo["status"]) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="data_proxima_manutencao">Data prevista</label>
                    <input
                        type="date"
                        id="data_proxima_manutencao"
                        name="data_proxima_manutencao"
                        min="<?= escaparManutencao($hoje) ?>"
                        required
                    >
                </p>
            </div>

            <button type="submit">Agendar manutenção</button>
        </form>
    </section>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Situação dos veículos</h2>
            <p>Consulte as datas previstas e atualize a situação da frota.</p>
        </div>

        <?php if ($totalVeiculos > 0): ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Modelo</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Data prevista</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <tr>
                                <td><?= escaparManutencao($veiculo["placa"]) ?></td>
                                <td><?= escaparManutencao($veiculo["modelo"]) ?></td>
                                <td><?= escaparManutencao($veiculo["tipo"]) ?></td>
                                <td>
                                    <?= escaparManutencao(
                                        $veiculo["status"] === "em_manutencao"
                                            ? "Em manutenção"
                                            : ($veiculo["status"] === "em_rota"
                                                ? "Em rota"
                                                : "Disponível")
                                    ) ?>
                                </td>
                                <td>
                                    <?= !empty($veiculo["data_proxima_manutencao"])
                                        ? escaparManutencao(
                                            date(
                                                "d/m/Y",
                                                strtotime($veiculo["data_proxima_manutencao"])
                                            )
                                        )
                                        : "Não informada" ?>
                                </td>
                                <td>
                                    <?php if ($veiculo["status"] === "em_manutencao"): ?>
                                        <form method="POST" action="manutencoes.php">
                <?= csrf_campo() ?>
                                            <input type="hidden" name="acao" value="concluir">
                                            <input
                                                type="hidden"
                                                name="placa"
                                                value="<?= escaparManutencao($veiculo["placa"]) ?>"
                                            >
                                            <button type="submit">
                                                Concluir manutenção
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span>Sem manutenção ativa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhum veículo cadastrado. Cadastre veículos em Gestão de Ativos.</p>
        <?php endif; ?>
    </section>

    <footer class="painel-footer">
        <p>SENN AI-X — Controle de manutenção</p>
    </footer>

</main>

</body>
</html>
