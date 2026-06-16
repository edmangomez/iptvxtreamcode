<?php
session_start();
require_once __DIR__ . '/../backend/api/config.php';
if (empty($_SESSION['admin'])) { header('Location: index.php'); exit; }

$providers = getJSON('providers');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $maxId = max(array_column($providers, 'id')) + 1;
        $providers[] = [
            'id' => $maxId,
            'name' => $_POST['name'],
            'server_url' => $_POST['server_url'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'created_at' => date('Y-m-d')
        ];
        putJSON('providers', $providers);
    } elseif ($action === 'delete') {
        $providers = array_filter($providers, fn($p) => $p['id'] != $_POST['id']);
        putJSON('providers', array_values($providers));
    }
    header('Location: providers.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Admin IPTV</a>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-outline-secondary btn-sm">Usuarios</a>
                <a href="subscriptions.php" class="btn btn-outline-secondary btn-sm">Suscripciones</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Proveedores</span>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('createForm').style.display='block'">+ Nuevo</button>
            </div>
            <div class="card-body">
                <form id="createForm" method="post" style="display:none" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-auto"><input type="text" name="name" class="form-control form-control-sm" placeholder="Nombre" required></div>
                    <div class="col-auto"><input type="text" name="server_url" class="form-control form-control-sm" placeholder="Server:port" required></div>
                    <div class="col-auto"><input type="text" name="username" class="form-control form-control-sm" placeholder="User API" required></div>
                    <div class="col-auto"><input type="text" name="password" class="form-control form-control-sm" placeholder="Pass API" required></div>
                    <div class="col-auto"><button class="btn btn-sm btn-success">Crear</button></div>
                </form>
                <table class="table table-sm table-hover">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Server</th><th>User API</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($providers as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['server_url']) ?></td>
                            <td><?= htmlspecialchars($p['username']) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Eliminar?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
