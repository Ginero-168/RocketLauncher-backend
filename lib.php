<?php

// Errors go to the server log, never into a page or a JSON reply.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Upload-Token, X-Panel-Token');
    header('X-Content-Type-Options: nosniff');
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

// A public JSON reply browsers and the panel may keep: they revalidate it
// every time (no-cache) and get an empty 304 when nothing changed.
function rl_json_revalidated($payload)
{
    $body = json_encode($payload);
    $etag = '"' . substr(sha1($body), 0, 20) . '"';
    rl_send_cors();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    header('ETag: ' . $etag);
    if (rl_etag_matches($etag)) {
        http_response_code(304);
        exit;
    }
    http_response_code(200);
    echo $body;
    exit;
}

function rl_etag_matches($etag)
{
    if (!isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        return false;
    }
    foreach (explode(',', (string) $_SERVER['HTTP_IF_NONE_MATCH']) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === $etag || $candidate === 'W/' . $etag || $candidate === '*') {
            return true;
        }
    }
    return false;
}

// HTML pages: nothing may frame them (so the panel "allow" button cannot be
// clickjacked), no scripts run at all, images come only from here and from
// Google profile photos.
function rl_page_headers()
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; img-src 'self' https://*.googleusercontent.com; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'none'; form-action 'self' https://accounts.google.com; frame-ancestors 'none'");
}

// A store that exists but cannot be read stops the request instead of being
// treated as empty (which the next write would save over the real data).
function rl_storage_failure($path)
{
    error_log('Rocket Launcher: cannot read ' . $path);
    if (defined('RL_PAGE')) {
        http_response_code(503);
        rl_page_headers();
        echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>Rocket Launcher Marketplace</title>';
        echo '<link rel="stylesheet" href="' . (RL_PAGE === 'sub' ? '../' : '') . 'assets/site.css"></head><body><main class="wrap">';
        echo '<p class="kicker">Rocket Launcher</p><h1>ปิดปรับปรุงชั่วคราว</h1><p class="lead">ระบบเก็บข้อมูลมีปัญหา ลองใหม่อีกครั้งภายหลัง</p></main></body></html>';
        exit;
    }
    rl_json(array('ok' => false, 'error' => 'Storage is damaged'), 503);
}

// A missing file gives $fallback. A file that exists but cannot be read or
// parsed gives null, so damage is never mistaken for "empty".
function rl_read_json_file($path, $fallback)
{
    if (!is_file($path)) {
        return $fallback;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// Written to a temporary file and renamed into place; the previous good
// version is kept as .bak (not for config.json, which holds secrets).
function rl_write_json_file($path, $data, $backup = true)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if ($backup && is_file($path) && rl_read_json_file($path, null) !== null) {
        @copy($path, $path . '.bak');
    }
    return rename($tmp, $path);
}

// Catalog, users, stars and panel sessions: a damaged file falls back to its
// .bak; with neither readable the request stops (see rl_storage_failure).
function rl_load_store($path, $fallback)
{
    $data = rl_read_json_file($path, $fallback);
    if ($data !== null) {
        return $data;
    }
    $backup = rl_read_json_file($path . '.bak', null);
    if ($backup === null) {
        rl_storage_failure($path);
    }
    error_log('Rocket Launcher: ' . basename($path) . ' is damaged, using its backup');
    return $backup;
}

function rl_config()
{
    $config = rl_config_raw();
    if (!isset($config['upload_token']) || !is_string($config['upload_token']) || $config['upload_token'] === '') {
        return null;
    }
    return $config;
}

function rl_catalog()
{
    $catalog = rl_load_store(rl_catalog_path(), array('items' => array()));
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

function rl_normalize_type($type)
{
    if ($type === 'appearance' || $type === 'style') {
        return 'style';
    }
    if ($type === 'script' || $type === 'color') {
        return $type;
    }
    return '';
}

function rl_public_item($item, $userId = '', $starred = false, $stars = 0, $ownerName = '')
{
    $type = rl_normalize_type(isset($item['type']) ? $item['type'] : '');
    $colors = array();
    if ($type === 'color' && isset($item['colors']) && is_array($item['colors'])) {
        $colors = array_values($item['colors']);
    }
    $ownerId = isset($item['owner_id']) ? (string) $item['owner_id'] : '';
    return array(
        'id' => $item['id'],
        'type' => $type,
        'name' => $item['name'],
        'description' => isset($item['description']) ? $item['description'] : '',
        'filename' => isset($item['filename']) ? $item['filename'] : '',
        'bytes' => isset($item['bytes']) ? (int) $item['bytes'] : 0,
        'created' => isset($item['created']) ? $item['created'] : '',
        'expires' => rl_expiry_time($item) > 0 ? gmdate('c', rl_expiry_time($item)) : '',
        'days_left' => rl_days_left($item),
        'downloads' => isset($item['downloads']) ? (int) $item['downloads'] : 0,
        'colors' => $colors,
        'preview' => !empty($item['preview']),
        'owner_name' => $ownerName,
        'starred' => (bool) $starred,
        'stars' => (int) $stars,
        'mine' => $userId !== '' && $ownerId !== '' && $ownerId === $userId,
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

// Content-Disposition that keeps Thai and other non-ASCII names intact.
function rl_attachment_header($filename)
{
    $filename = str_replace(array('"', "\r", "\n"), '', (string) $filename);
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
    if ($ascii === '' || $ascii === null) {
        $ascii = 'download';
    }
    return 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
}

function rl_is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https') {
            return true;
        }
    }
    return isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443';
}

function rl_request_base()
{
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(?::\d+)?$/', $host)) {
        $host = 'localhost';
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
    $dir = str_replace('\\', '/', dirname($script));
    if (preg_match('#/(auth|api)$#', $dir)) {
        $dir = str_replace('\\', '/', dirname($dir));
    }
    if ($dir === '/' || $dir === '.' || $dir === '') {
        $dir = '';
    }
    return (rl_is_https() ? 'https' : 'http') . '://' . $host . $dir;
}

function rl_valid_base_url($url)
{
    return (bool) preg_match('#^https?://[A-Za-z0-9.\-]+(?::\d+)?(?:/[A-Za-z0-9._~/-]*)?$#', $url);
}

function rl_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = rl_is_https();
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

// Read-only pages call this instead of rl_session_start(): a visitor without
// a session cookie (the panel, a first visit) gets no new session file.
function rl_session_resume()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (empty($_COOKIE[session_name()])) {
        return false;
    }
    rl_session_start();
    return true;
}

