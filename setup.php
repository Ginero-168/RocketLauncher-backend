<?php

define('RL_PAGE', 'root');
require __DIR__ . '/lib.php';

rl_page_headers();

function rl_setup_notice($status, $title, $message)
{
    http_response_code($status);
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Setup Marketplace</title><link rel="stylesheet" href="assets/site.css"><link rel="icon" href="assets/icon.svg" type="image/svg+xml"></head><body><main class="wrap">';
    echo '<p class="kicker">Rocket Launcher</p><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p class="lead">' . $message . '</p><p><a href="index.php">ไปหน้ารายการ</a></p></main></body></html>';
    exit;
}

// A damaged config.json is never recreated here: that would hand a new
// admin token to whoever opened this page first.
if (rl_config_damaged()) {
    rl_setup_notice(503, 'config.json อ่านไม่ได้', 'แก้หรือกู้ <code>config.json</code> ใน File Manager ก่อน หน้านี้จะไม่เขียนทับไฟล์เดิม');
}

// Once Google login is configured this page does nothing: it cannot be used
// to guess the admin token or to point logins somewhere else. Change
// config.json in the hosting file manager instead, and delete this file.
if (rl_config() !== null && rl_google_ready()) {
    rl_setup_notice(403, 'ตั้งค่าเสร็จแล้ว', 'หน้านี้ปิดแล้ว ลบ <code>setup.php</code> ออกจากเซิร์ฟเวอร์ได้เลย ถ้าต้องเปลี่ยนค่าให้แก้ <code>config.json</code> ใน File Manager');
}

$exists = rl_config() !== null;
$message = '';
$token = '';
$rawConfig = rl_config_raw();
$savedClient = isset($rawConfig['google_client_id']) ? (string) $rawConfig['google_client_id'] : '';

// The first run only ever creates a config.json that does not exist yet.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$exists && !is_file(rl_config_path()) && !isset($_POST['google_client_id'])) {
    $token = rl_clean_text(isset($_POST['upload_token']) ? $_POST['upload_token'] : '', 120);
    if (strlen($token) < 16) {
        $token = bin2hex(random_bytes(24));
    }
    if (!rl_ensure_storage() || !rl_write_json_file(rl_config_path(), array('upload_token' => $token), false)) {
        $message = 'เขียน config.json ไม่ได้ ตรวจสิทธิ์โฟลเดอร์บน Hostinger';
        $token = '';
    } else {
        $exists = true;
        $message = 'ตั้งค่าแล้ว เก็บโทเคนนี้ไว้ ใช้ยืนยันตอนใส่ Google login และลบรายการ หน้านี้จะไม่แสดงโทเคนอีก';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$exists && !isset($_POST['google_client_id'])) {
    $message = 'มี config.json อยู่แล้วแต่ไม่มีโทเคน แก้ไฟล์ใน File Manager';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exists && isset($_POST['google_client_id'])) {
    $config = rl_config();
    $given = isset($_POST['upload_token']) ? (string) $_POST['upload_token'] : '';
    if ($config === null || $given === '' || !hash_equals($config['upload_token'], $given)) {
        $message = 'โทเคนไม่ตรง จึงยังไม่บันทึก Google';
    } else {
        $clientId = trim((string) $_POST['google_client_id']);
        $baseUrl = rtrim(trim((string) $_POST['base_url']), '/');
        $secret = trim((string) $_POST['google_client_secret']);
        $raw = rl_config_raw();
        $hasSecret = isset($raw['google_client_secret']) && trim((string) $raw['google_client_secret']) !== '';
        if ($clientId === '' || ($secret === '' && !$hasSecret) || !rl_valid_base_url($baseUrl)) {
            $message = 'ต้องใส่ที่อยู่เว็บที่ขึ้นต้นด้วย http หรือ https, client id และ client secret';
        } else {
            $patch = array(
                'google_client_id' => $clientId,
                'base_url' => $baseUrl,
            );
            if ($secret !== '') {
                $patch['google_client_secret'] = $secret;
            }
            if (!rl_update_config($patch)) {
                $message = 'เขียน config.json ไม่ได้';
            } else {
                $message = 'บันทึก Google login แล้ว ใช้ Redirect URI ด้านล่างใน Google Cloud';
            }
        }
        if ($clientId !== '') {
            $savedClient = $clientId;
        }
    }
}

$baseValue = rl_base_url();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['base_url'])) {
    $postedBase = rtrim(trim((string) $_POST['base_url']), '/');
    if ($postedBase !== '') {
        $baseValue = $postedBase;
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Marketplace</title>
    <link rel="stylesheet" href="assets/site.css"><link rel="icon" href="assets/icon.svg" type="image/svg+xml">
</head>
<body>
    <main class="wrap">
        <p class="kicker">Rocket Launcher</p>
        <h1>ตั้งค่า Marketplace</h1>
        <?php if ($message !== '') { ?>
            <p class="note"><?php echo h($message); ?></p>
        <?php } ?>
        <?php if ($token !== '') { ?>
            <p class="token"><?php echo h($token); ?></p>
        <?php } ?>
        <?php if (rl_config() === null) { ?>
            <form method="post" class="stack">
                <label>
                    โทเคนสำหรับอัปโหลด
                    <input name="upload_token" type="text" placeholder="เว้นว่างไว้เพื่อสุ่มให้">
                </label>
                <button class="primary" type="submit">สร้าง config.json</button>
            </form>
        <?php } else { ?>
            <p>Marketplace พร้อมแล้ว ใส่ Google client ด้านล่าง แล้วค่อยลบ <code>setup.php</code></p>
            <p>Redirect URI ที่ต้องใส่ใน Google Cloud</p>
            <p class="token"><?php echo h(rl_google_redirect_uri()); ?></p>
            <form method="post" class="stack">
                <label>โทเคนเดิม เพื่อยืนยันว่าเป็นคนตั้งค่า<input name="upload_token" type="password" required></label>
                <label>ที่อยู่เว็บ<input name="base_url" type="url" placeholder="https://market.example.com" value="<?php echo h($baseValue); ?>" required></label>
                <label>Google client id<input name="google_client_id" type="text" required value="<?php echo h($savedClient); ?>"></label>
                <label>Google client secret<input name="google_client_secret" type="password" placeholder="เว้นว่างถ้าไม่เปลี่ยน"></label>
                <button class="primary" type="submit">บันทึก Google login</button>
            </form>
            <p><a href="index.php">ไปหน้ารายการ</a></p>
        <?php } ?>
    </main>
</body>
</html>
