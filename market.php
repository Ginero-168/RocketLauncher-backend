<?php

define('RL_PAGE', 'root');
require __DIR__ . '/lib.php';

rl_page_headers();

function rl_market_next($view, $type)
{
    $query = array();
    if ($view === 'library') {
        $query['view'] = 'library';
    }
    if ($type !== '') {
        $query['type'] = $type;
    }
    $text = http_build_query($query);
    return rl_safe_next('market.php' . ($text !== '' ? '?' . $text : ''));
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

$settings = rl_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = rl_safe_next(isset($_POST['next']) ? $_POST['next'] : 'market.php');
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
    if ($settings['maintenance'] && !rl_is_admin($actor) && $action !== 'disconnect') {
        rl_flash('Marketplace ปิดปรับปรุงชั่วคราว ดาวน์โหลดได้ แต่ยังกด Star หรือลบไม่ได้');
    } elseif ($action === 'star') {
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
        $result = rl_delete_item($id, $actor, rl_is_admin($actor));
        if (!empty($result['ok'])) {
            rl_flash('ลบรายการแล้ว');
            if (rl_is_admin($actor)) {
                rl_admin_log($actor, 'delete item', $id);
            }
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
$next = rl_market_next($view, $type);
$flash = rl_flash();

if ($view === 'library' && !$user) {
    header('Location: auth/login.php?next=' . rawurlencode($next));
    exit;
}

$admin = rl_is_admin($user);
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

rl_layout_start('Marketplace · Rocket Launcher', array(
    'active' => 'market',
    'user' => $user,
    'csrf' => $csrf,
    'next' => $next,
    'description' => 'Script, ชุดสี และ Graphic Style จากผู้ใช้ Rocket Launcher ดาวน์โหลดเข้าพาเนลได้ทันที',
));
?>
<section class="page-intro">
    <p class="kicker">Marketplace</p>
    <h1><?php echo $view === 'library' ? 'Library ของฉัน' : 'Script, Color และ Graphic Style'; ?></h1>
    <p class="lead">กด Star เพื่อเก็บเข้า Library รายการอยู่ได้ <?php echo (int) $settings['life_days']; ?> วัน และต่ออายุทุกครั้งที่มีคนดาวน์โหลด การอัปโหลดทำจากแท็บ Marketplace ในพาเนล</p>
    <?php if ($user) { ?>
        <div class="account-line">
            <span>พาเนลที่เชื่อมกับบัญชีนี้ <?php echo (int) $panels; ?> เครื่อง</span>
            <?php if ($panels > 0) { ?>
                <form method="post" action="market.php">
                    <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
                    <input type="hidden" name="action" value="disconnect">
                    <input type="hidden" name="next" value="<?php echo rl_h($next); ?>">
                    <button class="btn btn--sm btn--danger" type="submit">ตัดการเชื่อมต่อทั้งหมด</button>
                </form>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<?php if ($settings['maintenance']) { ?>
    <p class="note note--warn">Marketplace ปิดปรับปรุงชั่วคราว ดาวน์โหลดได้ตามปกติ แต่ยังอัปโหลด กด Star หรือลบไม่ได้</p>
<?php } ?>
<?php if ($flash !== '') { ?>
    <p class="note" role="status"><?php echo rl_h($flash); ?></p>
<?php } ?>

<?php if (!$ready) { ?>
    <p class="note">ยังไม่ได้ตั้งค่า Marketplace</p>
<?php } else { ?>
    <nav class="chips" aria-label="กรองตามชนิด">
        <?php if ($user) { ?>
            <a class="chip" href="<?php echo rl_h($view === 'library' ? 'market.php' : 'market.php?view=library'); ?>"<?php echo $view === 'library' ? ' aria-current="page"' : ''; ?>>Library</a>
        <?php } ?>
        <?php foreach ($filters as $key => $label) { ?>
            <?php
            $query = array();
            if ($view === 'library') {
                $query['view'] = 'library';
            }
            if ($key !== '') {
                $query['type'] = $key;
            }
            $href = 'market.php' . ($query ? '?' . http_build_query($query) : '');
            ?>
            <a class="chip" href="<?php echo rl_h($href); ?>"<?php echo $type === $key ? ' aria-current="true"' : ''; ?>><?php echo rl_h($label); ?></a>
        <?php } ?>
    </nav>

    <section class="catalog" aria-label="รายการ">
        <?php if (count($items) === 0) { ?>
            <p class="empty"><?php echo $view === 'library' ? 'ยังไม่มีรายการที่ Star' : 'ยังไม่มีรายการ'; ?></p>
        <?php } ?>
        <?php foreach ($items as $item) { ?>
            <article class="card">
                <div class="card-art card-art--<?php echo rl_h($item['type']); ?>">
                    <?php if ($item['type'] === 'style' && $item['preview']) { ?>
                        <img src="api/preview.php?id=<?php echo rl_h($item['id']); ?>" alt="<?php echo rl_h($item['name']); ?>" width="240" height="120" loading="lazy" decoding="async">
                    <?php } elseif ($item['type'] === 'color' && count($item['colors']) > 0) { ?>
                        <div class="swatches">
                            <?php foreach ($item['colors'] as $color) { ?>
                                <span style="background: <?php echo rl_h($color); ?>" title="<?php echo rl_h($color); ?>"></span>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <svg class="card-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.4 16.6 4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4Zm5.2 0 4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4Z"/></svg>
                    <?php } ?>
                </div>
                <p class="card-type"><?php echo rl_h(rl_type_label($item['type'])); ?></p>
                <h2 class="card-name"><?php echo rl_h($item['name']); ?></h2>
                <?php if ($item['description'] !== '') { ?>
                    <p class="card-desc"><?php echo rl_h($item['description']); ?></p>
                <?php } ?>
                <p class="card-meta">
                    <?php if ($item['owner_name'] !== '') { ?>โดย <?php echo rl_h($item['owner_name']); ?> · <?php } ?>
                    <?php echo (int) $item['stars']; ?> Star · โหลด <?php echo (int) $item['downloads']; ?>
                    <?php if ($item['bytes'] > 0) { ?> · <?php echo rl_h(rl_size_text($item['bytes'])); ?><?php } ?>
                    <?php if ($item['days_left'] > 0) { ?> · เหลือ <?php echo (int) $item['days_left']; ?> วัน<?php } ?>
                </p>
                <div class="card-actions">
                    <?php if ($user) { ?>
                        <form method="post" action="market.php">
                            <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
                            <input type="hidden" name="action" value="star">
                            <input type="hidden" name="id" value="<?php echo rl_h($item['id']); ?>">
                            <input type="hidden" name="next" value="<?php echo rl_h($next); ?>">
                            <button class="btn btn--sm<?php echo $item['starred'] ? ' is-on' : ''; ?>" type="submit" aria-pressed="<?php echo $item['starred'] ? 'true' : 'false'; ?>"><?php echo $item['starred'] ? 'Starred' : 'Star'; ?></button>
                        </form>
                    <?php } else { ?>
                        <a class="btn btn--sm" href="auth/login.php?next=<?php echo rl_h(rawurlencode($next)); ?>">Star</a>
                    <?php } ?>
                    <?php if ($item['type'] !== 'color') { ?>
                        <a class="btn btn--sm btn--primary" href="api/download.php?id=<?php echo rl_h($item['id']); ?>">ดาวน์โหลด</a>
                    <?php } ?>
                    <?php if ($user && ($item['mine'] || $admin)) { ?>
                        <details class="remove">
                            <summary class="btn btn--sm btn--ghost-danger">ลบ</summary>
                            <form method="post" action="market.php">
                                <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo rl_h($item['id']); ?>">
                                <input type="hidden" name="next" value="<?php echo rl_h($next); ?>">
                                <button class="btn btn--sm btn--danger" type="submit">ยืนยันลบ</button>
                            </form>
                        </details>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </section>
<?php } ?>
<?php
rl_layout_end('');
