<?php
session_start();
require_once __DIR__ . '/../backend/api/config.php';

if (empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$users = getJSON('users');
$providers = getJSON('providers');
$subs = getJSON('user_subscriptions');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand">Admin IPTV</a>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-outline-secondary btn-sm">Usuarios</a>
                <a href="providers.php" class="btn btn-outline-secondary btn-sm">Providers</a>
                <a href="subscriptions.php" class="btn btn-outline-secondary btn-sm">Suscripciones</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Salir</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Usuarios</h5>
                        <h2><?= count($users) ?></h2>
                        <a href="users.php" class="btn btn-sm btn-primary">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Proveedores</h5>
                        <h2><?= count($providers) ?></h2>
                        <a href="providers.php" class="btn btn-sm btn-primary">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Suscripciones Activas</h5>
                        <h2><?= count(array_filter($subs, fn($s) => $s['active'] ?? 0)) ?></h2>
                        <a href="subscriptions.php" class="btn btn-sm btn-primary">Gestionar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
