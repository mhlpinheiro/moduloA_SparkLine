
<?php

$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "senn_logistica_db";

$pdo = null;
$tipoBanco = "mysql";

try {
    $dsn = "mysql:host=$host;dbname=$banco;charset=utf8mb4";

    $pdo = new PDO($dsn, $usuario, $senha);

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    $pdo->setAttribute(
        PDO::ATTR_EMULATE_PREPARES,
        false
    );

} catch (PDOException $erroMySQL) {

    error_log(
        "Erro de conexão MySQL: " .
        $erroMySQL->getMessage()
    );

    http_response_code(500);

    exit(
        "Não foi possível conectar ao banco de dados. " .
        "Verifique se o MySQL está ativo no XAMPP, " .
        "se o banco senn_logistica_db existe e " .
        "se as configurações estão corretas."
    );
}

?>
