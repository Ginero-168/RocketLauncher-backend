<?php

require dirname(__DIR__) . '/lib.php';

rl_session_start();
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', array(
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => isset($params['samesite']) ? $params['samesite'] : 'Lax',
    ));
}
session_destroy();
header('Location: ../index.php');
exit;
