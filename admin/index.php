<?php
session_start();
if (!empty($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../backend/api/config.php';
    if (adminLogin($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Credenciales inválidas';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg-primary: #0d1117; --bg-card: #161b22; --border: #30363d; --accent: #58a6ff; --text: #e6edf3; }
        body { background: var(--bg-primary); color: var(--text); display: flex; align-items: center; min-height: 100vh; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; }
        .form-label { color: var(--text); }
        .form-control { background: #0d1117; border-color: var(--border); color: var(--text); }
        .form-control:focus { background: #0d1117; border-color: var(--accent); color: var(--text); }
        h4 { color: var(--text); }
        .alert-danger { background: rgba(248,81,73,0.15); border-color: var(--danger); color: #f85149; }
    </style>
</head>
<body>
    <div class="container" style="max-width:400px">
        <div class="card p-4">
            <h4 class="text-center mb-3">Admin IPTV</h4>
            <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label small">Usuario</label>
                    <input type="text" name="username" class="form-control" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
