<?php
require_once __DIR__ . '/config.php';
requireLogin();

$categoryId = trim($_GET['category_id'] ?? '');
$search     = trim($_GET['search'] ?? '');

$catResult = apiRequest('get_series_categories');
$categories = is_array($catResult['data'] ?? null) ? $catResult['data'] : [];
$catError   = $catResult['error'] ?? null;

$seriesList = [];
$seriesError = null;
$selectedCategoryName = '';

if ($categoryId !== '') {
    $seriesResult = apiRequest('get_series', ['category_id' => $categoryId]);
    $seriesList = is_array($seriesResult['data'] ?? null) ? $seriesResult['data'] : [];
    $seriesError = $seriesResult['error'] ?? null;

    foreach ($categories as $c) {
        if ((string)($c['category_id'] ?? '') === $categoryId) {
            $selectedCategoryName = $c['category_name'] ?? '';
            break;
        }
    }
}

if ($search !== '' && !empty($seriesList)) {
    $seriesList = array_filter($seriesList, function ($s) use ($search) {
        return stripos($s['name'] ?? '', $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <h4 class="mb-3"><i class="bi bi-collection-play me-2 text-warning"></i>Series</h4>

    <?php if ($catError): ?>
        <div class="alert alert-danger">Error al cargar categorías: <?= htmlspecialchars($catError) ?></div>
    <?php endif; ?>

    <?php if ($seriesError): ?>
        <div class="alert alert-danger">Error al cargar series: <?= htmlspecialchars($seriesError) ?></div>
    <?php endif; ?>

    <!-- Búsqueda -->
    <form method="get" class="row g-2 mb-3">
        <?php if ($categoryId): ?>
            <input type="hidden" name="category_id" value="<?= htmlspecialchars($categoryId) ?>">
        <?php endif; ?>
        <div class="col-sm-6 col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="search" placeholder="Buscar serie..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
            <?php if ($search !== ''): ?>
                <a href="series.php<?= $categoryId ? '?category_id=' . urlencode($categoryId) : '' ?>"
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
                    <a href="series.php"
                       class="list-group-item list-group-item-action <?= $categoryId === '' ? 'active' : '' ?>">
                        <i class="bi bi-grid me-2"></i>Todas las categorías
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <?php $cid = $cat['category_id'] ?? ''; ?>
                        <a href="series.php?category_id=<?= urlencode((string)$cid) ?>"
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

        <!-- Series -->
        <div class="col-md-9">
            <?php if ($categoryId !== ''): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <?= htmlspecialchars($selectedCategoryName ?: 'Series') ?>
                        <small class="text-secondary ms-2"><?= count($seriesList) ?> series</small>
                    </h5>
                </div>

                <?php if (empty($seriesList)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <?= $search ? 'No se encontraron series con ese nombre.' : 'No hay series en esta categoría.' ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($seriesList as $s): ?>
                            <?php
                            $sid    = $s['series_id'] ?? 0;
                            $title  = $s['name'] ?? 'Sin título';
                            $poster = $s['cover'] ?? $s['stream_icon'] ?? '';
                            $rating = $s['rating'] ?? $s['rating_5based'] ?? null;
                            $year   = $s['year'] ?? '';
                            ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <a href="series_info.php?series_id=<?= $sid ?>" class="text-decoration-none">
                                    <div class="card h-100 text-center card-hover">
                                        <div style="aspect-ratio: 2/3; overflow: hidden;">
                                            <?php if ($poster): ?>
                                                <img src="<?= htmlspecialchars($poster) ?>"
                                                     alt="<?= htmlspecialchars($title) ?>"
                                                     class="card-img-top"
                                                     style="height: 100%; width: 100%; object-fit: cover;"
                                                     onerror="this.style.display='none'">
                                            <?php endif; ?>
                                            <div class="h-100 w-100 d-flex align-items-center justify-content-center"
                                                 style="<?= $poster ? 'display:none' : '' ?>">
                                                <i class="bi bi-collection-play-fill display-4 text-secondary"></i>
                                            </div>
                                        </div>
                                        <div class="card-body p-2">
                                            <small class="fw-semibold text-truncate d-block text-white"
                                                   title="<?= htmlspecialchars($title) ?>">
                                                <?= htmlspecialchars($title) ?>
                                            </small>
                                            <small class="text-secondary"><?= htmlspecialchars((string)$year) ?></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-arrow-left-circle display-4 text-secondary d-block mb-3"></i>
                        <h5>Selecciona una categoría</h5>
                        <p class="text-secondary mb-0">Elige una categoría del panel izquierdo para ver las series.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
