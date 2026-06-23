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

function boolFromMixed($value, $default = false) {
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value === 1;
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
        if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) return false;
    }
    return (bool)$default;
}

function getAppSettings() {
    $defaults = [
        'auth_mode' => 'local',
        'remote_auth_url' => '',
        'remote_shared_key' => '',
        'remote_verify_ssl' => false,
        'offline_grace_days' => 7,
        'remote_timeout_sec' => 8,
        'remote_api_token_ttl_sec' => 3600,
        'token_secret' => 'xtream_secret_key_2024',
        'token_ttl_sec' => 86400,
    ];
    $saved = getJSON('app_settings');
    if (!is_array($saved)) {
        return $defaults;
    }
    $settings = array_merge($defaults, $saved);
    $mode = strtolower((string)($settings['auth_mode'] ?? 'local'));
    if (!in_array($mode, ['local', 'remote', 'hybrid'], true)) {
        $mode = 'local';
    }
    $settings['auth_mode'] = $mode;
    $settings['remote_verify_ssl'] = boolFromMixed($settings['remote_verify_ssl'] ?? false, false);
    $settings['offline_grace_days'] = max(1, (int)$settings['offline_grace_days']);
    $settings['remote_timeout_sec'] = max(2, (int)$settings['remote_timeout_sec']);
    $settings['remote_api_token_ttl_sec'] = max(300, (int)$settings['remote_api_token_ttl_sec']);
    $settings['token_ttl_sec'] = max(600, (int)$settings['token_ttl_sec']);
    return $settings;
}

function saveAppSettings($partial) {
    $current = getAppSettings();
    $next = array_merge($current, is_array($partial) ? $partial : []);
    putJSON('app_settings', $next);
    return getAppSettings();
}

function generateToken($payload, $ttl = null) {
    $settings = getAppSettings();
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    if (!isset($payload['exp'])) {
        $payload['exp'] = time() + ($ttl ? (int)$ttl : (int)$settings['token_ttl_sec']);
    }
    $payload = base64_encode(json_encode($payload));
    $secret = (string)$settings['token_secret'];
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$signature";
}

function validateToken($token) {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 3) return null;
    $settings = getAppSettings();
    $secret = (string)$settings['token_secret'];
    $validSig = base64_encode(hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true));
    if (!hash_equals($validSig, $parts[2])) return null;
    $payload = json_decode(base64_decode($parts[1]), true);
    return $payload && ($payload['exp'] ?? 0) > time() ? $payload : null;
}

function findActiveSubscriptionForUser($userId) {
    $subscriptions = getJSON('user_subscriptions');
    $providers = getJSON('providers');
    $sub = null;
    $provider = null;

    foreach ($subscriptions as $s) {
        if (($s['user_id'] ?? null) == $userId && ($s['active'] ?? 1)) {
            $sub = $s;
            foreach ($providers as $p) {
                if (($p['id'] ?? null) == ($s['provider_id'] ?? null)) {
                    $provider = $p;
                    break;
                }
            }
            break;
        }
    }

    if (!$sub || !$provider) {
        return ['error' => 'No tienes una suscripcion activa'];
    }
    if (($sub['end_date'] ?? '') < date('Y-m-d')) {
        return ['error' => 'Tu suscripcion ha expirado'];
    }
    return ['subscription' => $sub, 'provider' => $provider];
}

function appendActiveSession($token, $user) {
    $settings = getAppSettings();
    $sessions = getJSON('active_sessions');
    if (!is_array($sessions)) {
        $sessions = [];
    }
    $expiresAt = date('Y-m-d H:i:s', time() + (int)$settings['token_ttl_sec']);
    $sessions[] = [
        'token' => $token,
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => $expiresAt,
    ];
    putJSON('active_sessions', $sessions);
}

function getRemoteApiSessions() {
    $sessions = getJSON('remote_api_sessions');
    return is_array($sessions) ? $sessions : [];
}

function saveRemoteApiSessions($sessions) {
    putJSON('remote_api_sessions', is_array($sessions) ? array_values($sessions) : []);
}

