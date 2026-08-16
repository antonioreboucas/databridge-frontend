<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';

function type_badge_class(string $tipo): string
{
    $t = strtoupper($tipo);
    if (str_contains($t, 'INT') || str_contains($t, 'SERIAL')) {
        return 'type-int';
    }
    if (str_contains($t, 'NUMERIC') || str_contains($t, 'DECIMAL') || str_contains($t, 'FLOAT') || str_contains($t, 'DOUBLE') || str_contains($t, 'REAL')) {
        return 'type-numeric';
    }
    if (str_contains($t, 'TIMESTAMP') || str_contains($t, 'DATE') || str_contains($t, 'TIME')) {
        return 'type-date';
    }
    if (str_contains($t, 'BOOL')) {
        return 'type-bool';
    }
    if (str_contains($t, 'CHAR') || str_contains($t, 'TEXT') || str_contains($t, 'UUID')) {
        return 'type-text';
    }
    return 'type-other';
}

$pageTitle = 'Schemas / Tabelas';
require_once __DIR__ . '/../includes/header.php';

$conexaoId = isset($_GET['conexao_id']) ? (int) $_GET['conexao_id'] : null;
$schemaNome = $_GET['schema'] ?? null;
$tabelaNome = $_GET['tabela'] ?? null;

$listaResp = api_get('/api/conexoes');
$conexoes = ($listaResp['ok'] && is_array($listaResp['body'])) ? $listaResp['body'] : [];

$schemas = [];
$tabelas = [];
$colunas = [];
$erro = null;
$contagemTabelasPorSchema = [];

if ($conexaoId) {
    $schemasResp = api_get("/api/schemas/{$conexaoId}");
    if ($schemasResp['ok']) {
        $schemas = $schemasResp['body']['schemas'] ?? [];
        foreach ($schemas as $s) {
            $r = api_get("/api/schemas/{$conexaoId}/" . rawurlencode($s) . '/tabelas');
            $contagemTabelasPorSchema[$s] = $r['ok'] ? count($r['body']['tabelas'] ?? []) : null;
            if ($s === $schemaNome && $r['ok']) {
                $tabelas = $r['body']['tabelas'] ?? [];
            }
        }
    } else {
        $erro = api_error_message($schemasResp);
    }

    if ($erro === null && $schemaNome && $tabelaNome) {
        $colunasResp = api_get("/api/schemas/{$conexaoId}/" . rawurlencode($schemaNome) . '/tabelas/' . rawurlencode($tabelaNome) . '/colunas');
        if ($colunasResp['ok']) {
            $colunas = $colunasResp['body']['colunas'] ?? [];
        } else {
            $erro = api_error_message($colunasResp);
        }
    }
}
?>

<div class="page-header">
    <h1>Schemas / Tabelas</h1>
    <p>Explore os schemas, tabelas e colunas de um banco de destino cadastrado.</p>
</div>

<div class="card" style="max-width:420px; margin-bottom:20px;">
    <div class="form-group" style="margin-bottom:0;">
        <label for="conexao_id">Conexão de banco</label>
        <form method="get" action="<?= BASEURL ?>/schemas">
            <select id="conexao_id" name="conexao_id" onchange="this.form.submit()">
                <option value="">Selecione uma conexão…</option>
                <?php foreach ($conexoes as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $conexaoId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['host']) ?>/<?= htmlspecialchars($c['banco']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($conexaoId): ?>
    <?php if ($erro): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php else: ?>
        <div class="schema-layout">
            <div class="card">
                <div class="label" style="margin-bottom:12px;">Schemas</div>
                <?php if (empty($schemas)): ?>
                    <p class="text-muted" style="font-size:13px;">Nenhum schema encontrado.</p>
                <?php endif; ?>
                <?php foreach ($schemas as $s): ?>
                    <a class="schema-list__item<?= $schemaNome === $s ? ' is-active' : '' ?>" href="<?= BASEURL ?>/schemas?conexao_id=<?= $conexaoId ?>&schema=<?= rawurlencode($s) ?>">
                        <span style="display:flex; align-items:center;"><?= icon('folder', 'icon icon-sm') ?><?= htmlspecialchars($s) ?></span>
                        <?php if ($contagemTabelasPorSchema[$s] !== null): ?>
                            <span class="count"><?= $contagemTabelasPorSchema[$s] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div>
                <?php if (!$schemaNome): ?>
                    <div class="card empty-state">
                        <?= icon('folder', 'icon-lg') ?>
                        <h2 style="margin-top:12px;">Selecione um schema</h2>
                        <p>Escolha um schema à esquerda para ver suas tabelas.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="section-header">
                            <h2>Tabelas em "<?= htmlspecialchars($schemaNome) ?>"</h2>
                            <div class="search-box">
                                <?= icon('search') ?>
                                <input type="text" id="filtro-tabelas" placeholder="Buscar tabela…" autocomplete="off">
                            </div>
                        </div>
                        <?php if (empty($tabelas)): ?>
                            <p class="text-muted">Nenhuma tabela encontrada neste schema.</p>
                        <?php else: ?>
                            <div class="card-grid-sm" id="grid-tabelas">
                                <?php foreach ($tabelas as $t): ?>
                                    <a class="table-card__link" data-nome-tabela="<?= htmlspecialchars(strtolower($t)) ?>" href="<?= BASEURL ?>/schemas?conexao_id=<?= $conexaoId ?>&schema=<?= rawurlencode($schemaNome) ?>&tabela=<?= rawurlencode($t) ?>">
                                        <div class="table-card<?= $tabelaNome === $t ? ' is-active' : '' ?>">
                                            <div class="table-card__icon"><?= icon('table') ?></div>
                                            <div class="table-card__name"><?= htmlspecialchars($t) ?></div>
                                            <div class="table-card__meta">Ver colunas</div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($tabelaNome): ?>
                        <div class="card" style="margin-top:16px;">
                            <h2 style="margin-bottom:16px;">Colunas de <?= htmlspecialchars($tabelaNome) ?></h2>
                            <?php if (empty($colunas)): ?>
                                <p class="text-muted">Nenhuma coluna encontrada.</p>
                            <?php else: ?>
                                <div class="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Coluna</th>
                                            <th>Tipo</th>
                                            <th>Nulo?</th>
                                            <th>Default</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($colunas as $col): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($col['nome']) ?></td>
                                                <td><span class="type-badge <?= type_badge_class($col['tipo']) ?>"><?= htmlspecialchars($col['tipo']) ?></span></td>
                                                <td><?= $col['nullable'] ? 'Sim' : 'Não' ?></td>
                                                <td class="text-muted"><?= $col['default'] !== null ? htmlspecialchars($col['default']) : '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script src="<?= BASEURL ?>/assets/js/schemas.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
