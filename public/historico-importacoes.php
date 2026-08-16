<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Histórico de Importações';
require_once __DIR__ . '/../includes/header.php';

$statusFiltro = $_GET['status'] ?? '';
$tabelaFiltro = trim($_GET['tabela'] ?? '');
$periodoFiltro = $_GET['periodo'] ?? '';
$pastaIdFiltro = isset($_GET['pasta_id']) ? (int) $_GET['pasta_id'] : null;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$tamanhoPagina = 10;

$query = ['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina];
if ($statusFiltro !== '') {
    $query['status'] = $statusFiltro;
}
if ($tabelaFiltro !== '') {
    $query['tabela'] = $tabelaFiltro;
}
if ($periodoFiltro !== '') {
    $query['periodo_dias'] = (int) $periodoFiltro;
}
if ($pastaIdFiltro) {
    $query['pasta_monitorada_id'] = $pastaIdFiltro;
}

$resp = api_get('/api/importacoes?' . http_build_query($query));
$total = 0;
$itens = [];
if ($resp['ok']) {
    $total = $resp['body']['total'] ?? 0;
    $itens = $resp['body']['itens'] ?? [];
}

function manter_filtros(array $extra = []): string
{
    $atual = [
        'status' => $_GET['status'] ?? '',
        'tabela' => $_GET['tabela'] ?? '',
        'periodo' => $_GET['periodo'] ?? '',
        'pasta_id' => $_GET['pasta_id'] ?? '',
        'pagina' => $_GET['pagina'] ?? 1,
    ];
    $novo = array_merge($atual, $extra);
    return http_build_query(array_filter($novo, fn($v) => $v !== ''));
}
?>

<div class="page-header">
    <h1>Histórico de Importações</h1>
    <p>Acompanhe todas as importações manuais e automáticas, com filtros por status, tabela e período.</p>
</div>

<?php if ($pastaIdFiltro): ?>
    <div class="breadcrumbs">
        <a href="<?= BASEURL ?>/pastas-monitoradas">Pastas Monitoradas</a>
        <span>/</span>
        <span>Logs da pasta #<?= $pastaIdFiltro ?></span>
        <a href="<?= BASEURL ?>/historico-importacoes" style="margin-left:auto;">Ver histórico completo</a>
    </div>
<?php endif; ?>

<?php if (!$resp['ok']): ?>
    <div class="alert alert-error">Não foi possível conectar à API do backend: <?= htmlspecialchars(api_error_message($resp)) ?></div>
<?php endif; ?>

<form method="get" action="<?= BASEURL ?>/historico-importacoes" class="filter-bar">
    <div class="form-group">
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
            <option value="">Todos os status</option>
            <option value="sucesso" <?= $statusFiltro === 'sucesso' ? 'selected' : '' ?>>Sucesso</option>
            <option value="erro" <?= $statusFiltro === 'erro' ? 'selected' : '' ?>>Erro</option>
            <option value="processando" <?= $statusFiltro === 'processando' ? 'selected' : '' ?>>Processando</option>
        </select>
    </div>
    <div class="form-group">
        <label for="f-tabela">Tabela</label>
        <input type="text" id="f-tabela" name="tabela" value="<?= htmlspecialchars($tabelaFiltro) ?>" placeholder="Nome da tabela…">
    </div>
    <div class="form-group">
        <label for="f-periodo">Período</label>
        <select id="f-periodo" name="periodo">
            <option value="">Todo o período</option>
            <option value="7" <?= $periodoFiltro === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
            <option value="30" <?= $periodoFiltro === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
            <option value="90" <?= $periodoFiltro === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter', 'icon icon-sm') ?> Filtrar</button>
    <?php if ($statusFiltro || $tabelaFiltro || $periodoFiltro): ?>
        <a class="btn btn-secondary" href="<?= BASEURL ?>/historico-importacoes">Limpar</a>
    <?php endif; ?>
</form>

<div class="card">
    <?php if (empty($itens)): ?>
        <div class="empty-state">
            <?= icon('history', 'icon-lg') ?>
            <h2 style="margin-top:12px;">Nenhuma importação encontrada</h2>
            <p><?= $total === 0 && $statusFiltro === '' && $tabelaFiltro === '' && $periodoFiltro === '' ? 'Ainda não há importações registradas. Faça um upload manual para começar.' : 'Nenhum resultado para os filtros aplicados.' ?></p>
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
                    <th>Linhas</th>
                    <th>Registros (antes → depois)</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $imp): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <?= icon('file-text', 'icon icon-sm') ?>
                                <span style="<?= $imp['status'] === 'erro' ? 'color:var(--color-error);' : '' ?>"><?= htmlspecialchars($imp['nome_arquivo']) ?></span>
                            </div>
                            <?php if ($imp['status'] === 'erro' && !empty($imp['mensagem_erro'])): ?>
                                <div class="text-muted" style="font-size:12px; margin-top:4px; margin-left:24px;"><?= htmlspecialchars($imp['mensagem_erro']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="type-badge type-other"><?= htmlspecialchars($imp['schema_destino']) ?>.<?= htmlspecialchars($imp['tabela_destino']) ?></span></td>
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
                        <td class="text-muted"><?= (int) ($imp['linhas_processadas'] ?? 0) ?><?= $imp['linhas_com_erro'] ? ' (' . (int) $imp['linhas_com_erro'] . ' c/ erro)' : '' ?></td>
                        <td class="text-muted">
                            <?php if ($imp['total_registros_antes'] === null && $imp['total_registros_apos'] === null): ?>
                                —
                            <?php else: ?>
                                <?= $imp['total_registros_antes'] !== null ? (int) $imp['total_registros_antes'] : '?' ?>
                                <?= icon('chevron-right', 'icon icon-sm') ?>
                                <span style="font-weight:600; color:var(--color-text);"><?= $imp['total_registros_apos'] !== null ? (int) $imp['total_registros_apos'] : '?' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= $imp['iniciado_em'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($imp['iniciado_em']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="pagination">
            <span>Mostrando <?= count($itens) ?> de <?= $total ?> importações</span>
            <div style="display:flex; gap:8px;">
                <?php if ($pagina > 1): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= BASEURL ?>/historico-importacoes?<?= manter_filtros(['pagina' => $pagina - 1]) ?>">Anterior</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary btn-sm" disabled>Anterior</button>
                <?php endif; ?>
                <?php if ($pagina * $tamanhoPagina < $total): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= BASEURL ?>/historico-importacoes?<?= manter_filtros(['pagina' => $pagina + 1]) ?>">Próximo</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary btn-sm" disabled>Próximo</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
