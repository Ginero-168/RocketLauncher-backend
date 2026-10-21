<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

$user = rl_current_user();
if (!$user) {
    rl_json(array('ok' => false, 'error' => 'Login required'), 401);
}

$type = isset($_GET['type']) ? (string) $_GET['type'] : '';
header('Cache-Control: private, no-store');
rl_json(array(
    'ok' => true,
    'items' => rl_present_catalog($type, $user['id'], true),
), 200);
