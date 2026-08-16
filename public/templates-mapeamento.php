<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$podeGerenciar = usuario_tem_papel('superadmin', 'master');

// --- Proxies AJAX (somente leitura, usados pelos selects em cascata do formulário) ---
if (($_GET['ajax'] ?? '') === 'schemas') {
    header('Content-Type: application/json');
    $conexaoId = (int) ($_GET['conexao_id'] ?? 0);
    $resp = api_get("/api/schemas/{$conexaoId}");
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

if (($_GET['ajax'] ?? '') === 'tabelas') {
    header('Content-Type: application/json');
    $conexaoId = (int) ($_GET['conexao_id'] ?? 0);
    $schema = $_GET['schema'] ?? '';
    $resp = api_get("/api/schemas/{$conexaoId}/" . rawurlencode($schema) . '/tabelas');
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

if (($_GET['ajax'] ?? '') === 'colunas') {
    header('Content-Type: application/json');
    $conexaoId = (int) ($_GET['conexao_id'] ?? 0);
    $schema = $_GET['schema'] ?? '';
    $tabela = $_GET['tabela'] ?? '';
    $resp = api_get("/api/schemas/{$conexaoId}/" . rawurlencode($schema) . '/tabelas/' . rawurlencode($tabela) . '/colunas');
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['ajax'] ?? '') === 'gerar_sql') {
    header('Content-Type: application/json');
    if (!$podeGerenciar) {
        http_response_code(403);
        echo json_encode(['detail' => 'Você não tem permissão para gerenciar templates.']);
        exit;
    }
    $dados = json_decode(file_get_contents('php://input'), true) ?: [];
    $resp = api_post('/api/templates/gerar-sql', [
        'schema_destino' => $dados['schema_destino'] ?? '',
        'tabela_destino' => $dados['tabela_destino'] ?? '',
        'mapeamento_colunas' => $dados['mapeamento_colunas'] ?? [],
    ]);
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['ajax'] ?? '') === 'criar_tabela') {
    header('Content-Type: application/json');
    if (!$podeGerenciar) {
        http_response_code(403);
        echo json_encode(['detail' => 'Você não tem permissão para gerenciar templates.']);
        exit;
    }
    $dados = json_decode(file_get_contents('php://input'), true) ?: [];
    $resp = api_post('/api/templates/criar-tabela', [
        'conexao_id' => (int) ($dados['conexao_id'] ?? 0),
        'schema_destino' => $dados['schema_destino'] ?? '',
        'tabela_destino' => $dados['tabela_destino'] ?? '',
        'mapeamento_colunas' => $dados['mapeamento_colunas'] ?? [],
    ]);
    http_response_code($resp['status'] ?? 500);
    echo json_encode($resp['ok'] ? $resp['body'] : ['detail' => api_error_message($resp)]);
    exit;
}

