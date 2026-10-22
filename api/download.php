<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

if (rl_config() === null) {
    rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
}

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
    rl_json(array('ok' => false, 'error' => 'Missing item id'), 400);
}

rl_drop_expired();
$item = rl_find_item($id);
if ($item === null || rl_expiry_time($item) <= time()) {
    rl_json(array('ok' => false, 'error' => 'Item not found'), 404);
}

if (rl_normalize_type($item['type']) === 'color') {
    $renewed = rl_renew_item($id, rl_visitor_key());
    if (is_array($renewed)) {
        $item = $renewed;
    }
    rl_json(array('ok' => true, 'item' => rl_public_item($item)), 200);
}

if (!isset($item['stored']) || $item['stored'] === '') {
    rl_json(array('ok' => false, 'error' => 'Item not found'), 404);
}

$path = rl_storage_dir() . '/' . $item['stored'];
$realStorage = realpath(rl_files_dir());
$realFile = realpath($path);
if ($realStorage === false || $realFile === false || strpos($realFile, $realStorage . DIRECTORY_SEPARATOR) !== 0 || !is_file($realFile)) {
    rl_json(array('ok' => false, 'error' => 'File is missing'), 404);
}

$renewed = rl_renew_item($id, rl_visitor_key());
if (is_array($renewed)) {
    $item = $renewed;
}

$filename = isset($item['filename']) ? $item['filename'] : basename($realFile);
rl_send_cors();
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($realFile));
header(rl_attachment_header($filename));
header('X-Content-Type-Options: nosniff');
readfile($realFile);
exit;
