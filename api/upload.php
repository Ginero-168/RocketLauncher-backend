<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

rl_require_token();

if (!rl_ensure_storage()) {
    rl_json(array('ok' => false, 'error' => 'Storage is not writable'), 500);
}

$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
$rules = array(
    'script' => array('ext' => 'jsx', 'max' => 2 * 1024 * 1024),
    'appearance' => array('ext' => 'ai', 'max' => 40 * 1024 * 1024),
);
if (!isset($rules[$type])) {
    rl_json(array('ok' => false, 'error' => 'Type must be script or appearance'), 400);
}

$name = rl_clean_text(isset($_POST['name']) ? $_POST['name'] : '', 80);
$description = rl_clean_text(isset($_POST['description']) ? $_POST['description'] : '', 500);
if ($name === '') {
    rl_json(array('ok' => false, 'error' => 'Name is required'), 400);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    rl_json(array('ok' => false, 'error' => 'File is required'), 400);
}

$file = $_FILES['file'];
if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    rl_json(array('ok' => false, 'error' => 'Upload failed'), 400);
}

$rule = $rules[$type];
$size = isset($file['size']) ? (int) $file['size'] : 0;
if ($size < 1 || $size > $rule['max']) {
    rl_json(array('ok' => false, 'error' => 'File is empty or too large'), 400);
}

$original = isset($file['name']) ? (string) $file['name'] : '';
$extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
if ($extension !== $rule['ext']) {
    rl_json(array('ok' => false, 'error' => 'Expected a .' . $rule['ext'] . ' file'), 400);
}

$id = rl_new_id();
$stored = 'files/' . $id . '.' . $rule['ext'];
$destination = rl_storage_dir() . '/' . $stored;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    rl_json(array('ok' => false, 'error' => 'Could not store the file'), 500);
}

$item = array(
    'id' => $id,
    'type' => $type,
    'name' => $name,
    'description' => $description,
    'filename' => rl_download_name($name, $rule['ext']),
    'stored' => $stored,
    'bytes' => $size,
    'created' => gmdate('c'),
);

$catalog = rl_catalog();
$catalog['items'][] = $item;
if (!rl_save_catalog($catalog)) {
    unlink($destination);
    rl_json(array('ok' => false, 'error' => 'Could not update the catalog'), 500);
}

rl_json(array('ok' => true, 'item' => rl_public_item($item)), 201);
