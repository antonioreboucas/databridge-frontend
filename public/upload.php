<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// --- Proxies AJAX (multipart) para a API ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['ajax'] ?? '') === 'preview') {
    header('Content-Type: application/json');
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['detail' => 'Falha no upload do arquivo.']);
        exit;
    }
    $resp = api_upload('/api/upload/preview', [], $_FILES['arquivo']['tmp_name'], $_FILES['arquivo']['name']);
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['ajax'] ?? '') === 'importar') {
    header('Content-Type: application/json');
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['detail' => 'Falha no upload do arquivo.']);
        exit;
    }
    $campos = [
        'conexao_id' => $_POST['conexao_id'] ?? '',
        'schema_destino' => $_POST['schema_destino'] ?? '',
        'tabela_destino' => $_POST['tabela_destino'] ?? '',
        'mapeamento_colunas' => $_POST['mapeamento_colunas'] ?? '[]',
    ];
    $resp = api_upload('/api/upload/importar', $campos, $_FILES['arquivo']['tmp_name'], $_FILES['arquivo']['name']);
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

$pageTitle = 'Upload Manual de CSV';
require_once __DIR__ . '/../includes/header.php';

$conexaoId = isset($_GET['conexao_id']) ? (int) $_GET['conexao_id'] : null;
$schemaNome = $_GET['schema'] ?? null;
$tabelaNome = $_GET['tabela'] ?? null;

$conexoesResp = api_get('/api/conexoes');
$conexoes = ($conexoesResp['ok'] && is_array($conexoesResp['body'])) ? $conexoesResp['body'] : [];

$schemas = [];
$tabelas = [];
$colunasDestino = [];
$templates = [];
$erro = null;

if ($conexaoId) {
    $r = api_get("/api/schemas/{$conexaoId}");
    if ($r['ok']) {
        $schemas = $r['body']['schemas'] ?? [];
    } else {
        $erro = api_error_message($r);
    }

    if ($erro === null && $schemaNome) {
        $r = api_get("/api/schemas/{$conexaoId}/" . rawurlencode($schemaNome) . '/tabelas');
        if ($r['ok']) {
            $tabelas = $r['body']['tabelas'] ?? [];
        } else {
            $erro = api_error_message($r);
        }
    }

    if ($erro === null && $schemaNome && $tabelaNome) {
        $r = api_get("/api/schemas/{$conexaoId}/" . rawurlencode($schemaNome) . '/tabelas/' . rawurlencode($tabelaNome) . '/colunas');
        if ($r['ok']) {
            $colunasDestino = array_map(fn($c) => $c['nome'], $r['body']['colunas'] ?? []);
        } else {
            $erro = api_error_message($r);
        }

        $rt = api_get('/api/templates');
        if ($rt['ok']) {
            $templates = array_values(array_filter($rt['body'], fn($t) => $t['schema_destino'] === $schemaNome && $t['tabela_destino'] === $tabelaNome));
        }
    }
}
?>

<div class="page-header">
    <h1>Upload Manual de CSV</h1>
    <p>Envie um arquivo CSV, selecione o destino e o mapeamento de colunas.</p>
</div>

<div class="card" style="max-width:640px; margin-bottom:20px;">
    <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
            <label for="sel-conexao">Conexão de banco</label>
            <form method="get" action="<?= BASEURL ?>/upload.php">
                <select id="sel-conexao" name="conexao_id" onchange="this.form.submit()">
                    <option value="">Selecione…</option>
                    <?php foreach ($conexoes as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $conexaoId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($conexaoId && !$erro): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label for="sel-schema">Schema</label>
            <form method="get" action="<?= BASEURL ?>/upload.php">
                <input type="hidden" name="conexao_id" value="<?= $conexaoId ?>">
                <select id="sel-schema" name="schema" onchange="this.form.submit()">
                    <option value="">Selecione…</option>
                    <?php foreach ($schemas as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $schemaNome === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
        <?php if ($conexaoId && $schemaNome && !$erro): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label for="sel-tabela">Tabela destino</label>
            <form method="get" action="<?= BASEURL ?>/upload.php">
                <input type="hidden" name="conexao_id" value="<?= $conexaoId ?>">
                <input type="hidden" name="schema" value="<?= htmlspecialchars($schemaNome) ?>">
                <select id="sel-tabela" name="tabela" onchange="this.form.submit()">
                    <option value="">Selecione…</option>
                    <?php foreach ($tabelas as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $tabelaNome === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($erro): ?>
    <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
<?php elseif ($conexaoId && $schemaNome && $tabelaNome): ?>

    <div class="two-col">
        <div>
            <div class="dropzone" id="dropzone">
                <?= icon('upload', 'icon-lg') ?>
                <p style="font-weight:600; color:var(--color-text); margin-bottom:4px;">Arraste seu CSV aqui</p>
                <p style="margin-bottom:16px;">ou clique para selecionar um arquivo do seu computador</p>
                <button type="button" class="btn btn-secondary" id="btn-selecionar-arquivo">Selecionar arquivo</button>
                <input type="file" id="input-arquivo" accept=".csv,text/csv" style="display:none;">
                <p style="margin-top:12px; font-size:12px;">Tamanho máximo: 50MB</p>
                <p id="nome-arquivo-selecionado" class="text-muted" style="margin-top:8px; font-size:13px; display:none;"></p>
            </div>

            <?php if (!empty($templates)): ?>
            <div class="card" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="sel-template">Template de mapeamento (opcional)</label>
                    <select id="sel-template">
                        <option value="">Nenhum template selecionado</option>
                        <?php foreach ($templates as $t): ?>
                            <option value='<?= htmlspecialchars(json_encode($t["mapeamento_colunas"]), ENT_QUOTES) ?>'><?= htmlspecialchars($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div id="area-resultado" style="margin-top:16px;"></div>
            <div id="upload-progress" style="display:none; margin-top:16px;">
                <div class="progress-track"><div class="progress-fill" id="progress-fill" style="width:0%"></div></div>
                <div id="progress-text" style="margin-top:8px; font-size:13px; color:var(--color-text-muted);">0%</div>
            </div>
        </div>

        <div class="card" id="card-preview" style="display:none;">
            <div class="section-header">
                <h2>Prévia e mapeamento</h2>
                <span class="pill pill-success" id="badge-linhas"></span>
            </div>
            <div id="area-mapeamento"></div>
            <div class="table-scroll" style="margin-top:16px;">
                <table id="tabela-preview"></table>
            </div>
        </div>
    </div>

    <div style="margin-top:20px; text-align:right;">
        <button type="button" class="btn btn-primary" id="btn-confirmar-importar" disabled>
            Confirmar e importar
            <?= icon('chevron-right', 'icon icon-sm') ?>
        </button>
    </div>

    <script>
        window.DATABRIDGE_UPLOAD = {
            conexaoId: <?= json_encode($conexaoId) ?>,
            schema: <?= json_encode($schemaNome) ?>,
            tabela: <?= json_encode($tabelaNome) ?>,
            colunasDestino: <?= json_encode($colunasDestino) ?>
        };
    </script>
    <script src="<?= BASEURL ?>/assets/js/upload.js"></script>

<?php else: ?>
    <div class="card empty-state">
        <?= icon('table', 'icon-lg') ?>
        <h2 style="margin-top:12px;">Selecione conexão, schema e tabela</h2>
        <p>Escolha o destino acima para habilitar o upload do arquivo.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
