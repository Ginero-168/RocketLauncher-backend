<?php

require __DIR__ . '/lib.php';

$exists = rl_config() !== null;
$message = '';
$token = '';
$rawConfig = rl_config_raw();
$savedClient = isset($rawConfig['google_client_id']) ? (string) $rawConfig['google_client_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$exists && !isset($_POST['google_client_id'])) {
    $token = rl_clean_text(isset($_POST['upload_token']) ? $_POST['upload_token'] : '', 120);
    if (strlen($token) < 16) {
        $token = bin2hex(random_bytes(24));
    }
    if (!rl_ensure_storage() || !rl_write_json_file(rl_config_path(), array('upload_token' => $token))) {
        $message = 'เขียน config.json ไม่ได้ ตรวจสิทธิ์โฟลเดอร์บน Hostinger';
        $token = '';
    } else {
        $exists = true;
        $message = 'ตั้งค่าแล้ว เก็บโทเคนนี้ไว้ในพาเนล หน้านี้จะไม่แสดงโทเคนอีก';
    }
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
    <link rel="stylesheet" href="assets/site.css">
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
