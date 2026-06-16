<?php
require_once __DIR__ . '/backend/api/config.php';

$admins = getJSON('admin_users');
if (empty($admins)) {
    putJSON('admin_users', [
        ['id' => 1, 'username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT)]
    ]);
}

$users = getJSON('users');
if (empty($users)) {
    putJSON('users', [
        ['id' => 1, 'username' => 'cliente1', 'password' => password_hash('pass123', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d')]
    ]);
}

$providers = getJSON('providers');
if (empty($providers)) {
    putJSON('providers', [
        ['id' => 1, 'name' => 'Flores TV Oficial', 'server_url' => 'florestvoficial.com:8880', 'username' => 'Edmangomez77', 'password' => 'QQ7VEMpQNm', 'created_at' => date('Y-m-d')]
    ]);
}

$subs = getJSON('user_subscriptions');
if (empty($subs)) {
    putJSON('user_subscriptions', [
        ['id' => 1, 'user_id' => 1, 'provider_id' => 1, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+1 year')), 'active' => true, 'created_at' => date('Y-m-d')]
    ]);
}

$sessions = getJSON('active_sessions');
if (!is_array($sessions)) {
    putJSON('active_sessions', []);
}

echo "Sistema inicializado correctamente.";
