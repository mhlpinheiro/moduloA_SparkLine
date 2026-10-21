
<?php
require_once __DIR__ . "/auth.php";
verificar_autenticacao();

require_once __DIR__ . "/conexao.php";

$alertas = [];
$erroBanco = false;

$totalAlertas = 0;
$pendentes = 0;
$resolvidos = 0;
$criticos = 0;

try {
    $stmt = $pdo->query("SELECT * FROM alertas ORDER BY id DESC");
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAlertas = count($alertas);

    foreach ($alertas as $alerta) {
        $resolvido = !empty($alerta['resolvido']);

        if ($resolvido) {
            $resolvidos++;
        } else {
            $pendentes++;
        }

        $nivel = strtolower(trim($alerta['nivel'] ?? ''));

        if (in_array($nivel, ['critico', 'crítico', 'critica', 'crítica'], true)) {
            $criticos++;
        }
    }
} catch (PDOException $e) {
    $erroBanco = true;
}

function alertaEsc($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function alertaNivelClasse($nivel): string
{
    $nivel = strtolower(trim((string) $nivel));

    if (in_array($nivel, ['critico', 'crítico', 'critica', 'crítica'], true)) {
        return 'critico';
    }

    if (in_array($nivel, ['alto', 'alta'], true)) {
        return 'alto';
    }

    if (in_array($nivel, ['medio', 'médio', 'media', 'média'], true)) {
        return 'medio';
    }

    if (in_array($nivel, ['aviso', 'baixo', 'baixa', 'informativo'], true)) {
        return 'aviso';
    }

    return 'padrao';
}

require_once __DIR__ . "/header.php";
?>

<style>
/* =====================================================
   CENTRAL DE ALERTAS - SENN AI-X
   Estilos isolados para não alterar as outras páginas
===================================================== */

.alertas-page {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    margin: 0;
    padding: 28px;
    box-sizing: border-box;
    color: #173247;
    font-family: inherit;
}

.alertas-page *,
.alertas-page *::before,
.alertas-page *::after {
    box-sizing: border-box;
}

.alertas-page h1,
.alertas-page h2,
.alertas-page h3,
.alertas-page p {
    margin-top: 0;
}

.alertas-cabecalho {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 26px;
}

.alertas-titulo h2 {
    margin-bottom: 8px;
    font-size: clamp(23px, 2vw, 30px);
    font-weight: 800;
    line-height: 1.25;
    color: #173247;
}

.alertas-titulo p {
    margin-bottom: 0;
    color: #718096;
    font-size: 14px;
    line-height: 1.6;
}

.alertas-indicador-operacao {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 10px 14px;
    border: 1px solid #c8eed7;
    border-radius: 10px;
    background: #effbf3;
    color: #187746;
    font-size: 13px;
    font-weight: 700;
}

.alertas-indicador-operacao .ponto {
    width: 9px;
    height: 9px;
    flex-shrink: 0;
    border-radius: 50%;
    background: currentColor;
}

.alertas-resumo {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.alertas-card-resumo {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
    padding: 21px;
    background: #fff;
    border: 1px solid #e1e8ef;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(20, 45, 65, .045);
}

.alertas-icone {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 47px;
    height: 47px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #e8f5f8;
    color: #117f94;
    font-size: 22px;
    font-weight: 800;
}

.alertas-card-resumo.pendente .alertas-icone {
    background: #fff4df;
    color: #a96a00;
}

.alertas-card-resumo.critico .alertas-icone {
    background: #ffebeb;
    color: #c63737;
}

.alertas-card-resumo.resolvido .alertas-icone {
    background: #e8f8ee;
    color: #198754;
}

.alertas-card-texto {
    min-width: 0;
}

.alertas-card-texto span {
    display: block;
    margin-bottom: 6px;
    color: #718096;
    font-size: 12px;
    line-height: 1.4;
}

.alertas-card-texto strong {
    display: block;
    color: #173247;
    font-size: 28px;
    font-weight: 800;
    line-height: 1.1;
    overflow-wrap: anywhere;
}

.alertas-painel {
    display: block;
    width: 100%;
    min-width: 0;
    margin-bottom: 24px;
    padding: 26px;
    background: #fff;
    border: 1px solid #e1e8ef;
    border-radius: 16px;
    box-shadow: 0 4px 18px rgba(20, 45, 65, .05);
}

.alertas-painel-cabecalho {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 22px;
}

.alertas-painel-cabecalho h3 {
    margin-bottom: 7px;
    color: #173247;
    font-size: 19px;
    font-weight: 800;
}

.alertas-painel-cabecalho p {
    margin-bottom: 0;
    color: #718096;
    font-size: 13px;
    line-height: 1.5;
}

.alertas-filtros {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.alertas-filtros input,
.alertas-filtros select {
    min-width: 180px;
    max-width: 100%;
    min-height: 43px;
    padding: 10px 13px;
    border: 1px solid #dce5ed;
    border-radius: 9px;
    background: #fff;
    color: #173247;
    font: inherit;
    font-size: 13px;
    outline: none;
}

.alertas-filtros input {
    width: 235px;
}

.alertas-filtros input:focus,
.alertas-filtros select:focus {
    border-color: #12849a;
    box-shadow: 0 0 0 3px rgba(18, 132, 154, .12);
}

.alertas-tabela-wrapper {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border: 1px solid #dfe7ef;
    border-radius: 11px;
    -webkit-overflow-scrolling: touch;
}

.alertas-tabela {
    width: 100%;
    min-width: 850px;
    margin: 0;
    border-collapse: collapse;
    border-spacing: 0;
    table-layout: auto;
    background: #fff;
}

.alertas-tabela thead th {
    padding: 16px;
    background: #f1f6fa;
    color: #173247;
    border-bottom: 1px solid #dfe7ef;
    font-size: 11px;
    font-weight: 800;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .045em;
    white-space: nowrap;
}

.alertas-tabela tbody td {
    padding: 17px 16px;
    border-bottom: 1px solid #edf1f5;
    color: #354b5d;
    font-size: 13px;
    line-height: 1.6;
    text-align: left;
    vertical-align: middle;
}

.alertas-tabela tbody tr:last-child td {
    border-bottom: 0;
}

.alertas-tabela tbody tr:hover {
    background: #f8fbfd;
}

.alertas-data {
    min-width: 145px;
    white-space: nowrap;
}

.alertas-tipo {
    min-width: 150px;
    font-weight: 700;
    color: #173247 !important;
}

.alertas-mensagem {
    min-width: 250px;
    max-width: 450px;
    overflow-wrap: anywhere;
    white-space: normal;
}

.alertas-nivel,
.alertas-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    line-height: 1.4;
    white-space: nowrap;
}

.alertas-nivel.critico {
    background: #ffebeb;
    color: #bd3030;
}

.alertas-nivel.alto {
    background: #fff0e5;
    color: #a94d12;
}

.alertas-nivel.medio {
    background: #fff5d9;
    color: #896300;
}

.alertas-nivel.aviso {
    background: #e9f2ff;
    color: #245ca8;
}

.alertas-nivel.padrao {
    background: #edf0f4;
    color: #536477;
}

.alertas-status.pendente {
    background: #fff3dc;
    color: #936000;
}

.alertas-status.resolvido {
    background: #e5f7ec;
    color: #187746;
}

.alertas-vazio {
    padding: 45px 20px !important;
    text-align: center !important;
}

.alertas-vazio-icone {
    display: block;
    margin-bottom: 12px;
    font-size: 32px;
}

.alertas-vazio strong {
    display: block;
    margin-bottom: 7px;
    color: #173247;
    font-size: 15px;
}

.alertas-vazio span {
    display: block;
    color: #718096;
    font-size: 13px;
}

.alertas-sem-resultado {
    display: none;
    padding: 25px 15px;
    color: #718096;
    font-size: 13px;
    text-align: center;
}

.alertas-rodape {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
    color: #718096;
    font-size: 12px;
}

.alertas-erro {
    margin-bottom: 20px;
    padding: 15px 17px;
    border: 1px solid #f0caca;
    border-radius: 10px;
    background: #fff0f0;
    color: #a82e2e;
    font-size: 13px;
    line-height: 1.6;
}

@media (max-width: 1200px) {
    .alertas-page {
        padding: 22px;
    }

    .alertas-resumo {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .alertas-painel {
        padding: 21px;
    }
}

@media (max-width: 650px) {
    .alertas-page {
        padding: 14px;
    }

    .alertas-cabecalho {
        align-items: flex-start;
    }

    .alertas-resumo {
        gap: 10px;
    }

    .alertas-card-resumo {
        gap: 10px;
        padding: 13px;
    }

    .alertas-icone {
        width: 36px;
        height: 36px;
        font-size: 17px;
    }

    .alertas-card-texto span {
        font-size: 11px;
    }

    .alertas-card-texto strong {
        font-size: 23px;
    }

    .alertas-painel {
        padding: 15px;
    }

    .alertas-painel-cabecalho {
        align-items: stretch;
    }

    .alertas-filtros {
        width: 100%;
    }

    .alertas-filtros input,
    .alertas-filtros select {
        width: 100%;
        min-width: 0;
    }

    .alertas-rodape {
        flex-direction: column;
    }
}
</style>

<main class="alertas-page">

    <header class="alertas-cabecalho">
        <div class="alertas-titulo">
            <h2>Central de Alertas</h2>
            <p>
                Acompanhe notificações, eventos críticos e ocorrências
                da operação logística.
            </p>
        </div>

        <div class="alertas-indicador-operacao">
            <span class="ponto"></span>

            <?= $erroBanco
                ? 'Falha ao consultar alertas'
                : ($pendentes > 0
                    ? 'Existem alertas pendentes'
                    : 'Nenhum alerta pendente') ?>
        </div>
    </header>

    <?php if ($erroBanco): ?>
        <div class="alertas-erro">
            Não foi possível carregar os alertas. Verifique se a tabela
            <strong>alertas</strong> existe e se a conexão com o banco está funcionando.
        </div>
    <?php else: ?>

        <section class="alertas-resumo">

            <article class="alertas-card-resumo">
                <div class="alertas-icone" aria-hidden="true">▤</div>
                <div class="alertas-card-texto">
                    <span>Total de alertas</span>
                    <strong><?= $totalAlertas ?></strong>
                </div>
            </article>

            <article class="alertas-card-resumo pendente">
                <div class="alertas-icone" aria-hidden="true">◷</div>
                <div class="alertas-card-texto">
                    <span>Pendentes</span>
                    <strong><?= $pendentes ?></strong>
                </div>
            </article>

            <article class="alertas-card-resumo critico">
                <div class="alertas-icone" aria-hidden="true">!</div>
                <div class="alertas-card-texto">
                    <span>Críticos</span>
                    <strong><?= $criticos ?></strong>
                </div>
            </article>

            <article class="alertas-card-resumo resolvido">
                <div class="alertas-icone" aria-hidden="true">✓</div>
                <div class="alertas-card-texto">
                    <span>Resolvidos</span>
                    <strong><?= $resolvidos ?></strong>
                </div>
            </article>

        </section>

        <section class="alertas-painel">

            <div class="alertas-painel-cabecalho">
                <div>
                    <h3>Histórico de notificações</h3>
                    <p>Consulte os registros armazenados no sistema.</p>
                </div>

                <div class="alertas-filtros">
                    <input
                        type="search"
                        id="alertasBusca"
                        placeholder="Buscar alerta..."
                        aria-label="Pesquisar alertas"
                    >

                    <select id="alertasFiltroStatus" aria-label="Filtrar por status">
                        <option value="">Todos os status</option>
                        <option value="pendente">Pendentes</option>
                        <option value="resolvido">Resolvidos</option>
                    </select>
                </div>
            </div>

            <div class="alertas-tabela-wrapper">
                <table class="alertas-tabela">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Tipo</th>
                            <th>Mensagem</th>
                            <th>Nível</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody id="alertasCorpo">

                    <?php if (empty($alertas)): ?>

                        <tr>
                            <td colspan="5" class="alertas-vazio">
                                <span class="alertas-vazio-icone">✓</span>
                                <strong>Nenhum alerta registrado</strong>
                                <span>
                                    Quando houver notificações, elas serão
                                    exibidas neste histórico.
                                </span>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($alertas as $a): ?>

                            <?php
                            $nivel = trim((string) ($a['nivel'] ?? 'aviso'));
                            $nivelClasse = alertaNivelClasse($nivel);

                            $resolvido = !empty($a['resolvido']);
                            $status = $resolvido ? 'resolvido' : 'pendente';

                            $tipo = strtoupper(
                                str_replace('_', ' ', (string) ($a['tipo'] ?? 'Geral'))
                            );
                            ?>

                            <tr data-status="<?= alertaEsc($status) ?>">

                                <td class="alertas-data">
                                    <?= alertaEsc($a['criado_em'] ?? '') ?>
                                </td>

                                <td class="alertas-tipo">
                                    <?= alertaEsc($tipo) ?>
                                </td>

                                <td class="alertas-mensagem">
                                    <?= alertaEsc($a['mensagem'] ?? '') ?>
                                </td>

                                <td>
                                    <span class="alertas-nivel <?= alertaEsc($nivelClasse) ?>">
                                        <?= alertaEsc(mb_strtoupper($nivel, 'UTF-8')) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="alertas-status <?= alertaEsc($status) ?>">
                                        <?= $resolvido ? 'Resolvido' : 'Pendente' ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div id="alertasSemResultado" class="alertas-sem-resultado">
                Nenhum alerta corresponde aos filtros selecionados.
            </div>

            <footer class="alertas-rodape">
                <span>
                    Total de registros:
                    <strong><?= $totalAlertas ?></strong>
                </span>

                <span>Os dados são carregados do banco de dados.</span>
            </footer>

        </section>

    <?php endif; ?>

</main>

<script>
(function () {
    const busca = document.getElementById('alertasBusca');
    const filtro = document.getElementById('alertasFiltroStatus');
    const corpo = document.getElementById('alertasCorpo');
    const mensagemVazia = document.getElementById('alertasSemResultado');

    if (!busca || !filtro || !corpo) {
        return;
    }

    function filtrarAlertas() {
        const termo = busca.value
            .toLocaleLowerCase('pt-BR')
            .trim();

        const statusSelecionado = filtro.value;
        const linhas = corpo.querySelectorAll('tr[data-status]');

        let visiveis = 0;

        linhas.forEach(function (linha) {
            const texto = linha.textContent
                .toLocaleLowerCase('pt-BR');

            const status = linha.dataset.status;

            const correspondeTexto = texto.includes(termo);
            const correspondeStatus =
                !statusSelecionado || status === statusSelecionado;

            const mostrar = correspondeTexto && correspondeStatus;

            linha.hidden = !mostrar;

            if (mostrar) {
                visiveis++;
            }
        });

        if (mensagemVazia) {
            mensagemVazia.style.display =
                linhas.length > 0 && visiveis === 0
                    ? 'block'
                    : 'none';
        }
    }

    busca.addEventListener('input', filtrarAlertas);
    filtro.addEventListener('change', filtrarAlertas);
})();
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
