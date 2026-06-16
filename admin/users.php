<?php
session_start();
require_once __DIR__ . '/../backend/api/config.php';
if (empty($_SESSION['admin'])) { header('Location: index.php'); exit; }

$users = getJSON('users');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $maxId = max(array_column($users, 'id')) + 1;
        $users[] = [
            'id' => $maxId,
            'username' => $_POST['username'],
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d')
        ];
        putJSON('users', $users);
    } elseif ($action === 'delete') {
        $users = array_filter($users, fn($u) => $u['id'] != $_POST['id']);
        putJSON('users', array_values($users));
    }
    header('Location: users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Admin IPTV</a>
            <div class="d-flex gap-2">
                <a href="providers.php" class="btn btn-outline-secondary btn-sm">Providers</a>
                <a href="subscriptions.php" class="btn btn-outline-secondary btn-sm">Suscripciones</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Usuarios</span>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('createForm').style.display='block'">+ Nuevo</button>
            </div>
            <div class="card-body">
                <form id="createForm" method="post" style="display:none" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-auto"><input type="text" name="username" class="form-control form-control-sm" placeholder="Usuario" required></div>
                    <div class="col-auto"><input type="password" name="password" class="form-control form-control-sm" placeholder="Contraseña" required></div>
                    <div class="col-auto"><button class="btn btn-sm btn-success">Crear</button></div>
                </form>
                <table class="table table-sm table-hover">
                    <thead><tr><th>ID</th><th>Usuario</th><th>Creado</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= $u['created_at'] ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Eliminar?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
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
