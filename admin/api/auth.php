<?php
require_once __DIR__ . '/../../backend/api/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$settings = getAppSettings();
$requiredKey = trim((string)($settings['remote_shared_key'] ?? ''));
$clientKey = trim((string)($input['shared_key'] ?? ''));

if ($requiredKey !== '' && !hash_equals($requiredKey, $clientKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Shared key invalida']);
    exit;
}

function findUserByUsername($username) {
    $users = getJSON('users');
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username) {
            return $u;
        }
    }
    return null;
}

if ($action === 'user_login') {
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Usuario y contrasena son requeridos']);
        exit;
    }

    $user = findUserByUsername($username);
    if (!$user || !password_verify($password, $user['password'] ?? '')) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales invalidas']);
        exit;
    }

    $subData = findActiveSubscriptionForUser($user['id'] ?? null);
    if (isset($subData['error'])) {
        http_response_code(403);
        echo json_encode(['error' => $subData['error']]);
        exit;
    }

    $token = createRemoteApiSession($user);

    echo json_encode([
        'token' => $token,
        'user' => ['id' => $user['id'] ?? null, 'username' => $user['username'] ?? ''],
        'provider' => $subData['provider'],
        'subscription' => $subData['subscription'],
    ]);
    exit;
}

if ($action === 'validate') {
    $token = (string)($input['token'] ?? '');
    if ($token === '' || !isRemoteApiSessionValid($token)) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Sesion revocada o expirada']);
        exit;
    }

    $payload = validateToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['valid' => false]);
        exit;
    }

    $userId = $payload['user_id'] ?? null;
    $users = getJSON('users');
    $user = null;
    foreach ($users as $u) {
        if (($u['id'] ?? null) == $userId) {
            $user = $u;
            break;
        }
    }

    if (!$user) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    $subData = findActiveSubscriptionForUser($user['id'] ?? null);
    if (isset($subData['error'])) {
        http_response_code(403);
        echo json_encode(['valid' => false, 'error' => $subData['error']]);
        exit;
    }

    echo json_encode([
        'valid' => true,
        'user' => ['id' => $user['id'] ?? null, 'username' => $user['username'] ?? ''],
        'provider' => $subData['provider'],
        'subscription' => $subData['subscription'],
    ]);
    exit;
}

if ($action === 'refresh') {
    $token = (string)($input['token'] ?? '');
    if ($token === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Token requerido']);
        exit;
    }
    if (!isRemoteApiSessionValid($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Sesion revocada o expirada']);
        exit;
    }

    $payload = validateToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalido']);
        exit;
    }

    $user = findUserByUsername((string)($payload['username'] ?? ''));
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }

    revokeRemoteApiSession($token, 'refreshed');
    $newToken = createRemoteApiSession($user);

    echo json_encode([
        'token' => $newToken,
        'user' => ['id' => $user['id'] ?? null, 'username' => $user['username'] ?? ''],
    ]);
    exit;
}

if ($action === 'revoke') {
    $token = (string)($input['token'] ?? '');
    if ($token === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Token requerido']);
        exit;
    }

    $ok = revokeRemoteApiSession($token, 'manual-revoke');
    if (!$ok) {
        http_response_code(404);
        echo json_encode(['error' => 'Sesion no encontrada']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Accion no valida']);