function rl_flash($message = null)
{
    if ($message !== null) {
        rl_session_start();
        $_SESSION['flash'] = (string) $message;
        return '';
    }
    if (!rl_session_resume()) {
        return '';
    }
    $text = isset($_SESSION['flash']) ? (string) $_SESSION['flash'] : '';
    unset($_SESSION['flash']);
    return $text;
}

function rl_csrf_token()
{
    rl_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function rl_csrf_ok()
{
    rl_session_start();
    $given = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    $saved = isset($_SESSION['csrf']) ? (string) $_SESSION['csrf'] : '';
    return $given !== '' && $saved !== '' && hash_equals($saved, $given);
}

function rl_safe_next($value)
{
    $value = (string) $value;
    if (preg_match('/^index\.php(\?[A-Za-z0-9_\-=&%\.\+]*)?$/', $value)) {
        return $value;
    }
    if (preg_match('/^auth\/panel\.php\?code=[A-Z2-9]{4}-[A-Z2-9]{4}$/', $value)) {
        return $value;
    }
    return 'index.php';
}

function rl_config_raw()
{
    $config = rl_read_json_file(rl_config_path(), array());
    return is_array($config) ? $config : array();
}

// config.json exists but cannot be parsed: setup must not recreate it (that
// would hand the admin token to whoever opens setup.php first).
function rl_config_damaged()
{
    return is_file(rl_config_path()) && rl_read_json_file(rl_config_path(), array()) === null;
}

function rl_update_config($patch)
{
    if (rl_config_damaged()) {
        return false;
    }
    $config = rl_config_raw();
    foreach ($patch as $key => $value) {
        $config[$key] = $value;
    }
    return rl_write_json_file(rl_config_path(), $config, false);
}

function rl_base_url()
{
    $config = rl_config_raw();
    if (isset($config['base_url']) && is_string($config['base_url'])) {
        $base = rtrim(trim($config['base_url']), '/');
        if (rl_valid_base_url($base)) {
            return $base;
        }
    }
    return rl_request_base();
}

function rl_google_redirect_uri()
{
    return rl_base_url() . '/auth/callback.php';
}

function rl_google_ready()
{
    $config = rl_config_raw();
    foreach (array('google_client_id', 'google_client_secret') as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || trim($config[$key]) === '') {
            return false;
        }
    }
    return true;
}

function rl_users_path()
{
    return rl_storage_dir() . '/users.json';
}

function rl_stars_path()
{
    return rl_storage_dir() . '/stars.json';
}

function rl_locked($callback)
{
    if (!rl_ensure_storage()) {
        return null;
    }
    $handle = fopen(rl_storage_dir() . '/write.lock', 'c');
    if ($handle === false) {
        return null;
    }
    flock($handle, LOCK_EX);
    $result = call_user_func($callback);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $result;
}

function rl_client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

// Key for hashing visitor addresses: nothing readable is stored.
function rl_secret()
{
    $config = rl_config_raw();
    $token = isset($config['upload_token']) ? (string) $config['upload_token'] : '';
    return hash('sha256', 'rocket-launcher|' . $token);
}

function rl_visitor_key()
{
    return substr(hash_hmac('sha256', rl_client_ip() . '|' . gmdate('Y-m-d'), rl_secret()), 0, 16);
}

