<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

rl_require_token();

$id = isset($_POST['id']) ? (string) $_POST['id'] : '';
if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
    rl_json(array('ok' => false, 'error' => 'Missing item id'), 400);
}

$catalog = rl_catalog();
$kept = array();
$removed = null;
foreach ($catalog['items'] as $item) {
    if (isset($item['id']) && $item['id'] === $id) {
        $removed = $item;
        continue;
    }
    $kept[] = $item;
}

if ($removed === null) {
    rl_json(array('ok' => false, 'error' => 'Item not found'), 404);
}

$catalog['items'] = $kept;
if (!rl_save_catalog($catalog)) {
    rl_json(array('ok' => false, 'error' => 'Could not update the catalog'), 500);
}

if (isset($removed['stored'])) {
    $path = rl_storage_dir() . '/' . $removed['stored'];
    $realStorage = realpath(rl_files_dir());
    $realFile = realpath($path);
    if ($realStorage !== false && $realFile !== false && strpos($realFile, $realStorage . DIRECTORY_SEPARATOR) === 0 && is_file($realFile)) {
        unlink($realFile);
    }
}

rl_json(array('ok' => true), 200);
