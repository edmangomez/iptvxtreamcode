<?php
require_once __DIR__ . '/backend/api/config.php';
session_start();

// ── CORS headers (allow cross-origin requests from HLS.js) ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Range, Content-Type, Content-Length, Accept-Encoding');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function getNullRedirection() {
    return PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
}

function isCommandAvailable($command) {
    $cmd = $command . ' -version ' . getNullRedirection();
    $out = @shell_exec($cmd);
    return is_string($out) && trim($out) !== '';
}

function resolveFfmpegTools() {
    $root = __DIR__;
    $candidates = [
        [
            'ffmpeg' => '/tmp/ffmpeg-7.0.2-amd64-static/ffmpeg',
            'ffprobe' => '/tmp/ffmpeg-7.0.2-amd64-static/ffprobe',
        ],
        [
            'ffmpeg' => $root . '/desktop/bin/ffmpeg/win/ffmpeg.exe',
            'ffprobe' => $root . '/desktop/bin/ffmpeg/win/ffprobe.exe',
        ],
        [
            'ffmpeg' => $root . '/desktop/bin/ffmpeg/linux/ffmpeg',
            'ffprobe' => $root . '/desktop/bin/ffmpeg/linux/ffprobe',
        ],
        [
            'ffmpeg' => $root . '/desktop/bin/ffmpeg/mac/ffmpeg',
            'ffprobe' => $root . '/desktop/bin/ffmpeg/mac/ffprobe',
        ],
    ];

    foreach ($candidates as $pair) {
        if (is_file($pair['ffmpeg']) && is_file($pair['ffprobe'])) {
            return $pair;
        }
    }

    if (isCommandAvailable('ffmpeg') && isCommandAvailable('ffprobe')) {
        return ['ffmpeg' => 'ffmpeg', 'ffprobe' => 'ffprobe'];
    }

    return null;
}

function buildAbsoluteUrl($url, $baseUrl) {
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $base = parse_url($baseUrl);
    $scheme = $base['scheme'] ?? 'http';
    $host = $base['host'] ?? '';
    $port = isset($base['port']) ? ':' . $base['port'] : '';
    $path = $base['path'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    if ($dir === '.') {
        $dir = '';
    }

    if (strpos($url, '//') === 0) {
        return $scheme . ':' . $url;
    }
    if (strpos($url, '/') === 0) {
        return $scheme . '://' . $host . $port . $url;
    }

    return $scheme . '://' . $host . $port . ($dir ? $dir . '/' : '/') . $url;
}

function proxifyUrl($absoluteUrl) {
    return 'stream.php?url=' . rawurlencode($absoluteUrl);
}

function rewriteM3u8Body($body, $baseUrl) {
    $lines = preg_split('/\r\n|\n|\r/', (string)$body);
    $out = [];

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $out[] = $line;
            continue;
        }

        if ($trim[0] === '#') {
            if (strpos($line, 'URI="') !== false) {
                $line = preg_replace_callback('/URI="([^"]+)"/', function ($m) use ($baseUrl) {
                    $abs = buildAbsoluteUrl($m[1], $baseUrl);
                    return 'URI="' . proxifyUrl($abs) . '"';
                }, $line);
            }
            $out[] = $line;
            continue;
        }

        $abs = buildAbsoluteUrl($trim, $baseUrl);
        $out[] = proxifyUrl($abs);
    }

    return implode("\n", $out);
}