// True when this address already did $limit of $bucket within $window
// seconds; otherwise records the attempt. The counters are disposable: a
// damaged file simply starts over.
function rl_rate_limited($bucket, $limit, $window)
{
    $key = $bucket . ':' . substr(hash_hmac('sha256', rl_client_ip(), rl_secret()), 0, 24);
    $result = rl_locked(function () use ($key, $limit, $window) {
        $path = rl_storage_dir() . '/limits.json';
        $data = rl_read_json_file($path, array());
        if (!is_array($data)) {
            $data = array();
        }
        $now = time();
        $recent = array();
        foreach (isset($data[$key]) && is_array($data[$key]) ? $data[$key] : array() as $time) {
            if ($now - (int) $time < $window) {
                $recent[] = (int) $time;
            }
        }
        if (count($recent) >= $limit) {
            return true;
        }
        $recent[] = $now;
        $data[$key] = $recent;
        foreach ($data as $name => $times) {
            if (!is_array($times) || count($times) === 0 || $now - max($times) > 86400) {
                unset($data[$name]);
            }
        }
        rl_write_json_file($path, $data);
        return false;
    });
    return $result === true;
}

// At most once an hour, from the first catalog read after it is due: drop
// expired items, delete stored files that no item points to (only when older
// than an hour, so an upload in progress is never touched) and prune expired
// panel grants and sessions. Returns the number of stray files removed.
function rl_housekeeping($force = false)
{
    $marker = rl_storage_dir() . '/housekeeping.lock';
    $last = is_file($marker) ? (int) @filemtime($marker) : 0;
    if (!$force && $last > 0 && time() - $last < 3600) {
        return 0;
    }
    if (!rl_ensure_storage() || !@touch($marker)) {
        return 0;
    }
    rl_drop_expired();
    $referenced = array();
    foreach (rl_catalog()['items'] as $item) {
        foreach (array('stored', 'preview') as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                $referenced[$item[$key]] = true;
            }
        }
    }
    $removed = 0;
    $names = @scandir(rl_files_dir());
    foreach (is_array($names) ? $names : array() as $name) {
        $path = rl_files_dir() . '/' . $name;
        if ($name === '.' || $name === '..' || $name === '.gitkeep' || !is_file($path)) {
            continue;
        }
        if (isset($referenced['files/' . $name]) || time() - (int) @filemtime($path) < 3600) {
            continue;
        }
        rl_unlink_stored('files/' . $name);
        $removed++;
    }
    rl_locked(function () {
        $data = rl_panel_store();
        $pruned = rl_panel_prune($data, time());
        if (count($pruned['grants']) === count($data['grants']) && count($pruned['sessions']) === count($data['sessions'])) {
            return true;
        }
        return rl_panel_save($pruned);
    });
    return $removed;
}

function rl_users()
{
    $data = rl_load_store(rl_users_path(), array('users' => array()));
    if (!isset($data['users']) || !is_array($data['users'])) {
        $data['users'] = array();
    }
    return $data;
}

function rl_save_users($data)
{
    return rl_write_json_file(rl_users_path(), $data);
}

function rl_stars()
{
    $data = rl_load_store(rl_stars_path(), array('stars' => array()));
    if (!isset($data['stars']) || !is_array($data['stars'])) {
        $data['stars'] = array();
    }
    return $data;
}

function rl_save_stars($data)
{
    return rl_write_json_file(rl_stars_path(), $data);
}

function rl_current_user()
{
    if (!rl_session_resume()) {
        return null;
    }
    $id = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
    if ($id === '') {
        return null;
    }
    foreach (rl_users()['users'] as $user) {
        if (isset($user['id']) && $user['id'] === $id) {
            return $user;
        }
    }
    return null;
}

function rl_login_google_profile($profile)
{
    $sub = isset($profile['sub']) ? (string) $profile['sub'] : '';
    if ($sub === '') {
        return null;
    }
    return rl_locked(function () use ($profile, $sub) {
        $data = rl_users();
        $email = isset($profile['email']) ? rl_clean_text($profile['email'], 120) : '';
        $name = isset($profile['name']) ? rl_clean_text($profile['name'], 80) : '';
        if ($name === '') {
            $name = $email !== '' ? $email : 'Member';
        }
        $picture = isset($profile['picture']) ? (string) $profile['picture'] : '';
        if (strpos($picture, 'https://') !== 0) {
            $picture = '';
        }
        foreach ($data['users'] as $index => $user) {
            if (isset($user['google_sub']) && $user['google_sub'] === $sub) {
                $user['email'] = $email;
                $user['name'] = $name;
                $user['picture'] = $picture;
                $data['users'][$index] = $user;
                if (!rl_save_users($data)) {
                    return null;
                }
                return $user;
            }
        }
        $user = array(
            'id' => rl_new_id(),
            'google_sub' => $sub,
            'email' => $email,
            'name' => $name,
            'picture' => $picture,
            'created' => gmdate('c'),
        );
        $data['users'][] = $user;
        if (!rl_save_users($data)) {
            return null;
        }
        return $user;
    });
}

function rl_user_map()
{
    $map = array();
    foreach (rl_users()['users'] as $user) {
        if (isset($user['id'])) {
            $map[$user['id']] = $user;
        }
    }
    return $map;
}

