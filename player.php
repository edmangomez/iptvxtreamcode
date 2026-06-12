<?php
require_once __DIR__ . '/config.php';
requireLogin();

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$name = $_GET['name'] ?? 'Reproduciendo';
$ext  = $_GET['ext'] ?? '';

if (!in_array($type, ['live', 'vod', 'series'], true) || $id <= 0) {
    header('Location: dashboard.php');
    exit;
}

if ($ext === '') {
    $ext = $type === 'live' ? 'm3u8' : 'ts';
    if ($type === 'vod') {
        $info = apiRequest('get_vod_info', ['vod_id' => $id]);
        $ext = $info['data']['movie_data']['container_extension'] ?? 'mp4';
    }
}

$streamUrl = buildStreamUrl($type, $id, $ext);

$mimeType = match ($ext) {
    'ts'   => 'video/mp2t',
    'mp4'  => 'video/mp4',
    'mkv'  => 'video/x-matroska',
    'm3u8' => 'application/x-mpegURL',
    default => 'video/mp4',
};

$isHls = $ext === 'm3u8';

// EPG para Live
$epgListings = [];
if ($type === 'live') {
    $epgResult = apiRequest('get_short_epg', ['stream_id' => $id]);
    $epgListings = $epgResult['data']['epg_listings'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($name) ?> — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <?php if ($isHls): ?>
    <link href="https://cdn.jsdelivr.net/npm/video.js@8/dist/video-js.min.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-truncate me-3">
            <i class="bi bi-play-circle me-2 text-primary"></i><?= htmlspecialchars($name) ?>
        </h5>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm flex-shrink-0">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <?php if ($isHls): ?>
            <video id="player"
                   class="video-js vjs-default-skin vjs-big-play-centered w-100"
                   controls
                   preload="auto"
                   style="max-height: 80vh; aspect-ratio: 16/9;"
                   data-setup='{"fluid": true}'>
                <source src="<?= htmlspecialchars($streamUrl) ?>" type="application/x-mpegURL">
                <p class="vjs-no-js">Tu navegador no soporta este reproductor.</p>
            </video>
            <?php else: ?>
            <video id="player"
                   class="w-100"
                   controls
                   preload="auto"
                   style="max-height: 80vh; aspect-ratio: 16/9; background: #000;">
                <source src="<?= htmlspecialchars($streamUrl) ?>" type="<?= $mimeType ?>">
                Tu navegador no soporta este formato de video.
            </video>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-3 text-center text-secondary small">
        <i class="bi bi-info-circle me-1"></i>
        Si el stream no carga, verifica que tu proveedor esté activo y tengas conexiones disponibles.
        <?php if (!$isHls): ?>
        <br><span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Si no hay audio, prueba con otro navegador o instala códecs adicionales.</span>
        <?php endif; ?>
    </div>

    <?php if (!empty($epgListings)): ?>
        <div class="card mt-3">
            <div class="card-header py-2">
                <i class="bi bi-calendar-event me-2"></i>Guía de Programación (EPG)
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-striped table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Programa</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($epgListings as $epg): ?>
                            <?php
                            $start = (int)($epg['start'] ?? 0);
                            $end   = (int)($epg['end'] ?? 0);
                            $now   = time();
                            $isNow = $start <= $now && ($end === 0 || $end > $now);
                            ?>
                            <tr class="<?= $isNow ? 'table-active' : '' ?>">
                                <td class="fw-semibold">
                                    <?= $isNow ? '<span class="badge bg-success me-1">EN VIVO</span>' : '' ?>
                                    <?= htmlspecialchars($epg['title'] ?? '---') ?>
                                </td>
                                <td class="text-secondary"><?= $start ? date('H:i', $start) : '---' ?></td>
                                <td class="text-secondary"><?= $end ? date('H:i', $end) : '---' ?></td>
                                <td class="text-secondary small">
                                    <?= htmlspecialchars(mb_substr($epg['description'] ?? '', 0, 120)) ?>
                                    <?= isset($epg['description']) && mb_strlen($epg['description']) > 120 ? '...' : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php if ($isHls): ?>
<script src="https://cdn.jsdelivr.net/npm/video.js@8/dist/video.min.js"></script>
<?php endif; ?>
</body>
</html>
