<?php
require_once __DIR__ . '/backend/api/config.php';
session_start();

$token = $_SESSION['token'] ?? '';
if ($token) {
    $sessions = getJSON('active_sessions');
    $sessions = array_filter($sessions, fn($s) => $s['token'] !== $token);
    putJSON('active_sessions', array_values($sessions));
}
session_destroy();
header('Location: index.php');
