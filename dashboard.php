<?php
require_once __DIR__ . '/config.php';
requireLogin();

$result = apiRequest('', [], 300);
$apiError = $result['error'] ?? null;
$userInfo = $result['data']['user_info'] ?? [];
$serverInfo = $result['data']['server_info'] ?? [];

function fmtDate($ts): string {
    return $ts ? date('d/m/Y H:i', (int)$ts) : 'N/A';
}

function daysRemaining($ts): int {
    return $ts ? max(0, (int)(((int)$ts - time()) / 86400)) : 0;
}

$expDate = $userInfo['exp_date'] ?? 0;
$daysLeft = daysRemaining($expDate);
$status = $userInfo['status'] ?? 'Unknown';
$isActive = strtolower($status) === 'active';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <?php if ($apiError): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Error al conectar con el servidor: <?= htmlspecialchars($apiError) ?>
        </div>
    <?php elseif (!$userInfo): ?>
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-2"></i>
            No se pudo obtener información del usuario. Verifica tus credenciales.
        </div>
    <?php endif; ?>

    <!-- Info del usuario -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-person-circle me-2"></i>Información de la Cuenta
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <small class="text-secondary d-block">Usuario</small>
                            <span class="fs-5"><?= htmlspecialchars($userInfo['username'] ?? '---') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block">Estado</small>
                            <span class="badge bg-<?= $isActive ? 'success' : 'danger' ?> fs-6">
                                <?= $status ?>
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block">Expira</small>
                            <span class="<?= $daysLeft <= 3 ? 'text-danger' : '' ?>">
                                <?= fmtDate($expDate) ?>
                                <?php if ($daysLeft > 0): ?>
                                    <span class="badge bg-<?= $daysLeft <= 3 ? 'danger' : 'secondary' ?> ms-1">
                                        <?= $daysLeft ?> día<?= $daysLeft !== 1 ? 's' : '' ?>
                                    </span>
                                <?php elseif ($daysLeft === 0 && $expDate > 0): ?>
                                    <span class="badge bg-danger ms-1">Vence hoy</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block">Conexiones</small>
                            <span>
                                <?= (int)($userInfo['active_connections'] ?? 0) ?>
                                /
                                <?= (int)($userInfo['max_connections'] ?? '---') ?>
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block">Tipo de cuenta</small>
                            <span><?= ($userInfo['is_trial'] ?? 0) == 1 ? 'Trial' : 'Normal' ?></span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-secondary d-block">Creada</small>
                            <span><?= fmtDate($userInfo['created_at'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Servidor -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-server me-2"></i>Servidor
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-secondary d-block">URL</small>
                        <code><?= htmlspecialchars($serverInfo['url'] ?? getServerUrl()) ?></code>
                    </div>
                    <div class="mb-2">
                        <small class="text-secondary d-block">Puerto</small>
                        <span><?= htmlspecialchars($serverInfo['port'] ?? '---') ?></span>
                    </div>
                    <div>
                        <small class="text-secondary d-block">Protocolo</small>
                        <span><?= htmlspecialchars($serverInfo['server_protocol'] ?? '---') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Acceso rápido -->
    <div class="row g-4">
        <div class="col-md-4">
            <a href="live.php" class="text-decoration-none">
                <div class="card text-center h-100 card-hover">
                    <div class="card-body py-5">
                        <i class="bi bi-tv display-3 text-primary mb-3 d-block"></i>
                        <h5 class="card-title">Live TV</h5>
                        <p class="text-secondary mb-0">Canales en vivo</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="vod.php" class="text-decoration-none">
                <div class="card text-center h-100 card-hover">
                    <div class="card-body py-5">
                        <i class="bi bi-film display-3 text-success mb-3 d-block"></i>
                        <h5 class="card-title">VOD</h5>
                        <p class="text-secondary mb-0">Películas bajo demanda</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="series.php" class="text-decoration-none">
                <div class="card text-center h-100 card-hover">
                    <div class="card-body py-5">
                        <i class="bi bi-collection-play display-3 text-warning mb-3 d-block"></i>
                        <h5 class="card-title">Series</h5>
                        <p class="text-secondary mb-0">Temporadas y episodios</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
