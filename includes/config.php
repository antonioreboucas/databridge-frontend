<?php

$apiBaseUrl = getenv('DATABRIDGE_API_URL');
if ($apiBaseUrl === false || $apiBaseUrl === '') {
    $apiBaseUrl = 'https://api-databridge.onrender.com';
}

define('API_BASE_URL', rtrim($apiBaseUrl, '/'));

// URL base do próprio frontend (usada para montar todos os links, forms,
// redirects e chamadas AJAX). DATABRIDGE_BASE_URL continua funcionando
// como override manual (ex.: atrás de um proxy reverso), mas por padrão
// é detectada automaticamente a partir do caminho do script atual, para
// que o app funcione tanto na raiz (php -S / VirtualHost apontando para
// frontend/public) quanto num subdiretório do XAMPP (ex.: htdocs/databridge).
$baseUrl = getenv('DATABRIDGE_BASE_URL');
if ($baseUrl === false) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseUrl = ($scriptDir === '/' || $scriptDir === '.') ? '' : $scriptDir;
}
define('BASEURL', rtrim($baseUrl, '/'));

function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
