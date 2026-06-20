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
$directSource = $_GET['direct_source'] ?? '';

// Always build the stream URL from the standard format — $directSource is
// less reliable (can differ per provider) and is intentionally ignored here.
if ($type === 'vod') {
    $streamUrl = "$serverUrl/movie/$username/$password/$streamId.$ext";
} else {
    $streamUrl = "$serverUrl/series/$username/$password/$streamId.$ext";
}

// Transcoding proxy URL: ffmpeg will re-encode audio EAC3→AAC so the
// browser can decode it. Video track is stream-copied (no quality loss).
$transcodedUrl = 'stream.php?transcode=1&url=' . urlencode($streamUrl);
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
        .player-container { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; }
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
        <video id="videoPlayer" controls autoplay playsinline></video>
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
        <div class="ms-auto d-flex align-items-center gap-2">
            <span id="audioBadge" class="badge" style="display:none;"></span>
        </div>
    </div>

    <script>
    // ========================================================================
    // Configuration
    // ========================================================================
    const video        = document.getElementById('videoPlayer');
    const streamUrl    = <?= json_encode($streamUrl) ?>;
    const transcodedUrl = <?= json_encode($transcodedUrl) ?>;

    const historyType     = <?= json_encode($type) ?>;
    const historyId       = <?= json_encode($streamId) ?>;
    const historySeriesId = <?= json_encode($seriesId) ?>;
    const historyName     = <?= json_encode($name) ?>;
    const historyPoster   = <?= json_encode($poster) ?>;

    // ========================================================================
    // DOM References
    // ========================================================================
    const audioBadge = document.getElementById('audioBadge');

    // ========================================================================
    // History Recording
    // ========================================================================
    fetch('/backend/api/history.php?action=record', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            type: historyType, stream_id: String(historyId),
            series_id: historySeriesId || null, name: historyName, poster: historyPoster
        })
    }).catch(() => {});

    // ========================================================================
    // Audio Status UI
    // ========================================================================
    function showAudioOk() {
        audioBadge.style.display = 'inline-block';
        audioBadge.textContent   = '✅ Audio AAC';
        audioBadge.className     = 'badge bg-success';
    }

    function showAudioWarning() {
        audioBadge.style.display = 'inline-block';
        audioBadge.textContent   = '🔇 Sin audio (AC3)';
        audioBadge.className     = 'badge bg-warning text-dark';
    }

    // ========================================================================
    // STARTUP
    //
    // 1. Try transcoding proxy first (video copy + audio AAC → browser-safe)
    // 2. If ffmpeg transcoding fails, fall back to direct URL (video only,
    //    AC3 audio not decodable in browsers) and show a warning badge.
    // ========================================================================
    (function init() {
        // --- Attempt 1: transcoded stream (audio works) ---
        video.src = transcodedUrl;
        video.play().catch(() => {});

        video.onerror = function () {
            console.warn('[watch] Transcoding failed, falling back to direct URL');

            // Remove this handler before setting the new src so it doesn't
            // fire again on the fallback source.
            video.onerror = null;

            video.src = streamUrl;
            video.play().catch(() => {});

            showAudioWarning();
        };

        // Transcoding started without error → mark audio as OK once metadata loads
        video.addEventListener('loadedmetadata', function onMeta() {
            video.removeEventListener('loadedmetadata', onMeta);
            // Only show "AAC" badge if we're still on the transcoded URL
            if (video.src.includes('transcode=1')) {
                showAudioOk();
            }
        }, { once: true });
    })();
    </script>
</body>
</html>
