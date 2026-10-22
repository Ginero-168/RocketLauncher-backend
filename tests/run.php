<?php
/*
 * Marketplace tests: php tests/run.php
 *
 * Each scenario copies the site to a temporary folder, writes its own
 * config.json and storage, starts PHP's built-in server on it and talks to
 * it over HTTP the way the panel and browsers do. Nothing touches the real
 * storage. Needs the PHP CLI with curl.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = array();
$GLOBALS['servers'] = array();
$GLOBALS['dirs'] = array();

register_shutdown_function(function () {
    foreach ($GLOBALS['servers'] as $proc) {
        @proc_terminate($proc);
    }
    foreach ($GLOBALS['dirs'] as $dir) {
        remove_tree($dir);
    }
});

function check($name, $ok, $detail = '')
{
    if ($ok) {
        $GLOBALS['passed']++;
        echo "ok    $name\n";
        return;
    }
    $GLOBALS['failed'][] = $name;
    echo "FAIL  $name" . ($detail !== '' ? "\n      $detail" : '') . "\n";
}

function remove_tree($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function copy_tree($from, $to)
{
    @mkdir($to, 0777, true);
    foreach (scandir($from) as $name) {
        if ($name === '.' || $name === '..' || $name === '.git' || $name === 'tests') {
            continue;
        }
        $src = $from . '/' . $name;
        $dst = $to . '/' . $name;
        if (is_dir($src)) {
            copy_tree($src, $dst);
        } else {
            copy($src, $dst);
        }
    }
}

function write_json($path, $data)
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function read_json($path)
{
    return json_decode((string) @file_get_contents($path), true);
}

// A copy of the site with $config (null: no config.json) and a running server.
function site($config)
{
    $dir = sys_get_temp_dir() . '/rl-test-' . bin2hex(random_bytes(4));
    $GLOBALS['dirs'][] = $dir;
    copy_tree(dirname(__DIR__), $dir);
    @mkdir($dir . '/storage/files', 0777, true);
    @mkdir($dir . '/sessions', 0777, true);
    if ($config !== null) {
        write_json($dir . '/config.json', $config);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $cmd = array(PHP_BINARY, '-d', 'session.save_path=' . $dir . '/sessions', '-d', 'display_errors=0',
        '-S', '127.0.0.1:' . $port, '-t', $dir);
    $proc = proc_open($cmd, array(array('pipe', 'r'), array('file', $dir . '/server.log', 'a'), array('file', $dir . '/server.log', 'a')), $pipes);
    $GLOBALS['servers'][] = $proc;
    for ($i = 0; $i < 100; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($probe) {
            fclose($probe);
            break;
        }
        usleep(50000);
    }
    return array('dir' => $dir, 'base' => 'http://127.0.0.1:' . $port);
}

function http($method, $url, $opts = array())
{
    $ch = curl_init($url);
    $headers = isset($opts['headers']) ? $opts['headers'] : array();
    if (isset($opts['cookie'])) {
        $headers[] = 'Cookie: ' . $opts['cookie'];
    }
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ));
    if (isset($opts['fields']) || isset($opts['files'])) {
        $fields = isset($opts['fields']) ? $opts['fields'] : array();
        foreach (isset($opts['files']) ? $opts['files'] : array() as $field => $file) {
            $fields[$field] = new CURLFile($file[0], isset($file[2]) ? $file[2] : 'application/octet-stream', $file[1]);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr((string) $raw, 0, $headerSize);
    $parsed = array();
    foreach (preg_split('/\r?\n/', $head) as $line) {
        $colon = strpos($line, ':');
        if ($colon === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $colon)));
        $parsed[$name][] = trim(substr($line, $colon + 1));
    }
    $body = substr((string) $raw, $headerSize);
    return array('status' => $status, 'headers' => $parsed, 'body' => $body, 'json' => json_decode($body, true));
}

function header_of($res, $name)
{
    $name = strtolower($name);
    return isset($res['headers'][$name]) ? implode("\n", $res['headers'][$name]) : '';
}

// Run PHP inside the site (to approve a panel connection the way a signed-in
// browser would, without Google).
function site_php($site, $code)
{
    $script = $site['dir'] . '/rl-test-' . bin2hex(random_bytes(3)) . '.php';
    file_put_contents($script, "<?php\nrequire " . var_export($site['dir'] . '/lib.php', true) . ";\n" . $code);
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
    unlink($script);
    return trim((string) $out);
}

function panel_token($site, $userId, $name)
{
    return site_php($site, '$g = rl_panel_start(); rl_panel_approve($g["user_code"], array("id" => ' . var_export($userId, true) . ', "name" => ' . var_export($name, true) . ')); $p = rl_panel_poll($g["device"]); echo $p["token"];');
}

// A signed-in browser: a PHP session file holding the user id and a CSRF token.
function signed_in($site, $userId)
{
    $id = bin2hex(random_bytes(16));
    $csrf = bin2hex(random_bytes(16));
    file_put_contents($site['dir'] . '/sessions/sess_' . $id, 'user_id|' . serialize($userId) . 'csrf|' . serialize($csrf));
    return array('cookie' => 'PHPSESSID=' . $id, 'csrf' => $csrf, 'file' => $site['dir'] . '/sessions/sess_' . $id);
}

function iso($offset)
{
    return gmdate('c', time() + $offset);
}

function file_with($dir, $name, $bytes)
{
    $path = $dir . '/' . $name;
    file_put_contents($path, $bytes);
    return $path;
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$ready = array(
    'upload_token' => 'admin-token-0123456789abcdef',
    'base_url' => 'http://127.0.0.1',
    'google_client_id' => 'client',
    'google_client_secret' => 'secret',
    'max_uploads_per_day' => 3,
    'max_storage_mb' => 1,
);

// ------------------------------------------------------------ main site
$site = site($ready);
$dir = $site['dir'];
$base = $site['base'];
file_put_contents($dir . '/storage/files/aaaaaaaaaaaaaaa1.jsx', "alert('hi');\n");
file_put_contents($dir . '/storage/files/aaaaaaaaaaaaaaa2.ai', '%PDF-1.5 style');
file_put_contents($dir . '/storage/files/aaaaaaaaaaaaaaa2.png', $png);
file_put_contents($dir . '/storage/files/aaaaaaaaaaaaaaa9.jsx', "old\n");
write_json($dir . '/storage/catalog.json', array('items' => array(
    array('id' => 'aaaaaaaaaaaaaaa1', 'type' => 'script', 'name' => 'สวัสดี Swap', 'filename' => 'สวัสดี Swap.jsx', 'stored' => 'files/aaaaaaaaaaaaaaa1.jsx', 'preview' => '', 'bytes' => 13, 'owner_id' => 'u1', 'created' => iso(-60), 'expires' => iso(5 * 86400), 'downloads' => 0),
    array('id' => 'aaaaaaaaaaaaaaa2', 'type' => 'style', 'name' => 'Gold', 'filename' => 'Gold.ai', 'stored' => 'files/aaaaaaaaaaaaaaa2.ai', 'preview' => 'files/aaaaaaaaaaaaaaa2.png', 'bytes' => 14, 'owner_id' => 'u2', 'created' => iso(-120), 'expires' => iso(5 * 86400), 'downloads' => 0),
    array('id' => 'aaaaaaaaaaaaaaa3', 'type' => 'color', 'name' => 'Sunset', 'colors' => array('#FF0000', '#00FF00'), 'owner_id' => 'u2', 'created' => iso(-180), 'expires' => iso(86400), 'downloads' => 0),
    array('id' => 'aaaaaaaaaaaaaaa9', 'type' => 'script', 'name' => 'Old', 'stored' => 'files/aaaaaaaaaaaaaaa9.jsx', 'owner_id' => 'u2', 'created' => iso(-20 * 86400), 'expires' => iso(-86400)),
)));
write_json($dir . '/storage/users.json', array('users' => array(
    array('id' => 'u1', 'name' => 'Mike', 'google_sub' => 's1', 'picture' => 'https://lh3.googleusercontent.com/a/x'),
    array('id' => 'u2', 'name' => 'Nok', 'google_sub' => 's2'),
)));

echo "Catalog\n";
$res = http('GET', $base . '/api/list.php');
$ids = array_map(function ($i) { return $i['id']; }, isset($res['json']['items']) ? $res['json']['items'] : array());
check('list answers with the live items, newest first', $res['status'] === 200 && $ids === array('aaaaaaaaaaaaaaa1', 'aaaaaaaaaaaaaaa2', 'aaaaaaaaaaaaaaa3'), implode(',', $ids));
check('expired items and their files are removed', !is_file($dir . '/storage/files/aaaaaaaaaaaaaaa9.jsx'));
check('a visitor without a session gets no cookie', header_of($res, 'set-cookie') === '');
check('JSON replies are nosniff and open to the panel (CORS)', header_of($res, 'x-content-type-options') === 'nosniff' && header_of($res, 'access-control-allow-origin') === '*');
$etag = header_of($res, 'etag');
check('the public list can be revalidated', $etag !== '' && stripos(header_of($res, 'cache-control'), 'no-cache') !== false);
$again = http('GET', $base . '/api/list.php', array('headers' => array('If-None-Match: ' . $etag)));
check('an unchanged list is an empty 304', $again['status'] === 304 && $again['body'] === '');
@unlink($dir . '/storage/write.lock');
http('GET', $base . '/api/list.php');
check('reading the catalog takes no write lock', !is_file($dir . '/storage/write.lock'));
$styles = http('GET', $base . '/api/list.php?type=style');
check('list filters by type', count($styles['json']['items']) === 1 && $styles['json']['items'][0]['id'] === 'aaaaaaaaaaaaaaa2');
check('stored paths and visitor hashes are never published', strpos($res['body'], 'files/') === false && strpos($res['body'], 'owner_id') === false);
check('list rejects other methods', http('DELETE', $base . '/api/list.php')['status'] === 405);

echo "Website\n";
$page = http('GET', $base . '/index.php');
$csp = header_of($page, 'content-security-policy');
check('the home page lists the items', $page['status'] === 200 && strpos($page['body'], 'Gold') !== false && strpos($page['body'], 'สวัสดี Swap') !== false);
check('pages cannot be framed and run no scripts', strpos($csp, "frame-ancestors 'none'") !== false && strpos($csp, "script-src 'none'") !== false && header_of($page, 'x-frame-options') === 'DENY');
check('anonymous page views create no session', header_of($page, 'set-cookie') === '');
check('cards show the days left and lazy previews', strpos($page['body'], 'เหลือ 5 วัน') !== false && strpos($page['body'], 'loading="lazy"') !== false);
check('visitors see no delete buttons', strpos($page['body'], 'value="delete"') === false);
$mike = signed_in($site, 'u1');
$page = http('GET', $base . '/index.php', array('cookie' => $mike['cookie']));
check('a member sees delete only on their own item', substr_count($page['body'], 'value="delete"') === 1 && strpos($page['body'], 'value="aaaaaaaaaaaaaaa1"') !== false);
check('a member sees how many panels are connected', strpos($page['body'], 'พาเนลที่เชื่อมกับบัญชีนี้ 0 เครื่อง') !== false);
$res = http('POST', $base . '/index.php', array('cookie' => $mike['cookie'], 'fields' => array('action' => 'delete', 'id' => 'aaaaaaaaaaaaaaa2', 'csrf' => $mike['csrf'], 'next' => 'index.php')));
check('a member cannot delete someone else\'s item', $res['status'] === 302 && read_json($dir . '/storage/catalog.json')['items'][1]['id'] === 'aaaaaaaaaaaaaaa2');
$res = http('POST', $base . '/index.php', array('cookie' => $mike['cookie'], 'fields' => array('action' => 'delete', 'id' => 'aaaaaaaaaaaaaaa1', 'csrf' => 'wrong', 'next' => 'index.php')));
check('a delete without the CSRF token does nothing', count(read_json($dir . '/storage/catalog.json')['items']) === 3);
$res = http('POST', $base . '/index.php', array('cookie' => $mike['cookie'], 'fields' => array('action' => 'delete', 'id' => 'aaaaaaaaaaaaaaa1', 'csrf' => $mike['csrf'], 'next' => 'index.php')));
$left = array_map(function ($i) { return $i['id']; }, read_json($dir . '/storage/catalog.json')['items']);
check('a member deletes their own item and its file', !in_array('aaaaaaaaaaaaaaa1', $left, true) && !is_file($dir . '/storage/files/aaaaaaaaaaaaaaa1.jsx'));
$res = http('GET', $base . '/auth/logout.php', array('cookie' => $mike['cookie']));
check('a link cannot sign anyone out', $res['status'] === 302 && is_file($mike['file']) && strpos((string) file_get_contents($mike['file']), 'user_id') !== false);
$res = http('POST', $base . '/auth/logout.php', array('cookie' => $mike['cookie'], 'fields' => array('csrf' => $mike['csrf'])));
clearstatcache();
check('the sign-out button signs out', $res['status'] === 302 && (!is_file($mike['file']) || strpos((string) file_get_contents($mike['file']), 'user_id') === false));
check('star needs a signed-in member', http('POST', $base . '/api/star.php', array('fields' => array('id' => 'aaaaaaaaaaaaaaa2')))['status'] === 401);
check('library needs a signed-in member', http('GET', $base . '/api/library.php')['status'] === 401);

echo "Previews and downloads\n";
$res = http('GET', $base . '/api/preview.php?id=aaaaaaaaaaaaaaa2');
check('a preview is served as an image', $res['status'] === 200 && header_of($res, 'content-type') === 'image/png' && $res['body'] === $png);
check('a preview is cached for good', strpos(header_of($res, 'cache-control'), 'immutable') !== false && header_of($res, 'etag') !== '');
$res = http('GET', $base . '/api/preview.php?id=aaaaaaaaaaaaaaa2', array('headers' => array('If-None-Match: ' . header_of($res, 'etag'))));
check('a preview revalidates to an empty 304', $res['status'] === 304 && $res['body'] === '');
check('preview ids are validated', http('GET', $base . '/api/preview.php?id=../../config')['status'] === 400 && http('GET', $base . '/api/preview.php?id=bbbbbbbbbbbbbbbb')['status'] === 404);
$res = http('GET', $base . '/api/download.php?id=aaaaaaaaaaaaaaa2');
check('a style downloads as its file', $res['status'] === 200 && $res['body'] === '%PDF-1.5 style');
check('download names keep Thai and other characters', strpos(header_of($res, 'content-disposition'), "filename*=UTF-8''Gold.ai") !== false);
http('GET', $base . '/api/download.php?id=aaaaaaaaaaaaaaa2');
$gold = read_json($dir . '/storage/catalog.json')['items'][0];
check('a download counts once per visitor per day', (int) $gold['downloads'] === 1, 'downloads=' . $gold['downloads']);
check('a download renews the item for 10 days', strtotime($gold['expires']) > time() + 9 * 86400);
$res = http('GET', $base . '/api/download.php?id=aaaaaaaaaaaaaaa3');
check('a color set downloads as JSON', $res['status'] === 200 && $res['json']['item']['colors'] === array('#FF0000', '#00FF00'));
check('download ids are validated', http('GET', $base . '/api/download.php?id=x')['status'] === 400 && http('GET', $base . '/api/download.php?id=bbbbbbbbbbbbbbbb')['status'] === 404);

echo "Panel connection\n";
$start = http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'start')));
$device = isset($start['json']['device_code']) ? $start['json']['device_code'] : '';
$userCode = isset($start['json']['user_code']) ? $start['json']['user_code'] : '';
check('a panel starts a connection', $start['status'] === 200 && preg_match('/^[a-f0-9]{32}$/', $device) && preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $userCode));
check('the panel waits until the member allows it', http('GET', $base . '/api/panel.php?device=' . $device)['json']['status'] === 'pending');
$nok = signed_in($site, 'u2');
$approve = http('GET', $base . '/auth/panel.php?code=' . $userCode, array('cookie' => $nok['cookie']));
check('the allow page shows the code to compare', $approve['status'] === 200 && strpos($approve['body'], '>' . $userCode . '<') !== false && strpos($approve['body'], 'ตรงกับที่พาเนลของคุณแสดง') !== false);
check('the allow page cannot be framed', header_of($approve, 'x-frame-options') === 'DENY' && strpos(header_of($approve, 'content-security-policy'), "frame-ancestors 'none'") !== false);
check('the allow page sends visitors to sign in first', http('GET', $base . '/auth/panel.php?code=' . $userCode)['status'] === 302);
$res = http('POST', $base . '/auth/panel.php', array('cookie' => $nok['cookie'], 'fields' => array('code' => $userCode, 'csrf' => $nok['csrf'])));
check('allowing connects the panel', $res['status'] === 200 && strpos($res['body'], 'เชื่อมต่อแล้ว') !== false);
$poll = http('GET', $base . '/api/panel.php?device=' . $device);
$token = isset($poll['json']['token']) ? $poll['json']['token'] : '';
check('the panel receives its token', $poll['json']['status'] === 'approved' && preg_match('/^[a-f0-9]{48}$/', $token) && $poll['json']['name'] === 'Nok');
check('the token is handed over only once', http('GET', $base . '/api/panel.php?device=' . $device)['json']['status'] === 'expired');
check('the server keeps only a hash of the token', strpos((string) file_get_contents($dir . '/storage/panel.json'), $token) === false);
$profile = http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'profile', 'panel_token' => $token)));
check('a connected panel reads its profile', $profile['status'] === 200 && $profile['json']['name'] === 'Nok');
$page = http('GET', $base . '/index.php', array('cookie' => $nok['cookie']));
check('the website counts the connected panel', strpos($page['body'], 'พาเนลที่เชื่อมกับบัญชีนี้ 1 เครื่อง') !== false);
http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'logout', 'panel_token' => $token)));
check('signing out in the panel ends its session', http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'profile', 'panel_token' => $token)))['status'] === 401);
$second = panel_token($site, 'u2', 'Nok');
http('POST', $base . '/index.php', array('cookie' => $nok['cookie'], 'fields' => array('action' => 'disconnect', 'csrf' => $nok['csrf'], 'next' => 'index.php')));
check('the website disconnects every panel', http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'profile', 'panel_token' => $second)))['status'] === 401);
$codes = array();
for ($i = 0; $i < 19; $i++) {
    $codes[] = http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'start')))['status'];
}
$limited = http('POST', $base . '/api/panel.php', array('fields' => array('action' => 'start')));
check('connection starts are limited to 20 per 10 minutes', array_unique($codes) === array(200) && $limited['status'] === 429, implode(',', $codes) . ' then ' . $limited['status']);
check('unknown device codes are expired', http('GET', $base . '/api/panel.php?device=zz')['json']['status'] === 'expired');

echo "Uploads\n";
$mikeToken = panel_token($site, 'u1', 'Mike');
$up = function ($fields, $files) use ($base, $mikeToken) {
    return http('POST', $base . '/api/upload.php', array('fields' => array_merge(array('panel_token' => $mikeToken), $fields), 'files' => $files));
};
$tmp = $dir . '/uploads';
@mkdir($tmp);
$res = $up(array('type' => 'style', 'name' => 'Fake'), array('file' => array(file_with($tmp, 'a.ai', 'not illustrator'), 'style.ai')));
check('a renamed non-Illustrator file is refused', $res['status'] === 400 && $res['json']['error'] === 'This is not an Illustrator file');
$res = $up(array('type' => 'script', 'name' => 'Binary'), array('file' => array(file_with($tmp, 'b.jsx', "MZ\0\0binary"), 'button.jsx')));
check('a binary file renamed to .jsx is refused', $res['status'] === 400);
$before = glob($dir . '/storage/files/*');
$res = $up(array('type' => 'style', 'name' => 'Bad preview'), array('file' => array(file_with($tmp, 'c.ai', '%PDF-1.4 x'), 'style.ai'), 'preview' => array(file_with($tmp, 'p.png', 'not an image'), 'preview.png')));
check('a bad preview is refused and nothing is left behind', $res['status'] === 400 && glob($dir . '/storage/files/*') === $before, implode(',', glob($dir . '/storage/files/*')));
check('a file needs the right extension', $up(array('type' => 'script', 'name' => 'Ext'), array('file' => array(file_with($tmp, 'd.txt', 'x'), 'd.txt')))['status'] === 400);
check('an item needs a name', $up(array('type' => 'color', 'name' => '', 'colors' => '#FFFFFF'), array())['status'] === 400);
check('an item needs a known type', $up(array('type' => 'virus', 'name' => 'x'), array())['status'] === 400);
$res = $up(array('type' => 'style', 'name' => 'Silver'), array('file' => array(file_with($tmp, 'e.ai', '%PDF-1.4 silver'), 'style.ai'), 'preview' => array(file_with($tmp, 'q.png', $png), 'preview.png', 'image/png')));
check('a member uploads a Graphic Style with its preview', $res['status'] === 201 && $res['json']['item']['preview'] === true && $res['json']['item']['owner_name'] === '');
$silver = $res['json']['item']['id'];
check('a member uploads a script', $up(array('type' => 'script', 'name' => 'Tidy'), array('file' => array(file_with($tmp, 'f.jsx', "app.redraw();\n"), 'button.jsx')))['status'] === 201);
check('a member uploads a color set', $up(array('type' => 'color', 'name' => 'Sea', 'colors' => '#0af, 123456, nope'), array())['status'] === 201);
$colors = null;
foreach (read_json($dir . '/storage/catalog.json')['items'] as $item) {
    if ($item['name'] === 'Sea') {
        $colors = $item['colors'];
    }
}
check('colors are cleaned to #RRGGBB', $colors === array('#00AAFF', '#123456'));
$res = $up(array('type' => 'color', 'name' => 'Fourth', 'colors' => '#000000'), array());
check('a member uploads at most the daily limit', $res['status'] === 429);
$big = file_with($tmp, 'big.ai', '%PDF' . str_repeat('x', 1100000));
$res = http('POST', $base . '/api/upload.php', array('fields' => array('type' => 'style', 'name' => 'Big', 'token' => 'admin-token-0123456789abcdef'), 'files' => array('file' => array($big, 'big.ai'))));
check('uploads stop when the storage limit is reached', $res['status'] === 507);
check('an ended panel session cannot upload', http('POST', $base . '/api/upload.php', array('fields' => array('type' => 'color', 'name' => 'x', 'colors' => '#000000', 'panel_token' => $token)))['status'] === 401);
check('a wrong admin token cannot upload', http('POST', $base . '/api/upload.php', array('fields' => array('type' => 'color', 'name' => 'x', 'colors' => '#000000', 'token' => 'nope')))['status'] === 401);

echo "Deleting\n";
$nokToken = panel_token($site, 'u2', 'Nok');
$res = http('POST', $base . '/api/delete.php', array('fields' => array('id' => $silver, 'panel_token' => $nokToken)));
check('a member cannot delete another member\'s item from a panel', $res['status'] === 403);
$res = http('POST', $base . '/api/delete.php', array('fields' => array('id' => $silver, 'panel_token' => $mikeToken)));
check('a member deletes their own item from a panel', $res['status'] === 200 && http('GET', $base . '/api/preview.php?id=' . $silver)['status'] === 404);
check('a wrong admin token cannot delete', http('POST', $base . '/api/delete.php', array('fields' => array('id' => 'aaaaaaaaaaaaaaa3', 'token' => 'nope')))['status'] === 401);
check('the admin token deletes any item', http('POST', $base . '/api/delete.php', array('fields' => array('id' => 'aaaaaaaaaaaaaaa3', 'token' => 'admin-token-0123456789abcdef')))['status'] === 200);
check('delete needs POST', http('GET', $base . '/api/delete.php?id=aaaaaaaaaaaaaaa2')['status'] === 405);

echo "Setup\n";
$res = http('GET', $base . '/setup.php');
check('setup closes itself once Google login is set', $res['status'] === 403 && header_of($res, 'x-frame-options') === 'DENY');
$res = http('POST', $base . '/setup.php', array('fields' => array('upload_token' => 'admin-token-0123456789abcdef', 'google_client_id' => 'evil', 'base_url' => 'https://evil.example', 'google_client_secret' => 'x')));
check('a closed setup cannot change the config', $res['status'] === 403 && read_json($dir . '/config.json')['google_client_id'] === 'client');

$fresh = site(null);
$res = http('GET', $fresh['base'] . '/setup.php');
check('a new site shows the setup form', $res['status'] === 200 && strpos($res['body'], 'สร้าง config.json') !== false);
http('POST', $fresh['base'] . '/setup.php', array('fields' => array('upload_token' => '')));
$created = read_json($fresh['dir'] . '/config.json');
check('setup creates config.json with a random admin token', isset($created['upload_token']) && preg_match('/^[a-f0-9]{48}$/', $created['upload_token']));
http('POST', $fresh['base'] . '/setup.php', array('fields' => array('upload_token' => 'another-token-0123456789')));
check('setup never replaces an existing admin token', read_json($fresh['dir'] . '/config.json')['upload_token'] === $created['upload_token']);
http('POST', $fresh['base'] . '/setup.php', array('fields' => array('upload_token' => $created['upload_token'], 'google_client_id' => 'cid', 'base_url' => 'https://rl.example', 'google_client_secret' => 'sec')));
check('setup saves Google login with the admin token', read_json($fresh['dir'] . '/config.json')['google_client_id'] === 'cid');
check('config.json is never copied to a backup', !is_file($fresh['dir'] . '/config.json.bak'));

$damaged = site(null);
file_put_contents($damaged['dir'] . '/config.json', '{"upload_token": "half-writ');
$res = http('POST', $damaged['base'] . '/setup.php', array('fields' => array('upload_token' => '')));
check('a damaged config.json is never recreated by setup', $res['status'] === 503 && file_get_contents($damaged['dir'] . '/config.json') === '{"upload_token": "half-writ');
check('a damaged config.json keeps the API closed', http('GET', $damaged['base'] . '/api/list.php')['status'] === 503);

echo "Damaged storage\n";
$backup = site($ready);
file_put_contents($backup['dir'] . '/storage/catalog.json', '{"items": [ {"id": "trunc');
write_json($backup['dir'] . '/storage/catalog.json.bak', array('items' => array(
    array('id' => 'bbbbbbbbbbbbbbb1', 'type' => 'color', 'name' => 'Saved', 'colors' => array('#111111'), 'created' => iso(-60), 'expires' => iso(86400)),
)));
$res = http('GET', $backup['base'] . '/api/list.php');
check('a damaged catalog is read from its backup', $res['status'] === 200 && count($res['json']['items']) === 1 && $res['json']['items'][0]['name'] === 'Saved');
http('POST', $backup['base'] . '/api/upload.php', array('fields' => array('type' => 'color', 'name' => 'New', 'colors' => '#222222', 'token' => 'admin-token-0123456789abcdef')));
$repaired = read_json($backup['dir'] . '/storage/catalog.json');
check('the next write repairs the catalog without losing items', is_array($repaired) && count($repaired['items']) === 2);

$broken = site($ready);
file_put_contents($broken['dir'] . '/storage/catalog.json', 'garbage');
check('a damaged catalog without a backup stops the API', http('GET', $broken['base'] . '/api/list.php')['status'] === 503);
$res = http('POST', $broken['base'] . '/api/upload.php', array('fields' => array('type' => 'color', 'name' => 'New', 'colors' => '#222222', 'token' => 'admin-token-0123456789abcdef')));
check('uploads never overwrite a damaged catalog', $res['status'] === 503 && file_get_contents($broken['dir'] . '/storage/catalog.json') === 'garbage');
$res = http('GET', $broken['base'] . '/index.php');
check('the website shows a maintenance page instead', $res['status'] === 503 && strpos($res['body'], 'ปิดปรับปรุงชั่วคราว') !== false);

echo "Housekeeping\n";
$house = site($ready);
file_put_contents($house['dir'] . '/storage/files/cccccccccccccc01.jsx', 'kept');
file_put_contents($house['dir'] . '/storage/files/cccccccccccccc02.jsx', 'stray');
file_put_contents($house['dir'] . '/storage/files/cccccccccccccc03.jsx', 'uploading');
touch($house['dir'] . '/storage/files/cccccccccccccc01.jsx', time() - 7200);
touch($house['dir'] . '/storage/files/cccccccccccccc02.jsx', time() - 7200);
write_json($house['dir'] . '/storage/catalog.json', array('items' => array(
    array('id' => 'cccccccccccccc01', 'type' => 'script', 'name' => 'Kept', 'stored' => 'files/cccccccccccccc01.jsx', 'created' => iso(-60), 'expires' => iso(86400)),
)));
http('GET', $house['base'] . '/api/list.php');
check('stray files older than an hour are deleted', !is_file($house['dir'] . '/storage/files/cccccccccccccc02.jsx'));
check('files of live items and uploads in progress are kept', is_file($house['dir'] . '/storage/files/cccccccccccccc01.jsx') && is_file($house['dir'] . '/storage/files/cccccccccccccc03.jsx'));

echo "Web server rules\n";
$root = file_get_contents(dirname(__DIR__) . '/.htaccess');
$storage = file_get_contents(dirname(__DIR__) . '/storage/.htaccess');
check('config, backups and helpers are denied', strpos($root, '<Files "config.json">') !== false && strpos($root, '\.(bak|tmp)$') !== false && strpos($root, 'lib\.php') !== false);
check('dot folders and the tests are denied', strpos($root, '"(^|/)\.(?!well-known/)"') !== false && strpos($root, '"^tests(/|$)"') !== false && trim(file_get_contents(__DIR__ . '/.htaccess')) === 'Require all denied');
check('storage never serves data files or scripts', strpos($storage, 'php|phtml|phar|json|lock|bak|tmp') !== false);

$total = $GLOBALS['passed'] + count($GLOBALS['failed']);
echo "\n" . $GLOBALS['passed'] . " of $total checks passed\n";
exit(count($GLOBALS['failed']) ? 1 : 0);
