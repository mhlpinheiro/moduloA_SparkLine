<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';
exigir_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $tabelas = ['usuarios', 'veiculos', 'motoristas', 'entregas', 'comprovantes_pod', 'alertas', 'logs_notificacoes'];
    $backup = ['gerado_em' => date(DATE_ATOM), 'sistema' => 'SENN AI-X', 'dados' => []];
    foreach ($tabelas as $tabela) {
        // Os nomes vêm de uma lista fixa, nunca de entrada do usuário.
        $backup['dados'][$tabela] = $pdo->query('SELECT * FROM `' . $tabela . '`')->fetchAll(PDO::FETCH_ASSOC);
    }
    $nome = 'senn_backup_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Cache-Control: no-store');
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
require_once __DIR__ . '/header.php';
?>
<main class="container">
    <h2>Backup e recuperação</h2>
    <p>Baixe uma cópia JSON dos dados atuais para armazenamento seguro. O arquivo contém dados pessoais e deve ser protegido.</p>
    <form method="POST" action="backup.php">
        <?= csrf_campo() ?>
        <button type="submit">Baixar backup dos dados</button>
    </form>
    <p><strong>Importante:</strong> este recurso exporta os dados para backup; a restauração deve ser feita por um administrador técnico após validar o arquivo. Guarde o backup fora da pasta pública do site.</p>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
