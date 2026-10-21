<?php

define('RL_PAGE', 'sub');
require dirname(__DIR__) . '/lib.php';

rl_session_start();
$next = rl_safe_next(isset($_GET['next']) ? $_GET['next'] : 'index.php');

if (!rl_google_ready()) {
    if (is_file(dirname(__DIR__) . '/setup.php')) {
        rl_flash('ยังไม่ได้ใส่ Google client ในหน้า setup');
        header('Location: ../setup.php');
        exit;
    }
    rl_page_headers();
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>Login</title><link rel="stylesheet" href="../assets/site.css"></head>';
    echo '<body><main class="wrap"><p class="kicker">Rocket Launcher</p><h1>ยังไม่ได้ตั้งค่า Google login</h1></main></body></html>';
    exit;
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['oauth_next'] = $next;
$config = rl_config_raw();
$params = array(
    'client_id' => $config['google_client_id'],
    'redirect_uri' => rl_google_redirect_uri(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['oauth_state'],
    'prompt' => 'select_account',
);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
