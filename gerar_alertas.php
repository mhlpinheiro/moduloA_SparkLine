
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';

exigir_admin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$mensagem = '';
$erro = '';
$alertas = [];
$totalVeiculos = 0;
$inseridos = 0;

/**
 * Escapa valores para exibição segura no HTML.
 */
function gerarAlertaEsc($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Processa a geração de alertas.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    try {
        $pdo->beginTransaction();

        $consultaVeiculos = $pdo->query(
            "SELECT id, placa, modelo
             FROM veiculos
             WHERE status = 'em_manutencao'"
        );

        $veiculos = $consultaVeiculos->fetchAll(PDO::FETCH_ASSOC);
        $totalVeiculos = count($veiculos);

        $verificar = $pdo->prepare(
            "SELECT id
             FROM alertas
             WHERE mensagem = :mensagem
               AND resolvido = 0
             LIMIT 1"
        );

        $inserir = $pdo->prepare(
            "INSERT INTO alertas
                (tipo, mensagem, nivel, resolvido)
             VALUES
                (:tipo, :mensagem, :nivel, 0)"
        );

        foreach ($veiculos as $veiculo) {
            $placa = $veiculo['placa'] ?? 'Sem placa';
            $modelo = $veiculo['modelo'] ?? 'Modelo não informado';

            $texto = 'Veículo ' . $placa . ' (' .
                $modelo . ') está em manutenção.';

            $verificar->execute([
                'mensagem' => $texto
            ]);

            if (!$verificar->fetch(PDO::FETCH_ASSOC)) {
                $inserir->execute([
                    'tipo' => 'manutencao_cnh',
                    'mensagem' => $texto,
                    'nivel' => 'aviso'
                ]);

                $inseridos++;
            }
        }

        $pdo->commit();

        if ($totalVeiculos === 0) {
            $mensagem = 'Nenhum veículo está marcado como em manutenção.';
        } elseif ($inseridos === 0) {
            $mensagem = 'Verificação concluída. Os alertas dos veículos em manutenção já estavam registrados.';
        } else {
            $mensagem = $inseridos .
                ' novo(s) alerta(s) criado(s) para ' .
                $totalVeiculos . ' veículo(s) em manutenção.';
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Falha ao gerar alertas: ' . $e->getMessage()
        );

        $erro = 'Não foi possível gerar os alertas. Verifique a conexão e a estrutura do banco de dados.';
    }
}

/**
 * Carrega o histórico de alertas.
 */
try {
    $consultaAlertas = $pdo->query(
        "SELECT id, tipo, mensagem, nivel, resolvido, criado_em
         FROM alertas
         ORDER BY criado_em DESC, id DESC
         LIMIT 100"
    );

    $alertas = $consultaAlertas->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'Falha ao consultar alertas: ' . $e->getMessage()
    );

    $erro = $erro ?:
        'Não foi possível carregar o histórico de alertas.';
}

/**
 * Calcula os indicadores do painel.
 */
$totalAlertas = count($alertas);
$pendentes = 0;
$resolvidos = 0;

foreach ($alertas as $item) {
    if (!empty($item['resolvido'])) {
        $resolvidos++;
    } else {
        $pendentes++;
    }
}

require_once __DIR__ . '/header.php';
?>

<style>
/* ==================================================
   CENTRAL DE ALERTAS — SENN AI-X
   Estilos exclusivos desta página
================================================== */

/* Corrige o layout global somente nesta página. */
body:has(main.gerar-alertas-page):not(:has(.login-container)) {
    display: block !important;
    width: 100% !important;
    min-height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    align-items: initial !important;
    justify-content: initial !important;
    background: #f4f7fb !important;
}

body:has(main.gerar-alertas-page) main.gerar-alertas-page {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
    margin: 0 !important;
    padding: 28px !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    box-sizing: border-box !important;
    color: #173247;
    font-family: inherit;
}

.gerar-alertas-page *,
.gerar-alertas-page *::before,
.gerar-alertas-page *::after {
    box-sizing: border-box;
}

.gerar-alertas-page h2,
.gerar-alertas-page h3,
.gerar-alertas-page p {
    margin-top: 0;
}

/* Cabeçalho */

.ga-cabecalho {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 25px;
}

.ga-cabecalho h2 {
    margin-bottom: 8px;
    color: #173247;
    font-size: clamp(24px, 2vw, 31px);
    font-weight: 800;
}

.ga-descricao {
    max-width: 760px;
    margin-bottom: 0;
    color: #718096;
    font-size: 14px;
    line-height: 1.7;
}

