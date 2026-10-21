<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/seguranca.php";

if (!in_array($_SESSION["usuario_nivel"] ?? "", ["administrador", "gestor"], true)) {
    http_response_code(403);
    exit('Acesso negado: o dashboard é restrito a administradores e gestores. <a href="painel.php">Voltar</a>');
}

// Região = cidade extraída do endereço ("Rua X, 10, Taubaté - SP" => "Taubaté").
const REGIAO_SQL = "TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(e.endereco, ',', -1), ' - ', 1))";

function dataValida(?string $v, string $padrao): string
{
    if (
        $v !== null &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) &&
        checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4))
    ) {
        return $v;
    }
    return $padrao;
}

function consultar(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function csvSeguro($valor): string
{
    $texto = (string) ($valor ?? "");
    // Evita injeção de fórmulas ao abrir no Excel.
    if ($texto !== "" && in_array($texto[0], ["=", "+", "-", "@"], true)) {
        $texto = "'" . $texto;
    }
    return $texto;
}

$inicio = dataValida($_GET["inicio"] ?? null, date("Y-m-d", strtotime("-30 days")));
$fim = dataValida($_GET["fim"] ?? null, date("Y-m-d"));
if ($inicio > $fim) {
    [$inicio, $fim] = [$fim, $inicio];
}
$regiao = mb_substr(trim($_GET["regiao"] ?? ""), 0, 60);

$whereBase = "e.data_criacao >= :ini AND e.data_criacao < DATE_ADD(:fim, INTERVAL 1 DAY)";
$paramsBase = [":ini" => $inicio, ":fim" => $fim];

$where = $whereBase;
$params = $paramsBase;
if ($regiao !== "") {
    $where .= " AND " . REGIAO_SQL . " = :reg";
    $params[":reg"] = $regiao;
}

$erro = "";
$porStatus = [];
$porRegiao = [];
$regioesLista = [];
$otd = null;
$entreguesComPod = 0;
$kmTotal = 0.0;
$custoTotal = 0.0;
$custoPorEntrega = null;
$frota = [];
$ocupacaoMedia = null;
$recentes = [];

try {
    foreach (consultar($pdo, "SELECT e.status, COUNT(*) AS total FROM entregas e WHERE $where GROUP BY e.status", $params) as $l) {
        $porStatus[$l["status"]] = (int) $l["total"];
    }

    $porRegiao = consultar(
        $pdo,
        "SELECT " . REGIAO_SQL . " AS regiao, COUNT(*) AS total, SUM(e.status = 'entregue') AS entregues
         FROM entregas e WHERE $whereBase GROUP BY regiao ORDER BY total DESC LIMIT 12",
        $paramsBase
    );
    $regioesLista = array_column($porRegiao, "regiao");

    // OTD: entregues cujo comprovante foi registrado até o fim da janela.
    $o = consultar(
        $pdo,
        "SELECT COUNT(*) AS total, SUM(TIME(p.data_hora) <= e.janela_fim) AS no_prazo
         FROM entregas e
         JOIN (SELECT entrega_id, MAX(data_hora) AS data_hora FROM comprovantes_pod GROUP BY entrega_id) p
              ON p.entrega_id = e.id
         WHERE e.status = 'entregue' AND e.janela_fim IS NOT NULL AND $where",
        $params
    )[0];
    $entreguesComPod = (int) $o["total"];
    if ($entreguesComPod > 0) {
        $otd = round(((int) $o["no_prazo"]) / $entreguesComPod * 100, 1);
    }

    $c = consultar(
        $pdo,
        "SELECT COALESCE(SUM(e.distancia_km), 0) AS km, COALESCE(SUM(e.custo_estimado), 0) AS custo,
                COUNT(e.custo_estimado) AS n
         FROM entregas e WHERE $where AND e.status IN ('em_rota', 'entregue')",
        $params
    )[0];
    $kmTotal = (float) $c["km"];
    $custoTotal = (float) $c["custo"];
    if ((int) $c["n"] > 0) {
        $custoPorEntrega = $custoTotal / (int) $c["n"];
    }

    // Ocupação: retrato atual da frota (carga em rota / capacidade).
    $frota = $pdo->query(
        "SELECT v.placa, v.modelo, v.capacidade_kg, COALESCE(SUM(e.peso_kg), 0) AS carga
         FROM veiculos v
         LEFT JOIN entregas e ON e.veiculo_id = v.id AND e.status = 'em_rota'
         GROUP BY v.id, v.placa, v.modelo, v.capacidade_kg
         ORDER BY v.placa"
    )->fetchAll();

    $cargaTotal = 0.0;
    $capTotal = 0.0;
    foreach ($frota as $v) {
        if ((float) $v["carga"] > 0) {
            $cargaTotal += (float) $v["carga"];
            $capTotal += (float) $v["capacidade_kg"];
        }
    }
    if ($capTotal > 0) {
        $ocupacaoMedia = round($cargaTotal / $capTotal * 100, 1);
    }

    $recentes = consultar(
        $pdo,
        "SELECT e.codigo_pedido, e.cliente_nome, e.status, e.prioridade, e.data_criacao
         FROM entregas e WHERE $where ORDER BY e.data_criacao DESC LIMIT 10",
        $params
    );
} catch (PDOException $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $erro = "Não foi possível carregar os indicadores. Verifique se o script SQL de atualização foi executado.";
}

$nomesStatus = ["pendente" => "Pendente", "em_rota" => "Em rota", "entregue" => "Entregue", "falha" => "Falha"];
$totalEntregas = array_sum($porStatus);

// Exportação para Excel (CSV com ';' e BOM, abre direto no Excel).
if (($_GET["exportar"] ?? "") === "csv" && $erro === "") {
    $linhas = consultar(
        $pdo,
        "SELECT e.codigo_pedido, e.cliente_nome, " . REGIAO_SQL . " AS regiao, e.status, e.prioridade,
                e.janela_inicio, e.janela_fim, e.peso_kg, e.volume_m3, e.distancia_km, e.custo_estimado, e.data_criacao
         FROM entregas e WHERE $where ORDER BY e.data_criacao DESC",
        $params
    );

    header("Content-Type: text/csv; charset=UTF-8");
    header('Content-Disposition: attachment; filename="relatorio_entregas_' . date("Ymd_His") . '.csv"');

    $saida = fopen("php://output", "w");
    fwrite($saida, "\xEF\xBB\xBF");
    $csv = fn(array $linha) => fputcsv($saida, array_map("csvSeguro", $linha), ";", "\"", "");

    $csv(["Relatório SENN AI-X", "Período", "$inicio a $fim", "Região", $regiao !== "" ? $regiao : "Todas"]);
    $csv(["Total de entregas", $totalEntregas]);
    $csv(["OTD (%)", $otd !== null ? number_format($otd, 1, ",", "") : "sem dados"]);
    $csv(["Custo por entrega (R$)", $custoPorEntrega !== null ? number_format($custoPorEntrega, 2, ",", "") : "sem dados"]);
    $csv(["Km rodados", number_format($kmTotal, 1, ",", "")]);
    $csv(["Ocupação média da frota (%)", $ocupacaoMedia !== null ? number_format($ocupacaoMedia, 1, ",", "") : "sem dados"]);
    $csv([]);
    $csv(["Pedido", "Cliente", "Região", "Status", "Prioridade", "Janela início", "Janela fim",
          "Peso (kg)", "Volume (m3)", "Km", "Custo (R$)", "Criado em"]);
    foreach ($linhas as $l) {
        $csv(array_values($l));
    }
    fclose($saida);
    exit;
}

$maxRegiao = 1;
foreach ($porRegiao as $r) {
    $maxRegiao = max($maxRegiao, (int) $r["total"]);
}

$jsStatus = json_encode([
    "labels" => array_map(fn($s) => $nomesStatus[$s] ?? ucfirst($s), array_keys($porStatus)),
    "valores" => array_values($porStatus)
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$jsRegiao = json_encode([
    "labels" => array_column($porRegiao, "regiao"),
    "totais" => array_map("intval", array_column($porRegiao, "total")),
    "entregues" => array_map("intval", array_column($porRegiao, "entregues"))
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$qs = http_build_query(["inicio" => $inicio, "fim" => $fim, "regiao" => $regiao, "exportar" => "csv"]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Gerencial | SENN AI-X</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { margin:0; background:#f1f5f9; color:#0f172a; font-family:Arial,sans-serif; }
        .dash { max-width:1200px; margin:auto; padding:24px 16px; }
        .topo { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
        .topo h1 { margin:0 0 4px; }
        .sub { color:#64748b; margin:0; }
        a.link { color:#1d4ed8; }
        .filtros, .painel { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:20px; }
        .filtros form { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
        .filtros label { display:block; font-size:13px; font-weight:bold; margin-bottom:4px; }
        input, select { padding:8px; border:1px solid #94a3b8; border-radius:6px; }
        .btn { padding:9px 14px; border:0; border-radius:8px; background:#2563eb; color:#fff; cursor:pointer; text-decoration:none; font-size:14px; display:inline-block; }
        .btn-sec { background:#475569; }
        .btn:focus-visible, input:focus-visible, select:focus-visible, a:focus-visible { outline:3px solid #f59e0b; outline-offset:2px; }
        .cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; margin-bottom:20px; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; }
        .card p { margin:0 0 8px; color:#64748b; font-size:14px; }
        .card strong { font-size:26px; }
        .colunas { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; }
        .grafico { height:260px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:8px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:14px; }
        .barra { background:#e2e8f0; border-radius:6px; height:12px; }
        .barra span { display:block; height:12px; background:#2563eb; border-radius:6px; }
        .rolagem { overflow-x:auto; }
        .erro { background:#fee2e2; color:#7f1d1d; padding:12px; border-radius:8px; margin-bottom:16px; }
        @media print {
            .nao-imprimir { display:none !important; }
            body { background:#fff; }
            .painel, .card { break-inside:avoid; }
        }
    </style>
</head>
<body>
<main class="dash">

    <div class="topo">
        <div>
            <h1>Dashboard Gerencial</h1>
            <p class="sub">SENN AI-X — período de <?= h(date("d/m/Y", strtotime($inicio))) ?> a <?= h(date("d/m/Y", strtotime($fim))) ?>
                <?= $regiao !== "" ? " — região: " . h($regiao) : "" ?></p>
        </div>
        <a class="link nao-imprimir" href="painel.php">Voltar ao painel</a>
    </div>

    <?php if ($erro !== ""): ?><div class="erro" role="alert"><?= h($erro) ?></div><?php endif; ?>

    <section class="filtros nao-imprimir" aria-label="Filtros">
        <form method="GET" action="dashboard.php">
            <div>
                <label for="inicio">De</label>
                <input type="date" id="inicio" name="inicio" value="<?= h($inicio) ?>">
            </div>
            <div>
                <label for="fim">Até</label>
                <input type="date" id="fim" name="fim" value="<?= h($fim) ?>">
            </div>
            <div>
                <label for="regiao">Região</label>
                <select id="regiao" name="regiao">
                    <option value="">Todas</option>
                    <?php foreach ($regioesLista as $r): ?>
                        <option value="<?= h($r) ?>" <?= $r === $regiao ? "selected" : "" ?>><?= h($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Aplicar filtros</button>
            <a class="btn btn-sec" href="dashboard.php?<?= h($qs) ?>">Exportar Excel (CSV)</a>
            <button type="button" class="btn btn-sec" onclick="window.print()">Exportar PDF</button>
        </form>
    </section>

    <section class="cards" aria-label="Indicadores">
        <div class="card"><p>Total de entregas</p><strong><?= $totalEntregas ?></strong></div>
        <div class="card"><p>Entrega no prazo (OTD)</p>
            <strong><?= $otd !== null ? number_format($otd, 1, ",", ".") . "%" : "—" ?></strong>
            <p><small><?= $entreguesComPod ?> entrega(s) com comprovante</small></p></div>
        <div class="card"><p>Custo por entrega</p>
            <strong><?= $custoPorEntrega !== null ? "R$ " . number_format($custoPorEntrega, 2, ",", ".") : "—" ?></strong></div>
        <div class="card"><p>Quilômetros rodados</p><strong><?= number_format($kmTotal, 1, ",", ".") ?></strong></div>
        <div class="card"><p>Ocupação da frota em rota</p>
            <strong><?= $ocupacaoMedia !== null ? number_format($ocupacaoMedia, 1, ",", ".") . "%" : "—" ?></strong></div>
    </section>

    <section class="cards" aria-label="Entregas por status">
        <?php foreach ($nomesStatus as $chave => $nome): ?>
            <div class="card"><p><?= h($nome) ?></p><strong><?= (int) ($porStatus[$chave] ?? 0) ?></strong></div>
        <?php endforeach; ?>
    </section>

    <section class="colunas">
        <div class="painel">
            <h2>Entregas por status</h2>
            <div class="grafico"><canvas id="graficoStatus" role="img" aria-label="Gráfico de entregas por status"></canvas></div>
        </div>
        <div class="painel">
            <h2>Entregas por região</h2>
            <div class="grafico"><canvas id="graficoRegiao" role="img" aria-label="Gráfico de entregas por região"></canvas></div>
        </div>
    </section>

    <section class="colunas">
        <div class="painel">
            <h2>Regiões (tabela)</h2>
            <div class="rolagem">
                <table>
                    <thead><tr><th>Região</th><th>Total</th><th>Entregues</th><th>Volume</th></tr></thead>
                    <tbody>
                    <?php if (!$porRegiao): ?><tr><td colspan="4">Sem dados no período.</td></tr><?php endif; ?>
                    <?php foreach ($porRegiao as $r): ?>
                        <tr>
                            <td><?= h($r["regiao"]) ?></td>
                            <td><?= (int) $r["total"] ?></td>
                            <td><?= (int) $r["entregues"] ?></td>
                            <td style="width:35%"><div class="barra"><span style="width:<?= round((int) $r["total"] / $maxRegiao * 100) ?>%"></span></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="painel">
            <h2>Ocupação por veículo (agora)</h2>
            <div class="rolagem">
                <table>
                    <thead><tr><th>Veículo</th><th>Carga / Capacidade</th><th>Ocupação</th></tr></thead>
                    <tbody>
                    <?php foreach ($frota as $v):
                        $cap = max(0.01, (float) $v["capacidade_kg"]);
                        $pct = min(100, round((float) $v["carga"] / $cap * 100, 1));
                    ?>
                        <tr>
                            <td><?= h($v["placa"]) ?><br><small><?= h($v["modelo"]) ?></small></td>
                            <td><?= number_format((float) $v["carga"], 0, ",", ".") ?> / <?= number_format($cap, 0, ",", ".") ?> kg</td>
                            <td style="width:35%"><div class="barra"><span style="width:<?= $pct ?>%"></span></div><small><?= $pct ?>%</small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="painel">
        <h2>Entregas recentes</h2>
        <div class="rolagem">
            <table>
                <thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Prioridade</th><th>Criado em</th></tr></thead>
                <tbody>
                <?php if (!$recentes): ?><tr><td colspan="5">Sem entregas no período.</td></tr><?php endif; ?>
                <?php foreach ($recentes as $r): ?>
                    <tr>
                        <td><?= h($r["codigo_pedido"]) ?></td>
                        <td><?= h($r["cliente_nome"]) ?></td>
                        <td><?= h($nomesStatus[$r["status"]] ?? $r["status"]) ?></td>
                        <td><?= h(ucfirst($r["prioridade"])) ?></td>
                        <td><?= h(date("d/m/Y H:i", strtotime($r["data_criacao"]))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<script>
(function () {
    // Sem Chart.js (offline) os dados continuam visíveis nas tabelas e cartões.
    if (typeof Chart === "undefined") {
        document.querySelectorAll(".grafico").forEach(function (g) { g.hidden = true; });
        return;
    }

    var status = <?= $jsStatus ?>;
    var regiao = <?= $jsRegiao ?>;

    new Chart(document.getElementById("graficoStatus"), {
        type: "doughnut",
        data: {
            labels: status.labels,
            datasets: [{ data: status.valores, backgroundColor: ["#f59e0b", "#2563eb", "#16a34a", "#dc2626"] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById("graficoRegiao"), {
        type: "bar",
        data: {
            labels: regiao.labels,
            datasets: [
                { label: "Total", data: regiao.totais, backgroundColor: "#2563eb" },
                { label: "Entregues", data: regiao.entregues, backgroundColor: "#16a34a" }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
})();
</script>
</body>
</html>