function rl_star_counts()
{
    $counts = array();
    foreach (rl_stars()['stars'] as $star) {
        if (!isset($star['item_id'])) {
            continue;
        }
        $id = $star['item_id'];
        if (!isset($counts[$id])) {
            $counts[$id] = 0;
        }
        $counts[$id] += 1;
    }
    return $counts;
}

function rl_star_id_map($userId)
{
    $map = array();
    foreach (rl_stars()['stars'] as $star) {
        if (isset($star['user_id'], $star['item_id']) && $star['user_id'] === $userId) {
            $map[$star['item_id']] = true;
        }
    }
    return $map;
}

function rl_toggle_star($userId, $itemId)
{
    return rl_locked(function () use ($userId, $itemId) {
        $data = rl_stars();
        $kept = array();
        $removed = false;
        foreach ($data['stars'] as $star) {
            if (isset($star['user_id'], $star['item_id']) && $star['user_id'] === $userId && $star['item_id'] === $itemId) {
                $removed = true;
                continue;
            }
            $kept[] = $star;
        }
        if (!$removed) {
            $kept[] = array(
                'user_id' => $userId,
                'item_id' => $itemId,
                'created' => gmdate('c'),
            );
        }
        $data['stars'] = $kept;
        if (!rl_save_stars($data)) {
            return null;
        }
        return !$removed;
    });
}

function rl_parse_colors($raw)
{
    $parts = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw);
    $colors = array();
    if (!is_array($parts)) {
        return $colors;
    }
    foreach ($parts as $part) {
        $part = strtoupper(trim((string) $part));
        if ($part === '') {
            continue;
        }
        if ($part[0] !== '#') {
            $part = '#' . $part;
        }
        if (preg_match('/^#[0-9A-F]{3}$/', $part)) {
            $part = '#' . $part[1] . $part[1] . $part[2] . $part[2] . $part[3] . $part[3];
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $part)) {
            continue;
        }
        $colors[] = $part;
        if (count($colors) >= 8) {
            break;
        }
    }
    return $colors;
}

function rl_type_rules()
{
    return array(
        'script' => array('ext' => 'jsx', 'max' => 2 * 1024 * 1024),
        'style' => array('ext' => 'ai', 'max' => 40 * 1024 * 1024),
    );
}

// Upload limits; config.json can override them with max_uploads_per_day and
// max_storage_mb.
function rl_upload_limits()
{
    $config = rl_config_raw();
    $perDay = isset($config['max_uploads_per_day']) ? (int) $config['max_uploads_per_day'] : 20;
    $storageMb = isset($config['max_storage_mb']) ? (int) $config['max_storage_mb'] : 2048;
    return array(
        'per_day' => $perDay > 0 ? $perDay : 20,
        'storage' => ($storageMb > 0 ? $storageMb : 2048) * 1024 * 1024,
    );
}

function rl_storage_used()
{
    $total = 0;
    foreach (rl_catalog()['items'] as $item) {
        $total += isset($item['bytes']) ? (int) $item['bytes'] : 0;
        $total += isset($item['preview_bytes']) ? (int) $item['preview_bytes'] : 0;
    }
    return $total;
}

function rl_uploads_today($userId, $now = null)
{
    if ($userId === '') {
        return 0;
    }
    if ($now === null) {
        $now = time();
    }
    $count = 0;
    foreach (rl_catalog()['items'] as $item) {
        if (!isset($item['owner_id']) || $item['owner_id'] !== $userId || empty($item['created'])) {
            continue;
        }
        $created = strtotime($item['created']);
        if ($created !== false && $now - $created < 86400) {
            $count++;
        }
    }
    return $count;
}

// The first bytes of an Illustrator file: PDF-compatible (%PDF) or legacy
// PostScript (%!PS-Adobe).
function rl_looks_like_ai($path)
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $head = fread($handle, 16);
    fclose($handle);
    return is_string($head) && (strpos($head, '%PDF') === 0 || strpos($head, '%!PS-Adobe') === 0);
}

// Scripts are text: a NUL byte means a binary file was renamed to .jsx.
function rl_looks_like_text($path)
{
    $raw = @file_get_contents($path);
    return is_string($raw) && $raw !== '' && strpos($raw, "\0") === false;
}

function rl_life_seconds()
{
    return 10 * 24 * 60 * 60;
}

function rl_expiry_time($item)
{
    if (isset($item['expires']) && is_string($item['expires']) && $item['expires'] !== '') {
        $time = strtotime($item['expires']);
        if ($time !== false) {
            return $time;
        }
    }
    if (isset($item['created']) && is_string($item['created']) && $item['created'] !== '') {
        $time = strtotime($item['created']);
        if ($time !== false) {
            return $time + rl_life_seconds();
        }
    }
    return 0;
}

function rl_days_left($item, $now = null)
{
    if ($now === null) {
        $now = time();
    }
    $left = rl_expiry_time($item) - $now;
    if ($left <= 0) {
        return 0;
    }
    return (int) ceil($left / 86400);
}

function rl_fresh_expiry($now = null)
{
    if ($now === null) {
        $now = time();
    }
    return gmdate('c', $now + rl_life_seconds());
}

