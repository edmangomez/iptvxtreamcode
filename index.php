<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Cargar credenciales del .env si existe
$envServer = $envUser = $envPass = '';
$envFile = __DIR__ . '/../xtream-player.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    $envServer = trim($env['SERVER_URL'] ?? '');
    $envUser   = trim($env['USERNAME'] ?? '');
    $envPass   = $env['PASSWORD'] ?? '';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $server_url = trim($_POST['server_url'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    $errors = [];

    if ($server_url === '') {
        $errors[] = 'La URL del servidor es requerida.';
    }
    if ($username === '') {
        $errors[] = 'El usuario es requerido.';
    }
    if ($password === '') {
        $errors[] = 'La contraseña es requerida.';
    }

    if (empty($errors)) {
        if (!preg_match('#^https?://#i', $server_url)) {
            $server_url = 'http://' . $server_url;
        }
        $server_url = rtrim($server_url, '/');

        $testUrl = $server_url . '/player_api.php'
                 . '?username=' . urlencode($username)
                 . '&password=' . urlencode($password);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => APP_NAME . '/' . APP_VERSION,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            $errors[] = 'No se pudo conectar al servidor: ' . $curlError;
        } elseif ($httpCode >= 400) {
            $errors[] = "El servidor respondi\u{00f3} con c\u{00f3}digo HTTP $httpCode";
        } else {
            $data = json_decode($response, true);
            $auth = $data['user_info']['auth'] ?? null;

            if ($auth === null || $auth == 0) {
                $errors[] = 'Credenciales inv\u{00e1}lidas. Verifica tus datos.';
            } elseif ($auth == 1) {
                $_SESSION['server_url'] = $server_url;
                $_SESSION['username']   = $username;
                $_SESSION['password']   = $password;
                header('Location: dashboard.php');
                exit;
            }
        }
    }

    $error = implode('<br>', $errors);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-dark">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-11 col-sm-8 col-md-6 col-lg-4">

            <div class="text-center mb-4">
                <i class="bi bi-tv display-1 text-primary"></i>
                <h1 class="h3 mt-2 text-white"><?= APP_NAME ?></h1>
                <p class="text-secondary">Conecta con tu proveedor IPTV</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <form method="post">
                        <div class="mb-3">
                            <label for="server_url" class="form-label">URL del Servidor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-globe"></i></span>
                                <input type="url" class="form-control" id="server_url" name="server_url"
                                       placeholder="http://ejemplo.com:8080"
                                       value="<?= htmlspecialchars($_POST['server_url'] ?? $envServer) ?>" required>
                            </div>
                            <div class="form-text">Ej: <code>http://midominio.com:8080</code></div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username"
                                       placeholder="Usuario" value="<?= htmlspecialchars($_POST['username'] ?? $envUser) ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Contraseña"
                                       value="<?= htmlspecialchars($_POST['password'] ?? $envPass) ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Conectar
                        </button>
                    </form>
                    <hr class="my-3 border-secondary">
                    <a href="test_setup.php" class="btn btn-outline-secondary w-100 py-2">
                        <i class="bi bi-flask me-2"></i>Modo Demo (sin servidor real)
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
