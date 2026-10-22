<?php

define('RL_PAGE', 'root');
require __DIR__ . '/lib.php';

// The panel installer (.zxp) uploaded on the admin page.
$release = rl_config() !== null ? rl_release() : null;
if (!$release) {
    http_response_code(404);
    rl_page_headers();
    rl_layout_start('ยังไม่มีไฟล์ติดตั้ง · Rocket Launcher', array('active' => 'home', 'user' => null));
    echo '<section class="panel-card narrow"><p class="kicker">Download</p><h1>ยังไม่มีไฟล์ติดตั้ง</h1>';
    echo '<p class="lead">ผู้ดูแลยังไม่ได้อัปโหลดไฟล์ .zxp ลองใหม่ภายหลัง</p><p><a class="btn" href="index.php">กลับหน้าแรก</a></p></section>';
    rl_layout_end('');
    exit;
}

$file = rl_release_file($release);
rl_count_release_download(rl_visitor_key());
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($file));
header(rl_attachment_header(rl_release_name($release)));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
readfile($file);
exit;
