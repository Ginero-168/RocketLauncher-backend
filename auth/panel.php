<?php

define('RL_PAGE', 'sub');
require dirname(__DIR__) . '/lib.php';

rl_session_start();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rl_panel_page($title, $body)
{
    $user = rl_current_user();
    rl_page_headers();
    rl_layout_start($title . ' · Rocket Launcher', array(
        'base' => '../',
        'active' => 'account',
        'user' => $user,
        'csrf' => $user ? rl_csrf_token() : '',
        'next' => 'index.php',
    ));
    echo '<section class="panel-card narrow"><p class="kicker">Panel</p><h1>' . h($title) . '</h1>';
    echo $body;
    echo '</section>';
    rl_layout_end('../');
    exit;
}

$code = strtoupper(trim((string) (isset($_POST['code']) ? $_POST['code'] : (isset($_GET['code']) ? $_GET['code'] : ''))));
if (!rl_panel_valid_code($code)) {
    rl_panel_page('เชื่อมต่อพาเนล', '<p class="lead">ลิงก์นี้ใช้ไม่ได้ กลับไปกดเชื่อมต่อในพาเนลอีกครั้ง</p>');
}

$user = rl_current_user();
if (!$user) {
    header('Location: login.php?next=' . rawurlencode('auth/panel.php?code=' . $code));
    exit;
}

$grant = rl_panel_grant($code);
$name = isset($user['name']) ? (string) $user['name'] : 'Member';
$switch = '<p class="card-meta"><a class="link" href="login.php?next=' . h(rawurlencode('auth/panel.php?code=' . $code)) . '">ใช้บัญชี Google อื่น</a></p>';
if (!$grant) {
    rl_panel_page('เชื่อมต่อพาเนล', '<p class="lead">รหัสนี้หมดอายุแล้ว กลับไปกดเชื่อมต่อในพาเนลอีกครั้ง</p>' . $switch);
}

if (!empty($grant['user_id']) && $grant['user_id'] !== $user['id']) {
    rl_panel_page('เชื่อมต่อพาเนล', '<p class="lead">รหัสนี้ถูกใช้ด้วยบัญชีอื่นแล้ว</p>' . $switch);
}

$done = !empty($grant['user_id']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) {
    if (!rl_csrf_ok()) {
        $error = 'หน้านี้หมดอายุแล้ว ลองอีกครั้ง';
    } elseif (!rl_panel_approve($code, $user)) {
        $error = 'เชื่อมต่อไม่สำเร็จ กลับไปกดเชื่อมต่อในพาเนลอีกครั้ง';
    } else {
        $done = true;
    }
}

$flash = rl_flash();
$note = $flash !== '' ? '<p class="note">' . h($flash) . '</p>' : '';
if ($done) {
    rl_panel_page(
        'เชื่อมต่อแล้ว',
        $note . '<p class="lead">พาเนลอัปโหลดและใช้ AI ในชื่อ ' . h($name) . ' ได้แล้ว กลับไปที่ Illustrator ได้เลย</p>'
    );
}

$form = $note;
if ($error !== '') {
    $form .= '<p class="note">' . h($error) . '</p>';
}
$form .= '<p class="lead">พาเนล Illustrator ขอสิทธิ์อัปโหลด Script ชุดสี Graphic Style และใช้ AI ในชื่อ ' . h($name) . '</p>';
$form .= '<p class="code" aria-label="รหัสเชื่อมต่อ">' . h($code) . '</p>';
// Device-code phishing: someone else can send a link with their own code.
$form .= '<p class="card-meta">กดอนุญาตเฉพาะเมื่อรหัสนี้ตรงกับที่พาเนลของคุณแสดงอยู่ ถ้าคุณไม่ได้กดเชื่อมต่อในพาเนลเองเมื่อสักครู่ ให้ปิดหน้านี้</p>';
$form .= '<form method="post" action="panel.php" class="form-stack"><input type="hidden" name="csrf" value="' . h(rl_csrf_token()) . '">';
$form .= '<input type="hidden" name="code" value="' . h($code) . '">';
$form .= '<button class="btn btn--primary btn--lg" type="submit">อนุญาต</button></form>';
$form .= $switch;
rl_panel_page('เชื่อมต่อพาเนล', $form);
