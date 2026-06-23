<?php
session_start();
require_once __DIR__ . '/../backend/api/config.php';

if (empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$settings = getAppSettings();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = [
        'auth_mode' => $_POST['auth_mode'] ?? 'local',
        'remote_auth_url' => trim((string)($_POST['remote_auth_url'] ?? '')),
        'remote_shared_key' => trim((string)($_POST['remote_shared_key'] ?? '')),
        'remote_verify_ssl' => isset($_POST['remote_verify_ssl']) ? 1 : 0,
        'offline_grace_days' => (int)($_POST['offline_grace_days'] ?? 7),
        'remote_timeout_sec' => (int)($_POST['remote_timeout_sec'] ?? 8),
        'remote_api_token_ttl_sec' => (int)($_POST['remote_api_token_ttl_sec'] ?? 3600),
        'token_ttl_sec' => (int)($_POST['token_ttl_sec'] ?? 86400),
    ];
    $settings = saveAppSettings($next);
    $message = 'Configuracion guardada';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuracion - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Admin IPTV</a>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-outline-secondary btn-sm">Usuarios</a>
                <a href="providers.php" class="btn btn-outline-secondary btn-sm">Providers</a>
                <a href="subscriptions.php" class="btn btn-outline-secondary btn-sm">Suscripciones</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3" style="max-width:900px">
        <div class="card">
            <div class="card-header">Configuracion de autenticacion</div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Modo</label>
                        <select name="auth_mode" class="form-select">
                            <option value="local" <?= $settings['auth_mode'] === 'local' ? 'selected' : '' ?>>Local</option>
                            <option value="remote" <?= $settings['auth_mode'] === 'remote' ? 'selected' : '' ?>>Remoto</option>
                            <option value="hybrid" <?= $settings['auth_mode'] === 'hybrid' ? 'selected' : '' ?>>Hibrido</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">URL base remota (VPS)</label>
                        <input type="text" name="remote_auth_url" class="form-control" placeholder="https://mi-vps.com" value="<?= htmlspecialchars($settings['remote_auth_url']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Shared key remota (opcional)</label>
                        <input type="text" name="remote_shared_key" class="form-control" value="<?= htmlspecialchars($settings['remote_shared_key'] ?? '') ?>">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="remote_verify_ssl" name="remote_verify_ssl" value="1" <?= !empty($settings['remote_verify_ssl']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remote_verify_ssl">
                                Verificar SSL remoto (recomendado en produccion)
                            </label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Offline (dias)</label>
                        <input type="number" min="1" name="offline_grace_days" class="form-control" value="<?= (int)$settings['offline_grace_days'] ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Timeout remoto (s)</label>
                        <input type="number" min="2" name="remote_timeout_sec" class="form-control" value="<?= (int)$settings['remote_timeout_sec'] ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">TTL token local (seg)</label>
                        <input type="number" min="600" name="token_ttl_sec" class="form-control" value="<?= (int)$settings['token_ttl_sec'] ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">TTL token API remota (seg)</label>
                        <input type="number" min="300" name="remote_api_token_ttl_sec" class="form-control" value="<?= (int)($settings['remote_api_token_ttl_sec'] ?? 3600) ?>">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </form>

                <hr>
                <div class="small text-secondary">
                    <div><strong>Local:</strong> autentica solo contra este servidor.</div>
                    <div><strong>Remoto:</strong> autentica solo contra la API remota.</div>
                    <div><strong>Hibrido:</strong> remoto y, si no hay red, permite cache offline por dias configurados.</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
