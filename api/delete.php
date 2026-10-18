<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

rl_require_token();

$id = isset($_POST['id']) ? (string) $_POST['id'] : '';
$result = rl_delete_item($id, null, true);
if (empty($result['ok'])) {
    $status = isset($result['status']) ? (int) $result['status'] : 400;
    rl_json(array('ok' => false, 'error' => $result['error']), $status);
}

rl_json(array('ok' => true), 200);
