<?php

require __DIR__ . '/lib.php';

rl_session_start();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rl_thai_error($error)
{
    $map = array(
        'Name is required' => 'ต้องใส่ชื่อ',
        'At least one hex color is required' => 'ต้องมีรหัสสีอย่างน้อย 1 สี',
        'File is required' => 'ต้องเลือกไฟล์',
        'Upload failed' => 'อัปโหลดไม่สำเร็จ',
        'File is empty or too large' => 'ไฟล์ว่างหรือใหญ่เกินกำหนด',
        'Expected a .jsx file' => 'ต้องเป็นไฟล์ .jsx',
        'Expected a .ai file' => 'ต้องเป็นไฟล์ .ai',
        'Preview must be a PNG or JPEG under 2 MB' => 'รูปตัวอย่างต้องเป็น PNG หรือ JPEG ไม่เกิน 2 MB',
        'You can only remove your own items' => 'ลบได้เฉพาะรายการของตัวเอง',
        'Item not found' => 'ไม่พบรายการ',
        'Type must be script, color, or style' => 'ชนิดต้องเป็น Script, Color หรือ Graphic Style',
        'Could not store the file' => 'เก็บไฟล์ไม่ได้',
        'Could not update the catalog' => 'บันทึกรายการไม่ได้',
        'Storage is not writable' => 'โฟลเดอร์ storage เขียนไม่ได้',
    );
    return isset($map[$error]) ? $map[$error] : $error;
}

function rl_page_next($view, $type)
{
    $query = array();
    if ($view === 'library') {
        $query['view'] = 'library';
    }
    if ($type !== '') {
        $query['type'] = $type;
    }
    $text = http_build_query($query);
    return rl_safe_next('index.php' . ($text !== '' ? '?' . $text : ''));
}

