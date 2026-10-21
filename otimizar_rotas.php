<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/seguranca.php";
require_once __DIR__ . "/rotas_lib.php";

$perfisPermitidos = ["administrador", "gestor", "despachante"];

if (!in_array($_SESSION["usuario_nivel"] ?? "", $perfisPermitidos, true)) {
    http_response_code(403);
    exit('Acesso negado. <a href="painel.php">Voltar ao painel</a>');
}

$flash = $_SESSION["flash_rotas"] ?? null;
unset($_SESSION["flash_rotas"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_exigir();

    $saida = $_POST["horario_saida"] ?? "08:00";
    $inicioMin = preg_match('/^\d{2}:\d{2}$/', $saida) ? horaParaMinutos($saida) : 480;

    $_SESSION["flash_rotas"] = gerarRotas($pdo, $inicioMin);
    header("Location: otimizar_rotas.php");
    exit;
}

$totalPendentes = (int) $pdo->query("SELECT COUNT(*) FROM entregas WHERE status = 'pendente'")->fetchColumn();

$linhas = $pdo->query(
    "SELECT e.id, e.codigo_pedido, e.cliente_nome, e.endereco, e.latitude, e.longitude,
            e.janela_inicio, e.janela_fim, e.peso_kg, e.prioridade, e.ordem_parada,
            e.distancia_km, e.custo_estimado, e.eta_prevista, e.veiculo_id,
            m.nome AS motorista_nome, v.placa
     FROM entregas e
     LEFT JOIN motoristas m ON m.id = e.motorista_id
     LEFT JOIN veiculos v ON v.id = e.veiculo_id
     WHERE e.status = 'em_rota' AND e.ordem_parada IS NOT NULL
     ORDER BY v.placa ASC, e.ordem_parada ASC"
)->fetchAll();

$grupos = [];
foreach ($linhas as $l) {
    $grupos[$l["veiculo_id"]][] = $l;
}

$dadosMapa = [];
foreach ($grupos as $paradas) {
    $dadosMapa[] = [
        "placa" => $paradas[0]["placa"],
        "motorista" => $paradas[0]["motorista_nome"],
        "paradas" => array_map(fn($p) => [
            "ordem" => (int) $p["ordem_parada"],
            "codigo" => $p["codigo_pedido"],
            "cliente" => $p["cliente_nome"],
            "lat" => $p["latitude"] !== null ? (float) $p["latitude"] : null,
            "lon" => $p["longitude"] !== null ? (float) $p["longitude"] : null
        ], $paradas)
    ];
}

$jsonMapa = json_encode(
    $dadosMapa,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Otimização de Rotas | SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .bloco { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px; }
        .bloco h2 { margin-top:0; font-size:19px; }
        .msg { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .msg-sucesso { background:#dcfce7; color:#14532d; }
        .msg-erro { background:#fee2e2; color:#7f1d1d; }
        .msg-aviso { background:#fef9c3; color:#713f12; }
        #mapa { height:420px; border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:14px; }
        .atrasada { color:#b91c1c; font-weight:bold; }
        .tabela-rolagem { overflow-x:auto; }
        .rotulo { display:block; margin-bottom:4px; font-weight:bold; }
        .botao { padding:10px 16px; border:0; border-radius:8px; background:#2563eb; color:#fff; cursor:pointer; font-size:15px; }
        .botao:focus-visible, input:focus-visible { outline:3px solid #f59e0b; outline-offset:2px; }
        .num { display:inline-block; min-width:22px; text-align:center; background:#2563eb; color:#fff; border-radius:50%; font-weight:bold; }
    </style>
</head>
<body>
<main class="painel-container">

    <header class="painel-header">
        <div>
            <p class="painel-identificacao">SENN AI-X</p>
            <h1>Roteirização e Otimização de Rotas</h1>
            <p class="painel-subtitulo">Agrupa pedidos por capacidade e ordena as paradas respeitando janelas de entrega.</p>
        </div>
        <a class="painel-sair" href="painel.php">Voltar ao painel</a>
    </header>

    <?php if ($flash): ?>
        <div class="msg msg-<?= h($flash["tipo"]) ?>" role="alert">
            <strong><?= h($flash["mensagem"]) ?></strong>
            <?php if (!empty($flash["avisos"])): ?>
                <ul>
                    <?php foreach ($flash["avisos"] as $aviso): ?>
                        <li><?= h($aviso) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="bloco">
        <h2>Gerar rotas do dia</h2>
        <p>Entregas pendentes: <strong><?= $totalPendentes ?></strong></p>
        <form method="POST" action="otimizar_rotas.php">
            <?= csrf_campo() ?>
            <label class="rotulo" for="horario_saida">Horário de saída dos veículos</label>
            <input type="time" id="horario_saida" name="horario_saida" value="08:00" required>
            <button type="submit" class="botao" <?= $totalPendentes === 0 ? "disabled" : "" ?>>
                Gerar rotas otimizadas
            </button>
        </form>
        <p><small>Método: capacidade (kg e m³) + vizinho mais próximo com Haversine, prioridade e janela de entrega.
        Coordenadas ausentes são buscadas no OpenStreetMap; sem internet o sistema usa distância estimada.</small></p>
    </section>

    <section class="bloco">
        <h2>Mapa das rotas</h2>
        <div id="mapa" aria-label="Mapa com as rotas geradas"></div>
        <p id="aviso-mapa" class="msg msg-aviso" hidden>
            Mapa indisponível (modo offline). A tabela abaixo traz a sequência completa das rotas.
        </p>
    </section>

    <section class="bloco">
        <h2>Rotas liberadas</h2>
        <?php if (!$grupos): ?>
            <p>Nenhuma rota ativa no momento.</p>
        <?php endif; ?>

        <?php foreach ($grupos as $paradas): ?>
            <h3><?= h($paradas[0]["placa"]) ?> — <?= h($paradas[0]["motorista_nome"]) ?></h3>
            <div class="tabela-rolagem">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Pedido</th><th>Cliente / Endereço</th>
                            <th>Janela</th><th>Chegada prevista</th><th>Km</th><th>Custo (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($paradas as $p):
                        $eta = substr((string) $p["eta_prevista"], 0, 5);
                        $fim = substr((string) $p["janela_fim"], 0, 5);
                        $atrasada = $eta !== "" && $fim !== "" && $eta > $fim;
                    ?>
                        <tr>
                            <td><?= (int) $p["ordem_parada"] ?></td>
                            <td><?= h($p["codigo_pedido"]) ?></td>
                            <td><?= h($p["cliente_nome"]) ?><br><small><?= h($p["endereco"]) ?></small></td>
                            <td><?= h(substr((string) $p["janela_inicio"], 0, 5)) ?> – <?= h($fim) ?></td>
                            <td class="<?= $atrasada ? "atrasada" : "" ?>">
                                <?= h($eta) ?><?= $atrasada ? " (fora da janela)" : "" ?>
                            </td>
                            <td><?= number_format((float) $p["distancia_km"], 1, ",", ".") ?></td>
                            <td><?= number_format((float) $p["custo_estimado"], 2, ",", ".") ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </section>

</main>

<script>
(function () {
    var rotas = <?= $jsonMapa ?>;
    var aviso = document.getElementById("aviso-mapa");
    var elMapa = document.getElementById("mapa");

    if (typeof L === "undefined") {
        elMapa.hidden = true;
        aviso.hidden = false;
        return;
    }

    var deposito = [<?= DEPOSITO_LAT ?>, <?= DEPOSITO_LON ?>];
    var mapa = L.map("mapa").setView(deposito, 13);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "© OpenStreetMap contributors"
    }).addTo(mapa);

    var cores = ["#2563eb", "#dc2626", "#16a34a", "#9333ea", "#ea580c", "#0891b2"];
    var limites = [deposito];

    L.marker(deposito).addTo(mapa).bindPopup("Depósito");

    rotas.forEach(function (rota, i) {
        var cor = cores[i % cores.length];
        var linha = [deposito];

        rota.paradas.forEach(function (p) {
            if (p.lat === null || p.lon === null) { return; }
            var ponto = [p.lat, p.lon];
            linha.push(ponto);
            limites.push(ponto);

            var icone = L.divIcon({
                className: "",
                html: '<span class="num" style="background:' + cor + '">' + p.ordem + "</span>",
                iconSize: [22, 22]
            });

            var popup = document.createElement("div");
            var titulo = document.createElement("strong");
            titulo.textContent = p.codigo + " — " + p.cliente;
            popup.appendChild(titulo);
            popup.appendChild(document.createElement("br"));
            popup.appendChild(document.createTextNode(rota.placa + " | " + rota.motorista));

            L.marker(ponto, { icon: icone }).addTo(mapa).bindPopup(popup);
        });

        linha.push(deposito);
        L.polyline(linha, { color: cor, weight: 4, opacity: 0.8 }).addTo(mapa);
    });

    if (limites.length > 1) {
        mapa.fitBounds(limites, { padding: [30, 30] });
    }
})();
</script>
</body>
</html>