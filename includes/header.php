<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api-client.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

require_login();
$usuarioLogado = current_user();

$pageTitle = $pageTitle ?? 'DataBridge';
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    'dashboard.php' => ['label' => 'Dashboard', 'icon' => 'dashboard'],
    'conexoes.php' => ['label' => 'Conexões', 'icon' => 'connections'],
    'schemas.php' => ['label' => 'Tabelas', 'icon' => 'table'],
    'upload.php' => ['label' => 'Upload', 'icon' => 'upload'],
    'pastas-monitoradas.php' => ['label' => 'Pastas', 'icon' => 'folder'],
    'templates-mapeamento.php' => ['label' => 'Templates', 'icon' => 'templates'],
    'historico-importacoes.php' => ['label' => 'Histórico', 'icon' => 'history'],
];

if (usuario_tem_papel('master')) {
    $navItems['usuarios.php'] = ['label' => 'Usuários', 'icon' => 'users'];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$healthResp = api_get('/api/health');
$apiOnline = $healthResp['ok'];

$iniciais = '';
foreach (explode(' ', trim($usuarioLogado['nome'] ?? '')) as $parte) {
    $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
}
$iniciais = mb_substr($iniciais, 0, 2);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · DataBridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASEURL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <?= icon('connections', 'icon icon-brand') ?>
            <div>
                DataBridge
                <div class="sidebar__tagline">CSV to PostgreSQL</div>
            </div>
            <button type="button" class="sidebar__close" id="btn-close-sidebar" aria-label="Fechar menu"><?= icon('x', 'icon icon-sm') ?></button>
        </div>
        <nav class="sidebar__nav">
            <?php foreach ($navItems as $file => $item): ?>
                <a class="sidebar__link<?= $currentPage === $file ? ' is-active' : '' ?>" href="<?= BASEURL ?>/<?= substr($file, 0, -4) ?>">
                    <?= icon($item['icon'], 'icon icon-sm') ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar__footer">
            <a class="btn btn-primary btn-block" href="<?= BASEURL ?>/upload">
                <?= icon('plus', 'icon icon-sm') ?>
                Nova importação
            </a>
        </div>
    </aside>
    <div class="app-content">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                <button type="button" class="topbar__menu-btn" id="btn-toggle-sidebar" aria-label="Abrir menu"><?= icon('menu', 'icon icon-sm') ?></button>
                <div class="topbar__breadcrumb"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
            <div style="display:flex; align-items:center; gap:20px;">
                <div class="topbar__status">
                    <span class="status-dot <?= $apiOnline ? 'status-dot-success' : 'status-dot-error' ?>"></span>
                    <span class="topbar__status-text">API <?= $apiOnline ? 'conectada' : 'offline' ?></span>
                </div>
                <div class="topbar__user">
                    <div class="avatar"><?= htmlspecialchars($iniciais) ?></div>
                    <div class="topbar__user-info">
                        <div class="topbar__user-name"><?= htmlspecialchars($usuarioLogado['nome']) ?></div>
                        <span class="role-badge role-<?= htmlspecialchars($usuarioLogado['papel']) ?>"><?= htmlspecialchars(papel_label($usuarioLogado['papel'])) ?></span>
                    </div>
                    <a class="topbar__logout" href="<?= BASEURL ?>/logout" title="Sair"><?= icon('logout', 'icon icon-sm') ?></a>
                </div>
            </div>
        </header>
        <main class="main">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