function rl_has_expired($now)
{
    foreach (rl_catalog()['items'] as $item) {
        if (rl_expiry_time($item) <= $now) {
            return true;
        }
    }
    return false;
}

function rl_drop_expired()
{
    // Reads are the common case: only take the write lock when something
    // has actually expired.
    if (!rl_has_expired(time())) {
        return;
    }
    $removed = rl_locked(function () {
        $now = time();
        $catalog = rl_catalog();
        $kept = array();
        $gone = array();
        foreach ($catalog['items'] as $item) {
            if (rl_expiry_time($item) > $now) {
                $kept[] = $item;
                continue;
            }
            $gone[] = $item;
        }
        if (count($gone) === 0) {
            return array();
        }
        $catalog['items'] = $kept;
        if (!rl_save_catalog($catalog)) {
            return null;
        }
        $ids = array();
        foreach ($gone as $item) {
            if (isset($item['id'])) {
                $ids[$item['id']] = true;
            }
        }
        $stars = rl_stars();
        $keptStars = array();
        foreach ($stars['stars'] as $star) {
            if (!isset($star['item_id']) || empty($ids[$star['item_id']])) {
                $keptStars[] = $star;
            }
        }
        $stars['stars'] = $keptStars;
        rl_save_stars($stars);
        return $gone;
    });
    if (!is_array($removed)) {
        return;
    }
    foreach ($removed as $item) {
        if (!empty($item['stored'])) {
            rl_unlink_stored($item['stored']);
        }
        if (!empty($item['preview'])) {
            rl_unlink_stored($item['preview']);
        }
    }
}

// A download keeps the item for another 10 days and counts once per visitor
// per day, so reloading a link cannot inflate the count.
function rl_renew_item($id, $visitor = '')
{
    return rl_locked(function () use ($id, $visitor) {
        $now = time();
        $catalog = rl_catalog();
        foreach ($catalog['items'] as $index => $item) {
            if (!isset($item['id']) || $item['id'] !== $id) {
                continue;
            }
            if (rl_expiry_time($item) <= $now) {
                return false;
            }
            $today = gmdate('Y-m-d', $now);
            $seen = isset($item['seen'], $item['seen_day']) && $item['seen_day'] === $today && is_array($item['seen']) ? $item['seen'] : array();
            $repeat = $visitor !== '' && in_array($visitor, $seen, true);
            if ($repeat && rl_expiry_time($item) - $now > rl_life_seconds() - 86400) {
                return $item;
            }
            $item['expires'] = rl_fresh_expiry($now);
            if (!$repeat) {
                $item['downloads'] = (isset($item['downloads']) ? (int) $item['downloads'] : 0) + 1;
                if ($visitor !== '' && count($seen) < 2000) {
                    $seen[] = $visitor;
                }
            }
            $item['seen_day'] = $today;
            $item['seen'] = $seen;
            $catalog['items'][$index] = $item;
            if (!rl_save_catalog($catalog)) {
                return false;
            }
            return $item;
        }
        return false;
    });
}

function rl_present_catalog($type, $userId, $starredOnly)
{
    rl_drop_expired();
    $wanted = rl_normalize_type($type);
    $starIds = $userId !== '' ? rl_star_id_map($userId) : array();
    $counts = rl_star_counts();
    $users = rl_user_map();
    $items = array();
    foreach (rl_catalog()['items'] as $item) {
        if (!isset($item['id'], $item['type'], $item['name'])) {
            continue;
        }
        $norm = rl_normalize_type($item['type']);
        if ($norm === '') {
            continue;
        }
        if ($wanted !== '' && $norm !== $wanted) {
            continue;
        }
        if ($starredOnly && empty($starIds[$item['id']])) {
            continue;
        }
        if (rl_expiry_time($item) <= time()) {
            continue;
        }
        $ownerName = '';
        if (!empty($item['owner_id']) && isset($users[$item['owner_id']]['name'])) {
            $ownerName = $users[$item['owner_id']]['name'];
        }
        $items[] = rl_public_item(
            $item,
            $userId,
            !empty($starIds[$item['id']]),
            isset($counts[$item['id']]) ? $counts[$item['id']] : 0,
            $ownerName
        );
    }
    usort($items, function ($a, $b) {
        return strcmp($b['created'], $a['created']);
    });
    return $items;
}

function rl_store_preview($id, $file)
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        return false;
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($info['mime'])) {
        return false;
    }
    if ($info['mime'] === 'image/png') {
        $ext = 'png';
    } elseif ($info['mime'] === 'image/jpeg') {
        $ext = 'jpg';
    } else {
        return false;
    }
    $stored = 'files/' . $id . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rl_storage_dir() . '/' . $stored)) {
        return false;
    }
    return $stored;
}

function rl_unlink_stored($stored)
{
    if (!is_string($stored) || strpos($stored, 'files/') !== 0) {
        return;
    }
    $path = rl_storage_dir() . '/' . $stored;
    $realStorage = realpath(rl_files_dir());
    $realFile = realpath($path);
    if ($realStorage !== false && $realFile !== false && strpos($realFile, $realStorage . DIRECTORY_SEPARATOR) === 0 && is_file($realFile)) {
        unlink($realFile);
    }
}

