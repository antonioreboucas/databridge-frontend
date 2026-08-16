<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

require_papel('master');

// --- Processa ações (POST) antes de qualquer saída HTML ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['_acao'] ?? '';

    if ($acao === 'criar') {
        $payload = [
            'nome' => trim($_POST['nome'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'papel' => $_POST['papel'] ?? 'operator',
            'senha' => (string) ($_POST['senha'] ?? ''),
            'ativo' => isset($_POST['ativo']),
        ];
        $resp = api_post('/api/usuarios', $payload);
        if ($resp['ok']) {
            set_flash('success', "Usuário \"{$payload['nome']}\" criado com sucesso.");
            header('Location: ' . BASEURL . '/usuarios');
        } else {
            set_flash('error', api_error_message($resp));
            header('Location: ' . BASEURL . '/usuarios?form=novo');
        }
        exit;
    }

    if ($acao === 'atualizar') {
        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            'nome' => trim($_POST['nome'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'papel' => $_POST['papel'] ?? 'operator',
            'ativo' => isset($_POST['ativo']),
        ];
        if (!empty($_POST['senha'])) {
            $payload['senha'] = (string) $_POST['senha'];
        }
        $resp = api_put("/api/usuarios/{$id}", $payload);
        if ($resp['ok']) {
            set_flash('success', "Usuário \"{$payload['nome']}\" atualizado com sucesso.");
            header('Location: ' . BASEURL . '/usuarios');
        } else {
            set_flash('error', api_error_message($resp));
            header('Location: ' . BASEURL . "/usuarios?editar={$id}");
        }
        exit;
    }

    if ($acao === 'deletar') {
        $id = (int) ($_POST['id'] ?? 0);
        $resp = api_delete("/api/usuarios/{$id}");
        if ($resp['ok']) {
            set_flash('success', 'Usuário removido com sucesso.');
        } else {
            set_flash('error', api_error_message($resp));
        }
        header('Location: ' . BASEURL . '/usuarios');
        exit;
    }
}

// --- Carrega dados para a view ---
$modoFormulario = null;
$usuarioEdicao = null;

if (isset($_GET['form']) && $_GET['form'] === 'novo') {
    $modoFormulario = 'novo';
} elseif (isset($_GET['editar'])) {
    $idEdicao = (int) $_GET['editar'];
    $resp = api_get("/api/usuarios");
    $encontrado = null;
    if ($resp['ok']) {
        foreach ($resp['body'] as $u) {
            if ((int) $u['id'] === $idEdicao) {
                $encontrado = $u;
                break;
            }
        }
    }
    if ($encontrado) {
        $modoFormulario = 'editar';
        $usuarioEdicao = $encontrado;
    } else {
        set_flash('error', 'Usuário não encontrado.');
        header('Location: ' . BASEURL . '/usuarios');
        exit;
    }
}

$pageTitle = 'Usuários';
require_once __DIR__ . '/../includes/header.php';

$listaResp = api_get('/api/usuarios');
$usuarios = ($listaResp['ok'] && is_array($listaResp['body'])) ? $listaResp['body'] : [];

$totalUsuarios = count($usuarios);
$totalAtivos = count(array_filter($usuarios, fn($u) => !empty($u['ativo'])));
$totalMasters = count(array_filter($usuarios, fn($u) => $u['papel'] === 'master'));
$totalInativos = $totalUsuarios - $totalAtivos;

$euId = (int) $usuarioLogado['id'];
?>

<div class="page-header section-header">
    <div>
        <h1>Gestão de Usuários</h1>
        <p>Configure níveis de acesso e credenciais da equipe no DataBridge.</p>
    </div>
    <?php if ($modoFormulario === null): ?>
        <a class="btn btn-primary" href="<?= BASEURL ?>/usuarios?form=novo"><?= icon('plus', 'icon icon-sm') ?> Novo usuário</a>
    <?php endif; ?>
</div>

<?php if (!$listaResp['ok'] && $listaResp['error'] !== null): ?>
    <div class="alert alert-error">Não foi possível conectar à API do backend: <?= htmlspecialchars($listaResp['error']) ?></div>
<?php endif; ?>

<div class="card-grid">
    <div class="card">
        <div class="stat-card__icon"><?= icon('users') ?></div>
        <div class="stat-card__label">Total de usuários</div>
        <div class="stat-card__value"><?= $totalUsuarios ?></div>
    </div>
    <div class="card">
        <div class="stat-card__icon success"><?= icon('check-circle') ?></div>
        <div class="stat-card__label">Usuários ativos</div>
        <div class="stat-card__value success"><?= $totalAtivos ?></div>
    </div>
    <div class="card">
        <div class="stat-card__icon"><?= icon('shield') ?></div>
        <div class="stat-card__label">Masters</div>
        <div class="stat-card__value"><?= $totalMasters ?></div>
    </div>
    <div class="card">
        <div class="stat-card__icon error"><?= icon('x-circle') ?></div>
        <div class="stat-card__label">Inativos</div>
        <div class="stat-card__value <?= $totalInativos > 0 ? 'error' : 'text-muted' ?>"><?= $totalInativos ?></div>
    </div>
</div>

<?php if ($modoFormulario !== null): ?>
    <?php
        $ehEdicao = $modoFormulario === 'editar';
        $valores = $ehEdicao ? $usuarioEdicao : ['nome' => '', 'email' => '', 'papel' => 'operator', 'ativo' => true];
    ?>
    <div class="card" style="max-width:520px;">
        <h2 style="margin-bottom:16px;"><?= $ehEdicao ? 'Editar usuário' : 'Novo usuário' ?></h2>
        <form method="post" action="<?= BASEURL ?>/usuarios">
            <input type="hidden" name="_acao" value="<?= $ehEdicao ? 'atualizar' : 'criar' ?>">
            <?php if ($ehEdicao): ?>
                <input type="hidden" name="id" value="<?= (int) $valores['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($valores['email']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="papel">Papel</label>
                    <select id="papel" name="papel" <?= ($ehEdicao && (int) $valores['id'] === $euId) ? 'disabled' : '' ?>>
                        <option value="operator" <?= $valores['papel'] === 'operator' ? 'selected' : '' ?>>Operator</option>
                        <option value="superadmin" <?= $valores['papel'] === 'superadmin' ? 'selected' : '' ?>>SuperAdmin</option>
                        <option value="master" <?= $valores['papel'] === 'master' ? 'selected' : '' ?>>Master</option>
                    </select>
                    <?php if ($ehEdicao && (int) $valores['id'] === $euId): ?>
                        <input type="hidden" name="papel" value="master">
                        <p class="text-muted" style="font-size:12px; margin-top:4px;">Você não pode alterar seu próprio papel.</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="<?= $ehEdicao ? 'Deixe em branco para manter' : 'Mín. 8 caracteres' ?>" <?= $ehEdicao ? '' : 'required minlength=8' ?>>
                </div>
            </div>

            <div class="form-group">
                <label style="text-transform:none; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="ativo" style="width:auto;" <?= !empty($valores['ativo']) ? 'checked' : '' ?> <?= ($ehEdicao && (int) $valores['id'] === $euId) ? 'disabled checked' : '' ?>>
                    Usuário ativo
                </label>
            </div>

            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $ehEdicao ? 'Salvar alterações' : 'Criar usuário' ?></button>
                <a class="btn btn-secondary" href="<?= BASEURL ?>/usuarios">Cancelar</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($usuarios)): ?>
        <div class="empty-state">
            <?= icon('users', 'icon-lg') ?>
            <h2 style="margin-top:12px;">Nenhum usuário cadastrado</h2>
        </div>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Papel</th>
                    <th>Status</th>
                    <th>Último acesso</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php
                        $iniciaisLinha = '';
                        foreach (explode(' ', trim($u['nome'])) as $parte) {
                            $iniciaisLinha .= mb_strtoupper(mb_substr($parte, 0, 1));
                        }
                        $iniciaisLinha = mb_substr($iniciaisLinha, 0, 2);
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="avatar"><?= htmlspecialchars($iniciaisLinha) ?></div>
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars($u['nome']) ?></div>
                                    <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="role-badge role-<?= htmlspecialchars($u['papel']) ?>"><?= htmlspecialchars(papel_label($u['papel'])) ?></span></td>
                        <td>
                            <?php if (!empty($u['ativo'])): ?>
                                <span class="pill pill-success"><span class="status-dot status-dot-success"></span>Ativo</span>
                            <?php else: ?>
                                <span class="pill pill-muted"><span class="status-dot" style="background:var(--color-text-muted);"></span>Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= $u['ultimo_acesso'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['ultimo_acesso']))) : 'Nunca acessou' ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="icon-btn" title="Editar" href="<?= BASEURL ?>/usuarios?editar=<?= (int) $u['id'] ?>"><?= icon('edit') ?></a>
                                <?php if ((int) $u['id'] !== $euId): ?>
                                    <form method="post" action="<?= BASEURL ?>/usuarios" onsubmit="return confirm('Remover o usuário \'<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>\'?');">
                                        <input type="hidden" name="_acao" value="deletar">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
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
        <strong>Papéis de acesso</strong>
        Operator: visualizar dashboard/schemas e fazer upload manual de CSV. SuperAdmin: + gerenciar conexões, pastas monitoradas e templates. Master: + gerenciar usuários.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
