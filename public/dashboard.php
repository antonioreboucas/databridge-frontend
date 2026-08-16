<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$conexoesResp = api_get('/api/conexoes');
$conexoes = ($conexoesResp['ok'] && is_array($conexoesResp['body'])) ? $conexoesResp['body'] : [];
$totalConexoes = count($conexoes);

$totalGeralResp = api_get('/api/importacoes?tamanho_pagina=1');
$totalGeral = $totalGeralResp['ok'] ? ($totalGeralResp['body']['total'] ?? 0) : 0;

$totalHojeResp = api_get('/api/importacoes?periodo_dias=1&tamanho_pagina=1');
$totalHoje = $totalHojeResp['ok'] ? ($totalHojeResp['body']['total'] ?? 0) : 0;

$totalSemanaResp = api_get('/api/importacoes?periodo_dias=7&tamanho_pagina=1');
$totalSemana = $totalSemanaResp['ok'] ? ($totalSemanaResp['body']['total'] ?? 0) : 0;

$totalSucessoResp = api_get('/api/importacoes?status=sucesso&tamanho_pagina=1');
$totalSucesso = $totalSucessoResp['ok'] ? ($totalSucessoResp['body']['total'] ?? 0) : 0;

$totalErroResp = api_get('/api/importacoes?status=erro&tamanho_pagina=1');
$totalErro = $totalErroResp['ok'] ? ($totalErroResp['body']['total'] ?? 0) : 0;

$ultimasResp = api_get('/api/importacoes?tamanho_pagina=8');
$ultimasImportacoes = $ultimasResp['ok'] ? ($ultimasResp['body']['itens'] ?? []) : [];

$pctSucesso = $totalGeral > 0 ? round($totalSucesso / $totalGeral * 100) : null;
$pctErro = $totalGeral > 0 ? round($totalErro / $totalGeral * 100) : null;
?>

<div class="page-header section-header">
    <div>
        <h1>Data Dashboard</h1>
        <p>Visão geral das conexões e do pipeline de importação.</p>
    </div>
    <div style="display:flex; gap:8px;">
        <a class="btn btn-secondary" href="<?= BASEURL ?>/historico-importacoes.php">
            <?= icon('history', 'icon icon-sm') ?>
            Ver histórico
        </a>
        <a class="btn btn-primary" href="<?= BASEURL ?>/upload.php">
            <?= icon('upload', 'icon icon-sm') ?>
            Nova importação manual
        </a>
    </div>
</div>

<?php if (!$conexoesResp['ok'] && $conexoesResp['error'] !== null): ?>
    <div class="alert alert-error">Não foi possível conectar à API do backend: <?= htmlspecialchars($conexoesResp['error']) ?></div>
<?php endif; ?>

<div class="card-grid">
    <div class="card">
        <div class="stat-card__icon"><?= icon('connections') ?></div>
        <div class="stat-card__label">Conexões cadastradas</div>
        <div class="stat-card__value"><?= $totalConexoes ?></div>
    </div>
    <div class="card">
        <div class="stat-card__icon"><?= icon('history') ?></div>
        <div class="stat-card__label">Total de importações</div>
        <div class="stat-card__value"><?= $totalGeral ?></div>
        <div class="stat-card__caption">Hoje: <?= $totalHoje ?> · Semana: <?= $totalSemana ?></div>
    </div>
    <div class="card">
        <div class="stat-card__icon success"><?= icon('check-circle') ?></div>
        <div class="stat-card__label">Sucesso</div>
        <div class="stat-card__value success"><?= $pctSucesso !== null ? $pctSucesso . '%' : '—' ?></div>
        <?php if ($pctSucesso !== null): ?>
            <div class="progress-track"><div class="progress-fill success" style="width:<?= $pctSucesso ?>%;"></div></div>
        <?php endif; ?>
        <div class="stat-card__caption"><?= $totalSucesso ?> importação(ões)</div>
    </div>
    <div class="card">
        <div class="stat-card__icon error"><?= icon('alert-circle') ?></div>
        <div class="stat-card__label">Erro</div>
        <div class="stat-card__value <?= $totalErro > 0 ? 'error' : 'text-muted' ?>"><?= $pctErro !== null ? $pctErro . '%' : '—' ?></div>
        <?php if ($pctErro !== null && $totalErro > 0): ?>
            <div class="progress-track"><div class="progress-fill error" style="width:<?= $pctErro ?>%;"></div></div>
        <?php endif; ?>
        <div class="stat-card__caption"><?= $totalErro ?> importação(ões)</div>
    </div>
</div>

<div class="card">
    <div class="section-header">
        <h2>Últimas importações</h2>
        <a href="<?= BASEURL ?>/historico-importacoes.php" class="text-muted" style="font-size:13px; font-weight:600;">Ver tudo</a>
    </div>
    <?php if (empty($ultimasImportacoes)): ?>
        <div class="empty-state">
            <?= icon('history', 'icon-lg') ?>
            <h2 style="margin-top:12px;">Nenhuma importação registrada ainda</h2>
            <p>Faça um upload manual ou configure uma pasta monitorada para começar.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>Tabela</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimasImportacoes as $imp): ?>
                    <tr>
                        <td style="display:flex; align-items:center; gap:8px;">
                            <?= icon('file-text', 'icon icon-sm') ?>
                            <span style="<?= $imp['status'] === 'erro' ? 'color:var(--color-error);' : '' ?>"><?= htmlspecialchars($imp['nome_arquivo']) ?></span>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($imp['schema_destino']) ?>.<?= htmlspecialchars($imp['tabela_destino']) ?></td>
                        <td class="text-muted"><?= $imp['tipo_importacao'] === 'automatica' ? 'Automática' : 'Manual' ?></td>
                        <td>
                            <?php if ($imp['status'] === 'sucesso'): ?>
                                <span class="pill pill-success"><span class="status-dot status-dot-success"></span>Sucesso</span>
                            <?php elseif ($imp['status'] === 'erro'): ?>
                                <span class="pill pill-error"><span class="status-dot status-dot-error"></span>Erro</span>
                            <?php else: ?>
                                <span class="pill pill-muted"><span class="status-dot" style="background:var(--color-text-muted);"></span>Processando</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= $imp['iniciado_em'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($imp['iniciado_em']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
