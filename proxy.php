<?php
require_once __DIR__ . '/config.php';
requireLogin();

$url = $_GET['url'] ?? '';
if ($url === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing url parameter']);
    exit;
}

$decoded = base64_decode($url, true);
if ($decoded === false || $decoded === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid base64 encoding']);
    exit;
}

$result = curlRequest($decoded);
header('Content-Type: application/json');

if (isset($result['error'])) {
    http_response_code($result['http_code'] >= 400 ? $result['http_code'] : 502);
    echo json_encode(['error' => $result['error']]);
    exit;
}

http_response_code($result['http_code']);
echo json_encode($result['data']);
