<?php
require_once __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['token'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$provider = $_SESSION['provider'];
$serverUrl = 'http://' . $provider['server_url'];
$apiUser = $provider['username'];
$apiPass = $provider['password'];

$action = $_GET['action'] ?? '';
$categoryId = $_GET['category_id'] ?? '';
$seriesId = $_GET['series_id'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción requerida']);
    exit;
}

$url = "$serverUrl/player_api.php?username=$apiUser&password=$apiPass&action=$action";
if ($categoryId) $url .= "&category_id=$categoryId";
if ($seriesId) $url .= "&series_id=$seriesId";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 60
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo $httpCode === 200 ? $response : json_encode(['error' => 'Error del proveedor']);
