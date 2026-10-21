<?php

define('RL_PAGE', 'sub');
require dirname(__DIR__) . '/lib.php';

rl_session_start();
$next = isset($_SESSION['oauth_next']) ? rl_safe_next($_SESSION['oauth_next']) : 'index.php';
$state = isset($_GET['state']) ? (string) $_GET['state'] : '';
$saved = isset($_SESSION['oauth_state']) ? (string) $_SESSION['oauth_state'] : '';
unset($_SESSION['oauth_state']);

function rl_back($next, $message)
{
    rl_flash($message);
    header('Location: ../' . $next);
    exit;
}

if ($state === '' || $saved === '' || !hash_equals($saved, $state) || empty($_GET['code'])) {
    rl_back($next, 'เข้าสู่ระบบไม่สำเร็จ');
}

if (!function_exists('curl_init')) {
    rl_back($next, 'เซิร์ฟเวอร์ไม่มี curl จึงคุยกับ Google ไม่ได้');
}

$config = rl_config_raw();
$token = rl_http_request(
    'https://oauth2.googleapis.com/token',
    'POST',
    http_build_query(array(
        'code' => (string) $_GET['code'],
        'client_id' => $config['google_client_id'],
        'client_secret' => $config['google_client_secret'],
        'redirect_uri' => rl_google_redirect_uri(),
        'grant_type' => 'authorization_code',
    )),
    array('Content-Type: application/x-www-form-urlencoded')
);
$payload = json_decode($token['body'], true);
$access = is_array($payload) && isset($payload['access_token']) ? (string) $payload['access_token'] : '';
if ($token['status'] !== 200 || $access === '') {
    rl_back($next, 'Google ไม่ยืนยันตัวตน');
}

$info = rl_http_request(
    'https://openidconnect.googleapis.com/v1/userinfo',
    'GET',
    null,
    array('Authorization: Bearer ' . $access)
);
$profile = json_decode($info['body'], true);
if ($info['status'] !== 200 || !is_array($profile)) {
    rl_back($next, 'อ่านโปรไฟล์ Google ไม่ได้');
}

$user = rl_login_google_profile($profile);
if (!$user || empty($user['id'])) {
    rl_back($next, 'บันทึกสมาชิกไม่ได้');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
unset($_SESSION['oauth_next']);
rl_back($next, 'เข้าสู่ระบบแล้ว');
