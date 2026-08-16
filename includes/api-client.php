<?php

require_once __DIR__ . '/config.php';

/**
 * Faz uma requisição HTTP para a API do backend (FastAPI) via cURL.
 * Retorna sempre um array associativo: ['ok' => bool, 'status' => int|null, 'body' => mixed, 'error' => string|null]
 */
function api_request(string $method, string $path, ?array $data = null): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $url = API_BASE_URL . $path;

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (!empty($_SESSION['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['auth_token'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $raw = curl_exec($ch);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => $error];
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 401) {
        unset($_SESSION['auth_token'], $_SESSION['auth_usuario']);
    }

    $body = ($raw === '') ? null : json_decode($raw, true);

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body,
        'error' => null,
    ];
}

function api_get(string $path): array
{
    return api_request('GET', $path);
}

function api_post(string $path, array $data): array
{
    return api_request('POST', $path, $data);
}

function api_put(string $path, array $data): array
{
    return api_request('PUT', $path, $data);
}

function api_delete(string $path): array
{
    return api_request('DELETE', $path);
}

/**
 * Envia um arquivo (multipart/form-data) para a API, junto de campos extras.
 * Usado pelo fluxo de upload de CSV.
 */
function api_upload(string $path, array $campos, string $arquivoTmpPath, string $arquivoNome, string $campoArquivo = 'arquivo'): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $url = API_BASE_URL . $path;

    $campos[$campoArquivo] = new CURLFile($arquivoTmpPath, 'text/csv', $arquivoNome);

    $headers = ['Accept: application/json'];
    if (!empty($_SESSION['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['auth_token'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $campos,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $raw = curl_exec($ch);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => $error];
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 401) {
        unset($_SESSION['auth_token'], $_SESSION['auth_usuario']);
    }

    $body = ($raw === '') ? null : json_decode($raw, true);

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body,
        'error' => null,
    ];
}

/**
 * Extrai uma mensagem de erro legível de uma resposta da API (formato FastAPI: {"detail": "..."}).
 */
function api_error_message(array $response): string
{
    if ($response['error'] !== null) {
        return 'Não foi possível conectar à API: ' . $response['error'];
    }
    $body = $response['body'];
    if (is_array($body) && isset($body['detail'])) {
        return is_string($body['detail']) ? $body['detail'] : json_encode($body['detail']);
    }
    return 'Erro inesperado ao chamar a API (status ' . $response['status'] . ').';
}
