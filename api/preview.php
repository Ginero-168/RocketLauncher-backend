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

// A preview belongs to one item id and is never replaced: browsers and the
// panel keep it for 30 days, the CDN for one (so a deleted item's preview
// does not linger there); a revalidation gets an empty 304.
$etag = '"' . $id . '-' . filesize($realFile) . '"';
rl_send_cors();
header('Cache-Control: public, max-age=2592000, s-maxage=86400, immutable');
header('ETag: ' . $etag);
if (rl_etag_matches($etag)) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($realFile));
readfile($realFile);
exit;