// --- Processa ações (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['_acao'] ?? '';

    if (in_array($acao, ['criar', 'atualizar'], true) && !$podeGerenciar) {
        set_flash('error', 'Você não tem permissão para gerenciar templates.');
        header('Location: ' . BASEURL . '/templates-mapeamento.php');
        exit;
    }

    if ($acao === 'criar' || $acao === 'atualizar') {
        $colunasCsv = $_POST['coluna_csv'] ?? [];
        $colunasDestino = $_POST['coluna_destino'] ?? [];
        $colunasTipo = $_POST['tipo_dado'] ?? [];
        $mapeamento = [];
        foreach ($colunasCsv as $i => $csv) {
            $csv = trim($csv);
            $destino = trim($colunasDestino[$i] ?? '');
            $tipo = trim($colunasTipo[$i] ?? '');
            if ($csv !== '' && $destino !== '') {
                $item = ['coluna_csv' => $csv, 'coluna_destino' => $destino];
                if ($tipo !== '') {
                    $item['tipo_dado'] = $tipo;
                }
                $mapeamento[] = $item;
            }
        }

        $payload = [
            'nome' => trim($_POST['nome'] ?? ''),
            'schema_destino' => trim($_POST['schema_destino'] ?? ''),
            'tabela_destino' => trim($_POST['tabela_destino'] ?? ''),
            'mapeamento_colunas' => $mapeamento,
        ];

        if ($acao === 'criar') {
            $resp = api_post('/api/templates', $payload);
            if ($resp['ok']) {
                set_flash('success', "Template \"{$payload['nome']}\" criado com sucesso.");
                header('Location: ' . BASEURL . '/templates-mapeamento.php');
            } else {
                set_flash('error', api_error_message($resp));
                header('Location: ' . BASEURL . '/templates-mapeamento.php?form=novo');
            }
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_put("/api/templates/{$id}", $payload);
        if ($resp['ok']) {
            set_flash('success', "Template \"{$payload['nome']}\" atualizado com sucesso.");
            header('Location: ' . BASEURL . '/templates-mapeamento.php');
        } else {
            set_flash('error', api_error_message($resp));
            header('Location: ' . BASEURL . "/templates-mapeamento.php?editar={$id}");
        }
        exit;
    }

    if ($acao === 'deletar') {
        if (!$podeGerenciar) {
            set_flash('error', 'Você não tem permissão para gerenciar templates.');
            header('Location: ' . BASEURL . '/templates-mapeamento.php');
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_delete("/api/templates/{$id}");
        if ($resp['ok']) {
            set_flash('success', 'Template removido com sucesso.');
        } else {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/templates-mapeamento.php');
        exit;
    }
}

// --- Carrega dados para a view ---
$modoFormulario = null;
$templateEdicao = null;

if (isset($_GET['form']) || isset($_GET['editar'])) {
    if (!$podeGerenciar) {
        set_flash('error', 'Você não tem permissão para gerenciar templates.');
        header('Location: ' . BASEURL . '/templates-mapeamento.php');
        exit;
    }
}

if (isset($_GET['form']) && $_GET['form'] === 'novo') {
    $modoFormulario = 'novo';
} elseif (isset($_GET['editar'])) {
    $idEdicao = (int) $_GET['editar'];
    $resp = api_get("/api/templates/{$idEdicao}");
    if ($resp['ok']) {
        $modoFormulario = 'editar';
        $templateEdicao = $resp['body'];
    } else {
        set_flash('error', api_error_message($resp));
        header('Location: ' . BASEURL . '/templates-mapeamento.php');
        exit;
    }
}

$pageTitle = 'Templates de Mapeamento';
require_once __DIR__ . '/../includes/header.php';

$listaResp = api_get('/api/templates');
$templates = ($listaResp['ok'] && is_array($listaResp['body'])) ? $listaResp['body'] : [];

$conexoes = [];
if ($modoFormulario !== null) {
    $conexoesResp = api_get('/api/conexoes');
    $conexoes = ($conexoesResp['ok'] && is_array($conexoesResp['body'])) ? $conexoesResp['body'] : [];
}
?>

<div class="page-header section-header">
    <div>
        <h1>Templates de Mapeamento</h1>
        <p>Modelos reutilizáveis de mapeamento coluna a coluna (CSV → tabela de destino).</p>
    </div>
    <?php if ($modoFormulario === null && $podeGerenciar): ?>
        <a class="btn btn-primary" href="<?= BASEURL ?>/templates-mapeamento.php?form=novo"><?= icon('plus', 'icon icon-sm') ?> Novo template</a>
    <?php endif; ?>
</div>

<?php if (!$listaResp['ok'] && $listaResp['error'] !== null): ?>
    <div class="alert alert-error">Não foi possível conectar à API do backend: <?= htmlspecialchars($listaResp['error']) ?></div>
<?php endif; ?>

<?php if ($modoFormulario !== null): ?>
    <?php
        $ehEdicao = $modoFormulario === 'editar';
        $valores = $ehEdicao ? $templateEdicao : ['nome' => '', 'schema_destino' => '', 'tabela_destino' => '', 'mapeamento_colunas' => [['coluna_csv' => '', 'coluna_destino' => '']]];
        $colunasIniciais = !empty($valores['mapeamento_colunas']) ? $valores['mapeamento_colunas'] : [['coluna_csv' => '', 'coluna_destino' => '']];
    ?>
    <div class="card" style="max-width:640px;">
        <h2 style="margin-bottom:4px;"><?= $ehEdicao ? 'Editar template' : 'Novo template' ?></h2>
        <p class="text-muted" style="margin-bottom:16px;">Mapeie as colunas do seu CSV para os campos da tabela PostgreSQL.</p>
        <form method="post" action="<?= BASEURL ?>/templates-mapeamento.php" id="form-template">
            <input type="hidden" name="_acao" value="<?= $ehEdicao ? 'atualizar' : 'criar' ?>">
            <?php if ($ehEdicao): ?>
                <input type="hidden" name="id" value="<?= (int) $valores['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nome">Nome do template</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" placeholder="Ex: Importação ERP" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="sel-conexao">Banco de dados</label>
                    <select id="sel-conexao">
                        <option value="">Selecione…</option>
                        <?php foreach ($conexoes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sel-schema">Schema</label>
                    <select id="sel-schema" name="schema_destino" disabled required>
                        <?php if ($ehEdicao && $valores['schema_destino']): ?>
                            <option value="<?= htmlspecialchars($valores['schema_destino']) ?>" selected><?= htmlspecialchars($valores['schema_destino']) ?></option>
                        <?php else: ?>
                            <option value="">Selecione o banco primeiro…</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="sel-tabela">Tabela de destino</label>
                <select id="sel-tabela" name="tabela_destino" disabled required>
                    <?php if ($ehEdicao && $valores['tabela_destino']): ?>
                        <option value="<?= htmlspecialchars($valores['tabela_destino']) ?>" selected><?= htmlspecialchars($valores['tabela_destino']) ?></option>
                    <?php else: ?>
                        <option value="">Selecione o schema primeiro…</option>
                    <?php endif; ?>
                </select>
                <input type="text" id="input-tabela-nova" name="tabela_destino" placeholder="nome_da_nova_tabela" style="display:none;" disabled>
                <p class="text-muted" style="font-size:12px; margin-top:4px;">
                    <?php if ($ehEdicao): ?>
                        Para trocar o destino, selecione o banco de dados acima novamente.
                    <?php else: ?>
                        <a href="#" id="link-alternar-modo-tabela">A tabela ainda não existe? Crie a partir deste template »</a>
                    <?php endif; ?>
                </p>
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <label style="margin-bottom:0;">Mapeamento de colunas</label>
                    <button type="button" id="btn-add-coluna" class="btn btn-secondary btn-sm"><?= icon('plus', 'icon icon-sm') ?> Adicionar campo</button>
                </div>
                <div id="lista-colunas" style="margin-top:10px;">
                    <?php foreach ($colunasIniciais as $col): ?>
                        <div style="display:flex; gap:8px; align-items:flex-start; margin-bottom:8px;">
                            <div class="form-row coluna-mapeamento-row" style="flex:1; margin-bottom:0;">
                                <div>
                                    <label class="text-muted" style="text-transform:none; font-weight:400;">Coluna CSV</label>
                                    <input type="text" name="coluna_csv[]" placeholder="Ex: ID_Venda" value="<?= htmlspecialchars($col['coluna_csv']) ?>" required>
                                </div>
                                <div class="celula-coluna-destino">
                                    <label class="text-muted" style="text-transform:none; font-weight:400;">Coluna Tabela</label>
                                    <select name="coluna_destino[]" class="sel-coluna-destino" required>
                                        <option value="<?= htmlspecialchars($col['coluna_destino']) ?>" selected><?= htmlspecialchars($col['coluna_destino']) ?: '—' ?></option>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="icon-btn danger" title="Remover coluna" style="margin-top:20px;" onclick="this.parentElement.remove()">&minus;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card" id="painel-nova-tabela" style="display:none; background:var(--color-bg-subtle, #f7f8fa); margin-bottom:16px;">
                <h3 style="margin-bottom:4px; font-size:14px;">Criar tabela no banco de dados</h3>
                <p class="text-muted" style="font-size:13px; margin-bottom:12px;">
                    A tabela é criada com uma coluna <code>id</code> (chave primária automática) seguida das colunas mapeadas acima, no tipo escolhido para cada uma.
                </p>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="btn-gerar-sql" class="btn btn-secondary btn-sm">Gerar SQL</button>
                    <button type="button" id="btn-criar-tabela" class="btn btn-primary btn-sm">Criar tabela agora</button>
                </div>
                <div id="status-criacao-tabela" style="margin-top:10px;"></div>
                <pre id="sql-preview" style="display:none; margin-top:10px; padding:12px; background:#1e1e2e; color:#e2e2f0; border-radius:6px; font-size:12px; overflow-x:auto;"></pre>
            </div>

            <div style="display:flex; gap:8px; margin-top:16px;">
                <button type="submit" class="btn btn-primary"><?= $ehEdicao ? 'Salvar alterações' : 'Salvar template' ?></button>
                <a class="btn btn-secondary" href="<?= BASEURL ?>/templates-mapeamento.php">Cancelar</a>
            </div>
        </form>
    </div>
    <script>
        window.DATABRIDGE_TEMPLATE = { edicao: <?= json_encode($ehEdicao) ?> };
    </script>
    <script src="<?= BASEURL ?>/assets/js/templates.js"></script>
<?php endif; ?>

<div class="item-card-grid">
    <?php foreach ($templates as $t): ?>
        <div class="item-card">
            <div class="item-card__top">
                <div class="item-card__icon"><?= icon('templates') ?></div>
                <span class="type-badge type-other">table: <?= htmlspecialchars($t['tabela_destino']) ?></span>
            </div>
            <div class="item-card__name"><?= htmlspecialchars($t['nome']) ?></div>
            <div class="item-card__meta"><?= count($t['mapeamento_colunas']) ?> coluna(s) mapeada(s) · schema <?= htmlspecialchars($t['schema_destino']) ?></div>
            <?php if ($podeGerenciar): ?>
                <div class="item-card__footer">
                    <a class="btn btn-secondary btn-sm" href="<?= BASEURL ?>/templates-mapeamento.php?editar=<?= (int) $t['id'] ?>"><?= icon('edit', 'icon icon-sm') ?> Editar</a>
                    <form method="post" action="<?= BASEURL ?>/templates-mapeamento.php" onsubmit="return confirm('Remover o template \'<?= htmlspecialchars($t['nome'], ENT_QUOTES) ?>\'?');">
                        <input type="hidden" name="_acao" value="deletar">
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <button type="submit" class="icon-btn danger" title="Remover"><?= icon('trash') ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($modoFormulario === null && $podeGerenciar): ?>
        <a class="item-card-add" href="<?= BASEURL ?>/templates-mapeamento.php?form=novo">
            <?= icon('plus') ?>
            Criar novo template
        </a>
    <?php endif; ?>
</div>

<?php if (empty($templates) && $modoFormulario === null): ?>
    <div class="card empty-state" style="margin-top:16px;">
        <?= icon('templates', 'icon-lg') ?>
        <h2 style="margin-top:12px;">Nenhum template cadastrado</h2>
        <p>Templates de mapeamento agilizam o upload manual, reaproveitando o de-para de colunas entre o CSV e a tabela de destino.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
