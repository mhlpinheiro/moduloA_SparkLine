
<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";

$perfil = $_SESSION["usuario_nivel"] ?? "";
$usuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$usuarioNome = trim($_SESSION["usuario_nome"] ?? "");

$perfisCadastro = ["administrador", "gestor", "despachante"];
$perfisAtualizacao = ["administrador", "gestor", "despachante", "motorista"];

$podeCadastrar = in_array($perfil, $perfisCadastro, true);
$podeAtualizar = in_array($perfil, $perfisAtualizacao, true);

$mensagem = "";
$tipoMensagem = "";
$entregas = [];

function escaparMovimentacao($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function converterDataEntrega(string $data): ?string
{
    $data = trim($data);

    $formato = DateTime::createFromFormat("Y-m-d\TH:i", $data);

    if (!$formato || $formato->format("Y-m-d\TH:i") !== $data) {
        return null;
    }

    return $formato->format("Y-m-d H:i:s");
}

try {
    /*
     * Localiza o registro de motorista associado à conta.
     *
     * Como o esquema atual não possui uma coluna que vincule
     * usuarios.id a motoristas.id, é utilizado o nome da sessão
     * como correspondência provisória.
     */
    $motoristaId = null;

    if ($perfil === "motorista") {
        if ($usuarioNome !== "") {
            $stmtMotorista = $pdo->prepare(
                "SELECT id
                 FROM motoristas
                 WHERE nome = :nome
                 LIMIT 1"
            );

            $stmtMotorista->execute([
                ":nome" => $usuarioNome
            ]);

            $registroMotorista = $stmtMotorista->fetch(PDO::FETCH_ASSOC);

            if ($registroMotorista) {
                $motoristaId = (int) $registroMotorista["id"];
            }
        }
    }

    /*
     * Cadastro manual de entrega.
     */
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["cadastrar_entrega"])
    ) {
        if (!$podeCadastrar) {
            http_response_code(403);
            exit("Você não tem permissão para cadastrar entregas.");
        }

        $codigo = trim($_POST["codigo_pedido"] ?? "");
        $cliente = trim($_POST["cliente_nome"] ?? "");
        $endereco = trim($_POST["endereco"] ?? "");
        $inicioInformado = trim($_POST["janela_inicio"] ?? "");
        $fimInformado = trim($_POST["janela_fim"] ?? "");
        $pesoTexto = str_replace(",", ".", trim($_POST["peso_kg"] ?? ""));
        $volumeTexto = str_replace(",", ".", trim($_POST["volume_m3"] ?? ""));
        $prioridade = strtolower(trim($_POST["prioridade"] ?? ""));

        $inicio = converterDataEntrega($inicioInformado);
        $fim = converterDataEntrega($fimInformado);

        $peso = filter_var($pesoTexto, FILTER_VALIDATE_FLOAT);
        $volume = filter_var($volumeTexto, FILTER_VALIDATE_FLOAT);

        $prioridadesValidas = ["baixa", "media", "alta", "urgente"];

        if (
            $codigo === "" ||
            $cliente === "" ||
            $endereco === "" ||
            $inicio === null ||
            $fim === null ||
            $peso === false ||
            $volume === false ||
            $peso <= 0 ||
            $volume <= 0 ||
            !in_array($prioridade, $prioridadesValidas, true)
        ) {
            $mensagem = "Preencha todos os campos corretamente.";
            $tipoMensagem = "erro";
        } elseif (strtotime($fim) <= strtotime($inicio)) {
            $mensagem = "O fim da janela de entrega deve ser posterior ao início.";
            $tipoMensagem = "erro";
        } else {
            $stmtDuplicado = $pdo->prepare(
                "SELECT id
                 FROM entregas
                 WHERE codigo_pedido = :codigo
                 LIMIT 1"
            );

            $stmtDuplicado->execute([
                ":codigo" => $codigo
            ]);

            if ($stmtDuplicado->fetch()) {
                $mensagem = "Já existe uma entrega cadastrada com esse código.";
                $tipoMensagem = "erro";
            } else {
                $stmtCadastro = $pdo->prepare(
                    "INSERT INTO entregas (
                        codigo_pedido,
                        cliente_nome,
                        endereco,
                        janela_inicio,
                        janela_fim,
                        peso_kg,
                        volume_m3,
                        prioridade,
                        status
                    ) VALUES (
                        :codigo,
                        :cliente,
                        :endereco,
                        :inicio,
                        :fim,
                        :peso,
                        :volume,
                        :prioridade,
                        'pendente'
                    )"
                );

                $stmtCadastro->execute([
                    ":codigo" => $codigo,
                    ":cliente" => $cliente,
                    ":endereco" => $endereco,
                    ":inicio" => $inicio,
                    ":fim" => $fim,
                    ":peso" => $peso,
                    ":volume" => $volume,
                    ":prioridade" => $prioridade
                ]);

                $mensagem = "Entrega cadastrada com sucesso!";
                $tipoMensagem = "sucesso";
            }
        }
    }

    /*
     * Atualização de status.
     */
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["atualizar_status"])
    ) {
        if (!$podeAtualizar) {
            http_response_code(403);
            exit("Você não tem permissão para atualizar entregas.");
        }

        $entregaId = filter_var(
            $_POST["entrega_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        $novoStatus = trim($_POST["novo_status"] ?? "");

        $statusValidos = ["pendente", "em_rota", "entregue", "falha"];

        if (
            $entregaId === false ||
            $entregaId === null ||
            $entregaId <= 0 ||
            !in_array($novoStatus, $statusValidos, true)
        ) {
            $mensagem = "Os dados informados para atualização são inválidos.";
            $tipoMensagem = "erro";
        } elseif ($perfil === "motorista") {
            if ($motoristaId === null) {
                $mensagem = "Sua conta ainda não está vinculada a um cadastro de motorista. Solicite o ajuste ao administrador.";
                $tipoMensagem = "erro";
            } else {
                $stmtAtualizacao = $pdo->prepare(
                    "UPDATE entregas
                     SET status = :status
                     WHERE id = :id
                       AND motorista_id = :motorista_id"
                );

                $stmtAtualizacao->execute([
                    ":status" => $novoStatus,
                    ":id" => $entregaId,
                    ":motorista_id" => $motoristaId
                ]);

                if ($stmtAtualizacao->rowCount() > 0) {
                    $mensagem = "Status da entrega atualizado com sucesso!";
                    $tipoMensagem = "sucesso";
                } else {
                    $mensagem = "A entrega não foi encontrada, não está atribuída a você ou já possui esse status.";
                    $tipoMensagem = "erro";
                }
            }
        } else {
            $stmtAtualizacao = $pdo->prepare(
                "UPDATE entregas
                 SET status = :status
                 WHERE id = :id"
            );

            $stmtAtualizacao->execute([
                ":status" => $novoStatus,
                ":id" => $entregaId
            ]);

            if ($stmtAtualizacao->rowCount() > 0) {
                $mensagem = "Status da entrega atualizado com sucesso!";
                $tipoMensagem = "sucesso";
            } else {
                $stmtExiste = $pdo->prepare(
                    "SELECT id FROM entregas WHERE id = :id LIMIT 1"
                );

                $stmtExiste->execute([
                    ":id" => $entregaId
                ]);

                if ($stmtExiste->fetch()) {
                    $mensagem = "A entrega já estava com esse status.";
                    $tipoMensagem = "sucesso";
                } else {
                    $mensagem = "Entrega não encontrada.";
                    $tipoMensagem = "erro";
                }
            }
        }
    }

    /*
     * Listagem de entregas.
     */
    if ($perfil === "motorista") {
        if ($motoristaId !== null) {
            $stmtEntregas = $pdo->prepare(
                "SELECT e.*, m.nome AS motorista_nome, v.placa
                 FROM entregas e
                 LEFT JOIN motoristas m ON m.id = e.motorista_id
                 LEFT JOIN veiculos v ON v.id = e.veiculo_id
                 WHERE e.motorista_id = :motorista_id
                 ORDER BY e.id DESC"
            );

            $stmtEntregas->execute([
                ":motorista_id" => $motoristaId
            ]);

            $entregas = $stmtEntregas->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $stmtEntregas = $pdo->query(
            "SELECT e.*, m.nome AS motorista_nome, v.placa
             FROM entregas e
             LEFT JOIN motoristas m ON m.id = e.motorista_id
             LEFT JOIN veiculos v ON v.id = e.veiculo_id
             ORDER BY e.id DESC"
        );

        $entregas = $stmtEntregas->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erro em movimentacoes.php: " . $e->getMessage());

    $mensagem = "Não foi possível concluir a operação. Verifique a configuração do banco de dados.";
    $tipoMensagem = "erro";
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentações - SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="painel-container">
        <header class="painel-header">
            <div>
                <h1>Movimentações e Entregas</h1>
                <p>Gerencie os pedidos e acompanhe o status das entregas.</p>
            </div>

            <a href="painel.php" class="btn">Voltar ao painel</a>
        </header>

        <?php if ($mensagem !== ""): ?>
            <div class="mensagem-<?= escaparMovimentacao($tipoMensagem) ?>">
                <?= escaparMovimentacao($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if ($podeCadastrar): ?>
            <section class="painel-secao">
                <h2>Cadastrar nova entrega</h2>

                <div class="formulario-cadastro">
                    <form method="POST">
                <?= csrf_campo() ?>
                        <input type="hidden" name="cadastrar_entrega" value="1">

                        <div class="form-grid">
                            <div>
                                <label for="codigo_pedido">Código do pedido</label>
                                <input
                                    type="text"
                                    id="codigo_pedido"
                                    name="codigo_pedido"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <div>
                                <label for="cliente_nome">Nome do cliente</label>
                                <input
                                    type="text"
                                    id="cliente_nome"
                                    name="cliente_nome"
                                    maxlength="150"
                                    required
                                >
                            </div>

                            <div>
                                <label for="endereco">Endereço de entrega</label>
                                <input
                                    type="text"
                                    id="endereco"
                                    name="endereco"
                                    maxlength="255"
                                    required
                                >
                            </div>

                            <div>
                                <label for="janela_inicio">Início da janela</label>
                                <input
                                    type="datetime-local"
                                    id="janela_inicio"
                                    name="janela_inicio"
                                    required
                                >
                            </div>

                            <div>
                                <label for="janela_fim">Fim da janela</label>
                                <input
                                    type="datetime-local"
                                    id="janela_fim"
                                    name="janela_fim"
                                    required
                                >
                            </div>

                            <div>
                                <label for="peso_kg">Peso (kg)</label>
                                <input
                                    type="number"
                                    id="peso_kg"
                                    name="peso_kg"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >
                            </div>

                            <div>
                                <label for="volume_m3">Volume (m³)</label>
                                <input
                                    type="number"
                                    id="volume_m3"
                                    name="volume_m3"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >
                            </div>

                            <div>
                                <label for="prioridade">Prioridade</label>
                                <select id="prioridade" name="prioridade" required>
                                    <option value="baixa">Baixa</option>
                                    <option value="media" selected>Média</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn">
                            Cadastrar entrega
                        </button>
                    </form>
                </div>

                <p>
                    Precisa cadastrar vários pedidos de uma vez?
                    <a href="importar_entregas.php">Importar entregas por CSV</a>.
                </p>
            </section>
        <?php endif; ?>

        <section class="painel-secao">
            <h2>Entregas cadastradas</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Endereço</th>
                            <th>Janela de entrega</th>
                            <th>Peso</th>
                            <th>Volume</th>
                            <th>Prioridade</th>
                            <th>Motorista</th>
                            <th>Veículo</th>
                            <th>Status</th>
                            <?php if ($podeAtualizar): ?>
                                <th>Ação</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($entregas) > 0): ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td><?= escaparMovimentacao($entrega["codigo_pedido"]) ?></td>
                                    <td><?= escaparMovimentacao($entrega["cliente_nome"]) ?></td>
                                    <td><?= escaparMovimentacao($entrega["endereco"]) ?></td>
                                    <td>
                                        <?= escaparMovimentacao(date("d/m/Y H:i", strtotime($entrega["janela_inicio"]))) ?>
                                        –
                                        <?= escaparMovimentacao(date("d/m/Y H:i", strtotime($entrega["janela_fim"]))) ?>
                                    </td>
                                    <td><?= escaparMovimentacao($entrega["peso_kg"]) ?> kg</td>
                                    <td><?= escaparMovimentacao($entrega["volume_m3"]) ?> m³</td>
                                    <td><?= escaparMovimentacao(ucfirst($entrega["prioridade"])) ?></td>
                                    <td>
                                        <?= escaparMovimentacao($entrega["motorista_nome"] ?? "Não atribuído") ?>
                                    </td>
                                    <td>
                                        <?= escaparMovimentacao($entrega["placa"] ?? "Não atribuído") ?>
                                    </td>
                                    <td>
                                        <?= escaparMovimentacao(ucfirst(str_replace("_", " ", $entrega["status"]))) ?>
                                    </td>

                                    <?php if ($podeAtualizar): ?>
                                        <td>
                                            <?php if ($entrega["status"] !== "entregue" && $entrega["status"] !== "falha"): ?>
                                                <form method="POST">
                <?= csrf_campo() ?>
                                                    <input
                                                        type="hidden"
                                                        name="atualizar_status"
                                                        value="1"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="entrega_id"
                                                        value="<?= (int) $entrega["id"] ?>"
                                                    >

                                                    <select name="novo_status" required>
                                                        <option
                                                            value="pendente"
                                                            <?= $entrega["status"] === "pendente" ? "selected" : "" ?>
                                                        >
                                                            Pendente
                                                        </option>

                                                        <option
                                                            value="em_rota"
                                                            <?= $entrega["status"] === "em_rota" ? "selected" : "" ?>
                                                        >
                                                            Em rota
                                                        </option>

                                                        <option value="entregue">
                                                            Entregue
                                                        </option>

                                                        <option value="falha">
                                                            Falha
                                                        </option>
                                                    </select>

                                                    <button type="submit" class="btn">
                                                        Atualizar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span>Finalizada</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td
                                    colspan="<?= $podeAtualizar ? 11 : 10 ?>"
                                >
                                    Nenhuma entrega encontrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
