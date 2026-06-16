<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = loginUser($input['username'] ?? '', $input['password'] ?? '');
    if (isset($result['error'])) {
        http_response_code(401);
        echo json_encode($result);
    } else {
        echo json_encode(['token' => $result['token']]);
    }
    exit;
}

if ($action === 'validate') {
    $token = $_GET['token'] ?? $_SESSION['token'] ?? '';
    $payload = validateToken($token);
    if ($payload) {
        echo json_encode(['valid' => true, 'user' => $payload]);
    } else {
        http_response_code(401);
        echo json_encode(['valid' => false]);
    }
    exit;
}

if ($action === 'logout') {
    $token = $_SESSION['token'] ?? '';
    if ($token) {
        $sessions = getJSON('active_sessions');
        $sessions = array_filter($sessions, fn($s) => $s['token'] !== $token);
        putJSON('active_sessions', array_values($sessions));
    }
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
