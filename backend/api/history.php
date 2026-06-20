<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['token'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$userId = $_SESSION['user']['id'];
$action = $_GET['action'] ?? '';

if ($action === 'record') {
    $input = json_decode(file_get_contents('php://input'), true);
    $history = getJSON('watch_history');
    $history[] = [
        'user_id' => $userId,
        'type' => $input['type'] ?? 'vod',
        'stream_id' => $input['stream_id'] ?? '',
        'series_id' => $input['series_id'] ?? null,
        'name' => $input['name'] ?? '',
        'poster' => $input['poster'] ?? '',
        'season_num' => $input['season_num'] ?? null,
        'episode_num' => $input['episode_num'] ?? null,
        'watched_at' => date('Y-m-d H:i:s')
    ];
    putJSON('watch_history', $history);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'get') {
    $limit = intval($_GET['limit'] ?? 20);
    $history = getJSON('watch_history');
    $userHistory = array_values(array_filter($history, fn($h) => ($h['user_id'] ?? 0) == $userId));
    usort($userHistory, fn($a, $b) => strcmp($b['watched_at'] ?? '', $a['watched_at'] ?? ''));
    echo json_encode(array_slice($userHistory, 0, $limit));
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
