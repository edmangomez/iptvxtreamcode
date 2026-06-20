<?php
require_once __DIR__ . '/backend/api/config.php';

session_start();

if (empty($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

$provider = $_SESSION['provider'];
$subscription = $_SESSION['subscription'];
$serverUrl = 'http://' . $provider['server_url'];
$username = $provider['username'];
$password = $provider['password'];

function cacheGet($key, $ttl) {
    $file = sys_get_temp_dir() . '/xtream_' . md5($key) . '.cache';
    if (file_exists($file) && filemtime($file) + $ttl > time()) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function cacheSet($key, $data, $ttl) {
    $file = sys_get_temp_dir() . '/xtream_' . md5($key) . '.cache';
    file_put_contents($file, json_encode($data));
}

function apiRequest($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? json_decode($resp, true) : null;
}

$liveCategories = cacheGet("live_cat_$username", 300) ?? apiRequest("$serverUrl/player_api.php?username=$username&password=$password&action=get_live_categories");
if ($liveCategories) cacheSet("live_cat_$username", $liveCategories, 300);

$vodCategories = cacheGet("vod_cat_$username", 300) ?? apiRequest("$serverUrl/player_api.php?username=$username&password=$password&action=get_vod_categories");
if ($vodCategories) cacheSet("vod_cat_$username", $vodCategories, 300);

$seriesCategories = cacheGet("series_cat_$username", 300) ?? apiRequest("$serverUrl/player_api.php?username=$username&password=$password&action=get_series_categories");
if ($seriesCategories) cacheSet("series_cat_$username", $seriesCategories, 300);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-card: #1c2333;
            --bg-hover: #21262d;
            --text-primary: #e6edf3;
            --text-secondary: #8b949e;
            --accent: #58a6ff;
            --border: #30363d;
            --success: #3fb950;
            --danger: #f85149;
            --warning: #d29922;
        }
        * { scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
        body { background-color: var(--bg-primary); color: var(--text-primary); }
        .navbar { background-color: var(--bg-secondary) !important; border-bottom: 1px solid var(--border); }
        .nav-link { color: var(--text-secondary) !important; transition: all 0.2s; }
        .nav-link:hover, .nav-link.active { color: var(--text-primary) !important; background-color: var(--bg-card); }
        .card { background-color: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; }
        .list-group-item { background-color: var(--bg-card); border-color: var(--border); color: var(--text-primary); cursor: pointer; }
        .list-group-item:hover { background-color: var(--bg-hover); }
        .list-group-item.active { background-color: var(--accent); border-color: var(--accent); color: #000; }
        .user-info { font-size: 0.85rem; color: var(--text-secondary); }
        .player-wrapper { aspect-ratio: 16/9; background: #000; border-radius: 10px; overflow: hidden; position: relative; }
        .player-wrapper video { width: 100%; height: 100%; object-fit: contain; }

        .cat-pills { scrollbar-width: none; -ms-overflow-style: none; }
        .cat-pills::-webkit-scrollbar { display: none; }
        .cat-pill { white-space: nowrap; color: var(--text-secondary); border-color: var(--border); background: transparent; font-size: 0.85rem; }
        .cat-pill:hover, .cat-pill.active { background: var(--accent); border-color: var(--accent); color: #000; }

        .movie-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        .movie-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer; transition: transform 0.2s; }
        .movie-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.4); }
        .movie-card-poster { aspect-ratio: 2/3; background: var(--bg-secondary); position: relative; overflow: hidden; }
        .movie-card-poster img { width: 100%; height: 100%; object-fit: cover; }
        .movie-card-poster .placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--text-secondary); }
        .movie-card-body { padding: 0.5rem; }
        .movie-card-body h6 { font-size: 0.8rem; margin: 0; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: var(--text-primary); }
        .movie-card-rating { position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.75); padding: 1px 5px; border-radius: 4px; font-size: 0.7rem; color: var(--warning); }
        .movie-card-year { position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,0.75); padding: 1px 5px; border-radius: 4px; font-size: 0.7rem; color: var(--text-secondary); }

        @media (max-width: 767.98px) {
            .movie-grid { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            .scroll-area { max-height: none !important; }
            .main-row > [class*="col-"] { padding-bottom: 0.5rem; }
        }
        @media (min-width: 768px) and (max-width: 991.98px) {
            .movie-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 992px) {
            .movie-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
        }

        .scroll-area { max-height: calc(100vh - 160px); overflow-y: auto; }
        .scroll-area::-webkit-scrollbar { width: 5px; }
        .scroll-area::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .now-playing { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.4rem 0.8rem; font-size: 0.9rem; }

        .search-input { background: var(--bg-secondary) !important; border-color: var(--border) !important; color: var(--text-primary) !important; }
        .search-input:focus { background: var(--bg-secondary) !important; border-color: var(--accent) !important; color: var(--text-primary) !important; box-shadow: 0 0 0 0.15rem rgba(88,166,255,0.25) !important; }
        .search-input::placeholder { color: var(--text-secondary) !important; opacity: 1 !important; }
        .loading-spinner { color: var(--accent); }
        .episode-group { margin-bottom: 1rem; }
        .episode-group h6 { color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 0.25rem; margin-bottom: 0.5rem; }
        .episode-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; padding: 0.4rem 0.7rem; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem; }
        .episode-item:hover { background: var(--bg-hover); }
        .episode-num { color: var(--text-secondary); font-size: 0.8rem; min-width: 2rem; }
        .episode-title { flex: 1; font-size: 0.9rem; }
        .episode-play { color: var(--accent); }

        .recent-row { border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
        .recent-row .recent-header { font-size: 0.85rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; }
        .recent-row .recent-header i { color: var(--accent); }
        .recent-scroll { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem; scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
        .recent-scroll::-webkit-scrollbar { height: 4px; }
        .recent-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
        .recent-item { flex: 0 0 auto; width: 90px; cursor: pointer; text-align: center; transition: transform 0.15s; }
        .recent-item:hover { transform: translateY(-2px); }
        .recent-item .poster { width: 90px; height: 60px; border-radius: 6px; overflow: hidden; background: var(--bg-secondary); position: relative; }
        .recent-item .poster img { width: 100%; height: 100%; object-fit: cover; }
        .recent-item .poster .no-poster { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text-secondary); }
        .recent-item .name { font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .recent-item .badge-type { position: absolute; top: 2px; left: 2px; font-size: 0.55rem; padding: 1px 4px; border-radius: 3px; }
        .recent-item .badge-live { background: var(--danger); color: #fff; }
        .recent-item .badge-vod { background: var(--accent); color: #000; }
        .recent-item .badge-series { background: var(--warning); color: #000; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-tv me-2"></i>IPTV</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="#" data-section="live"><i class="bi bi-broadcast me-1"></i>Live</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-section="vod"><i class="bi bi-film me-1"></i>Películas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-section="series"><i class="bi bi-collection-play me-1"></i>Series</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <div class="user-info text-end d-none d-md-block">
                        <div><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
                        <div class="small">Expira: <?= $subscription['end_date'] ?></div>
                    </div>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-left"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <div id="recentContainer" class="container-fluid mt-1 recent-row" style="display:none">
        <div class="recent-header mb-1"><i class="bi bi-clock-history"></i>Recientes</div>
        <div id="recentScroll" class="recent-scroll"></div>
    </div>

    <div class="container-fluid mt-2">
        <div class="row g-2 main-row">
            <!-- Categories sidebar (desktop only) -->
            <div class="col-lg-2 col-md-3 d-none d-md-block">
                <div class="card">
                    <div class="card-body p-0">
                        <div id="categoryList" class="list-group list-group-flush scroll-area"></div>
                    </div>
                </div>
            </div>

            <!-- LIVE: player column -->
            <div class="col-lg-6 col-md-5 col-12" id="livePlayerCol">
                <div class="player-wrapper" id="playerWrapper">
                    <video id="videoPlayer" class="w-100 h-100" controls playsinline></video>
                </div>

                <div id="nowPlaying" class="now-playing mt-2 d-flex align-items-center justify-content-between" style="display:none !important;">
                    <div><i class="bi bi-play-circle me-2 text-success"></i><span id="nowPlayingText"></span></div>
                    <button class="btn btn-sm btn-outline-danger" onclick="stopPlayback()"><i class="bi bi-stop-fill"></i></button>
                </div>
            </div>

            <!-- LIVE: right panel -->
            <div class="col-lg-4 col-md-4 col-12" id="livePanel">
                <div class="d-md-none mb-2">
                    <div id="mobilePills" class="cat-pills d-flex gap-1 overflow-auto pb-1"></div>
                </div>
                <div class="mt-1 mb-2">
                    <input type="search" class="form-control form-control-sm search-input" id="streamSearch" placeholder="Buscar en esta categoría...">
                </div>
                <div id="streamList" class="list-group list-group-flush scroll-area"></div>
            </div>

            <!-- VOD/SERIES: grid column (hidden for Live) -->
            <div id="vodSeriesCol" class="col-lg-10 col-md-9 col-12" style="display:none">
                <div class="d-md-none mb-2">
                    <div id="vodPills" class="cat-pills d-flex gap-1 overflow-auto pb-1"></div>
                </div>
                <div class="row mt-1 mb-2">
                    <div class="col-12">
                        <input type="search" class="form-control form-control-sm search-input" id="vodSearch" placeholder="Buscar en esta categoría...">
                    </div>
                </div>
                <div id="loadingIndicator" class="text-center py-5" style="display:none">
                    <div class="spinner-border loading-spinner" style="width:3rem;height:3rem" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-secondary"><i class="bi bi-hourglass-split me-2"></i>Cargando catálogo...</p>
                </div>
                <div id="streamGrid"></div>
                <div id="episodeContainer" style="display:none">
                    <button class="btn btn-sm btn-outline-secondary mb-2" onclick="closeEpisodes()">
                        <i class="bi bi-arrow-left me-1"></i>Volver a series
                    </button>
                    <div id="episodeList"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const serverUrl = '<?= $serverUrl ?>';
    const username = '<?= $username ?>';
    const password = '<?= $password ?>';
    const categories = {
        live: <?= json_encode($liveCategories ?? []) ?>,
        vod: <?= json_encode($vodCategories ?? []) ?>,
        series: <?= json_encode($seriesCategories ?? []) ?>
    };
    let currentSection = 'live';
    let currentCategory = null;
    let streams = {};
    let hls = null;
    let currentStream = null;

    function destroyHls() {
        if (hls) { hls.destroy(); hls = null; }
    }

    function stopPlayback() {
        destroyHls();
        const v = document.getElementById('videoPlayer');
        v.pause();
        v.removeAttribute('src');
        v.load();
        document.getElementById('nowPlaying').style.display = 'none';
        currentStream = null;
    }

    async function playStream(stream) {
        currentStream = stream;
        const name = stream.name || stream.title;
        document.getElementById('nowPlayingText').textContent = name;
        document.getElementById('nowPlaying').style.display = 'flex';

        const videoEl = document.getElementById('videoPlayer');
        videoEl.removeAttribute('src');
        videoEl.load();
        destroyHls();

        const streamId = stream.stream_id || stream.id;
        recordHistory({ type: 'live', stream_id: String(streamId), name: name, poster: stream.stream_icon || '' });
        const streamUrl = `${serverUrl}/live/${username}/${password}/${streamId}.m3u8`;

        if (Hls.isSupported()) {
            hls = new Hls();
            hls.loadSource(streamUrl);
            hls.attachMedia(videoEl);
            hls.on(Hls.Events.MANIFEST_PARSED, () => videoEl.play().catch(() => {}));
            hls.on(Hls.Events.ERROR, (e, data) => {
                if (data.fatal && data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                    destroyHls();
                    videoEl.src = streamUrl;
                    videoEl.play().catch(() => {});
                }
            });
        } else if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
            videoEl.src = streamUrl;
            videoEl.play().catch(() => {});
        }
    }

    async function loadStreams(categoryId, type) {
        const ck = `${type}_${categoryId}_${username}`;
        if (streams[ck]) return streams[ck];
        const action = type === 'series' ? 'get_series' : `get_${type}_streams`;
        const url = `/backend/api/proxy.php?action=${action}&category_id=${categoryId}`;
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            streams[ck] = data || [];
            return streams[ck];
        } catch (e) {
            return [];
        }
    }

    async function loadSeriesEpisodes(seriesId) {
        const ck = `episodes_${seriesId}_${username}`;
        if (streams[ck]) return streams[ck];
        const url = `/backend/api/proxy.php?action=get_series_info&series_id=${seriesId}`;
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            const episodes = data && data.episodes ? data.episodes : {};
            streams[ck] = episodes;
            return episodes;
        } catch (e) {
            return {};
        }
    }

    function renderCategories(cats, type) {
        const desktop = document.getElementById('categoryList');
        const mobile = document.getElementById('mobilePills');
        const vodPills = document.getElementById('vodPills');
        if (desktop) desktop.innerHTML = '';
        if (mobile) mobile.innerHTML = '';
        if (vodPills) vodPills.innerHTML = '';

        [{ el: desktop, isPill: false }, { el: mobile, isPill: true }, { el: vodPills, isPill: true }].forEach(({ el, isPill }) => {
            if (!el) return;

            (cats || []).forEach((cat, idx) => {
                const e = document.createElement(isPill ? 'button' : 'a');
                const isActive = currentCategory == cat.category_id;
                if (isPill) {
                    e.className = 'btn btn-sm cat-pill' + (isActive ? ' active' : '');
                    e.textContent = cat.category_name;
                    e.onclick = () => { currentCategory = cat.category_id; selectPill(e); loadSectionContent(type); };
                } else {
                    e.href = '#';
                    e.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center' + (isActive ? ' active' : '');
                    e.innerHTML = `<span>${cat.category_name}</span><span class="badge bg-secondary">${cat.num_streams || ''}</span>`;
                    e.onclick = () => { currentCategory = cat.category_id; loadSectionContent(type); };
                }
                el.appendChild(e);
            });
        });
    }

    function selectPill(el) {
        document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
    }

    function showLoading() {
        const loader = document.getElementById('loadingIndicator');
        const grid = document.getElementById('streamGrid');
        const episodes = document.getElementById('episodeContainer');
        if (loader) loader.style.display = 'block';
        if (grid) grid.style.display = 'none';
        if (episodes) episodes.style.display = 'none';
    }

    function hideLoading() {
        const loader = document.getElementById('loadingIndicator');
        if (loader) loader.style.display = 'none';
    }

    function getSearchValue(type) {
        if (type === 'live') {
            const el = document.getElementById('streamSearch');
            return el ? el.value : '';
        }
        const el = document.getElementById('vodSearch');
        return el ? el.value : '';
    }

    function loadSectionContent(type) {
        const filter = getSearchValue(type);
        if (type === 'live') {
            renderStreams(type, filter);
            return;
        }
        showLoading();
        renderStreams(type, filter);
    }

    async function renderStreams(type, filter = '') {
        const isGrid = type === 'vod' || type === 'series';
        const container = isGrid ? document.getElementById('streamGrid') : document.getElementById('streamList');
        if (!container) {
            hideLoading();
            return;
        }

        container.innerHTML = '';
        if (isGrid) container.style.display = 'block';
        const ec = document.getElementById('episodeContainer');
        if (ec) ec.style.display = 'none';

        let data;
        try {
            if (!currentCategory) { hideLoading(); return; }
            data = await loadStreams(currentCategory, type);

            if (filter) {
                const lf = filter.toLowerCase();
                data = data.filter(s => (s.name || s.title || s.stream_display_name || '').toLowerCase().includes(lf));
            }
        } catch (e) {
            data = [];
        } finally {
            hideLoading();
        }

        if (!data || data.length === 0) {
            container.innerHTML = '<div class="text-secondary p-3 text-center"><i class="bi bi-inbox me-2"></i>Sin resultados</div>';
            return;
        }

        if (isGrid) {
            const grid = document.createElement('div');
            grid.className = 'movie-grid';
            data.forEach(stream => {
                const title = stream.title || stream.name || stream.stream_display_name;
                const year = stream.year || '';
                const rating = stream.rating || '';
                const poster = stream.stream_icon || stream.cover || '';
                const card = document.createElement('div');
                card.className = 'movie-card';
                card.innerHTML = `
                    <div class="movie-card-poster">
                        ${poster ? `<img src="${poster}" alt="" loading="lazy" onerror="this.parentElement.innerHTML='<div class=placeholder><i class=\\'bi bi-film\\'></i></div>'">`
                                 : `<div class="placeholder"><i class="bi bi-film"></i></div>`}
                        ${rating ? `<span class="movie-card-rating"><i class="bi bi-star-fill me-1"></i>${rating}</span>` : ''}
                        ${year ? `<span class="movie-card-year">${year}</span>` : ''}
                    </div>
                    <div class="movie-card-body"><h6>${title}</h6></div>
                `;
                if (type === 'series') {
                    card.onclick = () => {
                        const sid = stream.series_id || stream.id;
                        recordHistory({ type: 'series', stream_id: String(sid), name: title, poster: poster });
                        showSeriesEpisodes(stream);
                    };
                } else {
                    card.onclick = () => {
                        const sid = stream.stream_id || stream.id;
                        const ext = stream.container_extension || 'mp4';
                        const ds = stream.direct_source || '';
                        recordHistory({ type: 'vod', stream_id: String(sid), name: title, poster: poster });
                        window.location.href = `watch.php?type=vod&id=${sid}&name=${encodeURIComponent(title)}&ext=${ext}&poster=${encodeURIComponent(poster)}&direct_source=${encodeURIComponent(ds)}`;
                    };
                }
                grid.appendChild(card);
            });
            container.appendChild(grid);
        } else {
            data.forEach(stream => {
                const name = stream.name || stream.title || stream.stream_display_name;
                const num = stream.num || '';
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action d-flex align-items-center';
                item.innerHTML = `<span class="me-auto">${name}</span>${num ? `<span class="badge bg-secondary ms-2">${num}</span>` : ''}`;
                item.onclick = () => playStream(stream);
                container.appendChild(item);
            });
        }
    }

    async function showSeriesEpisodes(series) {
        const seriesId = series.series_id || series.id;
        showLoading();
        document.getElementById('streamGrid').style.display = 'none';

        let episodes;
        try {
            episodes = await loadSeriesEpisodes(seriesId);
        } finally {
            hideLoading();
        }

        const container = document.getElementById('episodeContainer');
        const list = document.getElementById('episodeList');
        list.innerHTML = '';

        if (!episodes || Object.keys(episodes).length === 0) {
            list.innerHTML = '<div class="text-secondary p-3 text-center">Sin episodios disponibles</div>';
            container.style.display = 'block';
            return;
        }

        const seasons = Object.keys(episodes).sort((a, b) => parseInt(a) - parseInt(b));
        seasons.forEach(season => {
            const eps = episodes[season];
            if (!eps || eps.length === 0) return;
            const group = document.createElement('div');
            group.className = 'episode-group';
            const heading = document.createElement('h6');
            heading.innerHTML = `<i class="bi bi-collection me-1"></i>Temporada ${season}`;
            group.appendChild(heading);
            eps.forEach(ep => {
                const epId = ep.id;
                const epTitle = ep.title || `Episodio ${ep.episode_num || ''}`;
                const epNum = ep.episode_num || ep.info?.episode_num || '';
                const row = document.createElement('div');
                row.className = 'episode-item';
                row.innerHTML = `
                    <span class="episode-num">${epNum ? `${epNum}.` : ''}</span>
                    <span class="episode-title">${epTitle}</span>
                    <span class="episode-play"><i class="bi bi-play-circle-fill"></i></span>
                `;
                row.onclick = () => {
                    const ext = ep.container_extension || 'mp4';
                    const ds = ep.direct_source || '';
                    recordHistory({ type: 'series', stream_id: String(epId), series_id: String(seriesId), name: epTitle, poster: series.stream_icon || series.cover || '', season_num: season, episode_num: epNum });
                    window.location.href = `watch.php?type=series&id=${epId}&series_id=${seriesId}&name=${encodeURIComponent(epTitle)}&ext=${ext}&poster=${encodeURIComponent(series.stream_icon || series.cover || '')}&direct_source=${encodeURIComponent(ds)}`;
                };
                group.appendChild(row);
            });
            list.appendChild(group);
        });

        container.style.display = 'block';
    }

    function closeEpisodes() {
        document.getElementById('episodeContainer').style.display = 'none';
        document.getElementById('streamGrid').style.display = 'block';
    }

    function switchSection(section) {
        currentSection = section;
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.querySelector(`[data-section="${section}"]`).classList.add('active');
        currentCategory = null;

        const isGrid = section === 'vod' || section === 'series';

        if (isGrid) {
            stopPlayback();
            document.getElementById('livePlayerCol').style.display = 'none';
            document.getElementById('livePanel').style.display = 'none';
            document.getElementById('vodSeriesCol').style.display = '';
        } else {
            document.getElementById('livePlayerCol').style.display = '';
            document.getElementById('livePanel').style.display = '';
            document.getElementById('vodSeriesCol').style.display = 'none';
            document.getElementById('playerWrapper').innerHTML = '<video id="videoPlayer" class="w-100 h-100" controls playsinline></video>';
            document.getElementById('nowPlaying').style.display = 'none';
        }

        const cats = categories[section] || [];
        if (cats.length > 0) {
            currentCategory = cats[0].category_id;
        }
        renderCategories(cats, section);
        loadSectionContent(section);
    }

    async function recordHistory(item) {
        try { await fetch('/backend/api/history.php?action=record', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(item) }); } catch {}
    }

    async function loadRecentHistory() {
        try {
            const resp = await fetch('/backend/api/history.php?action=get&limit=15');
            const data = await resp.json();
            if (!data || data.length === 0) { document.getElementById('recentContainer').style.display = 'none'; return; }
            document.getElementById('recentContainer').style.display = '';
            const scroll = document.getElementById('recentScroll');
            scroll.innerHTML = '';
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'recent-item';
                const badgeClass = item.type === 'live' ? 'badge-live' : item.type === 'vod' ? 'badge-vod' : 'badge-series';
                const badgeLabel = item.type === 'live' ? 'LIVE' : item.type === 'vod' ? 'VOD' : 'SERIE';
                div.innerHTML = `
                    <div class="poster">
                        ${item.poster ? `<img src="${item.poster}" alt="" loading="lazy" onerror="this.parentElement.innerHTML='<div class=no-poster><i class=\\'bi bi-tv\\'></i></div>'">`
                            : `<div class="no-poster"><i class="bi bi-tv"></i></div>`}
                        <span class="badge-type ${badgeClass}">${badgeLabel}</span>
                    </div>
                    <div class="name" title="${item.name}">${item.name}</div>
                `;
                div.onclick = () => {
                    if (item.type === 'live') {
                        switchSection('live');
                    } else if (item.type === 'vod') {
                        window.location.href = `watch.php?type=vod&id=${item.stream_id}&name=${encodeURIComponent(item.name)}&poster=${encodeURIComponent(item.poster || '')}`;
                    } else if (item.type === 'series') {
                        if (item.stream_id) {
                            window.location.href = `watch.php?type=series&id=${item.stream_id}&series_id=${item.series_id || ''}&name=${encodeURIComponent(item.name)}&poster=${encodeURIComponent(item.poster || '')}`;
                        }
                    }
                };
                scroll.appendChild(div);
            });
        } catch {}
    }

    document.getElementById('vodSearch').oninput = e => renderStreams(currentSection, e.target.value);
    document.getElementById('streamSearch').oninput = e => renderStreams(currentSection, e.target.value);

    document.querySelectorAll('.nav-link').forEach(el => {
        el.onclick = () => switchSection(el.dataset.section);
    });

    const params = new URLSearchParams(window.location.search);
    const initialSection = params.get('section') || 'live';
    switchSection(initialSection);
    loadRecentHistory();
    </script>
</body>
</html>