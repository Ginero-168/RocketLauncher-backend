<?php

require __DIR__ . '/lib.php';

$ready = rl_config() !== null;
$items = array();
if ($ready) {
    foreach (rl_catalog()['items'] as $item) {
        if (isset($item['id'], $item['type'], $item['name'], $item['filename'])) {
            $items[] = $item;
        }
    }
    usort($items, function ($a, $b) {
        $left = isset($a['created']) ? $a['created'] : '';
        $right = isset($b['created']) ? $b['created'] : '';
        return strcmp($right, $left);
    });
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rl_size_label($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rocket Launcher Marketplace</title>
    <link rel="stylesheet" href="assets/site.css">
</head>
<body>
    <main class="wrap">
        <p class="kicker">Rocket Launcher</p>
        <h1>Marketplace</h1>
        <p class="lead">ที่กลางสำหรับ Script และ Appearance ของพาเนล พาเนลอ่านรายการจาก <code>api/list.php</code></p>

        <?php if (!$ready) { ?>
            <p class="note">ยังไม่ได้ตั้งค่า <a href="setup.php">เปิดหน้า setup</a></p>
        <?php } else { ?>
            <section class="catalog">
                <?php if (count($items) === 0) { ?>
                    <p class="empty">ยังไม่มีรายการ</p>
                <?php } ?>
                <?php foreach ($items as $item) { ?>
                    <article class="card">
                        <p class="type"><?php echo $item['type'] === 'appearance' ? 'Appearance' : 'Script'; ?></p>
                        <h2><?php echo h($item['name']); ?></h2>
                        <?php if (!empty($item['description'])) { ?>
                            <p><?php echo h($item['description']); ?></p>
                        <?php } ?>
                        <p class="meta"><?php echo h($item['filename']); ?> · <?php echo h(rl_size_label(isset($item['bytes']) ? $item['bytes'] : 0)); ?></p>
                        <a class="download" href="api/download.php?id=<?php echo h($item['id']); ?>">ดาวน์โหลด</a>
                    </article>
                <?php } ?>
            </section>

            <section class="panel">
                <h2>เพิ่มรายการ</h2>
                <form class="stack" action="api/upload.php" method="post" enctype="multipart/form-data">
                    <label>โทเคน<input name="token" type="password" required></label>
                    <label>ชนิด
                        <select name="type">
                            <option value="script">Script (.jsx)</option>
                            <option value="appearance">Appearance (.ai)</option>
                        </select>
                    </label>
                    <label>ชื่อ<input name="name" type="text" maxlength="80" required></label>
                    <label>คำอธิบาย<input name="description" type="text" maxlength="500"></label>
                    <label>ไฟล์<input name="file" type="file" required></label>
                    <button type="submit">อัปโหลด</button>
                </form>
            </section>
        <?php } ?>
    </main>
</body>
</html>
