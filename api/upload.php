<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

if (rl_config() === null) {
    rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
}

rl_session_start();
$token = rl_request_token();
$user = null;
if ($token !== '') {
    rl_require_token();
} else {
    $user = rl_current_user();
    if (!$user) {
        rl_json(array('ok' => false, 'error' => 'Login required'), 401);
    }
    if (!rl_csrf_ok()) {
        rl_json(array('ok' => false, 'error' => 'Invalid session'), 403);
    }
}

$result = rl_create_item($user);
if (empty($result['ok'])) {
    $status = isset($result['error']) && $result['error'] === 'Storage is not writable' ? 500 : 400;
    rl_json(array('ok' => false, 'error' => $result['error']), $status);
}

rl_json(array(
    'ok' => true,
    'item' => rl_public_item($result['item'], $user ? $user['id'] : '', false, 0, $user ? $user['name'] : ''),
), 201);
