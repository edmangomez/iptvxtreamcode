<?php
require_once __DIR__ . '/backend/api/config.php';
session_start();

if (!empty($_SESSION['token'])) {
    header('Location: player.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser($_POST['username'] ?? '', $_POST['password'] ?? '');
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $_SESSION['token'] = $result['token'];
        $_SESSION['user'] = $result['user'];
        $_SESSION['provider'] = $result['provider'];
        $_SESSION['subscription'] = $result['subscription'];
        header('Location: player.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg-primary: #0d1117; --bg-card: #161b22; --border: #30363d; --accent: #58a6ff; --text: #e6edf3; --text-sec: #8b949e; }
        body { background: var(--bg-primary); color: var(--text); display: flex; align-items: center; min-height: 100vh; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; }
        .form-label { color: var(--text); }
        .form-control { background: #0d1117; border-color: var(--border); color: var(--text); }
        .form-control:focus { background: #0d1117; border-color: var(--accent); color: var(--text); box-shadow: 0 0 0 2px rgba(88,166,255,0.2); }
        .btn-primary { background: var(--accent); border: none; }
        .btn-primary:hover { opacity: 0.9; }
        .alert-danger { background: rgba(248,81,73,0.15); border-color: var(--danger); color: #f85149; }
        a:not(.btn) { color: var(--accent); }
        a:not(.btn):hover { color: #79c0ff; }
    </style>
</head>
<body>
    <div class="container" style="max-width:400px">
        <div class="card p-4">
            <h4 class="text-center mb-3"><i class="bi bi-tv me-2"></i>IPTV</h4>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label small">Usuario</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>
            <div class="mt-3 text-center small text-secondary">
                <a href="admin/index.php" class="text-secondary">Admin</a>
            </div>
        </div>
    </div>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</body>
</html>
