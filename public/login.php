<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

if (is_logged_in()) {
    header('Location: ' . BASEURL . '/dashboard');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
    $lembrar = isset($_POST['lembrar']);

    $resp = do_login($email, $senha, $lembrar);
    if ($resp['ok']) {
        header('Location: ' . BASEURL . '/dashboard');
        exit;
    }
    $erro = api_error_message($resp);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · DataBridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASEURL ?>/assets/css/style.css">
</head>
<body class="auth-body">
    <aside class="auth-brand">
        <div class="auth-brand__content">
            <div class="auth-brand__logo"><?= icon('connections', 'icon icon-lg') ?></div>
            <h1>DataBridge</h1>
            <p class="tagline">Centralize a importação dos seus CSVs direto no PostgreSQL — sem planilha perdida, sem Power BI quebrado.</p>
            <div class="auth-brand__features">
                <div class="auth-brand__feature">
                    <?= icon('upload', 'icon') ?>
                    <span>Upload manual com mapeamento de colunas</span>
                </div>
                <div class="auth-brand__feature">
                    <?= icon('folder', 'icon') ?>
                    <span>Pastas monitoradas com importação automática</span>
                </div>
                <div class="auth-brand__feature">
                    <?= icon('history', 'icon') ?>
                    <span>Histórico completo e dashboard de indicadores</span>
                </div>
            </div>
        </div>
    </aside>
    <div class="auth-form-panel">
        <div class="auth-card">
            <div class="auth-card-mobile-header">
                <div class="auth-logo-sm"><?= icon('connections', 'icon icon-sm') ?></div>
                <div>
                    <strong>DataBridge</strong>
                    <div class="text-muted" style="font-size:12px;">CSV to PostgreSQL</div>
                </div>
            </div>

            <h2>Bem-vindo de volta</h2>
            <p class="text-muted" style="margin-bottom:24px;">Acesse com sua conta para gerenciar as importações.</p>

            <?php if ($erro): ?>
                <div class="alert alert-error" style="display:flex; align-items:center; gap:8px;">
                    <?= icon('alert-circle', 'icon icon-sm') ?>
                    <span><?= htmlspecialchars($erro) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= BASEURL ?>/login" id="form-login">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="nome@empresa.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-with-action">
                        <input type="password" id="senha" name="senha" required>
                        <button type="button" class="input-action-btn" id="btn-toggle-senha" aria-label="Mostrar senha">
                            <span class="icon-toggle-mostrar"><?= icon('eye', 'icon icon-sm') ?></span>
                            <span class="icon-toggle-ocultar" style="display:none;"><?= icon('eye-off', 'icon icon-sm') ?></span>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label style="text-transform:none; font-size:14px; display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="lembrar" style="width:auto;">
                        Manter conectado por 30 dias
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="btn-entrar">
                    <span class="btn-label">Entrar</span>
                    <span class="btn-spinner"></span>
                    <?= icon('chevron-right', 'icon icon-sm btn-icon-chevron') ?>
                </button>
            </form>
        </div>
    </div>
    <script src="<?= BASEURL ?>/assets/js/app.js"></script>
    <script src="<?= BASEURL ?>/assets/js/login.js"></script>
</body>
</html>
