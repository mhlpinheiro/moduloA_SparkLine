<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/seguranca.php";

$perfil = $_SESSION["usuario_nivel"] ?? "";
$nomeUsuario = trim($_SESSION["usuario_nome"] ?? "");

// Motorista só vê as próprias entregas (vínculo provisório pelo nome, como em movimentacoes.php).
$motoristaId = null;
if ($perfil === "motorista") {
    $st = $pdo->prepare("SELECT id FROM motoristas WHERE nome = :nome LIMIT 1");
    $st->execute([":nome" => $nomeUsuario]);
    $r = $st->fetch();
    if ($r) {
        $motoristaId = (int) $r["id"];
    }
}

function entregasAbertas(PDO $pdo, string $perfil, ?int $motoristaId): array
{
    $sql = "SELECT id, codigo_pedido, cliente_nome, endereco, motorista_id, veiculo_id
            FROM entregas WHERE status = 'em_rota'";

    if ($perfil === "motorista") {
        if ($motoristaId === null) {
            return [];
        }
        $st = $pdo->prepare($sql . " AND motorista_id = :m ORDER BY ordem_parada, id");
        $st->execute([":m" => $motoristaId]);
        return $st->fetchAll();
    }

    return $pdo->query($sql . " ORDER BY motorista_id, ordem_parada, id")->fetchAll();
}

function coordenada($valor, float $min, float $max): ?float
{
    if ($valor === null || $valor === "" || !is_numeric($valor)) {
        return null;
    }
    $f = (float) $valor;
    return ($f >= $min && $f <= $max) ? $f : null;
}

// Valida e salva a evidência. Devolve o texto/caminho a gravar em evidencia_dado.
function salvarEvidencia(string $tipo, int $entregaId): string
{
    if ($tipo === "codigo") {
        $codigo = trim($_POST["codigo"] ?? "");
        if (!preg_match('/^[A-Za-z0-9\-_.]{3,40}$/', $codigo)) {
            throw new RuntimeException("Informe um código de 3 a 40 caracteres (letras, números, - _ .).");
        }
        return $codigo;
    }

    $pasta = __DIR__ . "/uploads/pod";
    if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) {
        throw new RuntimeException("Não foi possível criar a pasta de comprovantes.");
    }

    $base = sprintf("%s_%d_%s", $tipo === "assinatura" ? "ass" : "foto", $entregaId, bin2hex(random_bytes(6)));

    if ($tipo === "assinatura") {
        $url = $_POST["assinatura"] ?? "";
        $prefixo = "data:image/png;base64,";

        if (!is_string($url) || strpos($url, $prefixo) !== 0) {
            throw new RuntimeException("Faça a assinatura no quadro antes de salvar.");
        }

        $binario = base64_decode(substr($url, strlen($prefixo)), true);
        if ($binario === false || strlen($binario) > 400000) {
            throw new RuntimeException("Assinatura inválida ou grande demais.");
        }

        $info = @getimagesizefromstring($binario);
        if (!$info || $info[2] !== IMAGETYPE_PNG) {
            throw new RuntimeException("A assinatura precisa ser uma imagem PNG válida.");
        }

        $arquivo = $base . ".png";
        file_put_contents($pasta . "/" . $arquivo, $binario);
    } else {
        if (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Envie a foto do comprovante.");
        }
        if ($_FILES["foto"]["size"] > 3 * 1024 * 1024) {
            throw new RuntimeException("A foto deve ter no máximo 3 MB.");
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES["foto"]["tmp_name"]);
        $ext = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"][$mime] ?? null;
        if ($ext === null) {
            throw new RuntimeException("Formato inválido. Use JPG, PNG ou WEBP.");
        }

        $arquivo = $base . "." . $ext;
        if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $pasta . "/" . $arquivo)) {
            throw new RuntimeException("Não foi possível salvar a foto.");
        }
    }

    return "uploads/pod/" . $arquivo;
}