.ga-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 9px;
    background: #e8f5f8;
    color: #117f94;
    font-size: 12px;
    font-weight: 800;
}

/* Indicadores */

.ga-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.ga-card {
    min-width: 0;
    padding: 22px;
    border: 1px solid #e1e8ef;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 4px 15px rgba(20, 45, 65, .045);
}

.ga-card-label {
    display: block;
    margin-bottom: 10px;
    color: #718096;
    font-size: 13px;
}

.ga-card-valor {
    display: block;
    color: #173247;
    font-size: 30px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.ga-card.pendente {
    border-left: 4px solid #e3a128;
}

.ga-card.resolvido {
    border-left: 4px solid #209765;
}

/* Mensagens de retorno */

.ga-alerta {
    margin-bottom: 20px;
    padding: 15px 18px;
    border: 1px solid;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.ga-alerta.sucesso {
    border-color: #bce9cb;
    background: #effbf3;
    color: #187746;
}

.ga-alerta.erro {
    border-color: #f0caca;
    background: #fff0f0;
    color: #a82e2e;
}

/* Painéis */

.ga-painel {
    width: 100%;
    min-width: 0;
    margin-bottom: 24px;
    padding: 26px;
    border: 1px solid #e1e8ef;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 4px 18px rgba(20, 45, 65, .05);
}

.ga-painel-topo {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 22px;
}

.ga-painel-topo h3 {
    margin-bottom: 7px;
    color: #173247;
    font-size: 19px;
    font-weight: 800;
}

.ga-painel-topo p {
    margin-bottom: 0;
    color: #718096;
    font-size: 13px;
    line-height: 1.6;
}

/* Botão */

.ga-formulario {
    width: 100%;
    margin: 0;
}

.ga-botao {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    min-height: 46px;
    padding: 12px 20px;
    border: 0;
    border-radius: 9px;
    background: #117f94;
    color: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
    cursor: pointer;
    transition: background .2s, transform .2s;
}

.ga-botao:hover {
    background: #0d6d7f;
    transform: translateY(-1px);
}

.ga-botao:focus-visible {
    outline: 3px solid rgba(17, 127, 148, .25);
    outline-offset: 3px;
}

.ga-aviso {
    margin: 13px 0 0;
    color: #718096;
    font-size: 12px;
    line-height: 1.7;
}

/* Pesquisa e filtros */

.ga-filtros {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.ga-filtros input,
.ga-filtros select {
    min-width: 170px;
    max-width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #dce5ed;
    border-radius: 9px;
    background: #fff;
    color: #173247;
    font-family: inherit;
    font-size: 13px;
}

.ga-filtros input {
    width: 260px;
}

.ga-filtros input:focus,
.ga-filtros select:focus {
    outline: none;
    border-color: #117f94;
    box-shadow: 0 0 0 3px rgba(17, 127, 148, .12);
}

/* Tabela */

.ga-tabela-wrapper {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border: 1px solid #dfe7ef;
    border-radius: 11px;
    -webkit-overflow-scrolling: touch;
}

.ga-tabela {
    width: 100%;
    min-width: 850px;
    margin: 0;
    border-collapse: collapse;
    border-spacing: 0;
    table-layout: auto;
    background: #fff;
}

.ga-tabela thead th {
    padding: 15px 16px;
    border-bottom: 1px solid #dfe7ef;
    background: #f1f6fa;
    color: #173247;
    font-size: 11px;
    font-weight: 800;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.ga-tabela tbody td {
    padding: 16px;
    border-bottom: 1px solid #edf1f5;
    color: #354b5d;
    font-size: 13px;
    line-height: 1.6;
    text-align: left;
    vertical-align: middle;
}

.ga-tabela tbody tr:last-child td {
    border-bottom: 0;
}

.ga-tabela tbody tr:hover {
    background: #f8fbfd;
}

.ga-tabela tbody tr[hidden] {
    display: none !important;
}

.ga-data {
    min-width: 145px;
    white-space: nowrap;
}

.ga-tipo {
    min-width: 150px;
    color: #173247 !important;
    font-weight: 700;
}

.ga-mensagem {
    min-width: 260px;
    max-width: 460px;
    overflow-wrap: anywhere;
    white-space: normal;
}

/* Etiquetas de nível */

.ga-nivel,
.ga-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.ga-nivel.aviso {
    background: #e9f2ff;
    color: #245ca8;
}

.ga-nivel.critico {
    background: #ffebeb;
    color: #bd3030;
}

.ga-nivel.alto {
    background: #fff0e5;
    color: #a94d12;
}

.ga-nivel.medio {
    background: #fff5d9;
    color: #896300;
}

.ga-nivel.padrao {
    background: #edf0f4;
    color: #536477;
}

/* Status */

.ga-status.pendente {
    background: #fff3dc;
    color: #936000;
}

.ga-status.resolvido {
    background: #e5f7ec;
    color: #187746;
}

/* Estados vazios */

.ga-vazio {
    padding: 42px 20px !important;
    text-align: center !important;
}

.ga-vazio strong {
    display: block;
    margin-bottom: 7px;
    color: #173247;
    font-size: 15px;
}

.ga-vazio span {
    color: #718096;
    font-size: 13px;
}

.ga-sem-resultado {
    display: none;
    padding: 24px 12px;
    color: #718096;
    font-size: 13px;
    text-align: center;
}

/* Rodapé da tabela */

.ga-rodape {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
    color: #718096;
    font-size: 12px;
}

/* Responsividade */

@media (max-width: 1100px) {
    body:has(main.gerar-alertas-page) main.gerar-alertas-page {
        padding: 22px !important;
    }

    .ga-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    body:has(main.gerar-alertas-page) main.gerar-alertas-page {
        padding: 14px !important;
    }

    .ga-grid {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .ga-card {
        padding: 16px;
    }

    .ga-card-valor {
        font-size: 25px;
    }

    .ga-painel {
        padding: 15px;
    }

    .ga-painel-topo {
        flex-direction: column;
    }

    .ga-painel-topo > div,
    .ga-filtros,
    .ga-filtros input,
    .ga-filtros select {
        width: 100%;
        min-width: 0;
    }

    .ga-rodape {
        flex-direction: column;
    }

    .ga-tag {
        max-width: 100%;
    }
}
</style>

<main class="gerar-alertas-page">

    <header class="ga-cabecalho">
        <div>
            <h2>Central de Alertas</h2>

            <p class="ga-descricao">
                Monitore os veículos em manutenção e acompanhe
                as notificações automáticas da operação logística.
            </p>
        </div>

        <span class="ga-tag">
            Monitoramento automático
        </span>
    </header>

    <?php if ($mensagem !== ''): ?>
        <div class="ga-alerta sucesso" role="status">
            <?= gerarAlertaEsc($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($erro !== ''): ?>
        <div class="ga-alerta erro" role="alert">
            <?= gerarAlertaEsc($erro) ?>
        </div>
    <?php endif; ?>

    <section class="ga-grid" aria-label="Resumo dos alertas">

        <article class="ga-card">
            <span class="ga-card-label">Alertas registrados</span>
            <strong class="ga-card-valor"><?= $totalAlertas ?></strong>
        </article>

        <article class="ga-card pendente">
            <span class="ga-card-label">Alertas pendentes</span>
            <strong class="ga-card-valor"><?= $pendentes ?></strong>
        </article>

        <article class="ga-card resolvido">
            <span class="ga-card-label">Alertas resolvidos</span>
            <strong class="ga-card-valor"><?= $resolvidos ?></strong>
        </article>

    </section>

    <section class="ga-painel">

        <div class="ga-painel-topo">
            <div>
                <h3>Gerar alertas de manutenção</h3>

                <p>
                    Verifique os veículos marcados como
                    <strong>em manutenção</strong> e registre
                    notificações que ainda não existam.
                </p>
            </div>
        </div>

        <form
            class="ga-formulario"
            method="POST"
            action="gerar_alertas.php"
        >
            <?= csrf_campo() ?>

            <button class="ga-botao" type="submit">
                Verificar veículos e gerar alertas
            </button>
        </form>

        <p class="ga-aviso">
            A verificação evita criar novamente um alerta pendente
            com a mesma mensagem. Os alertas existentes não são
            apagados por esta operação.
        </p>

    </section>

    <section class="ga-painel">

        <div class="ga-painel-topo">
            <div>
                <h3>Alertas recentes</h3>

                <p>
                    Histórico dos últimos <?= $totalAlertas ?>
                    registros carregados.
                </p>
            </div>

            <div class="ga-filtros">
                <input
                    type="search"
                    id="gaBusca"
                    placeholder="Buscar por tipo ou mensagem..."
                    aria-label="Pesquisar alertas"
                >

                <select id="gaFiltro" aria-label="Filtrar por status">
                    <option value="">Todos os status</option>
                    <option value="pendente">Pendentes</option>
                    <option value="resolvido">Resolvidos</option>
                </select>
            </div>
        </div>

        <div class="ga-tabela-wrapper">
            <table class="ga-tabela">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Nível</th>
                        <th>Mensagem</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody id="gaCorpo">

                <?php if (empty($alertas)): ?>

                    <tr>
                        <td colspan="5" class="ga-vazio">
                            <strong>Nenhum alerta registrado</strong>
                            <span>
                                Clique em “Verificar veículos e gerar alertas”
                                para iniciar uma verificação.
                            </span>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($alertas as $a): ?>
                        <?php
                        $nivel = trim(
                            (string) ($a['nivel'] ?? 'aviso')
                        );

                        $nivelNormalizado = function_exists('mb_strtolower')
                            ? mb_strtolower($nivel, 'UTF-8')
                            : strtolower($nivel);

                        if (in_array(
                            $nivelNormalizado,
                            ['critico', 'crítico', 'critica', 'crítica'],
                            true
                        )) {
                            $classeNivel = 'critico';
                        } elseif (in_array(
                            $nivelNormalizado,
                            ['alto', 'alta'],
                            true
                        )) {
                            $classeNivel = 'alto';
                        } elseif (in_array(
                            $nivelNormalizado,
                            ['medio', 'médio', 'media', 'média'],
                            true
                        )) {
                            $classeNivel = 'medio';
                        } elseif (in_array(
                            $nivelNormalizado,
                            ['aviso', 'baixo', 'baixa', 'informativo'],
                            true
                        )) {
                            $classeNivel = 'aviso';
                        } else {
                            $classeNivel = 'padrao';
                        }

                        $resolvido = !empty($a['resolvido']);
                        $status = $resolvido ? 'resolvido' : 'pendente';

                        $tipo = strtoupper(
                            str_replace(
                                '_',
                                ' ',
                                (string) ($a['tipo'] ?? 'Geral')
                            )
                        );

                        $dataCriacao = $a['criado_em'] ?? '';

                        $nivelExibicao = function_exists('mb_strtoupper')
                            ? mb_strtoupper($nivel, 'UTF-8')
                            : strtoupper($nivel);
                        ?>

                        <tr data-status="<?= gerarAlertaEsc($status) ?>">

                            <td class="ga-data">
                                <?= gerarAlertaEsc($dataCriacao) ?>
                            </td>

                            <td class="ga-tipo">
                                <?= gerarAlertaEsc($tipo) ?>
                            </td>

                            <td>
                                <span class="ga-nivel <?= gerarAlertaEsc($classeNivel) ?>">
                                    <?= gerarAlertaEsc($nivelExibicao) ?>
                                </span>
                            </td>

                            <td class="ga-mensagem">
                                <?= gerarAlertaEsc($a['mensagem'] ?? '') ?>
                            </td>

                            <td>
                                <span class="ga-status <?= gerarAlertaEsc($status) ?>">
                                    <?= $resolvido ? 'Resolvido' : 'Pendente' ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <div
            id="gaSemResultado"
            class="ga-sem-resultado"
            role="status"
        >
            Nenhum alerta corresponde à sua pesquisa.
        </div>

        <div class="ga-rodape">
            <span>
                Registros exibidos:
                <strong><?= $totalAlertas ?></strong>
            </span>

            <span>
                A listagem é atualizada ao recarregar a página.
            </span>
        </div>

    </section>

</main>

<script>
(function () {
    'use strict';

    const busca = document.getElementById('gaBusca');
    const filtro = document.getElementById('gaFiltro');
    const corpo = document.getElementById('gaCorpo');
    const semResultado = document.getElementById('gaSemResultado');

    if (!busca || !filtro || !corpo || !semResultado) {
        return;
    }

    function filtrar() {
        const termo = busca.value
            .toLocaleLowerCase('pt-BR')
            .trim();

        const statusSelecionado = filtro.value;
        const linhas = corpo.querySelectorAll('tr[data-status]');

        let quantidadeVisivel = 0;

        linhas.forEach(function (linha) {
            const texto = linha.textContent
                .toLocaleLowerCase('pt-BR');

            const correspondeTexto = texto.includes(termo);

            const correspondeStatus =
                statusSelecionado === '' ||
                linha.dataset.status === statusSelecionado;

            const mostrar = correspondeTexto && correspondeStatus;

            linha.hidden = !mostrar;

            if (mostrar) {
                quantidadeVisivel++;
            }
        });

        semResultado.style.display =
            linhas.length > 0 && quantidadeVisivel === 0
                ? 'block'
                : 'none';
    }

    busca.addEventListener('input', filtrar);
    filtro.addEventListener('change', filtrar);
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
