<?php

/**
 * Router pro servidor embutido do PHP (php -S), usado só em desenvolvimento
 * local. O servidor embutido não lê .htaccess, então as regras de URL limpa
 * (sem ".php") que o Apache aplica via mod_rewrite (ver public/.htaccess)
 * são replicadas aqui. Uso: php -S localhost:8080 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri === '/') {
    $uri = '/index';
}

// Acesso direto a "/pagina.php" -> redireciona pra "/pagina" (mesmo
// comportamento do redirect 301 feito pelo .htaccess no Apache). Precisa
// vir antes do passthrough de arquivo real abaixo, senão o servidor
// embutido serviria o .php direto sem redirecionar.
if (preg_match('#^/(.+)\.php$#', $uri, $m)) {
    header('Location: /' . $m[1], true, 301);
    exit;
}

$caminhoFisico = __DIR__ . $uri;

// Arquivo/pasta real (assets, etc.) -> deixa o servidor embutido servir normal.
if (file_exists($caminhoFisico) && !is_dir($caminhoFisico)) {
    return false;
}

// "/pagina" -> serve "pagina.php" internamente.
$scriptPhp = $caminhoFisico . '.php';
if (file_exists($scriptPhp)) {
    // Ajusta SCRIPT_NAME/PHP_SELF pra refletir o arquivo .php real, do
    // jeito que o Apache faz com o rewrite interno — sem isso, o
    // destaque do item ativo no menu (header.php) e a detecção de
    // BASEURL (config.php) veriam a URL limpa em vez do arquivo físico.
    $_SERVER['SCRIPT_NAME'] = rtrim($uri, '/') . '.php';
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    require $scriptPhp;
    return true;
}

return false;
