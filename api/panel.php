<?php

require dirname(__DIR__) . '/lib.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method === 'OPTIONS') {
    rl_send_cors();
}

if ($method === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'profile') {
        $user = rl_panel_user(isset($_POST['panel_token']) ? (string) $_POST['panel_token'] : '');
        if (!$user) {
            rl_json(array('ok' => false, 'error' => 'Panel session expired'), 401);
        }
        $name = isset($user['name']) ? rl_clean_text($user['name'], 80) : 'Member';
        if ($name === '') {
            $name = 'Member';
        }
        rl_json(array(
            'ok' => true,
            'name' => $name,
            'picture' => rl_google_picture(isset($user['picture']) ? $user['picture'] : ''),
        ), 200);
    }
    if ($action === 'logout') {
        rl_panel_revoke(isset($_POST['panel_token']) ? (string) $_POST['panel_token'] : '');
        rl_json(array('ok' => true), 200);
    }
    if ($action !== 'start') {
        rl_json(array('ok' => false, 'error' => 'Unknown action'), 400);
    }
    if (rl_rate_limited('panel-start', rl_settings()['panel_starts_per_10min'], 600)) {
        rl_json(array('ok' => false, 'error' => 'Too many connection attempts, try again in a few minutes'), 429);
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
        $payload['picture'] = isset($poll['picture']) ? $poll['picture'] : '';
    }
    rl_json($payload, 200);
}

rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
