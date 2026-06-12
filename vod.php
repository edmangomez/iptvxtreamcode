<?php
require_once __DIR__ . '/config.php';
requireLogin();

$categoryId = trim($_GET['category_id'] ?? '');
$search     = trim($_GET['search'] ?? '');

$catResult = apiRequest('get_vod_categories');
$categories = is_array($catResult['data'] ?? null) ? $catResult['data'] : [];
$catError   = $catResult['error'] ?? null;

$streams    = [];
$streamError = null;
$selectedCategoryName = '';

if ($categoryId !== '') {
    $streamResult = apiRequest('get_vod_streams', ['category_id' => $categoryId]);
    $streams = is_array($streamResult['data'] ?? null) ? $streamResult['data'] : [];
    $streamError = $streamResult['error'] ?? null;

    foreach ($categories as $c) {
        if ((string)($c['category_id'] ?? '') === $categoryId) {
            $selectedCategoryName = $c['category_name'] ?? '';
            break;
        }
    }
}

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
    <title>VOD — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <h4 class="mb-3"><i class="bi bi-film me-2 text-success"></i>Películas (VOD)</h4>

    <?php if ($catError): ?>
        <div class="alert alert-danger">Error al cargar categorías: <?= htmlspecialchars($catError) ?></div>
    <?php endif; ?>

    <?php if ($streamError): ?>
        <div class="alert alert-danger">Error al cargar películas: <?= htmlspecialchars($streamError) ?></div>
    <?php endif; ?>

    <!-- Búsqueda -->
    <form method="get" class="row g-2 mb-3">
        <?php if ($categoryId): ?>
            <input type="hidden" name="category_id" value="<?= htmlspecialchars($categoryId) ?>">
        <?php endif; ?>
        <div class="col-sm-6 col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="search" placeholder="Buscar película..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
            <?php if ($search !== ''): ?>
                <a href="vod.php<?= $categoryId ? '?category_id=' . urlencode($categoryId) : '' ?>"
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
                    <a href="vod.php"
                       class="list-group-item list-group-item-action <?= $categoryId === '' ? 'active' : '' ?>">
                        <i class="bi bi-grid me-2"></i>Todas las categorías
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <?php $cid = $cat['category_id'] ?? ''; ?>
                        <a href="vod.php?category_id=<?= urlencode((string)$cid) ?>"
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

        <!-- Películas -->
        <div class="col-md-9">
            <?php if ($categoryId !== ''): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <?= htmlspecialchars($selectedCategoryName ?: 'Películas') ?>
                        <small class="text-secondary ms-2"><?= count($streams) ?> películas</small>
                    </h5>
                </div>

                <?php if (empty($streams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <?= $search ? 'No se encontraron películas con ese nombre.' : 'No hay películas en esta categoría.' ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($streams as $s): ?>
                            <?php
                            $sid   = $s['stream_id'] ?? 0;
                            $title = $s['name'] ?? 'Sin título';
                            $poster = $s['stream_icon'] ?? '';
                            $rating = $s['rating'] ?? $s['rating_5based'] ?? null;
                            $year   = $s['year'] ?? $s['added'] ?? '';
                            $ext    = $s['container_extension'] ?? 'mp4';
                            ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100">
                                    <div class="position-relative" style="aspect-ratio: 2/3; overflow: hidden;">
                                        <?php if ($poster): ?>
                                            <img src="<?= htmlspecialchars($poster) ?>" alt="<?= htmlspecialchars($title) ?>"
                                                 class="card-img-top"
                                                 style="height: 100%; width: 100%; object-fit: cover;"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <?php if ($rating): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-star-fill me-1"></i><?= htmlspecialchars((string)$rating) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body d-flex flex-column p-2">
                                        <small class="fw-semibold text-truncate d-block mb-1"
                                               title="<?= htmlspecialchars($title) ?>">
                                            <?= htmlspecialchars($title) ?>
                                        </small>
                                        <?php if ($year): ?>
                                            <small class="text-secondary mb-2"><?= htmlspecialchars((string)$year) ?></small>
                                        <?php endif; ?>
                                        <a href="player.php?type=vod&id=<?= $sid ?>&name=<?= urlencode($title) ?>&ext=<?= urlencode($ext) ?>"
                                            class="btn btn-sm btn-success mt-auto w-100">
                                            <i class="bi bi-play-fill me-1"></i>Reproducir
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
                        <p class="text-secondary mb-0">Elige una categoría del panel izquierdo para ver las películas.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
