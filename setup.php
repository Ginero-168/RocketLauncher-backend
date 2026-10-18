<?php

require __DIR__ . '/lib.php';

$exists = rl_config() !== null;
$message = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$exists) {
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
                <button type="submit">สร้าง config.json</button>
            </form>
        <?php } else { ?>
            <p>Marketplace พร้อมแล้ว ลบ <code>setup.php</code> ออกจากเซิร์ฟเวอร์ได้หลังเก็บโทเคน</p>
            <p><a href="index.php">ไปหน้ารายการ</a></p>
        <?php } ?>
    </main>
</body>
</html>
