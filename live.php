<?php
require_once __DIR__ . '/config.php';
requireLogin();

$categoryId = trim($_GET['category_id'] ?? '');
$search     = trim($_GET['search'] ?? '');

// Categorías
$catResult = apiRequest('get_live_categories');
$categories = is_array($catResult['data'] ?? null) ? $catResult['data'] : [];
$catError   = $catResult['error'] ?? null;

// Streams
$streams    = [];
$streamError = null;
$selectedCategoryName = '';

if ($categoryId !== '') {
    $streamResult = apiRequest('get_live_streams', ['category_id' => $categoryId]);
    $streams = is_array($streamResult['data'] ?? null) ? $streamResult['data'] : [];
    $streamError = $streamResult['error'] ?? null;

    foreach ($categories as $c) {
        if ((string)($c['category_id'] ?? '') === $categoryId) {
            $selectedCategoryName = $c['category_name'] ?? '';
            break;
        }
    }
}

// Filtrar por búsqueda
if ($search !== '' && !empty($streams)) {
    $streams = array_filter($streams, function ($s) use ($search) {
        return stripos($s['name'] ?? '', $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live TV — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <h4 class="mb-3"><i class="bi bi-tv me-2 text-primary"></i>Canales en Vivo</h4>

    <?php if ($catError): ?>
        <div class="alert alert-danger">Error al cargar categorías: <?= htmlspecialchars($catError) ?></div>
    <?php endif; ?>

    <?php if ($streamError): ?>
        <div class="alert alert-danger">Error al cargar canales: <?= htmlspecialchars($streamError) ?></div>
    <?php endif; ?>

    <!-- Búsqueda -->
    <form method="get" class="row g-2 mb-3">
        <?php if ($categoryId): ?>
            <input type="hidden" name="category_id" value="<?= htmlspecialchars($categoryId) ?>">
        <?php endif; ?>
        <div class="col-sm-6 col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="search" placeholder="Buscar canal..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
            <?php if ($search !== ''): ?>
                <a href="live.php<?= $categoryId ? '?category_id=' . urlencode($categoryId) : '' ?>"
                   class="btn btn-outline-secondary">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="row">
        <!-- Sidebar categorías -->
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-header py-2">Categorías</div>
                <div class="list-group list-group-flush" style="max-height: 65vh; overflow-y: auto;">
                    <a href="live.php"
                       class="list-group-item list-group-item-action <?= $categoryId === '' ? 'active' : '' ?>">
                        <i class="bi bi-grid me-2"></i>Todas las categorías
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <?php $cid = $cat['category_id'] ?? ''; ?>
                        <a href="live.php?category_id=<?= urlencode((string)$cid) ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                                  <?= (string)$cid === $categoryId ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['category_name'] ?? 'Sin nombre') ?>
                            <span class="badge bg-secondary rounded-pill"><?= $cat['count'] ?? '' ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <div class="list-group-item text-secondary">Sin categorías</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Canales -->
        <div class="col-md-9">
            <?php if ($categoryId !== ''): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <?= htmlspecialchars($selectedCategoryName ?: 'Canales') ?>
                        <small class="text-secondary ms-2"><?= count($streams) ?> canales</small>
                    </h5>
                </div>

                <?php if (empty($streams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <?= $search ? 'No se encontraron canales con ese nombre.' : 'No hay canales en esta categoría.' ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($streams as $s): ?>
                            <?php $sid = $s['stream_id'] ?? 0; ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100 text-center">
                                    <div class="card-body d-flex flex-column align-items-center">
                                        <div class="mb-2"
                                             style="width: 64px; height: 64px; display: flex; align-items: center; justify-content: center;">
                                            <?php if (!empty($s['stream_icon'])): ?>
                                                <img src="<?= htmlspecialchars($s['stream_icon']) ?>" alt=""
                                                     style="max-width: 64px; max-height: 64px;"
                                                     onerror="this.style.display='none'">
                                            <?php endif; ?>
                                            <i class="bi bi-tv-fill fs-1 text-secondary"
                                               style="<?= empty($s['stream_icon']) ? '' : 'display:none' ?>"></i>
                                        </div>
                                        <small class="fw-semibold text-truncate w-100 d-block mb-2"
                                               title="<?= htmlspecialchars($s['name'] ?? '') ?>">
                                            <?= htmlspecialchars($s['name'] ?? 'Sin nombre') ?>
                                        </small>
                                        <a href="player.php?type=live&id=<?= $sid ?>&name=<?= urlencode($s['name'] ?? 'Canal') ?>"
                                           class="btn btn-sm btn-primary mt-auto w-100">
                                            <i class="bi bi-play-fill me-1"></i>Ver
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-arrow-left-circle display-4 text-secondary d-block mb-3"></i>
                        <h5>Selecciona una categoría</h5>
                        <p class="text-secondary mb-0">Elige una categoría del panel izquierdo para ver los canales.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
