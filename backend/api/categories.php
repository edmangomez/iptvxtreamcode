<?php
require_once __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['token'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$provider = $_SESSION['provider'];
$serverUrl = 'http://' . $provider['server_url'];
$apiUser = $provider['username'];
$apiPass = $provider['password'];

header('Content-Type: application/json');

$categories = [];
$types = ['live', 'vod', 'series'];
$actionMap = ['live' => 'get_live_categories', 'vod' => 'get_vod_categories', 'series' => 'get_series_categories'];

foreach ($types as $type) {
    $url = "$serverUrl/player_api.php?username=$apiUser&password=$apiPass&action={$actionMap[$type]}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $categories[$type] = $code === 200 ? json_decode($resp, true) : [];
}

echo json_encode($categories);
