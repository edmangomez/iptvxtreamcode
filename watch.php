<?php
require_once __DIR__ . '/backend/api/config.php';

session_start();

if (empty($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

$provider  = $_SESSION['provider'];
$serverUrl = 'http://' . $provider['server_url'];
$username  = $provider['username'];
$password  = $provider['password'];

$type       = $_GET['type']          ?? 'vod';
$streamId   = $_GET['id']            ?? '';
$seriesId   = $_GET['series_id']     ?? '';
$name       = $_GET['name']          ?? 'Reproduciendo';
$ext        = $_GET['ext']           ?? 'mp4';
$poster     = $_GET['poster']        ?? '';
// $directSource intentionally ignored — build URL from standard format only
$directSource = $_GET['direct_source'] ?? '';

// Always build the stream URL from the standard format — $directSource is
// less reliable (can differ per provider) and is intentionally ignored here.
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
        .player-container { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .player-container video { max-width: 100%; max-height: 100%; }
        .info-bar { background: var(--bg-secondary); border-top: 1px solid var(--border); padding: 0.5rem 1rem; font-size: 0.85rem; color: var(--text-secondary); flex-shrink: 0; display: flex; align-items: center; gap: 1rem; }
        .info-bar .poster-thumb { width: 40px; height: 56px; border-radius: 4px; overflow: hidden; flex-shrink: 0; background: var(--bg-primary); }
        .info-bar .poster-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .info-bar .poster-thumb .no-poster { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text-secondary); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest/dist/hls.min.js"></script>
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
        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap" id="controlBar">
            <span id="statusBadge" class="badge bg-secondary" style="display:none">Iniciando...</span>
            <div id="audioBar" class="d-flex gap-1" style="display:none"></div>
            <div id="subBar"   class="d-flex gap-1" style="display:none"></div>
        </div>
    </div>

    <script>
    // =========================================================================
    // Config — PHP values injected once, never duplicated
    // =========================================================================
    const video       = document.getElementById('videoPlayer');
    const streamUrl   = <?= json_encode($streamUrl) ?>;
    const statusBadge = document.getElementById('statusBadge');
    const audioBar    = document.getElementById('audioBar');
    const subBar      = document.getElementById('subBar');

    // =========================================================================
    // History recording (fire-and-forget)
    // =========================================================================
    fetch('/backend/api/history.php?action=record', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            type:      <?= json_encode($type) ?>,
            stream_id: <?= json_encode($streamId) ?>,
            series_id: <?= json_encode($seriesId ?: null) ?>,
            name:      <?= json_encode($name) ?>,
            poster:    <?= json_encode($poster) ?>
        })
    }).catch(() => {});

    // =========================================================================
    // State
    // =========================================================================
    let hls         = null;
    let sessionData = null;
    const isWindowsServer = <?= json_encode(PHP_OS_FAMILY === 'Windows') ?>;

    // =========================================================================
    // Start a new HLS session on the server
    // Returns the parsed JSON descriptor ({ session, playlistUrl, audioTracks,
    // subTracks, currentAudio }) or throws on network/server error.
    // =========================================================================
    async function startSession(audioIdx) {
        statusBadge.textContent   = 'Iniciando...';
        statusBadge.className     = 'badge bg-secondary';
        statusBadge.style.display = 'inline-block';

        const res = await fetch('stream.php?' + new URLSearchParams({
            hls:   'start',
            url:   streamUrl,
            audio: audioIdx ?? 0
        }));
        if (!res.ok) throw new Error('Session start failed: ' + res.status);
        return res.json();
    }

    // =========================================================================
    // Render audio-track buttons
    // Hidden when there is only one track (nothing to choose from).
    // =========================================================================
    function renderAudioButtons(tracks, currentIdx) {
        audioBar.innerHTML = '';
        if (tracks.length <= 1) { audioBar.style.display = 'none'; return; }

        tracks.forEach(t => {
            const btn = document.createElement('button');
            btn.className   = 'btn btn-xs ' + (t.index === currentIdx ? 'btn-primary' : 'btn-outline-secondary');
            btn.style.fontSize = '0.75rem';
            btn.style.padding  = '2px 8px';
            btn.textContent = t.lang || ('Audio ' + (t.index + 1));
            btn.onclick     = () => switchAudio(t.index);
            audioBar.appendChild(btn);
        });
        audioBar.style.display = 'flex';
    }

    // =========================================================================
    // Render subtitle-track buttons (always shows "Sub OFF" first)
    // =========================================================================
    function renderSubButtons(tracks) {
        subBar.innerHTML = '';
        if (tracks.length === 0) { subBar.style.display = 'none'; return; }

        // "Off" button — active by default
        const offBtn = document.createElement('button');
        offBtn.className      = 'btn btn-xs btn-outline-secondary active';
        offBtn.style.fontSize = '0.75rem';
        offBtn.style.padding  = '2px 8px';
        offBtn.textContent    = 'Sub OFF';
        offBtn.dataset.idx    = '-1';
        offBtn.onclick        = () => activateSub(-1);
        subBar.appendChild(offBtn);

        tracks.forEach(t => {
            const btn = document.createElement('button');
            btn.className      = 'btn btn-xs btn-outline-secondary';
            btn.style.fontSize = '0.75rem';
            btn.style.padding  = '2px 8px';
            btn.textContent    = t.lang || ('Sub ' + (t.index + 1));
            btn.dataset.idx    = t.index;
            btn.onclick        = () => activateSub(t.index);
            subBar.appendChild(btn);
        });
        subBar.style.display = 'flex';
    }

    // =========================================================================
    // Activate a subtitle track (or turn off)
    // Injects a <track> element pointing at the per-session VTT file served
    // by stream.php?hls=serve&session=…&file=subN.vtt
    // =========================================================================
    function activateSub(idx) {
        // Update button active states
        subBar.querySelectorAll('button').forEach(btn => {
            const isActive = parseInt(btn.dataset.idx) === idx;
            btn.className      = 'btn btn-xs ' + (isActive ? 'btn-primary' : 'btn-outline-secondary');
            btn.style.fontSize = '0.75rem';
            btn.style.padding  = '2px 8px';
        });

        // Remove any existing <track> elements
        video.querySelectorAll('track').forEach(t => t.remove());
        if (idx < 0) return;

        // Inject a new <track> pointing at the VTT served by the HLS session
        const track    = document.createElement('track');
        track.kind     = 'subtitles';
        track.default  = true;
        track.src      = 'stream.php?hls=serve&session=' + sessionData.session + '&file=sub' + idx + '.vtt';
        video.appendChild(track);

        // Some browsers need an explicit mode set after a tick
        setTimeout(() => {
            if (video.textTracks[0]) video.textTracks[0].mode = 'showing';
        }, 200);
    }

    // =========================================================================
    // Switch audio track — destroys the current HLS instance, starts a new
    // session with the requested audio index, and resumes at the saved position.
    // =========================================================================
    async function switchAudio(idx) {
        const savedTime = video.currentTime;
        if (hls) { hls.destroy(); hls = null; }

        try {
            sessionData = await startSession(idx);
            renderAudioButtons(sessionData.audioTracks, idx);
            renderSubButtons(sessionData.subTracks);
            await loadHls(sessionData.playlistUrl, savedTime);
        } catch (e) {
            console.error('[switchAudio]', e);
            statusBadge.textContent = 'Error';
            statusBadge.className   = 'badge bg-danger';
        }
    }

    // =========================================================================
    // Load an HLS playlist via HLS.js (with Safari native-HLS fallback).
    // seekTo: resume position in seconds (0 = play from start).
    // =========================================================================
    function loadHls(playlistUrl, seekTo) {
        if (hls) { hls.destroy(); hls = null; }

        // Safari has native HLS — HLS.js not needed there
        if (!Hls.isSupported()) {
            video.src = playlistUrl;
            if (seekTo > 1) {
                video.addEventListener('loadedmetadata', () => {
                    video.currentTime = seekTo;
                }, { once: true });
            }
            video.play().catch(() => {});
            statusBadge.style.display = 'none';
            return;
        }

        hls = new Hls({
            enableWorker:           true,
            maxMaxBufferLength:     120,
            manifestLoadingTimeOut: 30000,
            levelLoadingTimeOut:    30000,
            fragLoadingTimeOut:     30000,
        });

        hls.loadSource(playlistUrl);
        hls.attachMedia(video);

        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            statusBadge.style.display = 'none';
            if (seekTo > 1) video.currentTime = seekTo;
            video.play().catch(() => {});
        });

        hls.on(Hls.Events.ERROR, (e, data) => {
            if (!data.fatal) return;
            console.error('[HLS fatal]', data.type, data.details);
            hls.destroy();
            hls = null;
            // Last-resort fallback: direct URL (video works; AC3/EAC3 audio may not,
            // but at least the picture appears and the user can hear AAC tracks)
            statusBadge.style.display = 'none';
            video.src = streamUrl;
            video.play().catch(() => {});
        });
    }

    // =========================================================================
    // Startup — request session, render UI, begin playback
    // Falls back to direct URL if the HLS session cannot be created.
    // =========================================================================
    (async function init() {
        if (isWindowsServer) {
            statusBadge.style.display = 'none';
            const transcodeUrl = 'stream.php?transcode=1&url=' + encodeURIComponent(streamUrl);
            video.src = transcodeUrl;
            video.play().catch(() => {
                statusBadge.style.display = 'inline-block';
                statusBadge.textContent = 'Tap Play';
                statusBadge.className = 'badge bg-warning text-dark';
            });
            return;
        }

        try {
            sessionData = await startSession(0);
            renderAudioButtons(sessionData.audioTracks, sessionData.currentAudio);
            renderSubButtons(sessionData.subTracks);
            loadHls(sessionData.playlistUrl, 0);
        } catch (e) {
            console.error('[init]', e);
            // Could not start an HLS session — play direct stream as a last resort
            statusBadge.style.display = 'none';
            video.src = streamUrl;
            video.play().catch(() => {});
        }
    })();
    </script>
</body>
</html>
