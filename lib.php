<?php

function rl_base_dir()
{
    return __DIR__;
}

function rl_config_path()
{
    return rl_base_dir() . '/config.json';
}

function rl_storage_dir()
{
    return rl_base_dir() . '/storage';
}

function rl_files_dir()
{
    return rl_storage_dir() . '/files';
}

function rl_catalog_path()
{
    return rl_storage_dir() . '/catalog.json';
}

function rl_send_cors()
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Upload-Token');
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function rl_json($payload, $status)
{
    rl_send_cors();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function rl_read_json_file($path, $fallback)
{
    if (!is_file($path)) {
        return $fallback;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $fallback;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $fallback;
    }
    return $data;
}

function rl_write_json_file($path, $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $path);
}

function rl_config()
{
    $config = rl_read_json_file(rl_config_path(), array());
    if (!isset($config['upload_token']) || !is_string($config['upload_token']) || $config['upload_token'] === '') {
        return null;
    }
    return $config;
}

function rl_catalog()
{
    $catalog = rl_read_json_file(rl_catalog_path(), array('items' => array()));
    if (!isset($catalog['items']) || !is_array($catalog['items'])) {
        $catalog['items'] = array();
    }
    return $catalog;
}

function rl_save_catalog($catalog)
{
    if (!is_dir(rl_storage_dir()) && !mkdir(rl_storage_dir(), 0755, true)) {
        return false;
    }
    return rl_write_json_file(rl_catalog_path(), $catalog);
}

function rl_ensure_storage()
{
    if (!is_dir(rl_files_dir()) && !mkdir(rl_files_dir(), 0755, true)) {
        return false;
    }
    if (!is_file(rl_catalog_path())) {
        return rl_save_catalog(array('items' => array()));
    }
    return true;
}

function rl_request_token()
{
    if (isset($_SERVER['HTTP_X_UPLOAD_TOKEN'])) {
        return (string) $_SERVER['HTTP_X_UPLOAD_TOKEN'];
    }
    if (isset($_POST['token'])) {
        return (string) $_POST['token'];
    }
    return '';
}

function rl_require_token()
{
    $config = rl_config();
    if ($config === null) {
        rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
    }
    $given = rl_request_token();
    if ($given === '' || !hash_equals($config['upload_token'], $given)) {
        rl_json(array('ok' => false, 'error' => 'Invalid upload token'), 401);
    }
}

function rl_find_item($id)
{
    $catalog = rl_catalog();
    foreach ($catalog['items'] as $item) {
        if (isset($item['id']) && $item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

function rl_public_item($item)
{
    return array(
        'id' => $item['id'],
        'type' => $item['type'],
        'name' => $item['name'],
        'description' => isset($item['description']) ? $item['description'] : '',
        'filename' => $item['filename'],
        'bytes' => isset($item['bytes']) ? (int) $item['bytes'] : 0,
        'created' => isset($item['created']) ? $item['created'] : '',
    );
}

function rl_new_id()
{
    return bin2hex(random_bytes(8));
}

function rl_clean_text($value, $max)
{
    $value = trim(str_replace(array("\r", "\n"), ' ', (string) $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function rl_download_name($name, $extension)
{
    $name = rl_clean_text($name, 80);
    $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $name);
    $name = trim($name);
    if ($name === '') {
        $name = 'item';
    }
    return $name . '.' . $extension;
}
