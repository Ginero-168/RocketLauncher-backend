<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

rl_require_token();

$result = rl_create_item(null);
if (empty($result['ok'])) {
    $status = isset($result['error']) && $result['error'] === 'Storage is not writable' ? 500 : 400;
    rl_json(array('ok' => false, 'error' => $result['error']), $status);
}

rl_json(array(
    'ok' => true,
    'item' => rl_public_item($result['item']),
), 201);