function pruneRemoteApiSessions($sessions) {
    $now = date('Y-m-d H:i:s');
    $pruned = [];
    foreach (is_array($sessions) ? $sessions : [] as $s) {
        if (($s['expires_at'] ?? '') > $now) {
            $pruned[] = $s;
        }
    }
    return $pruned;
}

function findRemoteApiSessionByToken($token) {
    $sessions = pruneRemoteApiSessions(getRemoteApiSessions());
    saveRemoteApiSessions($sessions);
    foreach ($sessions as $s) {
        if (($s['token'] ?? '') === $token) {
            return $s;
        }
    }
    return null;
}

function createRemoteApiSession($user) {
    $settings = getAppSettings();
    $ttl = (int)$settings['remote_api_token_ttl_sec'];
    $token = generateToken([
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? '',
        'source' => 'remote-admin-api',
        'jti' => bin2hex(random_bytes(8)),
    ], $ttl);

    $sessions = pruneRemoteApiSessions(getRemoteApiSessions());
    $sessions[] = [
        'token' => $token,
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? '',
        'issued_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
        'revoked' => false,
        'revoked_reason' => '',
    ];
    saveRemoteApiSessions($sessions);
    return $token;
}

function revokeRemoteApiSession($token, $reason = 'manual') {
    $changed = false;
    $sessions = getRemoteApiSessions();
    foreach ($sessions as &$s) {
        if (($s['token'] ?? '') === $token) {
            $s['revoked'] = true;
            $s['revoked_reason'] = $reason;
            $changed = true;
            break;
        }
    }
    unset($s);
    if ($changed) {
        saveRemoteApiSessions($sessions);
    }
    return $changed;
}

function isRemoteApiSessionValid($token) {
    $session = findRemoteApiSessionByToken($token);
    if (!$session) {
        return false;
    }
    if (!empty($session['revoked'])) {
        return false;
    }
    return true;
}

function loginUserLocal($username, $password) {
    $users = getJSON('users');
    foreach ($users as $user) {
        if (($user['username'] ?? '') === $username && password_verify($password, $user['password'] ?? '')) {
            $subData = findActiveSubscriptionForUser($user['id'] ?? null);
            if (isset($subData['error'])) {
                return ['error' => $subData['error']];
            }
            $token = generateToken([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'source' => 'local',
            ]);
            appendActiveSession($token, $user);
            return [
                'token' => $token,
                'user' => ['id' => $user['id'], 'username' => $user['username']],
                'provider' => $subData['provider'],
                'subscription' => $subData['subscription'],
                'auth_source' => 'local',
            ];
        }
    }
    return ['error' => 'Credenciales invalidas'];
}

function remoteAuthRequest($baseUrl, $action, $payload = [], $timeoutSec = 8) {
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '') {
        return ['error' => 'URL remota no configurada'];
    }

    $settings = getAppSettings();
    if (!empty($settings['remote_shared_key'])) {
        $payload['shared_key'] = $settings['remote_shared_key'];
    }

    $verifySsl = (bool)($settings['remote_verify_ssl'] ?? false);

    $url = $baseUrl . '/admin/api/auth.php?action=' . rawurlencode($action);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_CONNECTTIMEOUT => max(2, (int)$timeoutSec),
        CURLOPT_TIMEOUT => max(2, (int)$timeoutSec),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code === 0) {
        return ['error' => 'No se pudo conectar con autenticacion remota', 'network_error' => $curlErr ?: 'network'];
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['error' => 'Respuesta remota invalida'];
    }

    if ($code >= 400 || isset($json['error'])) {
        return ['error' => $json['error'] ?? 'Error remoto', 'remote_code' => $code];
    }

    return $json;
}

function getRemoteAuthCache() {
    $cache = getJSON('remote_auth_cache');
    return is_array($cache) ? $cache : [];
}

function saveRemoteAuthCache($cache) {
    putJSON('remote_auth_cache', is_array($cache) ? $cache : []);
}

