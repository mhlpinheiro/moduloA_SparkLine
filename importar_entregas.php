
<?php

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";

$perfisPermitidos = ["administrador", "gestor", "despachante"];

if (!in_array($_SESSION["usuario_nivel"] ?? "", $perfisPermitidos, true)) {
    http_response_code(403);
    exit("Acesso negado. Você não tem permissão para importar entregas.");
}

$mensagem = "";
$tipoMensagem = "";
$resultados = [];
$importados = 0;
$ignorados = 0;

function escaparImportacao($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function converterDataCsv(string $valor): ?string
{
    $valor = trim($valor);

    $formatos = [
        "Y-m-d H:i:s",
        "Y-m-d H:i",
        "Y-m-d\TH:i:s",
        "Y-m-d\TH:i",
        "d/m/Y H:i:s",
        "d/m/Y H:i"
    ];

    foreach ($formatos as $formato) {
        $data = DateTime::createFromFormat("!" . $formato, $valor);
        $erros = DateTime::getLastErrors();

        if (
            $data !== false &&
            ($erros === false || (
                $erros["warning_count"] === 0 &&
                $erros["error_count"] === 0
            )) &&
            $data->format($formato) === $valor
        ) {
            return $data->format("Y-m-d H:i:s");
        }
    }

    return null;
}

function detectarSeparadorCsv(string $arquivo): string
{
    $handle = fopen($arquivo, "r");

    if ($handle === false) {
        return ";";
    }

    $primeiraLinha = fgets($handle);
    fclose($handle);

    if ($primeiraLinha === false) {
        return ";";
    }

    $primeiraLinha = preg_replace('/^\xEF\xBB\xBF/', '', $primeiraLinha);

    $pontoEVirgula = substr_count($primeiraLinha, ";");
    $virgula = substr_count($primeiraLinha, ",");

    return $virgula > $pontoEVirgula ? "," : ";";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_FILES["arquivo_csv"]) ||
        !is_array($_FILES["arquivo_csv"]) ||
        $_FILES["arquivo_csv"]["error"] !== UPLOAD_ERR_OK
    ) {
        $mensagem = "Selecione um arquivo CSV válido.";
        $tipoMensagem = "erro";
    } else {
        $arquivo = $_FILES["arquivo_csv"];

        if ($arquivo["size"] <= 0 || $arquivo["size"] > 5 * 1024 * 1024) {
            $mensagem = "O arquivo deve ter conteúdo e no máximo 5 MB.";
            $tipoMensagem = "erro";
        } elseif (
            strtolower(pathinfo($arquivo["name"], PATHINFO_EXTENSION)) !== "csv"
        ) {
            $mensagem = "Formato inválido. Envie um arquivo com extensão .csv.";
            $tipoMensagem = "erro";
        } else {
            $separador = detectarSeparadorCsv($arquivo["tmp_name"]);
            $handle = fopen($arquivo["tmp_name"], "r");

            if ($handle === false) {
                $mensagem = "Não foi possível ler o arquivo enviado.";
                $tipoMensagem = "erro";
            } else {
                $cabecalho = fgetcsv($handle, 0, $separador);

                if ($cabecalho !== false && isset($cabecalho[0])) {
                    $cabecalho[0] = preg_replace(
                        '/^\xEF\xBB\xBF/',
                        '',
                        (string) $cabecalho[0]
                    );

                    $cabecalho = array_map(
                        static fn($coluna) => strtolower(trim((string) $coluna)),
                        $cabecalho
                    );
                }

                $colunasObrigatorias = [
                    "codigo_pedido",
                    "cliente_nome",
                    "endereco",
                    "janela_inicio",
                    "janela_fim",
                    "peso_kg",
                    "volume_m3",
                    "prioridade"
                ];

                if (
                    $cabecalho === false ||
                    count(array_unique($cabecalho)) !== count($cabecalho) ||
                    count(array_diff($colunasObrigatorias, $cabecalho)) > 0
                ) {
                    $mensagem = "Cabeçalho inválido ou com colunas repetidas. Confira o modelo apresentado na página.";
                    $tipoMensagem = "erro";
                } else {
                    $indices = array_flip($cabecalho);
                    $linha = 1;
                    $codigosArquivo = [];
                    $linhasValidas = [];
                    $prioridadesValidas = ["baixa", "media", "alta", "urgente"];

                    while (($dados = fgetcsv($handle, 0, $separador)) !== false) {
                        $linha++;

                        if (
                            count($dados) === 1 &&
                            trim((string) $dados[0]) === ""
                        ) {
                            continue;
                        }

                        $registro = [];

                        foreach ($colunasObrigatorias as $coluna) {
                            $registro[$coluna] = trim(
                                (string) ($dados[$indices[$coluna]] ?? "")
                            );
                        }

                        $codigo = $registro["codigo_pedido"];
                        $cliente = $registro["cliente_nome"];
                        $endereco = $registro["endereco"];
                        $inicio = converterDataCsv($registro["janela_inicio"]);
                        $fim = converterDataCsv($registro["janela_fim"]);

                        $pesoTexto = str_replace(
                            ",",
                            ".",
                            $registro["peso_kg"]
                        );

                        $volumeTexto = str_replace(
                            ",",
                            ".",
                            $registro["volume_m3"]
                        );

                        $peso = filter_var($pesoTexto, FILTER_VALIDATE_FLOAT);
                        $volume = filter_var($volumeTexto, FILTER_VALIDATE_FLOAT);
                        $prioridade = strtolower($registro["prioridade"]);

                        $erros = [];

                        if ($codigo === "") {
                            $erros[] = "código do pedido vazio";
                        }

                        if ($cliente === "") {
                            $erros[] = "nome do cliente vazio";
                        }

                        if ($endereco === "") {
                            $erros[] = "endereço vazio";
                        }

                        if ($inicio === null || $fim === null) {
                            $erros[] = "data ou horário inválido";
                        } elseif (strtotime($fim) <= strtotime($inicio)) {
                            $erros[] = "a janela final deve ser posterior à inicial";
                        }

                        if ($peso === false || $peso <= 0) {
                            $erros[] = "peso inválido";
                        }

                        if ($volume === false || $volume <= 0) {
                            $erros[] = "volume inválido";
                        }

                        if (!in_array($prioridade, $prioridadesValidas, true)) {
                            $erros[] = "prioridade inválida";
                        }

                        if ($codigo !== "") {
                            if (isset($codigosArquivo[$codigo])) {
                                $erros[] = "código repetido no próprio arquivo";
                            } else {
                                $codigosArquivo[$codigo] = true;
                            }
                        }

                        if ($erros) {
                            $ignorados++;

                            if (count($resultados) < 50) {
                                $resultados[] = [
                                    "linha" => $linha,
                                    "codigo" => $codigo,
                                    "motivo" => implode(", ", $erros)
                                ];
                            }

                            continue;
                        }

                        $linhasValidas[] = [
                            "linha" => $linha,
                            "codigo" => $codigo,
                            "cliente" => $cliente,
                            "endereco" => $endereco,
                            "inicio" => $inicio,
                            "fim" => $fim,
                            "peso" => $peso,
                            "volume" => $volume,
                            "prioridade" => $prioridade
                        ];
                    }

                    fclose($handle);
                    $handle = false;

                    try {
                        $pdo->beginTransaction();

                        $sqlDuplicado = $pdo->prepare(
                            "SELECT id
                             FROM entregas
                             WHERE codigo_pedido = :codigo
                             LIMIT 1"
                        );

                        $sqlInserir = $pdo->prepare(
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

                        foreach ($linhasValidas as $registro) {
                            $sqlDuplicado->execute([
                                ":codigo" => $registro["codigo"]
                            ]);

                            if ($sqlDuplicado->fetch(PDO::FETCH_ASSOC)) {
                                $ignorados++;

                                if (count($resultados) < 50) {
                                    $resultados[] = [
                                        "linha" => $registro["linha"],
                                        "codigo" => $registro["codigo"],
                                        "motivo" => "código de pedido já cadastrado no banco"
                                    ];
                                }

                                continue;
                            }

                            $sqlInserir->execute([
                                ":codigo" => $registro["codigo"],
                                ":cliente" => $registro["cliente"],
                                ":endereco" => $registro["endereco"],
                                ":inicio" => $registro["inicio"],
                                ":fim" => $registro["fim"],
                                ":peso" => $registro["peso"],
                                ":volume" => $registro["volume"],
                                ":prioridade" => $registro["prioridade"]
                            ]);

                            $importados++;
                        }

                        $pdo->commit();

                        $mensagem = "Importação concluída: "
                            . $importados
                            . " entrega(s) importada(s) e "
                            . $ignorados
                            . " linha(s) ignorada(s).";

                        $tipoMensagem = $ignorados > 0 ? "aviso" : "sucesso";
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        error_log(
                            "Erro na importação CSV: " . $e->getMessage()
                        );

                        $importados = 0;
                        $mensagem = "A importação foi cancelada por um erro. Nenhuma das alterações desta operação foi mantida.";
                        $tipoMensagem = "erro";
                    }
                }

                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Entregas - SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="painel-container">
        <header class="painel-header">
            <div>
                <h1>Importação de Entregas</h1>
                <p>Cadastre vários pedidos utilizando um arquivo CSV.</p>
            </div>

            <a href="movimentacoes.php" class="btn">Voltar às entregas</a>
        </header>

        <section class="painel-secao">
            <h2>Enviar arquivo CSV</h2>

            <p>
                Envie um arquivo CSV de até 5 MB. O sistema identifica
                automaticamente o separador entre vírgula e ponto e vírgula.
            </p>

            <?php if ($mensagem !== ""): ?>
                <div class="mensagem-<?= escaparImportacao($tipoMensagem) ?>">
                    <?= escaparImportacao($mensagem) ?>
                </div>
            <?php endif; ?>

            <div class="formulario-cadastro">
                <form method="POST" enctype="multipart/form-data">
                <?= csrf_campo() ?>
                    <div class="form-grid">
                        <div>
                            <label for="arquivo_csv">Arquivo CSV</label>
                            <input
                                type="file"
                                id="arquivo_csv"
                                name="arquivo_csv"
                                accept=".csv,text/csv"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        Importar entregas
                    </button>
                </form>
            </div>

            <h3>Colunas obrigatórias</h3>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Coluna</th>
                            <th>Descrição</th>
                            <th>Exemplo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>codigo_pedido</td>
                            <td>Código único do pedido</td>
                            <td>PED-001</td>
                        </tr>
                        <tr>
                            <td>cliente_nome</td>
                            <td>Nome do cliente</td>
                            <td>João da Silva</td>
                        </tr>
                        <tr>
                            <td>endereco</td>
                            <td>Endereço de entrega</td>
                            <td>Rua Central, 100</td>
                        </tr>
                        <tr>
                            <td>janela_inicio</td>
                            <td>Início da janela de entrega</td>
                            <td>2026-11-10 08:00</td>
                        </tr>
                        <tr>
                            <td>janela_fim</td>
                            <td>Fim da janela de entrega</td>
                            <td>2026-11-10 12:00</td>
                        </tr>
                        <tr>
                            <td>peso_kg</td>
                            <td>Peso em quilogramas</td>
                            <td>25.5</td>
                        </tr>
                        <tr>
                            <td>volume_m3</td>
                            <td>Volume em metros cúbicos</td>
                            <td>1.2</td>
                        </tr>
                        <tr>
                            <td>prioridade</td>
                            <td>baixa, media, alta ou urgente</td>
                            <td>alta</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p>
                Datas aceitas: <strong>AAAA-MM-DD HH:MM</strong>,
                <strong>AAAA-MM-DDTHH:MM</strong> ou
                <strong>DD/MM/AAAA HH:MM</strong>.
                Use ponto ou vírgula para valores decimais.
            </p>

            <?php if ($resultados): ?>
                <h3>Linhas que não foram importadas</h3>

                <div class="tabela-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th>Linha</th>
                                <th>Pedido</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $resultado): ?>
                                <tr>
                                    <td><?= escaparImportacao($resultado["linha"]) ?></td>
                                    <td><?= escaparImportacao($resultado["codigo"]) ?></td>
                                    <td><?= escaparImportacao($resultado["motivo"]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($ignorados > count($resultados)): ?>
                    <p>
                        Existem outros erros que não foram exibidos.
                        Foram listados no máximo 50 registros.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
