
<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";

$nivelUsuario = $_SESSION["usuario_nivel"] ?? "";

if (!in_array($nivelUsuario, ["administrador", "gestor"], true)) {
    http_response_code(403);
    exit(
        'Você não tem permissão para cadastrar motoristas. ' .
        '<a href="painel.php">Voltar ao painel</a>'
    );
}

function escaparMotorista($valor): string
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

$mensagem = "";
$tipoMensagem = "";

$nome = "";
$cpf = "";
$cnh = "";
$categoriaCnh = "";
$validadeCnh = "";
$telefone = "";
$status = "disponivel";
$veiculoId = "";

$categoriasCnh = [
    "A" => "A",
    "B" => "B",
    "C" => "C",
    "D" => "D",
    "E" => "E",
    "AB" => "AB",
    "AC" => "AC",
    "AD" => "AD",
    "AE" => "AE"
];

$statusMotorista = [
    "disponivel" => "Disponível",
    "em_rota" => "Em rota",
    "inativo" => "Inativo"
];

try {
    $consulta = $pdo->query(
        "SELECT id, placa, modelo
         FROM veiculos
         ORDER BY placa ASC"
    );

    $veiculos = $consulta->fetchAll();
} catch (PDOException $erro) {
    error_log("Erro ao consultar veículos: " . $erro->getMessage());
    $veiculos = [];
    $mensagem = "Não foi possível carregar os veículos disponíveis.";
    $tipoMensagem = "erro";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $cpf = preg_replace('/\D/', '', $_POST["cpf"] ?? "");
    $cnh = trim($_POST["cnh"] ?? "");
    $categoriaCnh = $_POST["categoria_cnh"] ?? "";
    $validadeCnh = trim($_POST["validade_cnh"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $status = $_POST["status"] ?? "disponivel";
    $veiculoId = trim($_POST["veiculo_id"] ?? "");

    if (
        $nome === "" ||
        $cpf === "" ||
        $cnh === "" ||
        $categoriaCnh === "" ||
        $validadeCnh === "" ||
        $telefone === ""
    ) {
        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipoMensagem = "erro";
    } elseif (strlen($nome) > 100) {
        $mensagem = "O nome deve ter no máximo 100 caracteres.";
        $tipoMensagem = "erro";
    } elseif (!preg_match('/^\d{11}$/', $cpf)) {
        $mensagem = "O CPF deve conter 11 números.";
        $tipoMensagem = "erro";
    } elseif (strlen($cnh) > 20) {
        $mensagem = "Confira o número da CNH informado.";
        $tipoMensagem = "erro";
    } elseif (!isset($categoriasCnh[$categoriaCnh])) {
        $mensagem = "Selecione uma categoria de CNH válida.";
        $tipoMensagem = "erro";
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validadeCnh) ||
        !checkdate(
            (int) substr($validadeCnh, 5, 2),
            (int) substr($validadeCnh, 8, 2),
            (int) substr($validadeCnh, 0, 4)
        )
    ) {
        $mensagem = "Informe uma data de validade da CNH válida.";
        $tipoMensagem = "erro";
    } elseif (strlen($telefone) > 20) {
        $mensagem = "O telefone deve ter no máximo 20 caracteres.";
        $tipoMensagem = "erro";
    } elseif (!isset($statusMotorista[$status])) {
        $mensagem = "Selecione uma disponibilidade válida.";
        $tipoMensagem = "erro";
    } else {
        try {
            $veiculoSelecionado = null;

            if ($veiculoId !== "") {
                if (
                    filter_var($veiculoId, FILTER_VALIDATE_INT) === false ||
                    (int) $veiculoId <= 0
                ) {
                    $mensagem = "Selecione um veículo válido.";
                    $tipoMensagem = "erro";
                } else {
                    $consulta = $pdo->prepare(
                        "SELECT id
                         FROM veiculos
                         WHERE id = :id
                         LIMIT 1"
                    );

                    $consulta->execute([
                        ":id" => (int) $veiculoId
                    ]);

                    $veiculoSelecionado = $consulta->fetch();

                    if (!$veiculoSelecionado) {
                        $mensagem = "O veículo selecionado não foi encontrado.";
                        $tipoMensagem = "erro";
                    }
                }
            }

            if ($mensagem === "") {
                $consulta = $pdo->prepare(
                    "SELECT id
                     FROM motoristas
                     WHERE cpf = :cpf OR cnh = :cnh
                     LIMIT 1"
                );

                $consulta->execute([
                    ":cpf" => $cpf,
                    ":cnh" => $cnh
                ]);

                if ($consulta->fetch()) {
                    $mensagem = "Já existe um motorista com esse CPF ou número de CNH.";
                    $tipoMensagem = "erro";
                } else {
                    $sql = "INSERT INTO motoristas (
                                nome,
                                cpf,
                                cnh,
                                categoria_cnh,
                                validade_cnh,
                                telefone,
                                status,
                                veiculo_id
                            ) VALUES (
                                :nome,
                                :cpf,
                                :cnh,
                                :categoria_cnh,
                                :validade_cnh,
                                :telefone,
                                :status,
                                :veiculo_id
                            )";

                    $comando = $pdo->prepare($sql);

                    $comando->execute([
                        ":nome" => $nome,
                        ":cpf" => $cpf,
                        ":cnh" => $cnh,
                        ":categoria_cnh" => $categoriaCnh,
                        ":validade_cnh" => $validadeCnh,
                        ":telefone" => $telefone,
                        ":status" => $status,
                        ":veiculo_id" => $veiculoId !== ""
                            ? (int) $veiculoId
                            : null
                    ]);

                    $mensagem = "Motorista cadastrado com sucesso!";
                    $tipoMensagem = "sucesso";

                    $nome = "";
                    $cpf = "";
                    $cnh = "";
                    $categoriaCnh = "";
                    $validadeCnh = "";
                    $telefone = "";
                    $status = "disponivel";
                    $veiculoId = "";
                }
            }
        } catch (PDOException $erro) {
            error_log("Erro ao cadastrar motorista: " . $erro->getMessage());
            $mensagem = "Não foi possível cadastrar o motorista.";
            $tipoMensagem = "erro";
        }
    }
}

