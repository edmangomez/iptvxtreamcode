<?php
require_once __DIR__ . '/config.php';
requireLogin();

$q = trim($_GET['q'] ?? '');

$liveResults   = [];
$vodResults    = [];
$seriesResults = [];
$error         = null;

if ($q !== '') {
    // Buscar en Live
    $live = apiRequest('get_live_streams');
    if (!isset($live['error']) && is_array($live['data'])) {
        $liveResults = array_filter($live['data'], function ($s) use ($q) {
            return stripos($s['name'] ?? '', $q) !== false;
        });
    }

    // Buscar en VOD
    $vod = apiRequest('get_vod_streams');
    if (!isset($vod['error']) && is_array($vod['data'])) {
        $vodResults = array_filter($vod['data'], function ($s) use ($q) {
            return stripos($s['name'] ?? '', $q) !== false;
        });
    }

    // Buscar en Series
    $series = apiRequest('get_series');
    if (!isset($series['error']) && is_array($series['data'])) {
        $seriesResults = array_filter($series['data'], function ($s) use ($q) {
            return stripos($s['name'] ?? '', $q) !== false;
        });
    }

    $error = ($live['error'] ?? '') ?: ($vod['error'] ?? '') ?: ($series['error'] ?? '');
}

$total = count($liveResults) + count($vodResults) + count($seriesResults);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <h4 class="mb-3"><i class="bi bi-search me-2 text-primary"></i>Búsqueda Global</h4>

    <form method="get" class="row g-2 mb-4">
        <div class="col-sm-6 col-md-5">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="q" placeholder="Buscar canales, películas, series..."
                       value="<?= htmlspecialchars($q) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </div>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-danger">Error en la búsqueda: <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($q === ''): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-arrow-up display-4 text-secondary d-block mb-3"></i>
                <h5>Escribe algo para buscar</h5>
                <p class="text-secondary mb-0">Busca canales en vivo, películas o series por nombre.</p>
            </div>
        </div>
    <?php elseif ($total === 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No se encontraron resultados para "<strong><?= htmlspecialchars($q) ?></strong>".
        </div>
    <?php else: ?>
        <p class="text-secondary mb-3"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?> para "<strong><?= htmlspecialchars($q) ?></strong>"</p>

        <?php if (!empty($liveResults)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-tv me-2 text-primary"></i>Live TV
                    <span class="badge bg-primary ms-2"><?= count($liveResults) ?></span>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($liveResults, 0, 50) as $s): ?>
                        <a href="player.php?type=live&id=<?= $s['stream_id'] ?? 0 ?>&name=<?= urlencode($s['name'] ?? '') ?>"
                           class="list-group-item list-group-item-action d-flex align-items-center">
                            <?php if (!empty($s['stream_icon'])): ?>
                                <img src="<?= htmlspecialchars($s['stream_icon']) ?>" alt="" style="width:32px;height:32px;object-fit:contain;margin-right:12px;"
                                     onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span class="text-truncate"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                            <i class="bi bi-play-fill ms-auto text-primary"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($liveResults) > 50): ?>
                        <div class="list-group-item text-secondary text-center small">
                            ... y <?= count($liveResults) - 50 ?> resultados más
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($vodResults)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-film me-2 text-success"></i>VOD
                    <span class="badge bg-success ms-2"><?= count($vodResults) ?></span>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($vodResults, 0, 50) as $s): ?>
                        <a href="player.php?type=vod&id=<?= $s['stream_id'] ?? 0 ?>&name=<?= urlencode($s['name'] ?? '') ?>"
                           class="list-group-item list-group-item-action d-flex align-items-center">
                            <?php if (!empty($s['stream_icon'])): ?>
                                <img src="<?= htmlspecialchars($s['stream_icon']) ?>" alt="" style="width:32px;height:48px;object-fit:cover;margin-right:12px;border-radius:4px;"
                                     onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span class="text-truncate"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                            <i class="bi bi-play-fill ms-auto text-success"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($vodResults) > 50): ?>
                        <div class="list-group-item text-secondary text-center small">
                            ... y <?= count($vodResults) - 50 ?> resultados más
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($seriesResults)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-collection-play me-2 text-warning"></i>Series
                    <span class="badge bg-warning text-dark ms-2"><?= count($seriesResults) ?></span>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($seriesResults, 0, 50) as $s): ?>
                        <a href="series_info.php?series_id=<?= $s['series_id'] ?? 0 ?>"
                           class="list-group-item list-group-item-action d-flex align-items-center">
                            <?php if (!empty($s['cover'] ?? $s['stream_icon'] ?? '')): ?>
                                <img src="<?= htmlspecialchars($s['cover'] ?? $s['stream_icon'] ?? '') ?>" alt=""
                                     style="width:32px;height:48px;object-fit:cover;margin-right:12px;border-radius:4px;"
                                     onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span class="text-truncate"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                            <i class="bi bi-chevron-right ms-auto text-warning"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($seriesResults) > 50): ?>
                        <div class="list-group-item text-secondary text-center small">
                            ... y <?= count($seriesResults) - 50 ?> resultados más
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
