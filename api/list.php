<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

if (rl_config() === null) {
    rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
}

$user = rl_session_resume() ? rl_current_user() : null;
$userId = $user ? $user['id'] : '';
$type = isset($_GET['type']) ? (string) $_GET['type'] : '';

rl_json(array(
    'ok' => true,
    'items' => rl_present_catalog($type, $userId, false),
), 200);