try {
    $consulta = $pdo->query(
        "SELECT
            m.id,
            m.nome,
            m.cpf,
            m.cnh,
            m.categoria_cnh,
            m.validade_cnh,
            m.telefone,
            m.status,
            m.veiculo_id,
            v.placa,
            v.modelo
         FROM motoristas m
         LEFT JOIN veiculos v ON v.id = m.veiculo_id
         ORDER BY m.nome ASC"
    );

    $motoristas = $consulta->fetchAll();
} catch (PDOException $erro) {
    error_log("Erro ao consultar motoristas: " . $erro->getMessage());
    $motoristas = [];
    $mensagem = "Não foi possível carregar a lista de motoristas.";
    $tipoMensagem = "erro";
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Motoristas | SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="painel-container">

    <header class="painel-header">
        <div>
            <p class="painel-identificacao">SENN AI-X</p>
            <h1>Cadastro de Motoristas</h1>
            <p class="painel-subtitulo">
                Gerencie os motoristas e seus vínculos com a frota.
            </p>
        </div>

        <a class="painel-sair" href="painel.php">Voltar ao painel</a>
    </header>

    <?php if ($mensagem !== ""): ?>
        <p class="<?= escaparMotorista($tipoMensagem) ?>" role="alert">
            <?= escaparMotorista($mensagem) ?>
        </p>
    <?php endif; ?>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Novo motorista</h2>
            <p>Preencha os dados pessoais, a CNH e a disponibilidade.</p>
        </div>

        <form method="POST" action="motoristas.php" class="formulario-cadastro">
                <?= csrf_campo() ?>
            <div class="form-grid">
                <p>
                    <label for="nome">Nome completo *</label>
                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="100"
                        value="<?= escaparMotorista($nome) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="cpf">CPF *</label>
                    <input
                        type="text"
                        id="cpf"
                        name="cpf"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        value="<?= escaparMotorista($cpf) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="cnh">Número da CNH *</label>
                    <input
                        type="text"
                        id="cnh"
                        name="cnh"
                        maxlength="20"
                        value="<?= escaparMotorista($cnh) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="categoria_cnh">Categoria da CNH *</label>
                    <select id="categoria_cnh" name="categoria_cnh" required>
                        <option value="">Selecione a categoria</option>

                        <?php foreach ($categoriasCnh as $valor => $rotulo): ?>
                            <option
                                value="<?= escaparMotorista($valor) ?>"
                                <?= $categoriaCnh === $valor ? "selected" : "" ?>
                            >
                                <?= escaparMotorista($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="validade_cnh">Validade da CNH *</label>
                    <input
                        type="date"
                        id="validade_cnh"
                        name="validade_cnh"
                        value="<?= escaparMotorista($validadeCnh) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="telefone">Telefone de contato *</label>
                    <input
                        type="tel"
                        id="telefone"
                        name="telefone"
                        maxlength="20"
                        placeholder="(00) 00000-0000"
                        value="<?= escaparMotorista($telefone) ?>"
                        required
                    >
                </p>

                <p>
                    <label for="status">Disponibilidade *</label>
                    <select id="status" name="status" required>
                        <?php foreach ($statusMotorista as $valor => $rotulo): ?>
                            <option
                                value="<?= escaparMotorista($valor) ?>"
                                <?= $status === $valor ? "selected" : "" ?>
                            >
                                <?= escaparMotorista($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="veiculo_id">Veículo vinculado</label>
                    <select id="veiculo_id" name="veiculo_id">
                        <option value="">Nenhum veículo vinculado</option>

                        <?php foreach ($veiculos as $veiculo): ?>
                            <option
                                value="<?= escaparMotorista($veiculo["id"]) ?>"
                                <?= (string) $veiculoId === (string) $veiculo["id"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= escaparMotorista($veiculo["placa"]) ?>
                                — <?= escaparMotorista($veiculo["modelo"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>

            <button type="submit">Cadastrar motorista</button>
        </form>
    </section>

    <section class="painel-secao">
        <div class="painel-secao-titulo">
            <h2>Motoristas cadastrados</h2>
            <p>Total de registros: <?= count($motoristas) ?></p>
        </div>

        <?php if (count($motoristas) > 0): ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Categoria CNH</th>
                            <th>Validade CNH</th>
                            <th>Telefone</th>
                            <th>Disponibilidade</th>
                            <th>Veículo</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($motoristas as $motorista): ?>
                            <tr>
                                <td><?= escaparMotorista($motorista["nome"]) ?></td>
                                <td><?= escaparMotorista($motorista["cpf"]) ?></td>
                                <td><?= escaparMotorista($motorista["categoria_cnh"]) ?></td>
                                <td>
                                    <?= $motorista["validade_cnh"]
                                        ? escaparMotorista(
                                            date(
                                                "d/m/Y",
                                                strtotime($motorista["validade_cnh"])
                                            )
                                        )
                                        : "Não informada" ?>
                                </td>
                                <td><?= escaparMotorista($motorista["telefone"]) ?></td>
                                <td>
                                    <?= escaparMotorista(
                                        $statusMotorista[$motorista["status"]]
                                        ?? $motorista["status"]
                                    ) ?>
                                </td>
                                <td>
                                    <?= $motorista["placa"]
                                        ? escaparMotorista(
                                            $motorista["placa"] . " — " . $motorista["modelo"]
                                        )
                                        : "Não vinculado" ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhum motorista cadastrado até o momento.</p>
        <?php endif; ?>
    </section>

    <footer class="painel-footer">
        <p>SENN AI-X — Gestão de Motoristas</p>
    </footer>

</main>

</body>
</html>