function rl_type_label($type)
{
    if ($type === 'script') {
        return 'Script';
    }
    if ($type === 'color') {
        return 'Color';
    }
    return 'Graphic Style';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = rl_safe_next(isset($_POST['next']) ? $_POST['next'] : 'index.php');
    if (!rl_csrf_ok()) {
        rl_flash('คำขอหมดอายุ ลองใหม่อีกครั้ง');
        header('Location: ' . $next);
        exit;
    }
    $actor = rl_current_user();
    if (!$actor) {
        rl_flash('เข้าสู่ระบบก่อน');
        header('Location: auth/login.php?next=' . rawurlencode($next));
        exit;
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    if ($action === 'star') {
        $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
        if (rl_find_item($id) === null) {
            rl_flash('ไม่พบรายการ');
        } else {
            $on = rl_toggle_star($actor['id'], $id);
            if ($on === null) {
                rl_flash('บันทึก Star ไม่ได้');
            } else {
                rl_flash($on ? 'เก็บเข้า Library แล้ว' : 'เอาออกจาก Library แล้ว');
            }
        }
    } elseif ($action === 'upload') {
        if (rl_config() === null) {
            rl_flash('Marketplace ยังไม่ได้ตั้งค่า');
        } else {
            $result = rl_create_item($actor);
            rl_flash(!empty($result['ok']) ? 'เพิ่มรายการแล้ว' : rl_thai_error(isset($result['error']) ? $result['error'] : ''));
        }
    } elseif ($action === 'delete') {
        $result = rl_delete_item(isset($_POST['id']) ? (string) $_POST['id'] : '', $actor, false);
        rl_flash(!empty($result['ok']) ? 'ลบรายการแล้ว' : rl_thai_error(isset($result['error']) ? $result['error'] : ''));
    }
    header('Location: ' . $next);
    exit;
}

$ready = rl_config() !== null;
$view = (isset($_GET['view']) && $_GET['view'] === 'library') ? 'library' : 'all';
$type = rl_normalize_type(isset($_GET['type']) ? (string) $_GET['type'] : '');
$user = rl_current_user();
$next = rl_page_next($view, $type);
$flash = rl_flash();

if ($view === 'library' && !$user) {
    header('Location: auth/login.php?next=' . rawurlencode($next));
    exit;
}

$items = $ready ? rl_present_catalog($type, $user ? $user['id'] : '', $view === 'library') : array();
$csrf = rl_csrf_token();

$filters = array(
    '' => 'ทั้งหมด',
    'script' => 'Script',
    'color' => 'Color',
    'style' => 'Graphic Style',
);

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
        <header class="top">
            <div>
                <p class="kicker">Rocket Launcher</p>
                <h1>Marketplace</h1>
            </div>
            <nav class="nav">
                <?php if ($user) { ?>
                    <a class="<?php echo $view === 'library' ? 'is-on' : ''; ?>" href="index.php?view=library">Library</a>
                    <?php if (!empty($user['picture'])) { ?>
                        <img class="avatar" src="<?php echo h($user['picture']); ?>" alt="">
                    <?php } ?>
                    <span class="who"><?php echo h($user['name']); ?></span>
                    <a href="auth/logout.php">ออกจากระบบ</a>
                <?php } else { ?>
                    <a class="login" href="auth/login.php?next=<?php echo h(rawurlencode($next)); ?>">เข้าสู่ระบบด้วย Google</a>
                <?php } ?>
            </nav>
        </header>

        <p class="lead">เลือก Script, Color และ Graphic Style จากผู้ใช้คนอื่น กด Star เพื่อเก็บเข้า Library ของตัวเอง</p>

        <?php if ($flash !== '') { ?>
            <p class="note"><?php echo h($flash); ?></p>
        <?php } ?>
        <?php if (!$ready) { ?>
            <p class="note">ยังไม่ได้ตั้งค่า <a href="setup.php">เปิดหน้า setup</a></p>
        <?php } else { ?>
            <nav class="filters">
                <?php foreach ($filters as $key => $label) { ?>
                    <?php
                    $href = 'index.php';
                    $query = array();
                    if ($view === 'library') {
                        $query['view'] = 'library';
                    }
                    if ($key !== '') {
                        $query['type'] = $key;
                    }
                    if ($query) {
                        $href .= '?' . http_build_query($query);
                    }
                    $on = $type === $key;
                    ?>
                    <a class="<?php echo $on ? 'is-on' : ''; ?>" href="<?php echo h($href); ?>"><?php echo h($label); ?></a>
                <?php } ?>
            </nav>

            <section class="catalog">
                <?php if (count($items) === 0) { ?>
                    <p class="empty"><?php echo $view === 'library' ? 'ยังไม่มีรายการที่ Star' : 'ยังไม่มีรายการ'; ?></p>
                <?php } ?>
                <?php foreach ($items as $item) { ?>
                    <article class="card">
                        <p class="type"><?php echo h(rl_type_label($item['type'])); ?></p>
                        <?php if ($item['type'] === 'style' && $item['preview']) { ?>
                            <img class="preview" src="api/preview.php?id=<?php echo h($item['id']); ?>" alt="<?php echo h($item['name']); ?>">
                        <?php } ?>
                        <?php if ($item['type'] === 'color' && count($item['colors']) > 0) { ?>
                            <div class="swatches">
                                <?php foreach ($item['colors'] as $color) { ?>
                                    <span style="background: <?php echo h($color); ?>" title="<?php echo h($color); ?>"></span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <h2><?php echo h($item['name']); ?></h2>
                        <?php if ($item['description'] !== '') { ?>
                            <p><?php echo h($item['description']); ?></p>
                        <?php } ?>
                        <p class="meta">
                            <?php if ($item['owner_name'] !== '') { ?>โดย <?php echo h($item['owner_name']); ?> · <?php } ?>
                            <?php echo (int) $item['stars']; ?> Star
                            <?php if ($item['bytes'] > 0) { ?> · <?php echo h(rl_size_label($item['bytes'])); ?><?php } ?>
                        </p>
                        <div class="actions">
                            <?php if ($user) { ?>
                                <form method="post" action="index.php">
                                    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                    <input type="hidden" name="action" value="star">
                                    <input type="hidden" name="id" value="<?php echo h($item['id']); ?>">
                                    <input type="hidden" name="next" value="<?php echo h($next); ?>">
                                    <button class="star<?php echo $item['starred'] ? ' is-on' : ''; ?>" type="submit"><?php echo $item['starred'] ? 'Starred' : 'Star'; ?></button>
                                </form>
                            <?php } else { ?>
                                <a class="star" href="auth/login.php?next=<?php echo h(rawurlencode($next)); ?>">Star</a>
                            <?php } ?>
                            <?php if ($item['type'] !== 'color') { ?>
                                <a class="download" href="api/download.php?id=<?php echo h($item['id']); ?>">ดาวน์โหลด</a>
                            <?php } ?>
                            <?php if ($item['mine']) { ?>
                                <form method="post" action="index.php">
                                    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo h($item['id']); ?>">
                                    <input type="hidden" name="next" value="<?php echo h($next); ?>">
                                    <button class="danger" type="submit" onclick="return confirm('ลบรายการนี้จาก Marketplace?')">ลบ</button>
                                </form>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </section>

            <section class="panel">
                <h2>เพิ่มรายการ</h2>
                <?php if (!$user) { ?>
                    <p class="meta">เข้าสู่ระบบด้วย Google เพื่อเพิ่มหรือลบ Script, Color และ Graphic Style</p>
                <?php } else { ?>
                    <form class="stack" method="post" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="next" value="<?php echo h($next); ?>">
                        <label>ชนิด
                            <select name="type" id="item_type">
                                <option value="script">Script (.jsx)</option>
                                <option value="color">Color</option>
                                <option value="style">Graphic Style (.ai)</option>
                            </select>
                        </label>
                        <label>ชื่อ<input name="name" type="text" maxlength="80" required></label>
                        <label>คำอธิบาย<input name="description" type="text" maxlength="500"></label>
                        <label id="color_fields" hidden>รหัสสี
                            <textarea name="colors" id="colors_input" rows="3" placeholder="#E61919, #FFE500, #8B1A2B"></textarea>
                        </label>
                        <label id="file_fields">ไฟล์
                            <input name="file" id="file_input" type="file" accept=".jsx,text/javascript" required>
                        </label>
                        <label id="preview_fields" hidden>รูปตัวอย่างคำว่า TEXT
                            <input name="preview" type="file" accept="image/png,image/jpeg">
                        </label>
                        <button class="primary" type="submit">เพิ่ม</button>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>
    </main>
    <script>
        var typeInput = document.getElementById('item_type');
        if (typeInput) {
            var fileInput = document.getElementById('file_input');
            var colorsInput = document.getElementById('colors_input');
            function syncFields() {
                var type = typeInput.value;
                document.getElementById('color_fields').hidden = type !== 'color';
                document.getElementById('file_fields').hidden = type === 'color';
                document.getElementById('preview_fields').hidden = type !== 'style';
                colorsInput.required = type === 'color';
                fileInput.required = type !== 'color';
                fileInput.accept = type === 'style' ? '.ai,application/postscript' : '.jsx,text/javascript';
            }
            typeInput.addEventListener('change', syncFields);
            syncFields();
        }
    </script>
</body>
</html>
