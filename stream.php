<?php
require_once __DIR__ . '/backend/api/config.php';
session_start();

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
                            break 4;
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
