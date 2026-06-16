<?php
session_start();
require_once __DIR__ . '/../backend/api/config.php';
if (empty($_SESSION['admin'])) { header('Location: index.php'); exit; }

$subs = getJSON('user_subscriptions');
$users = getJSON('users');
$providers = getJSON('providers');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $maxId = max(array_column($subs, 'id')) + 1;
        $subs[] = [
            'id' => $maxId,
            'user_id' => intval($_POST['user_id']),
            'provider_id' => intval($_POST['provider_id']),
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'active' => true,
            'created_at' => date('Y-m-d')
        ];
        putJSON('user_subscriptions', $subs);
    } elseif ($action === 'toggle') {
        foreach ($subs as &$s) {
            if ($s['id'] == $_POST['id']) {
                $s['active'] = !($s['active'] ?? true);
                break;
            }
        }
        putJSON('user_subscriptions', $subs);
    } elseif ($action === 'delete') {
        $subs = array_filter($subs, fn($s) => $s['id'] != $_POST['id']);
        putJSON('user_subscriptions', array_values($subs));
    }
    header('Location: subscriptions.php');
    exit;
}

function getUserName($id, $users) {
    foreach ($users as $u) { if ($u['id'] == $id) return $u['username']; }
    return "ID:$id";
}
function getProviderName($id, $providers) {
    foreach ($providers as $p) { if ($p['id'] == $id) return $p['name']; }
    return "ID:$id";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripciones - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Admin IPTV</a>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-outline-secondary btn-sm">Usuarios</a>
                <a href="providers.php" class="btn btn-outline-secondary btn-sm">Providers</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Suscripciones</span>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('createForm').style.display='block'">+ Nueva</button>
            </div>
            <div class="card-body">
                <form id="createForm" method="post" style="display:none" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="create">
                    <div class="col-auto">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Usuario</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="provider_id" class="form-select form-select-sm" required>
                            <option value="">Proveedor</option>
                            <?php foreach ($providers as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto"><input type="date" name="start_date" class="form-control form-control-sm" required></div>
                    <div class="col-auto"><input type="date" name="end_date" class="form-control form-control-sm" required></div>
                    <div class="col-auto"><button class="btn btn-sm btn-success">Crear</button></div>
                </form>
                <table class="table table-sm table-hover">
                    <thead><tr><th>ID</th><th>Usuario</th><th>Proveedor</th><th>Inicio</th><th>Fin</th><th>Activo</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($subs as $s): ?>
                        <tr class="<?= !($s['active'] ?? 1) ? 'text-muted' : '' ?>">
                            <td><?= $s['id'] ?></td>
                            <td><?= htmlspecialchars(getUserName($s['user_id'], $users)) ?></td>
                            <td><?= htmlspecialchars(getProviderName($s['provider_id'], $providers)) ?></td>
                            <td><?= $s['start_date'] ?></td>
                            <td><?= $s['end_date'] ?></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button class="btn btn-sm <?= ($s['active'] ?? 1) ? 'btn-success' : 'btn-secondary' ?>">
                                        <?= ($s['active'] ?? 1) ? 'Activa' : 'Inactiva' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Eliminar?')" style="display:inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