function rl_create_item($user)
{
    $type = rl_normalize_type(isset($_POST['type']) ? (string) $_POST['type'] : '');
    if ($type === '') {
        return array('ok' => false, 'error' => 'Type must be script, color, or style');
    }
    $name = rl_clean_text(isset($_POST['name']) ? $_POST['name'] : '', 80);
    $description = rl_clean_text(isset($_POST['description']) ? $_POST['description'] : '', 500);
    if ($name === '') {
        return array('ok' => false, 'error' => 'Name is required');
    }
    if (!rl_ensure_storage()) {
        return array('ok' => false, 'error' => 'Storage is not writable');
    }
    $limits = rl_upload_limits();
    if ($user && rl_uploads_today($user['id']) >= $limits['per_day']) {
        return array('ok' => false, 'error' => 'Upload limit reached: ' . $limits['per_day'] . ' items a day', 'status' => 429);
    }
    $incoming = 0;
    foreach (array('file', 'preview') as $field) {
        if (isset($_FILES[$field]['size'])) {
            $incoming += (int) $_FILES[$field]['size'];
        }
    }
    if (rl_storage_used() + $incoming > $limits['storage']) {
        return array('ok' => false, 'error' => 'Marketplace storage is full', 'status' => 507);
    }

    $id = rl_new_id();
    $stored = '';
    $preview = '';
    $filename = '';
    $bytes = 0;
    $colors = array();
    $previewBytes = 0;

    if ($type === 'color') {
        $colors = rl_parse_colors(isset($_POST['colors']) ? $_POST['colors'] : '');
        if (count($colors) < 1) {
            return array('ok' => false, 'error' => 'At least one hex color is required');
        }
    } else {
        $rules = rl_type_rules();
        $rule = $rules[$type];
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return array('ok' => false, 'error' => 'File is required');
        }
        $file = $_FILES['file'];
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'error' => 'Upload failed');
        }
        $bytes = isset($file['size']) ? (int) $file['size'] : 0;
        if ($bytes < 1 || $bytes > $rule['max']) {
            return array('ok' => false, 'error' => 'File is empty or too large');
        }
        $original = isset($file['name']) ? (string) $file['name'] : '';
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension !== $rule['ext']) {
            return array('ok' => false, 'error' => 'Expected a .' . $rule['ext'] . ' file');
        }
        if ($type === 'style' && !rl_looks_like_ai($file['tmp_name'])) {
            return array('ok' => false, 'error' => 'This is not an Illustrator file');
        }
        if ($type === 'script' && !rl_looks_like_text($file['tmp_name'])) {
            return array('ok' => false, 'error' => 'A script must be a text file');
        }
        $stored = 'files/' . $id . '.' . $rule['ext'];
        if (!move_uploaded_file($file['tmp_name'], rl_storage_dir() . '/' . $stored)) {
            return array('ok' => false, 'error' => 'Could not store the file');
        }
        $filename = rl_download_name($name, $rule['ext']);
        if ($type === 'style' && isset($_FILES['preview']) && is_array($_FILES['preview'])) {
            $preview = rl_store_preview($id, $_FILES['preview']);
            if ($preview === false) {
                rl_unlink_stored($stored);
                return array('ok' => false, 'error' => 'Preview must be a PNG or JPEG under 2 MB');
            }
            if ($preview !== '') {
                $previewBytes = (int) $_FILES['preview']['size'];
            }
        }
    }

    $item = array(
        'id' => $id,
        'type' => $type,
        'name' => $name,
        'description' => $description,
        'filename' => $filename,
        'stored' => $stored,
        'preview' => $preview,
        'colors' => $colors,
        'bytes' => $bytes,
        'preview_bytes' => $previewBytes,
        'owner_id' => $user ? $user['id'] : '',
        'created' => gmdate('c'),
        'expires' => rl_fresh_expiry(),
        'downloads' => 0,
    );

    $saved = rl_locked(function () use ($item) {
        $catalog = rl_catalog();
        $catalog['items'][] = $item;
        return rl_save_catalog($catalog);
    });
    if (!$saved) {
        rl_unlink_stored($stored);
        rl_unlink_stored($preview);
        return array('ok' => false, 'error' => 'Could not update the catalog');
    }
    return array('ok' => true, 'item' => $item);
}

