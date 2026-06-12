<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('APP_NAME', 'xtream-player');
define('APP_VERSION', '1.0.0');
define('AUTH_CHECK_INTERVAL', 300); // 5 minutos entre verificaciones

function isLoggedIn(): bool {
    return !empty($_SESSION['server_url'])
        && !empty($_SESSION['username'])
        && !empty($_SESSION['password']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    checkSession();
}

function checkSession(): void {
    if (isTestMode()) {
        $_SESSION['_auth_check'] = time();
        return;
    }

    $last = $_SESSION['_auth_check'] ?? 0;
    if (time() - $last < AUTH_CHECK_INTERVAL) {
        return;
    }

    $result = apiRequest();
    $auth = $result['data']['user_info']['auth'] ?? null;

    if ($auth === null || $auth == 0) {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    $_SESSION['_auth_check'] = time();
}

function sessionGet(string $key, mixed $default = null): mixed {
    return $_SESSION[$key] ?? $default;
}

function getServerUrl(): string {
    return rtrim(sessionGet('server_url', ''), '/');
}

function getUsername(): string {
    return sessionGet('username', '');
}

function getPassword(): string {
    return sessionGet('password', '');
}

function buildApiUrl(string $action = '', array $extra = []): string {
    $url = getServerUrl() . '/player_api.php'
         . '?username=' . urlencode(getUsername())
         . '&password=' . urlencode(getPassword());
    if ($action !== '') {
        $url .= '&action=' . urlencode($action);
    }
    foreach ($extra as $key => $value) {
        $url .= '&' . urlencode($key) . '=' . urlencode((string)$value);
    }
    return $url;
}

function buildStreamUrl(string $type, int $id, string $ext = 'ts'): string {
    if (isTestMode()) {
        $sample = 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';
        return $sample;
    }

    $base = getServerUrl();
    $user = getUsername();
    $pass = getPassword();

    return match ($type) {
        'live'   => "$base/live/$user/$pass/$id.$ext",
        'vod'    => "$base/movie/$user/$pass/$id.$ext",
        'series' => "$base/series/$user/$pass/$id.$ext",
        default  => '',
    };
}

function isTestMode(): bool {
    return !empty($_SESSION['_test_mode']);
}

function apiRequest(string $action = '', array $extra = []): array {
    if (isTestMode()) {
        require_once __DIR__ . '/api/mock_data.php';
        $data = getMockData($action, $extra);
        return ['data' => $data, 'http_code' => 200];
    }
    $url = buildApiUrl($action, $extra);
    return curlRequest($url);
}

function curlRequest(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => APP_NAME . '/' . APP_VERSION,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    $errno    = curl_errno($ch);
    curl_close($ch);

    if ($error !== '') {
        $msg = match ($errno) {
            CURLE_OPERATION_TIMEOUTED => 'Tiempo de espera agotado. El servidor no responde.',
            CURLE_COULDNT_RESOLVE_HOST => 'No se pudo resolver el servidor. Verifica la URL.',
            CURLE_COULDNT_CONNECT     => 'No se pudo conectar al servidor.',
            CURLE_SSL_CONNECT_ERROR   => 'Error de conexión SSL.',
            default                   => $error,
        };
        return ['error' => $msg, 'http_code' => $httpCode];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Respuesta inválida del servidor.', 'http_code' => $httpCode];
    }

    return ['data' => $data, 'http_code' => $httpCode];
}
