<?php
require_once __DIR__ . '/backend/api/config.php';

session_start();

if (empty($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

$provider = $_SESSION['provider'];
$serverUrl = 'http://' . $provider['server_url'];
$username = $provider['username'];
$password = $provider['password'];

$type = $_GET['type'] ?? 'vod';
$streamId = $_GET['id'] ?? '';
$seriesId = $_GET['series_id'] ?? '';
$name = $_GET['name'] ?? 'Reproduciendo';
$ext = $_GET['ext'] ?? 'mp4';
$poster = $_GET['poster'] ?? '';

if ($type === 'vod') {
    $streamUrl = "$serverUrl/movie/$username/$password/$streamId.$ext";
} else {
    $streamUrl = "$serverUrl/series/$username/$password/$streamId.$ext";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($name) ?> - IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --bg-primary: #0d1117; --bg-secondary: #161b22; --text-primary: #e6edf3; --text-secondary: #8b949e; --accent: #58a6ff; --border: #30363d; }
        * { margin: 0; padding: 0; }
        body { background: #000; color: var(--text-primary); display: flex; flex-direction: column; height: 100vh; }
        .top-bar { background: var(--bg-secondary); border-bottom: 1px solid var(--border); padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }
        .top-bar a { color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; }
        .top-bar a:hover { color: var(--text-primary); }
        .top-bar .title { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .player-container { flex: 1; display: flex; align-items: center; justify-content: center; }
        .player-container video { max-width: 100%; max-height: 100%; }
        .info-bar { background: var(--bg-secondary); border-top: 1px solid var(--border); padding: 0.5rem 1rem; font-size: 0.85rem; color: var(--text-secondary); flex-shrink: 0; display: flex; align-items: center; gap: 1rem; }
        .info-bar .poster-thumb { width: 40px; height: 56px; border-radius: 4px; overflow: hidden; flex-shrink: 0; background: var(--bg-primary); }
        .info-bar .poster-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .info-bar .poster-thumb .no-poster { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text-secondary); }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="player.php?section=<?= $type ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        <span class="title"><i class="bi bi-play-circle me-1 text-success"></i><?= htmlspecialchars($name) ?></span>
        <?php if ($seriesId): ?>
            <span class="badge bg-accent" style="background:var(--accent);color:#000">Serie</span>
        <?php else: ?>
            <span class="badge bg-accent" style="background:var(--accent);color:#000">Película</span>
        <?php endif; ?>
    </div>

    <div class="player-container">
        <video id="videoPlayer" controls autoplay playsinline>
            <source src="<?= htmlspecialchars($streamUrl) ?>" type="video/mp4">
        </video>
    </div>

    <div class="info-bar">
        <?php if ($poster): ?>
            <div class="poster-thumb">
                <img src="<?= htmlspecialchars($poster) ?>" alt="" onerror="this.parentElement.innerHTML='<div class=no-poster><i class=\'bi bi-film\'></i></div>'">
            </div>
        <?php else: ?>
            <div class="poster-thumb"><div class="no-poster"><i class="bi bi-film"></i></div></div>
        <?php endif; ?>
        <div>
            <div><?= htmlspecialchars($name) ?></div>
            <small><?= $seriesId ? 'Serie' : 'Película' ?> · <?= htmlspecialchars($ext) ?></small>
        </div>
    </div>
</body>
</html>