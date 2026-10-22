<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

$id = isset($_POST['id']) ? (string) $_POST['id'] : '';
$panelToken = isset($_POST['panel_token']) ? (string) $_POST['panel_token'] : '';
if ($panelToken !== '') {
    // Members remove their own items from a connected panel.
    $user = rl_panel_user($panelToken);
    if (!$user) {
        rl_json(array('ok' => false, 'error' => 'Panel session expired'), 401);
    }
    if (rl_settings()['maintenance'] && !rl_is_admin($user)) {
        rl_json(array('ok' => false, 'error' => 'The Marketplace is in maintenance'), 503);
    }
    $result = rl_delete_item($id, $user, false);
} else {
    rl_require_token();
    $result = rl_delete_item($id, null, true);
}
if (empty($result['ok'])) {
    $status = isset($result['status']) ? (int) $result['status'] : 400;
    rl_json(array('ok' => false, 'error' => $result['error']), $status);
}

rl_json(array('ok' => true), 200);
