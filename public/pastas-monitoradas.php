<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$podeGerenciar = usuario_tem_papel('superadmin', 'master');

// --- Processa ações (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['_acao'] ?? '';

    if (in_array($acao, ['criar', 'atualizar', 'alternar'], true) && !$podeGerenciar) {
        set_flash('error', 'Você não tem permissão para gerenciar pastas monitoradas.');
        header('Location: ' . BASEURL . '/pastas-monitoradas');
        exit;
    }

    if ($acao === 'executar') {
        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_post("/api/pastas/{$id}/executar", []);
        if ($resp['ok']) {
            $tipo = $resp['body']['status'] === 'erro' ? 'error' : 'success';
            set_flash($tipo, $resp['body']['mensagem']);
        } else {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/pastas-monitoradas');
        exit;
    }

    if ($acao === 'criar' || $acao === 'atualizar') {
        $payload = [
            'conexao_id' => (int) ($_POST['conexao_id'] ?? 0),
            'caminho_pasta' => trim($_POST['caminho_pasta'] ?? ''),
            'schema_destino' => trim($_POST['schema_destino'] ?? ''),
            'tabela_destino' => trim($_POST['tabela_destino'] ?? ''),
            'template_mapeamento_id' => !empty($_POST['template_mapeamento_id']) ? (int) $_POST['template_mapeamento_id'] : null,
            'ativo' => isset($_POST['ativo']),
            'intervalo_minutos' => !empty($_POST['intervalo_minutos']) ? (int) $_POST['intervalo_minutos'] : null,
        ];

        if ($acao === 'criar') {
            $resp = api_post('/api/pastas', $payload);
            if ($resp['ok']) {
                set_flash('success', 'Pasta monitorada criada com sucesso.');
                header('Location: ' . BASEURL . '/pastas-monitoradas');
            } else {
                set_flash('error', api_error_message($resp));
                header('Location: ' . BASEURL . '/pastas-monitoradas?form=novo');
            }
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_put("/api/pastas/{$id}", $payload);
        if ($resp['ok']) {
            set_flash('success', 'Pasta monitorada atualizada com sucesso.');
            header('Location: ' . BASEURL . '/pastas-monitoradas');
        } else {
            set_flash('error', api_error_message($resp));
            header('Location: ' . BASEURL . "/pastas-monitoradas?editar={$id}");
        }
        exit;
    }

    if ($acao === 'alternar') {
        $id = (int) ($_POST['id'] ?? 0);
        $novoEstado = ($_POST['novo_estado'] ?? '1') === '1';
        $resp = api_put("/api/pastas/{$id}", ['ativo' => $novoEstado]);
        if (!$resp['ok']) {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/pastas-monitoradas');
        exit;
    }

    if ($acao === 'deletar') {
        if (!$podeGerenciar) {
            set_flash('error', 'Você não tem permissão para gerenciar pastas monitoradas.');
            header('Location: ' . BASEURL . '/pastas-monitoradas');
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_delete("/api/pastas/{$id}");
        if ($resp['ok']) {
            set_flash('success', 'Pasta monitorada removida com sucesso.');
        } else {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/pastas-monitoradas');
        exit;
    }
}

// --- Carrega dados para a view ---
$modoFormulario = null;
$pastaEdicao = null;

if (isset($_GET['form']) || isset($_GET['editar'])) {
    if (!$podeGerenciar) {
        set_flash('error', 'Você não tem permissão para gerenciar pastas monitoradas.');
        header('Location: ' . BASEURL . '/pastas-monitoradas');
        exit;
    }
}

if (isset($_GET['form']) && $_GET['form'] === 'novo') {
    $modoFormulario = 'novo';
} elseif (isset($_GET['editar'])) {
    $idEdicao = (int) $_GET['editar'];
    $resp = api_get("/api/pastas/{$idEdicao}");
    if ($resp['ok']) {
        $modoFormulario = 'editar';
        $pastaEdicao = $resp['body'];
    } else {
        set_flash('error', api_error_message($resp));
        header('Location: ' . BASEURL . '/pastas-monitoradas');
        exit;
    }
}

$pageTitle = 'Pastas Monitoradas';
require_once __DIR__ . '/../includes/header.php';

$listaResp = api_get('/api/pastas');
$pastas = ($listaResp['ok'] && is_array($listaResp['body'])) ? $listaResp['body'] : [];

$conexoesResp = api_get('/api/conexoes');
$conexoes = ($conexoesResp['ok'] && is_array($conexoesResp['body'])) ? $conexoesResp['body'] : [];
$conexoesPorId = [];
foreach ($conexoes as $c) {
    $conexoesPorId[$c['id']] = $c;
}

$templatesResp = api_get('/api/templates');
$templates = ($templatesResp['ok'] && is_array($templatesResp['body'])) ? $templatesResp['body'] : [];
?>

<div class="page-header section-header">
    <div>
        <h1>Pastas Monitoradas</h1>
        <p>Gerencie os diretórios que o DataBridge escaneia automaticamente para novas importações.</p>
    </div>
    <?php if ($modoFormulario === null && $podeGerenciar): ?>
        <a class="btn btn-primary" href="<?= BASEURL ?>/pastas-monitoradas?form=novo"><?= icon('plus', 'icon icon-sm') ?> Nova pasta monitorada</a>
    <?php endif; ?>
</div>

<?php if (!$listaResp['ok'] && $listaResp['error'] !== null): ?>
    <div class="alert alert-error">Não foi possível conectar à API do backend: <?= htmlspecialchars($listaResp['error']) ?></div>
<?php endif; ?>

<?php if ($modoFormulario !== null): ?>
    <?php
        $ehEdicao = $modoFormulario === 'editar';
        $valores = $ehEdicao ? $pastaEdicao : ['conexao_id' => '', 'caminho_pasta' => '', 'schema_destino' => 'public', 'tabela_destino' => '', 'template_mapeamento_id' => null, 'ativo' => true];
    ?>
    <div class="card" style="max-width:640px;">
        <h2 style="margin-bottom:16px;"><?= $ehEdicao ? 'Editar pasta monitorada' : 'Nova pasta monitorada' ?></h2>
        <form method="post" action="<?= BASEURL ?>/pastas-monitoradas">
            <input type="hidden" name="_acao" value="<?= $ehEdicao ? 'atualizar' : 'criar' ?>">
            <?php if ($ehEdicao): ?>
                <input type="hidden" name="id" value="<?= (int) $valores['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="conexao_id">Conexão de banco</label>
                <select id="conexao_id" name="conexao_id" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($conexoes as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $valores['conexao_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="caminho_pasta">Caminho da pasta (no servidor do DataBridge)</label>
                <input type="text" id="caminho_pasta" name="caminho_pasta" value="<?= htmlspecialchars($valores['caminho_pasta']) ?>" placeholder="C:\Imports\Vendas" required>
                <p class="text-muted" style="font-size:12px; margin-top:4px;">
                    O DataBridge só reconhece arquivos <strong>.csv soltos direto nessa pasta</strong> — não entra em subpastas.
                    Exemplo: coloque <code>vendas_2026.csv</code> em <code>C:\Imports\Vendas\vendas_2026.csv</code>, não dentro de uma subpasta dela.
                    Depois de processar, cada arquivo é movido automaticamente para <code>processados\</code> (sucesso) ou <code>com_erro\</code> (falha)
                    dentro dessa mesma pasta, pra não ser importado de novo. Se um arquivo cair em <code>com_erro\</code>, corrija o problema
                    (mapeamento de colunas, tabela de destino etc.) e mova-o de volta pra raiz da pasta antes de executar de novo.
                </p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="schema_destino">Schema destino</label>
                    <input type="text" id="schema_destino" name="schema_destino" value="<?= htmlspecialchars($valores['schema_destino']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="tabela_destino">Tabela destino</label>
                    <input type="text" id="tabela_destino" name="tabela_destino" value="<?= htmlspecialchars($valores['tabela_destino']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="template_mapeamento_id">Template de mapeamento (opcional)</label>
                <select id="template_mapeamento_id" name="template_mapeamento_id">
                    <option value="">Sem template — mapeia colunas com nomes idênticos</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($valores['template_mapeamento_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="intervalo_minutos">Verificação periódica (minutos, opcional)</label>
                <input type="number" id="intervalo_minutos" name="intervalo_minutos" min="1" max="10080" value="<?= htmlspecialchars((string) ($valores['intervalo_minutos'] ?? '')) ?>" placeholder="Ex: 15">
                <p class="text-muted" style="font-size:12px; margin-top:4px;">Além de detectar arquivos novos em tempo real, o DataBridge também reescaneia a pasta nesse intervalo — útil pra pastas em rede onde o evento em tempo real pode falhar. Deixe em branco para depender só do tempo real.</p>
            </div>

            <div class="form-group">
                <label style="text-transform:none; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="ativo" style="width:auto;" <?= !empty($valores['ativo']) ? 'checked' : '' ?>>
                    Pasta ativa
                </label>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $ehEdicao ? 'Salvar alterações' : 'Criar pasta monitorada' ?></button>
                <a class="btn btn-secondary" href="<?= BASEURL ?>/pastas-monitoradas">Cancelar</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="item-card-grid">
    <?php foreach ($pastas as $p): ?>
        <?php $conexaoNome = $conexoesPorId[$p['conexao_id']]['nome'] ?? ('Conexão #' . $p['conexao_id']); ?>
        <div class="item-card<?= !empty($p['ativo']) ? '' : ' is-muted' ?>">
            <div class="item-card__top">
                <div class="item-card__icon"><?= icon('folder') ?></div>
                <?php if ($podeGerenciar): ?>
                    <form method="post" action="<?= BASEURL ?>/pastas-monitoradas">
                        <input type="hidden" name="_acao" value="alternar">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="novo_estado" value="<?= !empty($p['ativo']) ? '0' : '1' ?>">
                        <button type="submit" class="toggle-visual<?= !empty($p['ativo']) ? ' is-on' : '' ?>" style="border:none; background:none; cursor:pointer; padding:0;" title="<?= !empty($p['ativo']) ? 'Desativar' : 'Ativar' ?>">
                            <?= icon(!empty($p['ativo']) ? 'toggle-on' : 'toggle-off') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="toggle-visual<?= !empty($p['ativo']) ? ' is-on' : '' ?>"><?= icon(!empty($p['ativo']) ? 'toggle-on' : 'toggle-off') ?></span>
                <?php endif; ?>
            </div>
            <div class="item-card__name"><?= htmlspecialchars($conexaoNome) ?></div>
            <div class="item-card__path"><?= htmlspecialchars($p['caminho_pasta']) ?></div>
            <div class="item-card__meta" style="margin-top:10px;">Destino</div>
            <div class="item-card__dest"><?= htmlspecialchars($p['schema_destino']) ?>.<?= htmlspecialchars($p['tabela_destino']) ?></div>

            <div class="item-card__meta" style="margin-top:10px;">
                <?= $p['intervalo_minutos'] ? 'Verifica a cada ' . (int) $p['intervalo_minutos'] . ' min · ' : 'Só tempo real · ' ?>
                Última execução: <?= $p['ultima_execucao'] ? htmlspecialchars(date('d/m H:i', strtotime($p['ultima_execucao']))) : 'nunca' ?>
            </div>

            <div class="item-card__footer">
                <div style="display:flex; gap:8px;">
                    <form method="post" action="<?= BASEURL ?>/pastas-monitoradas">
                        <input type="hidden" name="_acao" value="executar">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="icon-btn" title="Executar agora"><?= icon('play') ?></button>
                    </form>
                    <a class="icon-btn" title="Ver logs" href="<?= BASEURL ?>/historico-importacoes?pasta_id=<?= (int) $p['id'] ?>"><?= icon('history') ?></a>
                    <?php if ($podeGerenciar): ?>
                        <a class="icon-btn" title="Editar" href="<?= BASEURL ?>/pastas-monitoradas?editar=<?= (int) $p['id'] ?>"><?= icon('edit') ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($podeGerenciar): ?>
                    <form method="post" action="<?= BASEURL ?>/pastas-monitoradas" onsubmit="return confirm('Remover esta pasta monitorada?');">
                        <input type="hidden" name="_acao" value="deletar">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="icon-btn danger" title="Remover"><?= icon('trash') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($modoFormulario === null && $podeGerenciar): ?>
        <a class="item-card-add" href="<?= BASEURL ?>/pastas-monitoradas?form=novo">
            <?= icon('plus') ?>
            Nova pasta monitorada
        </a>
    <?php endif; ?>
</div>

<?php if (empty($pastas) && $modoFormulario === null): ?>
    <div class="card empty-state" style="margin-top:16px;">
        <?= icon('folder', 'icon-lg') ?>
        <h2 style="margin-top:12px;">Nenhuma pasta monitorada</h2>
        <p>O DataBridge escaneia pastas cadastradas e importa automaticamente qualquer CSV novo que aparecer nelas.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
