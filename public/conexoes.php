<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$podeGerenciarConexoes = usuario_tem_papel('superadmin', 'master');

// --- Teste de conexão via AJAX (antes de salvar), a partir dos dados digitados no formulário ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['ajax'] ?? '') === 'testar') {
    $dados = json_decode(file_get_contents('php://input'), true) ?: [];
    $payload = [
        'host' => trim($dados['host'] ?? ''),
        'porta' => (int) ($dados['porta'] ?? 5432),
        'banco' => trim($dados['banco'] ?? ''),
        'usuario' => trim($dados['usuario'] ?? ''),
        'senha' => (string) ($dados['senha'] ?? ''),
    ];
    $resp = api_post('/api/conexoes/testar', $payload);

    header('Content-Type: application/json');
    if ($resp['ok']) {
        echo json_encode($resp['body']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => api_error_message($resp)]);
    }
    exit;
}

// --- Processa ações (POST) antes de qualquer saída HTML ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['_acao'] ?? '';

    if ($acao === 'criar') {
        $payload = [
            'nome' => trim($_POST['nome'] ?? ''),
            'host' => trim($_POST['host'] ?? ''),
            'porta' => (int) ($_POST['porta'] ?? 5432),
            'banco' => trim($_POST['banco'] ?? ''),
            'usuario' => trim($_POST['usuario'] ?? ''),
            'senha' => (string) ($_POST['senha'] ?? ''),
            'ativo' => isset($_POST['ativo']),
        ];
        $resp = api_post('/api/conexoes', $payload);
        if ($resp['ok']) {
            set_flash('success', "Conexão \"{$payload['nome']}\" criada com sucesso.");
            header('Location: ' . BASEURL . '/conexoes');
        } else {
            set_flash('error', api_error_message($resp));
            header('Location: ' . BASEURL . '/conexoes?form=novo');
        }
        exit;
    }

    if ($acao === 'atualizar') {
        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            'nome' => trim($_POST['nome'] ?? ''),
            'host' => trim($_POST['host'] ?? ''),
            'porta' => (int) ($_POST['porta'] ?? 5432),
            'banco' => trim($_POST['banco'] ?? ''),
            'usuario' => trim($_POST['usuario'] ?? ''),
            'ativo' => isset($_POST['ativo']),
        ];
        if (!empty($_POST['senha'])) {
            $payload['senha'] = (string) $_POST['senha'];
        }
        $resp = api_put("/api/conexoes/{$id}", $payload);
        if ($resp['ok']) {
            set_flash('success', "Conexão \"{$payload['nome']}\" atualizada com sucesso.");
            header('Location: ' . BASEURL . '/conexoes');
        } else {
            set_flash('error', api_error_message($resp));
            header('Location: ' . BASEURL . "/conexoes?editar={$id}");
        }
        exit;
    }

    if ($acao === 'deletar') {
        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_delete("/api/conexoes/{$id}");
        if ($resp['ok']) {
            set_flash('success', 'Conexão removida com sucesso.');
        } else {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/conexoes');
        exit;
    }

    if ($acao === 'testar') {
        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_request('POST', "/api/conexoes/{$id}/testar");
        if ($resp['ok'] && !empty($resp['body']['sucesso'])) {
            set_flash('success', $resp['body']['mensagem']);
        } elseif ($resp['ok']) {
            set_flash('error', $resp['body']['mensagem'] ?? 'Falha ao testar a conexão.');
        } else {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/conexoes');
        exit;
    }
}

// --- Carrega dados para a view ---
$modoFormulario = null; // null | 'novo' | 'editar'
$conexaoEdicao = null;

if (isset($_GET['form']) || isset($_GET['editar'])) {
    if (!$podeGerenciarConexoes) {
        set_flash('error', 'Você não tem permissão para gerenciar conexões.');
        header('Location: ' . BASEURL . '/conexoes');
        exit;
    }
}

if (isset($_GET['form']) && $_GET['form'] === 'novo') {
    $modoFormulario = 'novo';
} elseif (isset($_GET['editar'])) {
    $idEdicao = (int) $_GET['editar'];
    $resp = api_get("/api/conexoes/{$idEdicao}");
    if ($resp['ok']) {
        $modoFormulario = 'editar';
        $conexaoEdicao = $resp['body'];
    } else {
        set_flash('error', api_error_message($resp));
        header('Location: ' . BASEURL . '/conexoes');
        exit;
    }
}

$pageTitle = 'Conexões de Banco';
require_once __DIR__ . '/../includes/header.php';

$listaResp = api_get('/api/conexoes');
$conexoes = ($listaResp['ok'] && is_array($listaResp['body'])) ? $listaResp['body'] : [];
?>

<div class="page-header section-header">
    <div>
        <h1>Configurações de Banco de Dados</h1>
        <p>Gerencie as credenciais e endereços de destino para suas importações.</p>
    </div>
    <?php if ($modoFormulario === null && $podeGerenciarConexoes): ?>
        <a class="btn btn-primary" href="<?= BASEURL ?>/conexoes?form=novo"><?= icon('plus', 'icon icon-sm') ?> Nova conexão</a>
    <?php endif; ?>
</div>

<?php if (!$listaResp['ok'] && $listaResp['error'] !== null): ?>
    <div class="alert alert-error">Não foi possível conectar à API do backend: <?= htmlspecialchars($listaResp['error']) ?></div>
<?php endif; ?>

<?php if ($modoFormulario !== null): ?>
    <?php
        $ehEdicao = $modoFormulario === 'editar';
        $valores = $ehEdicao ? $conexaoEdicao : ['nome' => '', 'host' => '', 'porta' => 5432, 'banco' => '', 'usuario' => '', 'ativo' => true];
    ?>
    <div class="card" style="max-width:640px;">
        <h2 style="margin-bottom:16px;"><?= $ehEdicao ? 'Editar conexão' : 'Nova conexão' ?></h2>
        <form method="post" action="<?= BASEURL ?>/conexoes">
            <input type="hidden" name="_acao" value="<?= $ehEdicao ? 'atualizar' : 'criar' ?>">
            <?php if ($ehEdicao): ?>
                <input type="hidden" name="id" value="<?= (int) $valores['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="host">Host</label>
                    <input type="text" id="host" name="host" value="<?= htmlspecialchars($valores['host']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="porta">Porta</label>
                    <input type="number" id="porta" name="porta" value="<?= (int) $valores['porta'] ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="banco">Banco</label>
                    <input type="text" id="banco" name="banco" value="<?= htmlspecialchars($valores['banco']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($valores['usuario']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="<?= $ehEdicao ? 'Deixe em branco para manter a senha atual' : '' ?>" <?= $ehEdicao ? '' : 'required' ?>>
            </div>

            <div class="form-group">
                <label style="text-transform:none; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="ativo" style="width:auto;" <?= !empty($valores['ativo']) ? 'checked' : '' ?>>
                    Conexão ativa
                </label>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $ehEdicao ? 'Salvar alterações' : 'Criar conexão' ?></button>
                <button type="button" id="btn-testar-conexao" class="btn btn-secondary"><?= icon('zap', 'icon icon-sm') ?> Testar conexão</button>
                <a class="btn btn-secondary" href="<?= BASEURL ?>/conexoes">Cancelar</a>
            </div>
            <div id="resultado-teste-conexao" style="margin-top:16px;"></div>
        </form>
    </div>
    <script src="<?= BASEURL ?>/assets/js/conexoes.js"></script>
<?php endif; ?>

<div class="card">
    <?php if (empty($conexoes)): ?>
        <div class="empty-state">
            <?= icon('connections', 'icon-lg') ?>
            <h2 style="margin-top:12px;">Nenhuma conexão cadastrada</h2>
            <p>Cadastre uma conexão com um banco PostgreSQL de destino para começar a importar dados.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Host</th>
                    <th>Banco</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conexoes as $c): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="table-card__icon" style="margin-bottom:0;"><?= icon('database') ?></div>
                                <?= htmlspecialchars($c['nome']) ?>
                            </div>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($c['host']) ?>:<?= (int) $c['porta'] ?></td>
                        <td class="text-muted"><?= htmlspecialchars($c['banco']) ?></td>
                        <td>
                            <?php if (!empty($c['ativo'])): ?>
                                <span class="pill pill-success"><span class="status-dot status-dot-success"></span>Ativa</span>
                            <?php else: ?>
                                <span class="pill pill-muted"><span class="status-dot" style="background:var(--color-text-muted);"></span>Inativa</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <form method="post" action="<?= BASEURL ?>/conexoes">
                                    <input type="hidden" name="_acao" value="testar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="icon-btn" title="Testar conexão"><?= icon('zap') ?></button>
                                </form>
                                <?php if ($podeGerenciarConexoes): ?>
                                    <a class="icon-btn" title="Editar" href="<?= BASEURL ?>/conexoes?editar=<?= (int) $c['id'] ?>"><?= icon('edit') ?></a>
                                <?php endif; ?>
                                <a class="icon-btn" title="Ver schemas" href="<?= BASEURL ?>/schemas?conexao_id=<?= (int) $c['id'] ?>"><?= icon('table') ?></a>
                                <?php if ($podeGerenciarConexoes): ?>
                                    <form method="post" action="<?= BASEURL ?>/conexoes" onsubmit="return confirm('Remover a conexão \'<?= htmlspecialchars($c['nome'], ENT_QUOTES) ?>\'?');">
                                        <input type="hidden" name="_acao" value="deletar">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="icon-btn danger" title="Remover"><?= icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="tip-box">
    <?= icon('shield') ?>
    <div>
        <strong>Dica de segurança</strong>
        Sempre use conexões SSL em ambientes de produção. As senhas das conexões são armazenadas criptografadas (Fernet/AES) no banco interno do DataBridge — nunca em texto puro.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
