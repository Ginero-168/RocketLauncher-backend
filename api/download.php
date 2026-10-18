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

$item = rl_find_item($id);
if ($item === null || !isset($item['stored'])) {
    rl_json(array('ok' => false, 'error' => 'Item not found'), 404);
}

$path = rl_storage_dir() . '/' . $item['stored'];
$realStorage = realpath(rl_files_dir());
$realFile = realpath($path);
if ($realStorage === false || $realFile === false || strpos($realFile, $realStorage . DIRECTORY_SEPARATOR) !== 0 || !is_file($realFile)) {
    rl_json(array('ok' => false, 'error' => 'File is missing'), 404);
}

$filename = isset($item['filename']) ? $item['filename'] : basename($realFile);
rl_send_cors();
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($realFile));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('X-Content-Type-Options: nosniff');
readfile($realFile);
exit;
