<?php

define('RL_PAGE', 'root');
require __DIR__ . '/lib.php';

rl_page_headers();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
    $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
    if ($action === 'star') {
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
    } elseif ($action === 'delete') {
        $result = rl_delete_item($id, $actor, false);
        if (!empty($result['ok'])) {
            rl_flash('ลบรายการแล้ว');
        } elseif (isset($result['status']) && (int) $result['status'] === 403) {
            rl_flash('ลบได้เฉพาะรายการของคุณ');
        } else {
            rl_flash('ลบไม่สำเร็จ');
        }
    } elseif ($action === 'disconnect') {
        rl_flash(rl_panel_revoke_user($actor['id']) ? 'ตัดการเชื่อมต่อพาเนลทั้งหมดแล้ว' : 'ตัดการเชื่อมต่อไม่สำเร็จ');
    }
    header('Location: ' . $next);
    exit;
}

$ready = rl_config() !== null;
if ($ready) {
    rl_housekeeping();
}
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
// Visitors who are not signed in get no session and no token.
$csrf = $user ? rl_csrf_token() : '';
$panels = $user ? rl_panel_count($user['id']) : 0;

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
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
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
                    <form class="logout" method="post" action="auth/logout.php">
                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                        <button type="submit">ออกจากระบบ</button>
                    </form>
                <?php } else { ?>
                    <a class="login" href="auth/login.php?next=<?php echo h(rawurlencode($next)); ?>">เข้าสู่ระบบด้วย Google</a>
                <?php } ?>
            </nav>
        </header>

        <p class="lead">เลือก Script, Color และ Graphic Style แล้วกด Star เพื่อเก็บเข้า Library ของตัวเอง รายการอยู่ได้ 10 วัน และต่ออายุทุกครั้งที่มีคนดาวน์โหลด</p>

        <?php if ($user) { ?>
            <div class="account">
                <span>พาเนลที่เชื่อมกับบัญชีนี้ <?php echo (int) $panels; ?> เครื่อง</span>
                <?php if ($panels > 0) { ?>
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="action" value="disconnect">
                        <input type="hidden" name="next" value="<?php echo h($next); ?>">
                        <button class="danger" type="submit">ตัดการเชื่อมต่อทั้งหมด</button>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>

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
                            <img class="preview" src="api/preview.php?id=<?php echo h($item['id']); ?>" alt="<?php echo h($item['name']); ?>" width="240" height="92" loading="lazy" decoding="async">
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
                            · โหลด <?php echo (int) $item['downloads']; ?>
                            <?php if ($item['bytes'] > 0) { ?> · <?php echo h(rl_size_label($item['bytes'])); ?><?php } ?>
                            <?php if ($item['days_left'] > 0) { ?> · เหลือ <?php echo (int) $item['days_left']; ?> วัน<?php } ?>
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
                            <?php if ($user && $item['mine']) { ?>
                                <details class="remove">
                                    <summary>ลบ</summary>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h($item['id']); ?>">
                                        <input type="hidden" name="next" value="<?php echo h($next); ?>">
                                        <button class="danger" type="submit">ยืนยันลบ</button>
                                    </form>
                                </details>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </section>
        <?php } ?>
    </main>
</body>
</html>