function rl_delete_item($id, $user, $admin)
{
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        return array('ok' => false, 'error' => 'Missing item id', 'status' => 400);
    }
    $result = rl_locked(function () use ($id, $user, $admin) {
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
            return array('ok' => false, 'error' => 'Item not found', 'status' => 404);
        }
        $ownerId = isset($removed['owner_id']) ? (string) $removed['owner_id'] : '';
        $owns = $user && $ownerId !== '' && $ownerId === $user['id'];
        if (!$admin && !$owns) {
            return array('ok' => false, 'error' => 'You can only remove your own items', 'status' => 403);
        }
        $catalog['items'] = $kept;
        if (!rl_save_catalog($catalog)) {
            return array('ok' => false, 'error' => 'Could not update the catalog', 'status' => 500);
        }
        return array('ok' => true, 'removed' => $removed);
    });
    if ($result === null) {
        return array('ok' => false, 'error' => 'Storage is not writable', 'status' => 500);
    }
    if (empty($result['ok'])) {
        return $result;
    }
    $removed = $result['removed'];
    if (!empty($removed['stored'])) {
        rl_unlink_stored($removed['stored']);
    }
    if (!empty($removed['preview'])) {
        rl_unlink_stored($removed['preview']);
    }
    $stars = rl_locked(function () use ($id) {
        $data = rl_stars();
        $kept = array();
        foreach ($data['stars'] as $star) {
            if (!isset($star['item_id']) || $star['item_id'] !== $id) {
                $kept[] = $star;
            }
        }
        $data['stars'] = $kept;
        return rl_save_stars($data);
    });
    if ($stars === null || $stars === false) {
        return array('ok' => true, 'warning' => 'Item removed, stars may remain');
    }
    return array('ok' => true);
}

function rl_http_request($url, $method, $body, $headers)
{
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'body' => '');
    }
    $ch = curl_init($url);
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    );
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) {
        return array('status' => 0, 'body' => '');
    }
    return array('status' => $status, 'body' => $response);
}

function rl_panel_path()
{
    return rl_storage_dir() . '/panel.json';
}

function rl_panel_store()
{
    $data = rl_load_store(rl_panel_path(), array('grants' => array(), 'sessions' => array()));
    if (!isset($data['grants']) || !is_array($data['grants'])) {
        $data['grants'] = array();
    }
    if (!isset($data['sessions']) || !is_array($data['sessions'])) {
        $data['sessions'] = array();
    }
    return $data;
}

function rl_panel_save($data)
{
    return rl_write_json_file(rl_panel_path(), $data);
}

function rl_panel_valid_code($code)
{
    return is_string($code) && (bool) preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
}

function rl_panel_code()
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = strlen($alphabet) - 1;
    $raw = '';
    for ($i = 0; $i < 8; $i++) {
        $raw .= $alphabet[random_int(0, $length)];
    }
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
}

function rl_panel_prune($data, $now)
{
    $grants = array();
    foreach ($data['grants'] as $grant) {
        if (isset($grant['expires']) && (int) $grant['expires'] > $now) {
            $grants[] = $grant;
        }
    }
    $sessions = array();
    foreach ($data['sessions'] as $session) {
        if (isset($session['expires']) && (int) $session['expires'] > $now) {
            $sessions[] = $session;
        }
    }
    // A hard cap on pending connections; starts are also rate limited per address.
    if (count($grants) > 400) {
        $grants = array_slice($grants, -400);
    }
    $data['grants'] = $grants;
    $data['sessions'] = $sessions;
    return $data;
}

function rl_panel_start()
{
    return rl_locked(function () {
        $now = time();
        $data = rl_panel_prune(rl_panel_store(), $now);
        $used = array();
        foreach ($data['grants'] as $existing) {
            if (isset($existing['user_code'])) {
                $used[$existing['user_code']] = true;
            }
        }
        $code = rl_panel_code();
        for ($i = 0; $i < 5 && isset($used[$code]); $i++) {
            $code = rl_panel_code();
        }
        $grant = array(
            'device' => bin2hex(random_bytes(16)),
            'user_code' => $code,
            'expires' => $now + 600,
            'user_id' => '',
            'name' => '',
            'token' => '',
        );
        $data['grants'][] = $grant;
        if (!rl_panel_save($data)) {
            return null;
        }
        return $grant;
    });
}

function rl_panel_grant($code)
{
    if (!rl_panel_valid_code($code)) {
        return null;
    }
    $now = time();
    foreach (rl_panel_store()['grants'] as $grant) {
        if (!isset($grant['user_code'], $grant['expires'])) {
            continue;
        }
        if ($grant['user_code'] === $code && (int) $grant['expires'] > $now) {
            return $grant;
        }
    }
    return null;
}

// A grant is approved once, by one account. Its token is handed to the panel
// exactly once (rl_panel_poll) and only its hash is kept as the session.
function rl_panel_approve($code, $user)
{
    if (!rl_panel_valid_code($code) || !is_array($user) || empty($user['id'])) {
        return false;
    }
    $approved = rl_locked(function () use ($code, $user) {
        $now = time();
        $data = rl_panel_prune(rl_panel_store(), $now);
        $found = false;
        foreach ($data['grants'] as $index => $grant) {
            if (!isset($grant['user_code']) || $grant['user_code'] !== $code) {
                continue;
            }
            $found = true;
            if (!empty($grant['user_id'])) {
                return $grant['user_id'] === $user['id'];
            }
            $token = bin2hex(random_bytes(24));
            $name = isset($user['name']) ? rl_clean_text($user['name'], 80) : 'Member';
            if ($name === '') {
                $name = 'Member';
            }
            $grant['user_id'] = $user['id'];
            $grant['name'] = $name;
            $grant['token'] = $token;
            $data['grants'][$index] = $grant;
            $data['sessions'][] = array(
                'hash' => hash('sha256', $token),
                'user_id' => $user['id'],
                'name' => $name,
                'created' => $now,
                'expires' => $now + 60 * 60 * 24 * 90,
            );
            break;
        }
        if (!$found || !rl_panel_save($data)) {
            return false;
        }
        return true;
    });
    return $approved === true;
}

