<?php
/**
 * Helpers centrais de segurança do SENN AI-X.
 * Incluir este arquivo antes de qualquer saída HTML.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Cookies de sessão com proteções apropriadas para HTTP local e HTTPS em produção.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_campo')) {
    function csrf_campo(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_exigir')) {
    function csrf_exigir(): void
    {
        $enviado = $_POST['csrf_token'] ?? '';
        if (
            !is_string($enviado) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $enviado)
        ) {
            http_response_code(403);
            exit('Requisição inválida. Recarregue a página e tente novamente.');
        }
    }
}
