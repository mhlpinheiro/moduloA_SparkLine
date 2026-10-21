
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| SENN AI-X — Gerenciamento de destinatários
|--------------------------------------------------------------------------
| Perfis autorizados: administrador, gestor e despachante.
| A tabela destinatarios é criada somente se ainda não existir.
|--------------------------------------------------------------------------
*/

$perfil = strtolower(trim((string) ($_SESSION['usuario_nivel'] ?? '')));

$perfisPermitidos = [
    'administrador',
    'gestor',
    'gestor_frota',
    'despachante'
];

if (!in_array($perfil, $perfisPermitidos, true)) {
    http_response_code(403);
    exit('Acesso negado. Você não tem permissão para gerenciar destinatários.');
}

function destinatarioEsc($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$erro = '';
$mensagem = '';
$busca = trim((string) ($_GET['busca'] ?? ''));
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;

$dados = [
    'id' => 0,
    'nome' => '',
    'email' => '',
    'telefone' => '',
    'cep' => '',
    'endereco' => '',
    'numero' => '',
    'complemento' => '',
    'bairro' => '',
    'cidade' => '',
    'estado' => '',
    'consentimento_lgpd' => 0
];

$destinatarios = [];
$totalDestinatarios = 0;
$totalComConsentimento = 0;

/*
|--------------------------------------------------------------------------
| Criação inicial da tabela
|--------------------------------------------------------------------------
| Se a tabela já existir, sua estrutura não será alterada.
|--------------------------------------------------------------------------
*/

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS destinatarios (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(120) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            telefone VARCHAR(30) DEFAULT NULL,
            cep VARCHAR(12) DEFAULT NULL,
            endereco VARCHAR(190) NOT NULL,
            numero VARCHAR(20) DEFAULT NULL,
            complemento VARCHAR(120) DEFAULT NULL,
            bairro VARCHAR(120) DEFAULT NULL,
            cidade VARCHAR(120) NOT NULL,
            estado CHAR(2) NOT NULL,
            consentimento_lgpd TINYINT(1) NOT NULL DEFAULT 0,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_destinatarios_nome (nome),
            INDEX idx_destinatarios_email (email)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

} catch (Throwable $e) {
    error_log('Erro ao preparar tabela de destinatários: ' . $e->getMessage());
    http_response_code(500);
    exit(
        'Não foi possível preparar o cadastro de destinatários. '
        . 'Verifique a conexão e as permissões do banco de dados.'
    );
}

/*
|--------------------------------------------------------------------------
| Mensagens após redirecionamento
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['destinatarios_flash'])) {
    $mensagem = (string) $_SESSION['destinatarios_flash'];
    unset($_SESSION['destinatarios_flash']);
}

/*
|--------------------------------------------------------------------------
| Recebimento e validação dos formulários
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $acao = (string) ($_POST['acao'] ?? '');

    try {
        if ($acao === 'excluir') {
            if ($perfil !== 'administrador') {
                throw new RuntimeException(
                    'Somente o administrador pode excluir destinatários.'
                );
            }

            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException(
                    'Destinatário inválido para exclusão.'
                );
            }

            $excluir = $pdo->prepare(
                'DELETE FROM destinatarios WHERE id = :id'
            );

            $excluir->execute(['id' => $id]);

            if ($excluir->rowCount() === 0) {
                throw new RuntimeException(
                    'O destinatário não foi encontrado.'
                );
            }

            $_SESSION['destinatarios_flash'] =
                'Destinatário excluído com sucesso.';

            header('Location: destinatarios.php');
            exit;
        }

        if (!in_array($acao, ['cadastrar', 'atualizar'], true)) {
            throw new RuntimeException('Operação inválida.');
        }

        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        ) ?: 0;

        $dados = [
            'id' => $id,
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'cep' => trim((string) ($_POST['cep'] ?? '')),
            'endereco' => trim((string) ($_POST['endereco'] ?? '')),
            'numero' => trim((string) ($_POST['numero'] ?? '')),
            'complemento' => trim((string) ($_POST['complemento'] ?? '')),
            'bairro' => trim((string) ($_POST['bairro'] ?? '')),
            'cidade' => trim((string) ($_POST['cidade'] ?? '')),
            'estado' => strtoupper(
                trim((string) ($_POST['estado'] ?? ''))
            ),
            'consentimento_lgpd' =>
                isset($_POST['consentimento_lgpd']) ? 1 : 0
        ];

        if (mb_strlen($dados['nome']) < 2 ||
            mb_strlen($dados['nome']) > 120) {
            throw new RuntimeException(
                'O nome deve conter entre 2 e 120 caracteres.'
            );
        }

        if ($dados['email'] !== '' &&
            !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'Informe um endereço de e-mail válido.'
            );
        }

        if (strlen($dados['email']) > 190) {
            throw new RuntimeException(
                'O e-mail ultrapassa o limite permitido.'
            );
        }

        if (strlen($dados['telefone']) > 30) {
            throw new RuntimeException(
                'O telefone ultrapassa o limite permitido.'
            );
        }

        if (strlen($dados['cep']) > 12) {
            throw new RuntimeException(
                'O CEP ultrapassa o limite permitido.'
            );
        }

        if ($dados['endereco'] === '' ||
            mb_strlen($dados['endereco']) > 190) {
            throw new RuntimeException(
                'Informe um endereço válido.'
            );
        }

        if ($dados['cidade'] === '' ||
            mb_strlen($dados['cidade']) > 120) {
            throw new RuntimeException(
                'Informe uma cidade válida.'
            );
        }

        if (!preg_match('/^[A-Z]{2}$/', $dados['estado'])) {
            throw new RuntimeException(
                'Informe a sigla do estado com duas letras.'
            );
        }

        if (mb_strlen($dados['numero']) > 20 ||
            mb_strlen($dados['complemento']) > 120 ||
            mb_strlen($dados['bairro']) > 120) {
            throw new RuntimeException(
                'Um dos campos de endereço ultrapassa o limite permitido.'
            );
        }

        if ($acao === 'cadastrar') {
            $sql = "INSERT INTO destinatarios (
                        nome, email, telefone, cep, endereco,
                        numero, complemento, bairro, cidade,
                        estado, consentimento_lgpd
                    ) VALUES (
                        :nome, :email, :telefone, :cep, :endereco,
                        :numero, :complemento, :bairro, :cidade,
                        :estado, :consentimento_lgpd
                    )";

        } else {
            if ($id < 1) {
                throw new RuntimeException(
                    'Destinatário inválido para edição.'
                );
            }

            $sql = "UPDATE destinatarios SET
                        nome = :nome,
                        email = :email,
                        telefone = :telefone,
                        cep = :cep,
                        endereco = :endereco,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        estado = :estado,
                        consentimento_lgpd = :consentimento_lgpd
                    WHERE id = :id";
        }

        $parametros = [
            'nome' => $dados['nome'],
            'email' => $dados['email'] !== '' ? $dados['email'] : null,
            'telefone' => $dados['telefone'] !== '' ? $dados['telefone'] : null,
            'cep' => $dados['cep'] !== '' ? $dados['cep'] : null,
            'endereco' => $dados['endereco'],
            'numero' => $dados['numero'] !== '' ? $dados['numero'] : null,
            'complemento' => $dados['complemento'] !== ''
                ? $dados['complemento'] : null,
            'bairro' => $dados['bairro'] !== '' ? $dados['bairro'] : null,
            'cidade' => $dados['cidade'],
            'estado' => $dados['estado'],
            'consentimento_lgpd' => $dados['consentimento_lgpd']
        ];

        if ($acao === 'atualizar') {
            $parametros['id'] = $id;
        }

        $gravar = $pdo->prepare($sql);
        $gravar->execute($parametros);

        if ($acao === 'atualizar' && $gravar->rowCount() === 0) {
            $verificar = $pdo->prepare(
                'SELECT id FROM destinatarios WHERE id = :id'
            );
            $verificar->execute(['id' => $id]);

            if (!$verificar->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'O destinatário não foi encontrado.'
                );
            }
        }

        $_SESSION['destinatarios_flash'] =
            $acao === 'cadastrar'
                ? 'Destinatário cadastrado com sucesso.'
                : 'Destinatário atualizado com sucesso.';

        header('Location: destinatarios.php');
        exit;

    } catch (RuntimeException $e) {
        $erro = $e->getMessage();

    } catch (Throwable $e) {
        error_log('Erro no gerenciamento de destinatários: ' . $e->getMessage());

        $erro = 'Não foi possível concluir a operação. Verifique os dados e tente novamente.';
    }
}

/*
|--------------------------------------------------------------------------
| Carrega destinatário selecionado para edição
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editarId > 0) {
    try {
        $consultaEditar = $pdo->prepare(
            'SELECT * FROM destinatarios WHERE id = :id LIMIT 1'
        );

        $consultaEditar->execute(['id' => $editarId]);
        $registro = $consultaEditar->fetch(PDO::FETCH_ASSOC);

        if ($registro) {
            $dados = array_merge($dados, $registro);
        } else {
            $erro = 'O destinatário solicitado não foi encontrado.';
            $editarId = 0;
        }

    } catch (Throwable $e) {
        error_log('Erro ao carregar destinatário: ' . $e->getMessage());
        $erro = 'Não foi possível carregar os dados para edição.';
        $editarId = 0;
    }
}

/*
|--------------------------------------------------------------------------
| Lista e indicadores
|--------------------------------------------------------------------------
*/

try {
    $totalDestinatarios = (int) $pdo->query(
        'SELECT COUNT(*) FROM destinatarios'
    )->fetchColumn();

    $totalComConsentimento = (int) $pdo->query(
        'SELECT COUNT(*) FROM destinatarios WHERE consentimento_lgpd = 1'
    )->fetchColumn();

    if ($busca !== '') {
        $consultaLista = $pdo->prepare(
            "SELECT id, nome, email, telefone, cidade, estado,
                    consentimento_lgpd, criado_em
             FROM destinatarios
             WHERE nome LIKE :busca
                OR email LIKE :busca
                OR telefone LIKE :busca
                OR cidade LIKE :busca
                OR estado LIKE :busca
             ORDER BY nome ASC
             LIMIT 300"
        );

        $consultaLista->execute([
            'busca' => '%' . $busca . '%'
        ]);

    } else {
        $consultaLista = $pdo->query(
            "SELECT id, nome, email, telefone, cidade, estado,
                    consentimento_lgpd, criado_em
             FROM destinatarios
             ORDER BY nome ASC
             LIMIT 300"
        );
    }

    $destinatarios = $consultaLista->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Erro ao listar destinatários: ' . $e->getMessage());

    $erro = $erro ?: 'Não foi possível carregar a lista de destinatários.';
}

require_once __DIR__ . '/header.php';
?>

<style>
/* Estilos exclusivos da página de destinatários */

body:has(main.destinatarios-page):not(:has(.login-container)) {
    display: block !important;
    min-height: 100vh;
    margin: 0 !important;
    padding: 0 !important;
    align-items: initial !important;
    justify-content: initial !important;
    background: #f4f7fb !important;
}

body:has(main.destinatarios-page) main.destinatarios-page {
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

.destinatarios-page *,
.destinatarios-page *::before,
.destinatarios-page *::after {
    box-sizing: border-box;
}

.destinatarios-page h2,
.destinatarios-page h3,
.destinatarios-page p {
    margin-top: 0;
}

.dt-cabecalho {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}

.dt-cabecalho h2 {
    margin-bottom: 7px;
    color: #173247;
    font-size: clamp(24px, 2vw, 31px);
    font-weight: 800;
}

.dt-descricao {
    margin-bottom: 0;
    color: #718096;
    font-size: 14px;
    line-height: 1.7;
}

.dt-etiqueta {
    padding: 10px 14px;
    border-radius: 9px;
    background: #e8f5f8;
    color: #117f94;
    font-size: 12px;
    font-weight: 800;
}

.dt-resumo {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.dt-card,
.dt-painel {
    min-width: 0;
    padding: 22px;
    border: 1px solid #e1e8ef;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 4px 16px rgba(20, 45, 65, .045);
}

.dt-card small {
    display: block;
    margin-bottom: 10px;
    color: #718096;
    font-size: 13px;
}

.dt-card strong {
    color: #173247;
    font-size: 29px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.dt-painel {
    margin-bottom: 22px;
}

.dt-painel h3 {
    margin-bottom: 7px;
    color: #173247;
    font-size: 18px;
}

.dt-ajuda {
    margin-bottom: 20px;
    color: #718096;
    font-size: 13px;
    line-height: 1.6;
}

.dt-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.dt-campo {
    min-width: 0;
}

.dt-campo.completo {
    grid-column: 1 / -1;
}

.dt-campo label {
    display: block;
    margin-bottom: 7px;
    color: #354b5d;
    font-size: 12px;
    font-weight: 700;
}

.dt-campo input {
    width: 100%;
    min-height: 43px;
    padding: 11px 12px;
    border: 1px solid #dce5ed;
    border-radius: 8px;
    background: #fff;
    color: #173247;
    font-family: inherit;
    font-size: 13px;
}

.dt-campo input:focus {
    outline: none;
    border-color: #117f94;
    box-shadow: 0 0 0 3px rgba(17, 127, 148, .12);
}

.dt-consentimento {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 18px 0;
    color: #526579;
    font-size: 12px;
    line-height: 1.6;
}

.dt-consentimento input {
    margin-top: 3px;
}

.dt-acoes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.dt-botao {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 42px;
    padding: 11px 16px;
    border: 0;
    border-radius: 8px;
    background: #117f94;
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.dt-botao:hover {
    background: #0d6d7f;
}

.dt-botao.secundario {
    border: 1px solid #dce5ed;
    background: #fff;
    color: #354b5d;
}

.dt-botao.secundario:hover {
    background: #f1f6fa;
}

.dt-alerta {
    margin-bottom: 18px;
    padding: 14px 17px;
    border: 1px solid;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.6;
}

.dt-alerta.sucesso {
    border-color: #bce9cb;
    background: #effbf3;
    color: #187746;
}

.dt-alerta.erro {
    border-color: #f0caca;
    background: #fff0f0;
    color: #a82e2e;
}

.dt-lista-topo {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 18px;
}

.dt-busca {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.dt-busca input {
    width: min(320px, 100%);
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #dce5ed;
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
}

.dt-tabela-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #dfe7ef;
    border-radius: 10px;
}

.dt-tabela {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
    background: #fff;
}

.dt-tabela th {
    padding: 14px;
    border-bottom: 1px solid #dfe7ef;
    background: #f1f6fa;
    color: #173247;
    font-size: 11px;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}

.dt-tabela td {
    padding: 14px;
    border-bottom: 1px solid #edf1f5;
    color: #354b5d;
    font-size: 12px;
    line-height: 1.6;
    vertical-align: middle;
}

.dt-tabela tr:last-child td {
    border-bottom: 0;
}

.dt-tabela tr:hover {
    background: #f8fbfd;
}

.dt-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #fff3dc;
    color: #936000;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.dt-status.ok {
    background: #e5f7ec;
    color: #187746;
}

.dt-link {
    color: #117f94;
    font-weight: 800;
    text-decoration: none;
}

.dt-link:hover {
    text-decoration: underline;
}

.dt-form-excluir {
    display: inline;
}

.dt-botao-excluir {
    padding: 5px 8px;
    border: 1px solid #f0caca;
    border-radius: 6px;
    background: #fff0f0;
    color: #a82e2e;
    font: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.dt-vazio {
    padding: 30px !important;
    color: #718096 !important;
    text-align: center;
}

@media (max-width: 900px) {
    .dt-resumo {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    body:has(main.destinatarios-page) main.destinatarios-page {
        padding: 14px !important;
    }

    .dt-resumo,
    .dt-form-grid {
        grid-template-columns: 1fr;
    }

    .dt-campo.completo {
        grid-column: auto;
    }

    .dt-card,
    .dt-painel {
        padding: 16px;
    }

    .dt-busca,
    .dt-busca input {
        width: 100%;
    }

    .dt-acoes .dt-botao {
        flex: 1;
    }
}
</style>

<main class="destinatarios-page">

    <header class="dt-cabecalho">
        <div>
            <h2>Destinatários</h2>
            <p class="dt-descricao">
                Cadastre e mantenha os dados necessários para a gestão das entregas.
            </p>
        </div>

        <span class="dt-etiqueta">Cadastro logístico</span>
    </header>

    <?php if ($mensagem !== ''): ?>
        <div class="dt-alerta sucesso" role="status">
            <?= destinatarioEsc($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($erro !== ''): ?>
        <div class="dt-alerta erro" role="alert">
            <?= destinatarioEsc($erro) ?>
        </div>
    <?php endif; ?>

    <section class="dt-resumo" aria-label="Resumo de destinatários">
        <article class="dt-card">
            <small>Destinatários cadastrados</small>
            <strong><?= $totalDestinatarios ?></strong>
        </article>

        <article class="dt-card">
            <small>Com consentimento registrado</small>
            <strong><?= $totalComConsentimento ?></strong>
        </article>

        <article class="dt-card">
            <small>Sem consentimento registrado</small>
            <strong><?= max(0, $totalDestinatarios - $totalComConsentimento) ?></strong>
        </article>
    </section>

    <section class="dt-painel" id="formulario-destinatario">
        <h3>
            <?= $dados['id'] ? 'Editar destinatário' : 'Cadastrar destinatário' ?>
        </h3>

        <p class="dt-ajuda">
            Preencha os dados necessários para identificar o destinatário e localizar a entrega.
        </p>

        <form method="POST" action="destinatarios.php">
            <?= csrf_campo() ?>

            <input
                type="hidden"
                name="acao"
                value="<?= $dados['id'] ? 'atualizar' : 'cadastrar' ?>"
            >

            <?php if ($dados['id']): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $dados['id'] ?>"
                >
            <?php endif; ?>

            <div class="dt-form-grid">

                <div class="dt-campo completo">
                    <label for="nome">Nome completo *</label>
                    <input
                        id="nome"
                        name="nome"
                        type="text"
                        maxlength="120"
                        autocomplete="name"
                        required
                        value="<?= destinatarioEsc($dados['nome']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="email">E-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="190"
                        autocomplete="email"
                        value="<?= destinatarioEsc($dados['email']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="telefone">Telefone</label>
                    <input
                        id="telefone"
                        name="telefone"
                        type="tel"
                        maxlength="30"
                        autocomplete="tel"
                        placeholder="(00) 00000-0000"
                        value="<?= destinatarioEsc($dados['telefone']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="cep">CEP</label>
                    <input
                        id="cep"
                        name="cep"
                        type="text"
                        maxlength="12"
                        autocomplete="postal-code"
                        value="<?= destinatarioEsc($dados['cep']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="endereco">Endereço *</label>
                    <input
                        id="endereco"
                        name="endereco"
                        type="text"
                        maxlength="190"
                        autocomplete="street-address"
                        required
                        value="<?= destinatarioEsc($dados['endereco']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="numero">Número</label>
                    <input
                        id="numero"
                        name="numero"
                        type="text"
                        maxlength="20"
                        value="<?= destinatarioEsc($dados['numero']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="complemento">Complemento</label>
                    <input
                        id="complemento"
                        name="complemento"
                        type="text"
                        maxlength="120"
                        value="<?= destinatarioEsc($dados['complemento']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="bairro">Bairro</label>
                    <input
                        id="bairro"
                        name="bairro"
                        type="text"
                        maxlength="120"
                        autocomplete="address-level3"
                        value="<?= destinatarioEsc($dados['bairro']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="cidade">Cidade *</label>
                    <input
                        id="cidade"
                        name="cidade"
                        type="text"
                        maxlength="120"
                        autocomplete="address-level2"
                        required
                        value="<?= destinatarioEsc($dados['cidade']) ?>"
                    >
                </div>

                <div class="dt-campo">
                    <label for="estado">Estado (UF) *</label>
                    <input
                        id="estado"
                        name="estado"
                        type="text"
                        maxlength="2"
                        minlength="2"
                        pattern="[A-Za-z]{2}"
                        autocomplete="address-level1"
                        placeholder="SP"
                        required
                        value="<?= destinatarioEsc($dados['estado']) ?>"
                    >
                </div>

            </div>

            <label class="dt-consentimento">
                <input
                    type="checkbox"
                    name="consentimento_lgpd"
                    value="1"
                    <?= !empty($dados['consentimento_lgpd']) ? 'checked' : '' ?>
                >
                <span>
                    Consentimento registrado para o tratamento dos dados pessoais
                    conforme a finalidade informada ao titular. Marque somente
                    quando esse consentimento tiver sido efetivamente obtido.
                </span>
            </label>

            <p class="dt-ajuda">
                Utilize os dados exclusivamente para finalidades legítimas da operação.
                O registro deste campo, isoladamente, não comprova conformidade integral com a LGPD.
            </p>

            <div class="dt-acoes">
                <button class="dt-botao" type="submit">
                    <?= $dados['id'] ? 'Salvar alterações' : 'Cadastrar destinatário' ?>
                </button>

                <?php if ($dados['id']): ?>
                    <a class="dt-botao secundario" href="destinatarios.php">
                        Cancelar edição
                    </a>
                <?php else: ?>
                    <button class="dt-botao secundario" type="reset">
                        Limpar formulário
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="dt-painel">
        <div class="dt-lista-topo">
            <div>
                <h3>Destinatários cadastrados</h3>
                <p class="dt-ajuda">
                    Exibindo até 300 registros. Use a pesquisa para localizar um cadastro.
                </p>
            </div>

            <form class="dt-busca" method="GET" action="destinatarios.php">
                <input
                    type="search"
                    name="busca"
                    placeholder="Nome, e-mail, telefone ou cidade..."
                    aria-label="Pesquisar destinatários"
                    value="<?= destinatarioEsc($busca) ?>"
                >

                <button class="dt-botao" type="submit">Pesquisar</button>

                <?php if ($busca !== ''): ?>
                    <a class="dt-botao secundario" href="destinatarios.php">
                        Limpar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="dt-tabela-wrapper">
            <table class="dt-tabela">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Contato</th>
                        <th>Localidade</th>
                        <th>LGPD</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$destinatarios): ?>
                    <tr>
                        <td colspan="6" class="dt-vazio">
                            Nenhum destinatário encontrado.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($destinatarios as $destinatario): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= destinatarioEsc($destinatario['nome']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= destinatarioEsc($destinatario['telefone'] ?? '') ?>

                                <?php if (!empty($destinatario['email'])): ?>
                                    <br>
                                    <?= destinatarioEsc($destinatario['email']) ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= destinatarioEsc($destinatario['cidade']) ?>
                                /
                                <?= destinatarioEsc($destinatario['estado']) ?>
                            </td>

                            <td>
                                <?php if (!empty($destinatario['consentimento_lgpd'])): ?>
                                    <span class="dt-status ok">
                                        Registrado
                                    </span>
                                <?php else: ?>
                                    <span class="dt-status">
                                        Não registrado
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= destinatarioEsc($destinatario['criado_em'] ?? '') ?>
                            </td>

                            <td>
                                <a
                                    class="dt-link"
                                    href="destinatarios.php?editar=<?= (int) $destinatario['id'] ?>"
                                >
                                    Editar
                                </a>

                                <?php if ($perfil === 'administrador'): ?>
                                    <form
                                        class="dt-form-excluir"
                                        method="POST"
                                        action="destinatarios.php"
                                        onsubmit="return confirm('Tem certeza de que deseja excluir este destinatário?');"
                                    >
                                        <?= csrf_campo() ?>

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="excluir"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $destinatario['id'] ?>"
                                        >

                                        <button
                                            class="dt-botao-excluir"
                                            type="submit"
                                        >
                                            Excluir
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/footer.php'; ?>
