<?php

require dirname(__DIR__) . '/lib.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method === 'OPTIONS') {
    rl_send_cors();
}

if ($method === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action !== 'start') {
        rl_json(array('ok' => false, 'error' => 'Unknown action'), 400);
    }
    $grant = rl_panel_start();
    if (!is_array($grant) || empty($grant['device']) || empty($grant['user_code'])) {
        rl_json(array('ok' => false, 'error' => 'Could not start a connection'), 500);
    }
    rl_json(array(
        'ok' => true,
        'device_code' => $grant['device'],
        'user_code' => $grant['user_code'],
        'verify_url' => rl_base_url() . '/auth/panel.php?code=' . rawurlencode($grant['user_code']),
        'interval' => 2,
        'expires_in' => 600,
    ), 200);
}

if ($method === 'GET') {
    $poll = rl_panel_poll(isset($_GET['device']) ? (string) $_GET['device'] : '');
    $payload = array('ok' => true, 'status' => $poll['status']);
    if ($poll['status'] === 'approved') {
        $payload['token'] = $poll['token'];
        $payload['name'] = $poll['name'];
    }
    rl_json($payload, 200);
}

rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
