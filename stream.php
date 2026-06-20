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

// ── Transcode mode ──
// Usage: stream.php?transcode=1&url=<full_encoded_url>
// Reads the source URL with ffmpeg, copies video, transcodes audio EAC3→AAC,
// and streams the result as a fragmented MP4 that any browser can play.
$transcodeMode = isset($_GET['transcode']) && $_GET['transcode'] === '1';
$videoUrl = $_GET['url'] ?? '';

if ($transcodeMode && !empty($videoUrl)) {
    if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid URL']);
        exit;
    }

    $ffmpeg  = '/tmp/ffmpeg-7.0.2-amd64-static/ffmpeg';
    $ffprobe = '/tmp/ffmpeg-7.0.2-amd64-static/ffprobe';

    if (!is_executable($ffmpeg) || !is_executable($ffprobe)) {
        http_response_code(500);
        echo json_encode(['error' => 'ffmpeg not available']);
        exit;
    }

    // ── Step 1: detect video codec from first 128 KB (fast Range request) ──
    // ffprobe with -probesize 131072 -analyzeduration 0 reads just enough to
    // identify codec names without downloading the whole file.
    $probeCmd = 'curl -s -L --max-time 10 -H "Range: bytes=0-131071" '
              . escapeshellarg($videoUrl)
              . ' | ' . $ffprobe
              . ' -v error -probesize 131072 -analyzeduration 0'
              . ' -show_entries stream=codec_name,codec_type'
              . ' -of csv=p=0 -i pipe:0 2>/dev/null';
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
    $curlCmd   = 'curl -s -L --max-time 7200 ' . escapeshellarg($videoUrl);
    $ffmpegCmd = $ffmpeg
        . ' -loglevel error'
        . ' -probesize 1000000 -analyzeduration 0 -fflags +genpts+discardcorrupt'
        . ' -i pipe:0'
        . ' -map 0:v:0 -map 0:a:0'          // first video + first audio only
        . ' ' . $videoOpts                   // video: copy H.264 or re-encode HEVC/other
        . ' -c:a aac -b:a 192k'             // audio: always AAC (handles every input codec)
        . ' -f mp4 -movflags frag_keyframe+empty_moov+default_base_moof'
        . ' pipe:1 2>/dev/null';

    $cmd = $curlCmd . ' | ' . $ffmpegCmd;

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

    // Ensure content-type for TS segments (HLS.js needs it)
    if (empty($info['content_type'])) {
        $ext = strtolower(pathinfo(parse_url($directUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
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
