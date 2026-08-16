<?php

require_once __DIR__ . '/api-client.php';

const PAPEL_LABELS = [
    'operator' => 'Operator',
    'superadmin' => 'SuperAdmin',
    'master' => 'Master',
];

function current_user(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['auth_usuario'] ?? null;
}

function is_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['auth_token']) && !empty($_SESSION['auth_usuario']);
}

function usuario_tem_papel(string ...$papeis): bool
{
    $usuario = current_user();
    return $usuario !== null && in_array($usuario['papel'], $papeis, true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASEURL . '/login.php');
        exit;
    }
}

function require_papel(string ...$papeis): void
{
    require_login();
    if (!usuario_tem_papel(...$papeis)) {
        http_response_code(403);
        echo '<div style="font-family:sans-serif; padding:40px;">Você não tem permissão para acessar esta página.</div>';
        exit;
    }
}

function do_login(string $email, string $senha, bool $lembrar): array
{
    $resp = api_post('/api/auth/login', ['email' => $email, 'senha' => $senha, 'lembrar' => $lembrar]);
    if ($resp['ok']) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['auth_token'] = $resp['body']['access_token'];
        $_SESSION['auth_usuario'] = $resp['body']['usuario'];
    }
    return $resp;
}

function do_logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['auth_token'], $_SESSION['auth_usuario']);
}

function papel_label(string $papel): string
{
    return PAPEL_LABELS[$papel] ?? ucfirst($papel);
}
