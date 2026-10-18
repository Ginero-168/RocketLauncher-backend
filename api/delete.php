<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

rl_session_start();
$token = rl_request_token();
$user = rl_current_user();
$admin = false;
if ($token !== '') {
    rl_require_token();
    $admin = true;
} elseif (!$user || !rl_csrf_ok()) {
    rl_json(array('ok' => false, 'error' => 'Login required'), 401);
}

$id = isset($_POST['id']) ? (string) $_POST['id'] : '';
$result = rl_delete_item($id, $user, $admin);
if (empty($result['ok'])) {
    $status = isset($result['status']) ? (int) $result['status'] : 400;
    rl_json(array('ok' => false, 'error' => $result['error']), $status);
}

rl_json(array('ok' => true), 200);
