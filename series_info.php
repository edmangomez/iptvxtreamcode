<?php
require_once __DIR__ . '/config.php';
requireLogin();

$seriesId = (int)($_GET['series_id'] ?? 0);
if ($seriesId <= 0) {
    header('Location: series.php');
    exit;
}

$result = apiRequest('get_series_info', ['series_id' => $seriesId], 300);
$error     = $result['error'] ?? null;
$info      = $result['data']['info'] ?? [];
$episodes  = $result['data']['episodes'] ?? [];

$title  = $info['name'] ?? 'Serie';
$poster = $info['cover'] ?? '';
$plot   = $info['plot'] ?? '';
$rating = $info['rating'] ?? $info['rating_5based'] ?? null;
$genre  = $info['genre'] ?? '';
$year   = $info['year'] ?? '';
$cast   = $info['cast'] ?? '';
$director = $info['director'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <?php if ($error): ?>
        <div class="alert alert-danger">Error al cargar la serie: <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <a href="series.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="bi bi-arrow-left me-1"></i>Volver a series
    </a>

    <!-- Info de la serie -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-3">
                    <?php if ($poster): ?>
                        <img src="<?= htmlspecialchars($poster) ?>" alt="<?= htmlspecialchars($title) ?>"
                             class="w-100 rounded"
                             style="aspect-ratio: 2/3; object-fit: cover;"
                             onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="w-100 rounded d-flex align-items-center justify-content-center bg-secondary bg-opacity-10"
                         style="aspect-ratio: 2/3; <?= $poster ? 'display:none' : '' ?>">
                        <i class="bi bi-collection-play-fill display-1 text-secondary"></i>
                    </div>
                </div>
                <div class="col-md-9">
                    <h4 class="mb-1"><?= htmlspecialchars($title) ?></h4>
                    <div class="mb-2">
                        <?php if ($year): ?>
                            <span class="badge bg-secondary me-1"><?= htmlspecialchars($year) ?></span>
                        <?php endif; ?>
                        <?php if ($rating): ?>
                            <span class="badge bg-warning text-dark me-1">
                                <i class="bi bi-star-fill me-1"></i><?= htmlspecialchars((string)$rating) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($genre): ?>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($genre) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($plot): ?>
                        <p class="text-secondary mt-3"><?= nl2br(htmlspecialchars($plot)) ?></p>
                    <?php endif; ?>
                    <?php if ($cast): ?>
                        <small class="text-secondary d-block mt-2">
                            <strong>Reparto:</strong> <?= htmlspecialchars($cast) ?>
                        </small>
                    <?php endif; ?>
                    <?php if ($director): ?>
                        <small class="text-secondary d-block">
                            <strong>Director:</strong> <?= htmlspecialchars($director) ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Temporadas y episodios -->
    <?php if (empty($episodes)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No hay episodios disponibles para esta serie.
        </div>
    <?php else: ?>
        <div class="accordion" id="seasonsAccordion">
            <?php
            $seasonNum = 0;
            foreach ($episodes as $seasonKey => $seasonEpisodes):
                $seasonNum++;
                $episodeList = is_array($seasonEpisodes) ? $seasonEpisodes : [];
                $epCount = count($episodeList);
                $collapseId = "season-$seasonNum";
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $seasonNum > 1 ? 'collapsed' : '' ?>"
                                type="button" data-bs-toggle="collapse"
                                data-bs-target="#<?= $collapseId ?>">
                            <i class="bi bi-collection me-2"></i>
                            Temporada <?= $seasonNum ?>
                            <span class="badge bg-secondary ms-2"><?= $epCount ?> episodios</span>
                        </button>
                    </h2>
                    <div id="<?= $collapseId ?>"
                         class="accordion-collapse collapse <?= $seasonNum === 1 ? 'show' : '' ?>"
                         data-bs-parent="#seasonsAccordion">
                        <div class="accordion-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($episodeList as $ep): ?>
                                    <?php
                                    $epId  = $ep['id'] ?? 0;
                                    $epNum = $ep['episode_num'] ?? '';
                                    $epTitle = $ep['title'] ?? 'Episodio ' . $epNum;
                                    $epExt   = $ep['container_extension'] ?? 'mp4';
                                    $duration = $ep['info']['duration'] ?? '';
                                    ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="text-truncate me-3">
                                            <small class="text-secondary me-2"><?= htmlspecialchars((string)$epNum) ?>.</small>
                                            <?= htmlspecialchars($epTitle) ?>
                                            <?php if ($duration): ?>
                                                <small class="text-secondary ms-2">(<?= htmlspecialchars($duration) ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                        <a href="player.php?type=series&id=<?= $epId ?>&ext=<?= urlencode($epExt) ?>&name=<?= urlencode($title . ' - T' . $seasonNum . ' E' . $epNum) ?>"
                                           class="btn btn-sm btn-warning flex-shrink-0">
                                            <i class="bi bi-play-fill me-1"></i>Reproducir
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
