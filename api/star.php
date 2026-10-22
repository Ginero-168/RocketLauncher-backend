<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

rl_session_start();
$user = rl_current_user();
if (!$user) {
    rl_json(array('ok' => false, 'error' => 'Login required'), 401);
}
if (!rl_csrf_ok()) {
    rl_json(array('ok' => false, 'error' => 'Invalid session'), 403);
}
if (rl_settings()['maintenance'] && !rl_is_admin($user)) {
    rl_json(array('ok' => false, 'error' => 'The Marketplace is in maintenance'), 503);
}

$id = isset($_POST['id']) ? (string) $_POST['id'] : '';
if (rl_find_item($id) === null) {
    rl_json(array('ok' => false, 'error' => 'Item not found'), 404);
}

$starred = rl_toggle_star($user['id'], $id);
if ($starred === null) {
    rl_json(array('ok' => false, 'error' => 'Could not update stars'), 500);
}

rl_json(array('ok' => true, 'starred' => $starred), 200);
