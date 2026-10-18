<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
    rl_json(array('ok' => false, 'error' => 'Missing item id'), 400);
}

$item = rl_find_item($id);
if ($item === null || empty($item['preview'])) {
    rl_json(array('ok' => false, 'error' => 'Preview not found'), 404);
}

$path = rl_storage_dir() . '/' . $item['preview'];
$realStorage = realpath(rl_files_dir());
$realFile = realpath($path);
if ($realStorage === false || $realFile === false || strpos($realFile, $realStorage . DIRECTORY_SEPARATOR) !== 0 || !is_file($realFile)) {
    rl_json(array('ok' => false, 'error' => 'Preview is missing'), 404);
}

$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$types = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg');
if (!isset($types[$ext])) {
    rl_json(array('ok' => false, 'error' => 'Preview is missing'), 404);
}

rl_send_cors();
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($realFile));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
readfile($realFile);
exit;