$mensagem = "";
$tipoMensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_exigir();

    try {
        $entregaId = (int) ($_POST["entrega_id"] ?? 0);
        $tipo = $_POST["evidencia_tipo"] ?? "";
        $obs = mb_substr(trim($_POST["observacoes"] ?? ""), 0, 500);
        $lat = coordenada($_POST["latitude"] ?? null, -90, 90);
        $lon = coordenada($_POST["longitude"] ?? null, -180, 180);

        if (!in_array($tipo, ["codigo", "assinatura", "foto"], true)) {
            throw new RuntimeException("Selecione um tipo de evidência válido.");
        }

        $entrega = null;
        foreach (entregasAbertas($pdo, $perfil, $motoristaId) as $e) {
            if ((int) $e["id"] === $entregaId) {
                $entrega = $e;
                break;
            }
        }
        if (!$entrega) {
            throw new RuntimeException("Entrega inválida ou indisponível para o seu perfil.");
        }

        $dado = salvarEvidencia($tipo, $entregaId);

        $pdo->beginTransaction();

        $pdo->prepare(
            "INSERT INTO comprovantes_pod
                (entrega_id, latitude, longitude, evidencia_tipo, evidencia_dado, observacoes)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$entregaId, $lat, $lon, $tipo, $dado, $obs !== "" ? $obs : null]);

        $pdo->prepare("UPDATE entregas SET status = 'entregue' WHERE id = ? AND status = 'em_rota'")
            ->execute([$entregaId]);

        // Libera motorista e veículo quando terminam todas as entregas da rota.
        if ($entrega["motorista_id"] !== null) {
            $pdo->prepare(
                "UPDATE motoristas SET status = 'disponivel'
                 WHERE id = ? AND status = 'em_rota'
                   AND NOT EXISTS (SELECT 1 FROM entregas WHERE motorista_id = ? AND status = 'em_rota')"
            )->execute([$entrega["motorista_id"], $entrega["motorista_id"]]);
        }
        if ($entrega["veiculo_id"] !== null) {
            $pdo->prepare(
                "UPDATE veiculos SET status = 'disponivel'
                 WHERE id = ? AND status = 'em_rota'
                   AND NOT EXISTS (SELECT 1 FROM entregas WHERE veiculo_id = ? AND status = 'em_rota')"
            )->execute([$entrega["veiculo_id"], $entrega["veiculo_id"]]);
        }

        $pdo->commit();

        $mensagem = "Comprovante registrado. Entrega finalizada.";
        $tipoMensagem = "sucesso";
    } catch (RuntimeException $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensagem = $erro->getMessage();
        $tipoMensagem = "erro";
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro no POD: " . $erro->getMessage());
        $mensagem = "Não foi possível registrar o comprovante.";
        $tipoMensagem = "erro";
    }
}

$abertas = entregasAbertas($pdo, $perfil, $motoristaId);

$sqlHistorico = "SELECT c.data_hora, c.latitude, c.longitude, c.evidencia_tipo, c.evidencia_dado,
                        e.codigo_pedido, e.cliente_nome
                 FROM comprovantes_pod c
                 JOIN entregas e ON e.id = c.entrega_id";

