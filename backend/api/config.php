<?php
$dataDir = __DIR__ . '/../data';

function getJSON($file) {
    global $dataDir;
    $path = "$dataDir/$file.json";
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

function putJSON($file, $data) {
    global $dataDir;
    file_put_contents("$dataDir/$file.json", json_encode($data, JSON_PRETTY_PRINT));
}

function generateToken($payload) {
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64_encode(json_encode($payload));
    $secret = 'xtream_secret_key_2024';
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$signature";
}

function validateToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $secret = 'xtream_secret_key_2024';
    $validSig = base64_encode(hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true));
    if (!hash_equals($validSig, $parts[2])) return null;
    $payload = json_decode(base64_decode($parts[1]), true);
    return $payload && ($payload['exp'] ?? 0) > time() ? $payload : null;
}

function loginUser($username, $password) {
    $users = getJSON('users');
    $subscriptions = getJSON('user_subscriptions');
    $providers = getJSON('providers');

    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            $sub = null;
            $provider = null;
            foreach ($subscriptions as $s) {
                if ($s['user_id'] == $user['id'] && ($s['active'] ?? 1)) {
                    $sub = $s;
                    foreach ($providers as $p) {
                        if ($p['id'] == $s['provider_id']) {
                            $provider = $p;
                            break;
                        }
                    }
                    break;
                }
            }
            if (!$sub || !$provider) {
                return ['error' => 'No tienes una suscripción activa'];
            }
            if ($sub['end_date'] < date('Y-m-d')) {
                return ['error' => 'Tu suscripción ha expirado'];
            }
            $token = generateToken([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'exp' => time() + 86400
            ]);
            $sessions = getJSON('active_sessions');
            $sessions[] = [
                'token' => $token,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + 86400)
            ];
            putJSON('active_sessions', $sessions);
            return [
                'token' => $token,
                'user' => ['id' => $user['id'], 'username' => $user['username']],
                'provider' => $provider,
                'subscription' => $sub
            ];
        }
    }
    return ['error' => 'Credenciales inválidas'];
}

function validateSession() {
    $token = $_SESSION['token'] ?? '';
    if (empty($token)) return false;
    $sessions = getJSON('active_sessions');
    foreach ($sessions as $s) {
        if ($s['token'] === $token && $s['expires_at'] > date('Y-m-d H:i:s')) {
            return $s;
        }
    }
    return false;
}

function adminLogin($username, $password) {
    $admins = getJSON('admin_users');
    foreach ($admins as $admin) {
        if ($admin['username'] === $username && password_verify($password, $admin['password'])) {
            $_SESSION['admin'] = $admin;
            return true;
        }
    }
    return false;
}