function rl_panel_poll($device)
{
    if (!is_string($device) || !preg_match('/^[a-f0-9]{32}$/', $device)) {
        return array('status' => 'expired');
    }
    $now = time();
    $match = null;
    foreach (rl_panel_store()['grants'] as $grant) {
        if (!isset($grant['device']) || !is_string($grant['device']) || strlen($grant['device']) !== strlen($device)) {
            continue;
        }
        if (hash_equals($grant['device'], $device)) {
            $match = $grant;
            break;
        }
    }
    if ($match === null || !isset($match['expires']) || (int) $match['expires'] <= $now) {
        return array('status' => 'expired');
    }
    if (empty($match['user_id'])) {
        return array('status' => 'pending');
    }
    // Approved: hand the token over once and forget it.
    $claimed = rl_locked(function () use ($device) {
        $data = rl_panel_store();
        foreach ($data['grants'] as $index => $grant) {
            if (!isset($grant['device']) || !is_string($grant['device']) || !hash_equals($grant['device'], $device)) {
                continue;
            }
            if (empty($grant['token'])) {
                return null;
            }
            $token = (string) $grant['token'];
            $grant['token'] = '';
            $grant['claimed'] = time();
            $data['grants'][$index] = $grant;
            return rl_panel_save($data) ? array('token' => $token, 'grant' => $grant) : null;
        }
        return null;
    });
    if (!is_array($claimed)) {
        return array('status' => 'expired');
    }
    $picture = '';
    foreach (rl_users()['users'] as $user) {
        if (isset($user['id']) && $user['id'] === $claimed['grant']['user_id']) {
            $picture = rl_google_picture(isset($user['picture']) ? $user['picture'] : '');
            break;
        }
    }
    return array(
        'status' => 'approved',
        'token' => $claimed['token'],
        'name' => isset($claimed['grant']['name']) && $claimed['grant']['name'] !== '' ? (string) $claimed['grant']['name'] : 'Member',
        'picture' => $picture,
    );
}

// Signing out in the panel ends that panel's session on the server too.
function rl_panel_revoke($token)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/', $token)) {
        return false;
    }
    $hash = hash('sha256', $token);
    return rl_locked(function () use ($hash) {
        $data = rl_panel_store();
        $kept = array();
        foreach ($data['sessions'] as $session) {
            if (isset($session['hash']) && is_string($session['hash']) && hash_equals($session['hash'], $hash)) {
                continue;
            }
            $kept[] = $session;
        }
        if (count($kept) === count($data['sessions'])) {
            return true;
        }
        $data['sessions'] = $kept;
        return rl_panel_save($data);
    }) === true;
}

// The website's "disconnect every panel" button.
function rl_panel_revoke_user($userId)
{
    if (!is_string($userId) || $userId === '') {
        return false;
    }
    return rl_locked(function () use ($userId) {
        $data = rl_panel_store();
        $kept = array();
        foreach ($data['sessions'] as $session) {
            if (isset($session['user_id']) && $session['user_id'] === $userId) {
                continue;
            }
            $kept[] = $session;
        }
        $data['sessions'] = $kept;
        return rl_panel_save($data);
    }) === true;
}

function rl_panel_count($userId)
{
    $count = 0;
    $now = time();
    foreach (rl_panel_store()['sessions'] as $session) {
        if (isset($session['user_id'], $session['expires']) && $session['user_id'] === $userId && (int) $session['expires'] > $now) {
            $count++;
        }
    }
    return $count;
}

function rl_google_picture($value)
{
    if (!is_string($value) || strpos($value, 'https://') !== 0) {
        return '';
    }
    $host = parse_url($value, PHP_URL_HOST);
    if (!is_string($host)) {
        return '';
    }
    $host = strtolower($host);
    $suffix = '.googleusercontent.com';
    if ($host !== 'googleusercontent.com' && substr($host, -strlen($suffix)) !== $suffix) {
        return '';
    }
    return $value;
}

function rl_panel_user($token)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $now = time();
    $userId = '';
    foreach (rl_panel_store()['sessions'] as $session) {
        if (!isset($session['hash'], $session['expires'], $session['user_id'])) {
            continue;
        }
        if ((int) $session['expires'] <= $now || !is_string($session['hash']) || strlen($session['hash']) !== strlen($hash)) {
            continue;
        }
        if (!hash_equals($session['hash'], $hash)) {
            continue;
        }
        $userId = (string) $session['user_id'];
        break;
    }
    if ($userId === '') {
        return null;
    }
    foreach (rl_users()['users'] as $user) {
        if (isset($user['id']) && $user['id'] === $userId) {
            return $user;
        }
    }
    return null;
}