if ($perfil === "motorista") {
    $stH = $pdo->prepare($sqlHistorico . " WHERE e.motorista_id = :m ORDER BY c.id DESC LIMIT 15");
    $stH->execute([":m" => $motoristaId ?? 0]);
    $historico = $stH->fetchAll();
} else {
    $historico = $pdo->query($sqlHistorico . " ORDER BY c.id DESC LIMIT 15")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprovação de Entrega | SENN AI-X</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .bloco { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px; }
        .bloco h2 { margin-top:0; font-size:19px; }
        .msg { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .msg-sucesso { background:#dcfce7; color:#14532d; }
        .msg-erro { background:#fee2e2; color:#7f1d1d; }
        label { display:block; font-weight:bold; margin:12px 0 4px; }
        input[type=text], select, textarea { width:100%; padding:9px; border:1px solid #94a3b8; border-radius:6px; box-sizing:border-box; }
        #assinatura { width:100%; max-width:480px; height:180px; border:2px dashed #64748b; border-radius:8px; background:#fff; display:block; }
        .botao { padding:10px 16px; border:0; border-radius:8px; background:#16a34a; color:#fff; cursor:pointer; font-size:15px; margin-top:14px; }
        .botao-sec { background:#475569; margin-top:8px; }
        .botao:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline:3px solid #f59e0b; outline-offset:2px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:14px; }
        .tabela-rolagem { overflow-x:auto; }
        .miniatura { max-width:90px; max-height:60px; border:1px solid #cbd5e1; border-radius:4px; }
        #geoStatus { font-size:13px; color:#475569; }
    </style>
</head>
<body>
<main class="painel-container">

    <header class="painel-header">
        <div>
            <p class="painel-identificacao">SENN AI-X</p>
            <h1>Comprovação de Entrega (POD)</h1>
            <p class="painel-subtitulo">Registre a evidência com data, hora e localização.</p>
        </div>
        <a class="painel-sair" href="painel.php">Voltar ao painel</a>
    </header>

    <?php if ($mensagem !== ""): ?>
        <div class="msg msg-<?= h($tipoMensagem) ?>" role="alert"><strong><?= h($mensagem) ?></strong></div>
    <?php endif; ?>

    <section class="bloco">
        <h2>Registrar entrega</h2>

        <?php if (!$abertas): ?>
            <p>Nenhuma entrega em rota disponível para registro.</p>
        <?php else: ?>
        <form method="POST" action="pod.php" enctype="multipart/form-data" id="formPod">
            <?= csrf_campo() ?>
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            <input type="hidden" name="assinatura" id="assinaturaDados">

            <label for="entrega_id">Entrega</label>
            <select name="entrega_id" id="entrega_id" required>
                <?php foreach ($abertas as $e): ?>
                    <option value="<?= (int) $e["id"] ?>">
                        <?= h($e["codigo_pedido"]) ?> — <?= h($e["cliente_nome"]) ?> (<?= h($e["endereco"]) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="evidencia_tipo">Tipo de evidência</label>
            <select name="evidencia_tipo" id="evidencia_tipo">
                <option value="codigo">Código de confirmação</option>
                <option value="assinatura">Assinatura digital</option>
                <option value="foto">Foto do comprovante</option>
            </select>

            <div data-tipo="codigo">
                <label for="codigo">Código de confirmação</label>
                <input type="text" name="codigo" id="codigo" maxlength="40" placeholder="Ex.: COD-88492" autocomplete="off">
            </div>

            <div data-tipo="assinatura" hidden>
                <label for="assinatura">Assinatura do recebedor</label>
                <canvas id="assinatura" width="480" height="180" aria-label="Quadro de assinatura"></canvas>
                <button type="button" class="botao botao-sec" id="limparAssinatura">Limpar assinatura</button>
            </div>

            <div data-tipo="foto" hidden>
                <label for="foto">Foto (JPG, PNG ou WEBP, até 3 MB)</label>
                <input type="file" name="foto" id="foto" accept="image/jpeg,image/png,image/webp" capture="environment">
            </div>

            <label for="observacoes">Observações</label>
            <textarea name="observacoes" id="observacoes" rows="3" maxlength="500"></textarea>

            <p id="geoStatus" aria-live="polite">Localização ainda não capturada.</p>
            <button type="button" class="botao botao-sec" id="btnLocal">Capturar localização novamente</button>
            <br>
            <button type="submit" class="botao">Finalizar entrega</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="bloco">
        <h2>Últimos comprovantes</h2>
        <div class="tabela-rolagem">
            <table>
                <thead>
                    <tr><th>Pedido / Cliente</th><th>Data / Hora</th><th>Evidência</th><th>Localização</th></tr>
                </thead>
                <tbody>
                <?php if (!$historico): ?>
                    <tr><td colspan="4">Nenhum comprovante registrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($historico as $c): ?>
                    <tr>
                        <td><strong><?= h($c["codigo_pedido"]) ?></strong><br><small><?= h($c["cliente_nome"]) ?></small></td>
                        <td><?= h(date("d/m/Y H:i:s", strtotime($c["data_hora"]))) ?></td>
                        <td>
                            <?php if ($c["evidencia_tipo"] === "codigo"): ?>
                                Código: <?= h($c["evidencia_dado"]) ?>
                            <?php else: ?>
                                <a href="<?= h($c["evidencia_dado"]) ?>" target="_blank" rel="noopener">
                                    <img class="miniatura" src="<?= h($c["evidencia_dado"]) ?>"
                                         alt="<?= $c["evidencia_tipo"] === "foto" ? "Foto" : "Assinatura" ?> do comprovante">
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c["latitude"] !== null && $c["longitude"] !== null): ?>
                                <?= h($c["latitude"]) ?>, <?= h($c["longitude"]) ?>
                            <?php else: ?>
                                Não informada
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<script>
(function () {
    var form = document.getElementById("formPod");
    if (!form) { return; }

    var tipo = document.getElementById("evidencia_tipo");
    var blocos = document.querySelectorAll("[data-tipo]");

    function atualizarTipo() {
        blocos.forEach(function (b) { b.hidden = (b.getAttribute("data-tipo") !== tipo.value); });
    }
    tipo.addEventListener("change", atualizarTipo);
    atualizarTipo();

    // Geolocalização automática (RF07)
    var lat = document.getElementById("latitude");
    var lon = document.getElementById("longitude");
    var status = document.getElementById("geoStatus");

    function capturar() {
        if (!navigator.geolocation) {
            status.textContent = "Geolocalização indisponível neste navegador. O comprovante será salvo sem coordenadas.";
            return;
        }
        status.textContent = "Obtendo localização...";
        navigator.geolocation.getCurrentPosition(function (p) {
            lat.value = p.coords.latitude.toFixed(8);
            lon.value = p.coords.longitude.toFixed(8);
            status.textContent = "Localização capturada: " + lat.value + ", " + lon.value;
        }, function (erro) {
            lat.value = "";
            lon.value = "";
            status.textContent = "Não foi possível obter a localização (" + erro.message + "). O comprovante será salvo sem coordenadas.";
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    }
    document.getElementById("btnLocal").addEventListener("click", capturar);
    capturar();

    // Quadro de assinatura
    var canvas = document.getElementById("assinatura");
    var ctx = canvas.getContext("2d");
    var desenhando = false;
    var desenhou = false;

    ctx.lineWidth = 2.5;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#0f172a";
    canvas.style.touchAction = "none";

    function pos(e) {
        var r = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - r.left) * canvas.width / r.width,
            y: (e.clientY - r.top) * canvas.height / r.height
        };
    }

    canvas.addEventListener("pointerdown", function (e) {
        desenhando = true;
        var p = pos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        canvas.setPointerCapture(e.pointerId);
    });
    canvas.addEventListener("pointermove", function (e) {
        if (!desenhando) { return; }
        var p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        desenhou = true;
    });
    ["pointerup", "pointercancel"].forEach(function (ev) {
        canvas.addEventListener(ev, function () { desenhando = false; });
    });

    document.getElementById("limparAssinatura").addEventListener("click", function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        desenhou = false;
    });

    form.addEventListener("submit", function (e) {
        if (tipo.value === "assinatura") {
            if (!desenhou) {
                e.preventDefault();
                alert("Faça a assinatura no quadro antes de finalizar.");
                return;
            }
            document.getElementById("assinaturaDados").value = canvas.toDataURL("image/png");
        }
    });
})();
</script>
</body>
</html>