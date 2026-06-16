<?php
require_once __DIR__ . '/backend/api/config.php';
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: admin/index.php');
    exit;
}

$provider = $_SESSION['provider'];
$subscription = $_SESSION['subscription'];
$serverUrl = 'http://' . $provider['server_url'];
$username = $provider['username'];
$password = $provider['password'];

$action = $_GET['action'] ?? 'user_info';
$userId = $_SESSION['user']['id'] ?? 0;

if ($action === 'user_info') {
    $users = getJSON('users');
    $user = null;
    foreach ($users as $u) {
        if ($u['id'] == $userId) { $user = $u; break; }
    }
    $subs = getJSON('user_subscriptions');
    $sub = null;
    foreach ($subs as $s) {
        if ($s['user_id'] == $userId && ($s['active'] ?? 1)) { $sub = $s; break; }
    }
    echo json_encode(['user' => $user, 'subscription' => $sub]);
    exit;
}

if ($action === 'renew' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $days = intval($input['days'] ?? 30);
    $subs = getJSON('user_subscriptions');
    foreach ($subs as &$s) {
        if ($s['user_id'] == $userId && ($s['active'] ?? 1)) {
            $currentEnd = new DateTime($s['end_date']);
            $today = new DateTime();
            if ($currentEnd < $today) $currentEnd = $today;
            $currentEnd->modify("+$days days");
            $s['end_date'] = $currentEnd->format('Y-m-d');
            putJSON('user_subscriptions', $subs);
            $_SESSION['subscription'] = $s;
            echo json_encode(['ok' => true, 'end_date' => $s['end_date']]);
            exit;
        }
    }
    http_response_code(400);
    echo json_encode(['error' => 'No se encontró suscripción activa']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
