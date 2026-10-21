<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

if (rl_config() === null) {
    rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
}

rl_housekeeping();
$user = rl_current_user();
$type = isset($_GET['type']) ? (string) $_GET['type'] : '';
$payload = array(
    'ok' => true,
    'items' => rl_present_catalog($type, $user ? $user['id'] : '', false),
);
if ($user) {
    // Starred and "mine" are personal: never kept by a shared cache.
    header('Cache-Control: private, no-store');
    rl_json($payload, 200);
}
rl_json_revalidated($payload);