// ═══════════════════════════════════════════════════════════════════════════
// ── HLS Mode A: Start a new HLS transcoding session ────────────────────────
// Usage: stream.php?hls=start&url=<encoded_url>[&audio=<idx>]
//
// Probes the remote file with ffprobe, launches ffmpeg in the background
// (curl | ffmpeg → disk segments), and immediately returns JSON with the
// session ID, playlist URL, audio tracks, and subtitle tracks.
// ═══════════════════════════════════════════════════════════════════════════
if (isset($_GET['hls']) && $_GET['hls'] === 'start') {
    header('Content-Type: application/json');

    $url = $_GET['url'] ?? '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid URL']);
        exit;
    }

    $audioIdx = max(0, (int)($_GET['audio'] ?? 0));

    if (PHP_OS_FAMILY === 'Windows') {
        http_response_code(501);
        echo json_encode(['error' => 'hls-start no soportado en Windows, use transcode']);
        exit;
    }

    $tools = resolveFfmpegTools();
    if (!$tools) {
        http_response_code(500);
        echo json_encode(['error' => 'ffmpeg not available']);
        exit;
    }

    $ffmpeg = $tools['ffmpeg'];
    $ffprobe = $tools['ffprobe'];

    // ── Generate unique session & working directory ──
    $session = bin2hex(random_bytes(8));
    $dir     = sys_get_temp_dir() . '/hls_' . $session;
    mkdir($dir, 0777, true);

    // ── Probe the stream via a fast 128 KB Range request ──
    // ffmpeg segfaults with direct HTTP URLs on this system, so we pipe
    // curl output into ffprobe stdin.
    $probeJson = shell_exec(
        'curl -s -L --max-time 10 -H "Range: bytes=0-131071" ' . escapeshellarg($url) .
        ' | ' . $ffprobe .
        ' -v error -probesize 131072 -analyzeduration 0' .
        ' -show_streams -print_format json -i pipe:0 2>/dev/null'
    );
    $streams = json_decode($probeJson, true)['streams'] ?? [];

    // ── Parse streams into typed track lists ──
    $videoCodec  = '';
    $audioTracks = [];
    $subTracks   = [];

    foreach ($streams as $s) {
        $codecType = $s['codec_type'] ?? '';

        if ($codecType === 'video' && $videoCodec === '') {
            $videoCodec = $s['codec_name'] ?? '';

        } elseif ($codecType === 'audio') {
            $n    = count($audioTracks);
            $lang = $s['tags']['language'] ?? ($s['tags']['title'] ?? ('Track ' . ($n + 1)));
            $audioTracks[] = [
                'index' => $n,
                'lang'  => strtoupper($lang),
                'codec' => $s['codec_name'] ?? '',
            ];

        } elseif ($codecType === 'subtitle') {
            $n    = count($subTracks);
            $lang = $s['tags']['language'] ?? ($s['tags']['title'] ?? ('Sub ' . ($n + 1)));
            $subTracks[] = [
                'index' => $n,
                'lang'  => strtoupper($lang),
            ];
        }
    }

    // Clamp audioIdx to a valid range
    $audioIdx = min($audioIdx, max(0, count($audioTracks) - 1));

    // ── Choose video encoding strategy ──
    // H.264 → stream-copy (fast, no quality loss)
    // HEVC / anything else → re-encode to H.264 for browser compatibility
    if (preg_match('/\bh264\b|\bavc1?\b/i', $videoCodec)) {
        $videoOpts = '-c:v copy';
    } else {
        $videoOpts = '-c:v libx264 -preset ultrafast -crf 22';
    }

    // ── Build the main HLS ffmpeg command ────────────────────────────────
    // Maps only video + selected audio track.  Subtitle extraction runs as
    // separate parallel processes (see below) because ffmpeg's WebVTT muxer
    // buffers all cues and only flushes at EOF — including it here would keep
    // the subtitle file at 0 bytes for the entire HLS session duration.
    // ─────────────────────────────────────────────────────────────────────
    $ffmpegCmd =
        'curl -s -L --max-time 7200 ' . escapeshellarg($url) .
        ' | ' . $ffmpeg .
        ' -loglevel error' .
        ' -probesize 1000000 -analyzeduration 0 -fflags +genpts+discardcorrupt' .
        ' -i pipe:0' .
        ' -map 0:v:0 -map 0:a:' . $audioIdx .
        ' ' . $videoOpts .
        ' -c:a aac -b:a 192k' .
        ' -hls_time 6' .
        ' -hls_list_size 0' .
        ' -hls_segment_filename ' . escapeshellarg($dir . '/seg%03d.ts') .
        ' ' . escapeshellarg($dir . '/playlist.m3u8') .
        ' 2>' . escapeshellarg($dir . '/ffmpeg.log');

    // ── Launch main HLS ffmpeg in the background (nohup, fully detached) ──
    shell_exec('nohup bash -c ' . escapeshellarg($ffmpegCmd) . ' > /dev/null 2>&1 &');

    // ── Launch one dedicated subtitle-extraction process per subtitle track ──
    // Each spawns its own curl | ffmpeg with only the relevant subtitle stream
    // mapped to a WebVTT output.  The VTT file will appear once the entire
    // source file has been downloaded (ffmpeg's WebVTT muxer flushes at EOF).
    foreach ($subTracks as $sub) {
        $subCmd =
            'curl -s -L --max-time 7200 ' . escapeshellarg($url) .
            ' | ' . $ffmpeg .
            ' -loglevel error' .
            ' -probesize 1000000 -analyzeduration 0 -fflags +genpts+discardcorrupt' .
            ' -i pipe:0' .
            ' -map 0:s:' . $sub['index'] .
            ' -c:s webvtt ' .
            escapeshellarg($dir . '/sub' . $sub['index'] . '.vtt') .
            ' 2>>' . escapeshellarg($dir . '/ffmpeg.log');
        shell_exec('nohup bash -c ' . escapeshellarg($subCmd) . ' > /dev/null 2>&1 &');
    }

    // ── Persist session metadata to disk ──
    file_put_contents($dir . '/meta.json', json_encode([
        'session'     => $session,
        'url'         => $url,
        'audioIdx'    => $audioIdx,
        'audioTracks' => $audioTracks,
        'subTracks'   => $subTracks,
        'created'     => time(),
    ]));

    // ── Garbage-collect stale sessions (older than 7200 s) ──
    $tmpBase = sys_get_temp_dir();
    foreach (glob($tmpBase . '/hls_*', GLOB_ONLYDIR) ?: [] as $oldDir) {
        if ($oldDir === $dir) continue;
        $metaPath = $oldDir . '/meta.json';
        $mtime    = file_exists($metaPath) ? filemtime($metaPath) : filectime($oldDir);
        if ((time() - $mtime) > 7200) {
            foreach (glob($oldDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($oldDir);
        }
    }

    // ── Return session descriptor ──
    echo json_encode([
        'session'      => $session,
        'playlistUrl'  => 'stream.php?hls=serve&session=' . $session . '&file=playlist.m3u8',
        'audioTracks'  => $audioTracks,
        'subTracks'    => $subTracks,
        'currentAudio' => $audioIdx,
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// ── HLS Mode B: Serve a file from an existing session ──────────────────────
// Usage: stream.php?hls=serve&session=<hex>&file=<filename>
//
// Polls until the file exists (up to 30 s for .m3u8, 15 s for .ts/.vtt),
// then streams it with the correct Content-Type.  Segment serving waits for
// the write to finish before sending (prevents truncated TS reads).
// ═══════════════════════════════════════════════════════════════════════════
if (isset($_GET['hls']) && $_GET['hls'] === 'serve') {
    // Sanitise inputs — session is strictly hex, file is basename only
    $session = preg_replace('/[^a-f0-9]/', '', $_GET['session'] ?? '');
    $file    = basename($_GET['file'] ?? '');

    if ($session === '' || $file === '') {
        http_response_code(400);
        exit;
    }

    $path = sys_get_temp_dir() . '/hls_' . $session . '/' . $file;
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    // ── VTT subtitles: smart wait ──────────────────────────────────────────
    // ffmpeg's WebVTT muxer buffers all cues and flushes only at EOF.
    // The subtitle file is created at process startup (0 bytes) and grows
    // only after the entire source download completes (minutes later).
    //
    // Strategy:
    //   • If file already has content → serve immediately.
    //   • If file doesn't exist yet   → wait up to 3 s (ffmpeg hasn't started).
    //   • If file exists but is 0 B   → 404 now; client retries on user action.
    // ──────────────────────────────────────────────────────────────────────
    if ($ext === 'vtt') {
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            // ffmpeg process may not have spawned yet — give it 3 s
            $vttWaited = 0.0;
            while ($vttWaited < 3.0) {
                usleep(300000);
                $vttWaited += 0.3;
                clearstatcache(true, $path);
                if (file_exists($path) && filesize($path) > 0) break;
            }
        }
        clearstatcache(true, $path);
        if (!file_exists($path) || filesize($path) === 0) {
            // Subtitle extraction in progress (buffered until EOF) — not ready yet
            http_response_code(404);
            exit;
        }
        // VTT has content — fall through to serve it
    } else {
        // ── m3u8 / ts: poll until file appears ──
        // Playlist needs more time (ffmpeg writes it after the first segment)
        $maxWait = ($ext === 'm3u8') ? 30.0 : 15.0;
        $waited  = 0.0;

        while ($waited < $maxWait) {
            clearstatcache(true, $path);
            if (file_exists($path) && filesize($path) > 0) {
                break;
            }
            usleep(300000); // 300 ms
            $waited += 0.3;
        }

        clearstatcache(true, $path);
        if (!file_exists($path) || filesize($path) === 0) {
            http_response_code(404);
            exit;
        }
    }

    // ── TS segments: wait for the write to finish before serving ──
    // ffmpeg writes segments sequentially; serving a partial segment causes
    // HLS.js decode errors. We compare file sizes 150 ms apart and wait an
    // extra 300 ms if the file is still growing.
    if ($ext === 'ts') {
        $size1 = filesize($path);
        usleep(150000); // 150 ms
        clearstatcache(true, $path);
        $size2 = filesize($path);
        if ($size2 > $size1) {
            usleep(300000); // 300 ms more
            clearstatcache(true, $path);
        }
    }

    // ── Headers ──
    $contentTypes = [
        'm3u8' => 'application/vnd.apple.mpegurl',
        'ts'   => 'video/mp2t',
        'vtt'  => 'text/vtt',
    ];
    $ct = $contentTypes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $ct);
    header('Cache-Control: no-cache');
    header('Access-Control-Allow-Origin: *');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// ── Transcode mode ──────────────────────────────────────────────────────────
// Usage: stream.php?transcode=1&url=<full_encoded_url>
// Reads the source URL with ffmpeg, copies video, transcodes audio EAC3→AAC,
// and streams the result as a fragmented MP4 that any browser can play.
// ═══════════════════════════════════════════════════════════════════════════
$transcodeMode = isset($_GET['transcode']) && $_GET['transcode'] === '1';
$videoUrl = $_GET['url'] ?? '';

if ($transcodeMode && !empty($videoUrl)) {
    if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid URL']);
        exit;
    }

    $tools = resolveFfmpegTools();
    if (!$tools) {
        http_response_code(500);
        echo json_encode(['error' => 'ffmpeg not available']);
        exit;
    }

    $ffmpeg = $tools['ffmpeg'];
    $ffprobe = $tools['ffprobe'];
    $nullRedir = getNullRedirection();

    // ── Step 1: detect video codec from first 128 KB (fast Range request) ──
    // ffprobe with -probesize 131072 -analyzeduration 0 reads just enough to
    // identify codec names without downloading the whole file.
    $probeCmd = escapeshellarg($ffprobe)
              . ' -v error -probesize 131072 -analyzeduration 0'
              . ' -show_entries stream=codec_name,codec_type'
              . ' -of csv=p=0 -i ' . escapeshellarg($videoUrl)
              . ' ' . $nullRedir;
    $probeOut = shell_exec($probeCmd) ?? '';

    // ── Step 2: choose video encoding strategy based on detected codec ──
    // H.264  → copy with avc1 tag (no re-encode, instant start)
    // HEVC   → re-encode to H.264 (Firefox/older Chrome don't support HEVC)
    // others → re-encode to H.264 (max compatibility)
    if (preg_match('/\bhevc\b|\bh265\b|\bhvc1\b|\bhev1\b/i', $probeOut)) {
        $videoOpts = '-c:v libx264 -preset ultrafast -crf 22 -tag:v avc1';
    } elseif (preg_match('/\bvp9\b|\bav1\b|\bmpeg4\b|\bmpeg2video\b|\bvc1\b|\bwmv\b/i', $probeOut)) {
        $videoOpts = '-c:v libx264 -preset ultrafast -crf 22 -tag:v avc1';
    } else {
        // h264 or unknown → copy with correct MP4 tag
        $videoOpts = '-c:v copy -tag:v avc1';
    }

    // ── Step 3: stream curl | ffmpeg → fragmented MP4 ──
    // ffmpeg segfaults when given HTTP URLs directly on this system,
    // so we pipe curl's output into ffmpeg stdin.
    // -probesize 1M -analyzeduration 0: don't buffer 5MB before starting
    //   (default probesize). Reduces time-to-first-byte from 15s to <1s.
    // -fflags +genpts+discardcorrupt: generate timestamps + skip corrupt packets.
    // Audio: always transcode to AAC regardless of input (AC3, EAC3, DTS, MP3…)
    $ffmpegCmd = escapeshellarg($ffmpeg)
        . ' -loglevel error'
        . ' -probesize 1000000 -analyzeduration 0 -fflags +genpts+discardcorrupt'
        . ' -i ' . escapeshellarg($videoUrl)
        . ' -map 0:v:0 -map 0:a:0'          // first video + first audio only
        . ' ' . $videoOpts                   // video: copy H.264 or re-encode HEVC/other
        . ' -c:a aac -b:a 192k'             // audio: always AAC (handles every input codec)
        . ' -f mp4 -movflags frag_keyframe+empty_moov+default_base_moof'
        . ' pipe:1 ' . $nullRedir;

    $cmd = $ffmpegCmd;

    header('Content-Type: video/mp4');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');

    @ini_set('output_buffering', 0);
    @ini_set('zlib.output_compression', 0);
    if (ob_get_level()) ob_end_clean();

    $proc = popen($cmd, 'r');
    if (!is_resource($proc)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to start transcoding pipeline']);
        exit;
    }

    while (!feof($proc)) {
        $chunk = fread($proc, 65536);
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        flush();
    }

    pclose($proc);
    exit;
}

// ── Generic URL proxy mode ──
// Usage: stream.php?url=<full_encoded_url>
$directUrl = $_GET['url'] ?? '';
if (!empty($directUrl)) {
    // Basic URL validation
    if (!filter_var($directUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid URL']);
        exit;
    }

    $directExt = strtolower(pathinfo(parse_url($directUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

    $ch = curl_init();

    // Forward client headers so the provider gets a realistic browser-like request
    $reqHeaders = [];
    if (isset($_SERVER['HTTP_RANGE'])) {
        $reqHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $reqHeaders[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];
    }
    if (isset($_SERVER['HTTP_ACCEPT'])) {
        $reqHeaders[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
    }
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $reqHeaders[] = 'Origin: ' . $_SERVER['HTTP_ORIGIN'];
    }
    if (isset($_SERVER['HTTP_REFERER'])) {
        $reqHeaders[] = 'Referer: ' . $_SERVER['HTTP_REFERER'];
    }

    // Detect HEAD requests (used by watch.php to probe HLS existence)
    $isHead = ($_SERVER['REQUEST_METHOD'] === 'HEAD');

    // TS passthrough mode (stream chunks, do not buffer full response)
    if ($directExt === 'ts' && !$isHead) {
        @ini_set('output_buffering', 0);
        @ini_set('zlib.output_compression', 0);
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        ignore_user_abort(true);
        set_time_limit(0);

        header('Content-Type: video/mp2t');
        header('Cache-Control: no-cache');
        header('Access-Control-Allow-Origin: *');

        $cmdParts = [
            'curl',
            '-L',
            '--connect-timeout',
            '10',
            '--max-time',
            '0',
            '-s',
        ];

        if (isset($_SERVER['HTTP_RANGE']) && trim($_SERVER['HTTP_RANGE']) !== '') {
            $cmdParts[] = '-H';
            $cmdParts[] = escapeshellarg('Range: ' . $_SERVER['HTTP_RANGE']);
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && trim($_SERVER['HTTP_USER_AGENT']) !== '') {
            $cmdParts[] = '-H';
            $cmdParts[] = escapeshellarg('User-Agent: ' . $_SERVER['HTTP_USER_AGENT']);
        }

        $cmdParts[] = escapeshellarg($directUrl);
        $nullRedir = (PHP_OS_FAMILY === 'Windows') ? '2>NUL' : '2>/dev/null';
        $cmd = implode(' ', $cmdParts) . ' ' . $nullRedir;

        $proc = @popen($cmd, 'rb');
        if (!is_resource($proc)) {
            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Proxy stream error: cannot start curl process']);
            exit;
        }

        $total = 0;
        while (!feof($proc)) {
            $chunk = fread($proc, 65536);
            if ($chunk === false) {
                break;
            }
            if ($chunk !== '') {
                $total += strlen($chunk);
                echo $chunk;
                flush();
            }
        }
        pclose($proc);

        if ($total === 0) {
            http_response_code(404);
        }
        exit;
    }

    curl_setopt($ch, CURLOPT_URL, $directUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (!empty($reqHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
    }

    if ($isHead) {
        // HEAD request: only fetch headers, no body
        // Used by watch.php's HLS probe to check if .m3u8 exists
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_NOBODY, true);
    } else {
        // Large buffer for video streaming (only for GET requests)
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 65536);
    }

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $info['http_code'] === 0) {
        http_response_code(502);
        echo json_encode(['error' => 'Proxy error: ' . $curlError]);
        exit;
    }

    // Split headers from body
    $headerSize = $info['header_size'];
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Set status code from upstream
    http_response_code($info['http_code']);

    // Forward only safe headers
    $allowedHeaders = [
        'Content-Type', 'Content-Length', 'Content-Range', 'Accept-Ranges',
        'Cache-Control', 'Expires', 'Last-Modified', 'ETag',
    ];
    foreach (explode("\r\n", $headerStr) as $h) {
        $colonPos = strpos($h, ':');
        if ($colonPos !== false) {
            $name = trim(substr($h, 0, $colonPos));
            if (in_array($name, $allowedHeaders)) {
                header(trim($h), false);
            }
        }
    }

    // For HEAD requests (HLS probe): forward status + Content-Type, no body
    if ($isHead) {
        // Forward the upstream status code — probe checks response.ok + Content-Type
        http_response_code($info['http_code']);
        // Forward Content-Type so the probe can distinguish m3u8 from HTML error page
        if (!empty($info['content_type'])) {
            header('Content-Type: ' . $info['content_type']);
        }
        exit;
    }

    $ext = $directExt;
    $contentType = strtolower((string)($info['content_type'] ?? ''));
    $isM3u8 = ($ext === 'm3u8' || $ext === 'm3u' || strpos($contentType, 'mpegurl') !== false || strpos($contentType, 'vnd.apple.mpegurl') !== false);

    if ($isM3u8) {
        $rewritten = rewriteM3u8Body($body, $directUrl);
        header('Content-Type: application/vnd.apple.mpegurl');
        echo $rewritten;
        exit;
    }

    // Ensure content-type for TS segments (HLS.js needs it)
    if (empty($info['content_type'])) {
        if ($ext === 'ts') {
            header('Content-Type: video/mp2t');
        } elseif ($ext === 'm3u8' || $ext === 'm3u') {
            header('Content-Type: application/vnd.apple.mpegurl');
        } elseif ($ext === 'aac') {
            header('Content-Type: audio/aac');
        } elseif ($ext === 'ac3' || $ext === 'eac3') {
            header('Content-Type: audio/mp4');
        } elseif ($ext === 'vtt') {
            header('Content-Type: text/vtt');
        }
    }

    echo $body;
    exit;
}

// ── Legacy type/stream mode (used by existing redirects) ──
$type = $_GET['type'] ?? '';
$streamPath = $_GET['stream'] ?? '';

if (empty($type) || empty($streamPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros requeridos']);
    exit;
}

$provider = $_SESSION['provider'] ?? null;
$serverUrl = $provider ? 'http://' . $provider['server_url'] : '';
$apiUser = $provider['username'] ?? '';
$apiPass = $provider['password'] ?? '';

if (!$provider) {
    $sessions = getJSON('active_sessions');
    $token = $_SESSION['token'] ?? '';
    foreach ($sessions as $s) {
        if ($s['token'] === $token && $s['expires_at'] > date('Y-m-d H:i:s')) {
            $users = getJSON('users');
            $subscriptions = getJSON('user_subscriptions');
            $providers = getJSON('providers');
            foreach ($subscriptions as $sub) {
                if ($sub['user_id'] == $s['user_id'] && ($sub['active'] ?? 1)) {
                    foreach ($providers as $p) {
                        if ($p['id'] == $sub['provider_id']) {
                            $serverUrl = 'http://' . $p['server_url'];
                            $apiUser = $p['username'];
                            $apiPass = $p['password'];
                            break 3;
                        }
                    }
                }
            }
        }
    }
}

if (empty($serverUrl) || empty($apiUser) || empty($apiPass)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo determinar el proveedor']);
    exit;
}

if ($type === 'live') {
    $url = "$serverUrl/live/$apiUser/$apiPass/$streamPath";
} elseif ($type === 'vod') {
    $url = "$serverUrl/movie/$apiUser/$apiPass/$streamPath";
} else {
    $url = "$serverUrl/series/$apiUser/$apiPass/$streamPath";
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false
]);
$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($info['http_code'] === 301 || $info['http_code'] === 302) {
    preg_match('/[Ll]ocation:\s*(.+?)[\r\n]/', $response, $m);
    $redirectUrl = trim($m[1] ?? '');
    if (!empty($redirectUrl)) {
        header("Location: $redirectUrl");
        exit;
    }
}

$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
if ($ext === 'm3u8') {
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15
    ]);
    $m3u8 = curl_exec($ch2);
    $info2 = curl_getinfo($ch2);
    curl_close($ch2);

    if ($info2['http_code'] === 200 && !empty($m3u8)) {
        header('Content-Type: application/vnd.apple.mpegurl');
        echo $m3u8;
        exit;
    }
}

header("Location: $url");