function updateRemoteAuthCache($username, $password, $result, $offlineDays) {
    $cache = getRemoteAuthCache();
    $found = false;
    foreach ($cache as &$item) {
        if (($item['username'] ?? '') === $username) {
            $item = [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'user' => $result['user'] ?? [],
                'provider' => $result['provider'] ?? [],
                'subscription' => $result['subscription'] ?? [],
                'remote_token' => $result['token'] ?? '',
                'expires_at' => date('Y-m-d H:i:s', time() + (int)$offlineDays * 86400),
                'last_remote_at' => date('Y-m-d H:i:s'),
            ];
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $cache[] = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'user' => $result['user'] ?? [],
            'provider' => $result['provider'] ?? [],
            'subscription' => $result['subscription'] ?? [],
            'remote_token' => $result['token'] ?? '',
            'expires_at' => date('Y-m-d H:i:s', time() + (int)$offlineDays * 86400),
            'last_remote_at' => date('Y-m-d H:i:s'),
        ];
    }

    saveRemoteAuthCache($cache);
}

function tryOfflineAuthFromCache($username, $password) {
    $cache = getRemoteAuthCache();
    foreach ($cache as $item) {
        if (($item['username'] ?? '') !== $username) {
            continue;
        }
        if (($item['expires_at'] ?? '') < date('Y-m-d H:i:s')) {
            return ['error' => 'Cache offline vencido. Conectate para revalidar.'];
        }
        if (!password_verify($password, $item['password_hash'] ?? '')) {
            return ['error' => 'Credenciales invalidas'];
        }
        $user = $item['user'] ?? ['username' => $username];
        $token = generateToken([
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? $username,
            'source' => 'offline-cache',
        ]);
        appendActiveSession($token, $user);
        return [
            'token' => $token,
            'user' => $user,
            'provider' => $item['provider'] ?? [],
            'subscription' => $item['subscription'] ?? [],
            'auth_source' => 'offline-cache',
        ];
    }
    return ['error' => 'No hay cache offline para este usuario'];
}

function loginUserRemote($username, $password, $allowOfflineFallback = false) {
    $settings = getAppSettings();
    $remote = remoteAuthRequest(
        $settings['remote_auth_url'] ?? '',
        'user_login',
        ['username' => $username, 'password' => $password],
        (int)$settings['remote_timeout_sec']
    );

    if (isset($remote['error'])) {
        if ($allowOfflineFallback) {
            $offline = tryOfflineAuthFromCache($username, $password);
            if (!isset($offline['error'])) {
                return $offline;
            }
        }
        return $remote;
    }

    if (empty($remote['user']) || empty($remote['provider']) || empty($remote['subscription'])) {
        return ['error' => 'Respuesta remota incompleta'];
    }

    $user = $remote['user'];
    $token = generateToken([
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? $username,
        'source' => 'remote',
        'remote_issuer' => $settings['remote_auth_url'] ?? '',
    ]);
    appendActiveSession($token, $user);
    updateRemoteAuthCache($username, $password, $remote, (int)$settings['offline_grace_days']);

    return [
        'token' => $token,
        'user' => $remote['user'],
        'provider' => $remote['provider'],
        'subscription' => $remote['subscription'],
        'auth_source' => 'remote',
    ];
}

function loginUser($username, $password) {
    $settings = getAppSettings();
    $mode = $settings['auth_mode'] ?? 'local';

    if ($mode === 'remote') {
        return loginUserRemote($username, $password, false);
    }
    if ($mode === 'hybrid') {
        return loginUserRemote($username, $password, true);
    }
    return loginUserLocal($username, $password);
}

function validateSession() {
    $token = $_SESSION['token'] ?? '';
    if (empty($token)) return false;
    $sessions = getJSON('active_sessions');
    foreach ($sessions as $s) {
        if (($s['token'] ?? '') === $token && ($s['expires_at'] ?? '') > date('Y-m-d H:i:s')) {
            return $s;
        }
    }
    return false;
}

function adminLogin($username, $password) {
    $admins = getJSON('admin_users');
    foreach ($admins as $admin) {
        if (($admin['username'] ?? '') === $username && password_verify($password, $admin['password'] ?? '')) {
            $_SESSION['admin'] = $admin;
            return true;
        }
    }
    return false;
}
