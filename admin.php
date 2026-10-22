<?php

define('RL_PAGE', 'root');
require __DIR__ . '/lib.php';

rl_page_headers();
header('Cache-Control: private, no-store');

if (rl_config() === null) {
    header('Location: setup.php');
    exit;
}

$user = rl_current_user();
if (!$user) {
    header('Location: auth/login.php?next=' . rawurlencode('admin.php'));
    exit;
}
$csrf = rl_csrf_token();
$isAdmin = rl_is_admin($user);

function rl_admin_back($section, $message)
{
    rl_flash($message);
    header('Location: admin.php' . ($section !== '' ? '#' . $section : ''));
    exit;
}

function rl_posted_bool($name)
{
    return isset($_POST[$name]) && (string) $_POST[$name] !== '' && (string) $_POST[$name] !== '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rl_csrf_ok()) {
        rl_admin_back('', 'หน้านี้หมดอายุแล้ว ลองอีกครั้ง');
    }
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'claim') {
        $result = rl_claim_admin($user, isset($_POST['token']) ? (string) $_POST['token'] : '');
        if ($result === 'ok') {
            rl_admin_log($user, 'became admin', '');
            rl_admin_back('', 'คุณเป็นผู้ดูแลแล้ว');
        }
        rl_admin_back('', $result === 'limited' ? 'ลองหลายครั้งเกินไป รอ 10 นาที' : 'โทเคนไม่ถูกต้อง');
    }
    if (!$isAdmin) {
        rl_admin_back('', 'หน้านี้สำหรับผู้ดูแลเท่านั้น');
    }

    if ($action === 'save_market') {
        $saved = rl_save_settings(array(
            'life_days' => isset($_POST['life_days']) ? $_POST['life_days'] : '',
            'max_script_mb' => isset($_POST['max_script_mb']) ? $_POST['max_script_mb'] : '',
            'max_style_mb' => isset($_POST['max_style_mb']) ? $_POST['max_style_mb'] : '',
            'max_preview_mb' => isset($_POST['max_preview_mb']) ? $_POST['max_preview_mb'] : '',
            'uploads_per_day' => isset($_POST['uploads_per_day']) ? $_POST['uploads_per_day'] : '',
            'storage_mb' => isset($_POST['storage_mb']) ? $_POST['storage_mb'] : '',
            'panel_starts_per_10min' => isset($_POST['panel_starts_per_10min']) ? $_POST['panel_starts_per_10min'] : '',
            'allow_script' => rl_posted_bool('allow_script'),
            'allow_color' => rl_posted_bool('allow_color'),
            'allow_style' => rl_posted_bool('allow_style'),
            'maintenance' => rl_posted_bool('maintenance'),
            'trust_forwarded_for' => rl_posted_bool('trust_forwarded_for'),
            'release_max_mb' => isset($_POST['release_max_mb']) ? $_POST['release_max_mb'] : '',
        ));
        if ($saved === null) {
            rl_admin_back('market', 'บันทึกไม่ได้ ตรวจสิทธิ์เขียนโฟลเดอร์ storage');
        }
        rl_admin_log($user, 'marketplace settings', 'life ' . $saved['life_days'] . 'd, style ' . $saved['max_style_mb'] . ' MB, ' . $saved['uploads_per_day'] . '/day' . ($saved['maintenance'] ? ', maintenance on' : ''));
        rl_admin_back('market', 'บันทึกการตั้งค่า Marketplace แล้ว');
    }

    if ($action === 'save_ai') {
        $saved = rl_save_settings(array(
            'ai_enabled' => rl_posted_bool('ai_enabled'),
            'ai_model' => isset($_POST['ai_model']) ? $_POST['ai_model'] : '',
            'ai_per_member_daily' => isset($_POST['ai_per_member_daily']) ? $_POST['ai_per_member_daily'] : '',
            'ai_total_daily' => isset($_POST['ai_total_daily']) ? $_POST['ai_total_daily'] : '',
            'ai_max_prompt_kb' => isset($_POST['ai_max_prompt_kb']) ? $_POST['ai_max_prompt_kb'] : '',
        ));
        if ($saved === null) {
            rl_admin_back('ai', 'บันทึกไม่ได้ ตรวจสิทธิ์เขียนโฟลเดอร์ storage');
        }
        $key = isset($_POST['replicate_api_token']) ? trim((string) $_POST['replicate_api_token']) : '';
        $note = '';
        if (rl_posted_bool('remove_key')) {
            rl_update_config(array('replicate_api_token' => ''));
            $note = ', key removed';
        } elseif ($key !== '') {
            if (!preg_match('/^[A-Za-z0-9_\-]{20,200}$/', $key)) {
                rl_admin_back('ai', 'รูปแบบ API key ไม่ถูกต้อง');
            }
            rl_update_config(array('replicate_api_token' => $key));
            $note = ', key changed';
        }
        rl_admin_log($user, 'AI settings', ($saved['ai_enabled'] ? 'on' : 'off') . ', ' . $saved['ai_model'] . ', ' . $saved['ai_per_member_daily'] . '/member/day' . $note);
        rl_admin_back('ai', 'บันทึกการตั้งค่า AI แล้ว');
    }

    if ($action === 'test_ai') {
        if (rl_ai_token() === '') {
            rl_admin_back('ai', 'ยังไม่ได้ใส่ API key');
        }
        $check = rl_replicate('GET', '/v1/account', null, 0);
        if ($check['status'] === 200 && is_array($check['data'])) {
            $who = isset($check['data']['username']) ? (string) $check['data']['username'] : 'บัญชีนี้';
            rl_admin_back('ai', 'API key ใช้ได้ (Replicate: ' . rl_clean_text($who, 60) . ')');
        }
        rl_admin_back('ai', $check['status'] === 401 ? 'API key ใช้ไม่ได้ Replicate ปฏิเสธ' : 'ติดต่อ Replicate ไม่ได้ (HTTP ' . (int) $check['status'] . ')');
    }

    if ($action === 'upload_release') {
        $result = rl_store_release(isset($_FILES['zxp']) ? $_FILES['zxp'] : null, isset($_POST['notes']) ? $_POST['notes'] : '', rl_settings());
        if (empty($result['ok'])) {
            rl_admin_back('release', $result['error']);
        }
        rl_admin_log($user, 'panel release', 'v' . $result['release']['version'] . ', ' . rl_size_text($result['release']['bytes']));
        rl_admin_back('release', 'อัปโหลด v' . $result['release']['version'] . ' แล้ว ปุ่มดาวน์โหลดหน้าแรกใช้ไฟล์นี้');
    }

    if ($action === 'delete_item') {
        $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
        $result = rl_delete_item($id, $user, true);
        if (!empty($result['ok'])) {
            rl_admin_log($user, 'delete item', $id);
        }
        rl_admin_back('items', !empty($result['ok']) ? 'ลบรายการแล้ว' : 'ลบไม่สำเร็จ');
    }

    if ($action === 'disconnect_member') {
        $member = isset($_POST['member']) ? (string) $_POST['member'] : '';
        $ok = rl_panel_revoke_user($member);
        if ($ok) {
            rl_admin_log($user, 'disconnect panels', $member);
        }
        rl_admin_back('members', $ok ? 'ตัดการเชื่อมต่อพาเนลของสมาชิกแล้ว' : 'ตัดการเชื่อมต่อไม่สำเร็จ');
    }

    if ($action === 'remove_admin') {
        $member = isset($_POST['member']) ? (string) $_POST['member'] : '';
        $ok = rl_remove_admin($member);
        if ($ok) {
            rl_admin_log($user, 'remove admin', $member);
        }
        rl_admin_back('admins', $ok ? 'เอาสิทธิ์ผู้ดูแลออกแล้ว' : 'เอาออกไม่ได้ ต้องมีผู้ดูแลอย่างน้อยหนึ่งคน');
    }

    rl_admin_back('', 'ไม่รู้จักคำสั่งนี้');
}

$flash = rl_flash();

rl_layout_start('Admin · Rocket Launcher', array(
    'active' => 'admin',
    'user' => $user,
    'csrf' => $csrf,
    'next' => 'admin.php',
));

if (!$isAdmin) {
    ?>
    <section class="panel-card narrow">
        <p class="kicker">Admin</p>
        <h1>ยืนยันว่าเป็นผู้ดูแล</h1>
        <?php if ($flash !== '') { ?><p class="note" role="status"><?php echo rl_h($flash); ?></p><?php } ?>
        <p class="lead">ใส่โทเคนแอดมินที่ได้จากหน้า setup ครั้งเดียว บัญชี Google นี้ (<?php echo rl_h($user['name']); ?>) จะเปิดหน้า Admin ได้จากนั้นเป็นต้นไป</p>
        <form method="post" action="admin.php" class="form-stack">
            <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
            <input type="hidden" name="action" value="claim">
            <label class="field-label">โทเคนแอดมิน<input class="field" name="token" type="password" autocomplete="off" required></label>
            <button class="btn btn--primary" type="submit">ยืนยัน</button>
        </form>
    </section>
    <?php
    rl_layout_end('');
    exit;
}

$settings = rl_settings();
$overview = rl_admin_overview();
$release = rl_release();
$aiUsage = rl_ai_usage();
$catalog = rl_catalog()['items'];
usort($catalog, function ($a, $b) {
    return strcmp(isset($b['created']) ? $b['created'] : '', isset($a['created']) ? $a['created'] : '');
});
$users = rl_users()['users'];
$userMap = rl_user_map();
$adminIds = rl_admin_ids();
$phpLimit = rl_php_upload_limit();
$token = rl_ai_token();
$storageCap = $settings['storage_mb'] * 1024 * 1024;
$storagePct = $storageCap > 0 ? min(100, round($overview['storage'] / $storageCap * 100)) : 0;
$itemsByOwner = array();
foreach ($catalog as $item) {
    $owner = isset($item['owner_id']) ? (string) $item['owner_id'] : '';
    $itemsByOwner[$owner] = (isset($itemsByOwner[$owner]) ? $itemsByOwner[$owner] : 0) + 1;
}
$panelsByUser = array();
foreach (rl_panel_store()['sessions'] as $session) {
    if (isset($session['user_id'], $session['expires']) && (int) $session['expires'] > time()) {
        $panelsByUser[$session['user_id']] = (isset($panelsByUser[$session['user_id']]) ? $panelsByUser[$session['user_id']] : 0) + 1;
    }
}
$sizeWarn = $phpLimit > 0 && max($settings['max_style_mb'], $settings['release_max_mb']) * 1024 * 1024 > $phpLimit;

function rl_admin_number($name, $label, $value, $min, $max, $unit, $help)
{
    echo '<label class="field-label">' . rl_h($label);
    echo '<span class="field-unit"><input class="field" type="number" name="' . rl_h($name) . '" value="' . (int) $value . '" min="' . (int) $min . '" max="' . (int) $max . '" required><span>' . rl_h($unit) . '</span></span>';
    if ($help !== '') {
        echo '<span class="field-help">' . rl_h($help) . '</span>';
    }
    echo '</label>';
}

function rl_admin_toggle($name, $label, $on, $help)
{
    echo '<label class="toggle"><input type="checkbox" name="' . rl_h($name) . '" value="1"' . ($on ? ' checked' : '') . '><span class="toggle-ui" aria-hidden="true"></span><span class="toggle-text">' . rl_h($label);
    if ($help !== '') {
        echo '<span class="field-help">' . rl_h($help) . '</span>';
    }
    echo '</span></label>';
}
?>
<section class="page-intro">
    <p class="kicker">Admin</p>
    <h1>จัดการ Rocket Launcher</h1>
    <nav class="chips" aria-label="ส่วนของหน้า Admin">
        <a class="chip" href="#overview">ภาพรวม</a>
        <a class="chip" href="#market">Marketplace</a>
        <a class="chip" href="#ai">AI</a>
        <a class="chip" href="#release">ไฟล์ติดตั้ง</a>
        <a class="chip" href="#items">รายการ</a>
        <a class="chip" href="#members">สมาชิก</a>
        <a class="chip" href="#admins">ผู้ดูแล</a>
        <a class="chip" href="#log">บันทึก</a>
    </nav>
</section>

<?php if ($flash !== '') { ?><p class="note" role="status"><?php echo rl_h($flash); ?></p><?php } ?>
<?php if ($settings['maintenance']) { ?><p class="note note--warn">ตอนนี้เปิดโหมดปิดปรับปรุงอยู่ สมาชิกอัปโหลด กด Star และลบไม่ได้</p><?php } ?>

<section id="overview" class="admin-section">
    <h2 class="section-title">ภาพรวม</h2>
    <div class="stat-grid">
        <div class="stat"><span class="stat-label">รายการ</span><span class="stat-value"><?php echo (int) $overview['items']; ?></span><span class="stat-sub">Script <?php echo (int) $overview['types']['script']; ?> · Color <?php echo (int) $overview['types']['color']; ?> · Style <?php echo (int) $overview['types']['style']; ?></span></div>
        <div class="stat"><span class="stat-label">พื้นที่</span><span class="stat-value"><?php echo rl_h(rl_size_text($overview['storage'])); ?></span><span class="meter" role="img" aria-label="ใช้ไป <?php echo (int) $storagePct; ?>%"><span style="width: <?php echo (int) $storagePct; ?>%"></span></span><span class="stat-sub">จาก <?php echo (int) $settings['storage_mb']; ?> MB</span></div>
        <div class="stat"><span class="stat-label">สมาชิก</span><span class="stat-value"><?php echo (int) $overview['members']; ?></span><span class="stat-sub">พาเนลที่เชื่อมอยู่ <?php echo (int) $overview['panels']; ?></span></div>
        <div class="stat"><span class="stat-label">AI วันนี้</span><span class="stat-value"><?php echo (int) $overview['ai_today']; ?></span><span class="stat-sub">เพดาน <?php echo (int) $settings['ai_total_daily']; ?> ครั้งต่อวัน</span></div>
        <div class="stat"><span class="stat-label">ไฟล์ติดตั้ง</span><span class="stat-value"><?php echo $release ? 'v' . rl_h($release['version']) : '—'; ?></span><span class="stat-sub"><?php echo $release ? 'โหลดแล้ว ' . (int) (isset($release['downloads']) ? $release['downloads'] : 0) . ' ครั้ง' : 'ยังไม่ได้อัปโหลด'; ?></span></div>
    </div>
    <details class="diag">
        <summary>ข้อมูลเซิร์ฟเวอร์</summary>
        <dl>
            <dt>IP ที่เซิร์ฟเวอร์เห็น</dt><dd><code><?php echo rl_h(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''); ?></code></dd>
            <dt>X-Forwarded-For</dt><dd><code><?php echo rl_h(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '—'); ?></code></dd>
            <dt>IP ที่ระบบใช้จำกัดจำนวนครั้ง</dt><dd><code><?php echo rl_h(rl_client_ip()); ?></code> ถ้าไม่ใช่ IP ของคุณจริง ให้เปิด "เชื่อ X-Forwarded-For" ด้านล่าง</dd>
            <dt>ขนาดอัปโหลดสูงสุดของ PHP</dt><dd><?php echo $phpLimit > 0 ? rl_h(rl_size_text($phpLimit)) : 'ไม่ทราบ'; ?></dd>
            <dt>PHP</dt><dd><?php echo rl_h(PHP_VERSION); ?> · curl <?php echo function_exists('curl_init') ? 'มี' : 'ไม่มี'; ?> · zip <?php echo class_exists('ZipArchive') ? 'มี' : 'ไม่มี'; ?></dd>
        </dl>
    </details>
</section>

<section id="market" class="admin-section">
    <h2 class="section-title">ตั้งค่า Marketplace</h2>
    <?php if ($sizeWarn) { ?><p class="note note--warn">PHP บนโฮสต์นี้รับไฟล์ได้สูงสุด <?php echo rl_h(rl_size_text($phpLimit)); ?> ไฟล์ที่ใหญ่กว่านี้จะอัปโหลดไม่ผ่าน แม้จะตั้งค่าไว้สูงกว่า (แก้ upload_max_filesize และ post_max_size ใน hPanel)</p><?php } ?>
    <form method="post" action="admin.php" class="form-grid">
        <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
        <input type="hidden" name="action" value="save_market">
        <?php
        rl_admin_number('life_days', 'อายุรายการ', $settings['life_days'], 1, 365, 'วัน', 'นับจากวันที่เพิ่ม และเริ่มนับใหม่ทุกครั้งที่มีคนดาวน์โหลด');
        rl_admin_number('max_script_mb', 'Script ใหญ่สุด', $settings['max_script_mb'], 1, 20, 'MB', '');
        rl_admin_number('max_style_mb', 'Graphic Style (.ai) ใหญ่สุด', $settings['max_style_mb'], 1, 200, 'MB', '');
        rl_admin_number('max_preview_mb', 'รูปตัวอย่างใหญ่สุด', $settings['max_preview_mb'], 1, 10, 'MB', '');
        rl_admin_number('uploads_per_day', 'อัปโหลดต่อคนต่อวัน', $settings['uploads_per_day'], 1, 1000, 'รายการ', '');
        rl_admin_number('storage_mb', 'พื้นที่รวมสูงสุด', $settings['storage_mb'], 1, 200000, 'MB', 'เต็มแล้วจะรับเพิ่มไม่ได้จนกว่ารายการเก่าหมดอายุ');
        rl_admin_number('panel_starts_per_10min', 'เริ่มเชื่อมพาเนลต่อ IP', $settings['panel_starts_per_10min'], 1, 500, 'ครั้ง / 10 นาที', '');
        rl_admin_number('release_max_mb', 'ไฟล์ .zxp ใหญ่สุด', $settings['release_max_mb'], 1, 500, 'MB', '');
        ?>
        <fieldset class="toggles">
            <legend>รับอัปโหลด</legend>
            <?php
            rl_admin_toggle('allow_script', 'Script', $settings['allow_script'], '');
            rl_admin_toggle('allow_color', 'ชุดสี', $settings['allow_color'], '');
            rl_admin_toggle('allow_style', 'Graphic Style', $settings['allow_style'], '');
            ?>
        </fieldset>
        <fieldset class="toggles">
            <legend>อื่น ๆ</legend>
            <?php
            rl_admin_toggle('maintenance', 'ปิดปรับปรุง', $settings['maintenance'], 'ดาวน์โหลดได้ แต่สมาชิกอัปโหลด กด Star และลบไม่ได้');
            rl_admin_toggle('trust_forwarded_for', 'เชื่อ X-Forwarded-For', $settings['trust_forwarded_for'], 'เปิดเฉพาะเมื่อ IP ที่ระบบใช้ด้านบนไม่ใช่ IP จริงของผู้ใช้ (เว็บอยู่หลัง CDN)');
            ?>
        </fieldset>
        <div class="form-actions"><button class="btn btn--primary" type="submit">บันทึก</button></div>
    </form>
</section>

<section id="ai" class="admin-section">
    <h2 class="section-title">AI กลาง</h2>
    <p class="lead">พาเนลส่งคำขอ AI ผ่านเว็บนี้ เว็บเรียก Replicate ด้วย key ด้านล่าง ผู้ใช้ไม่ต้องใส่ key เอง แต่ต้องเชื่อมพาเนลด้วย Google ก่อน</p>
    <p class="status-line">
        <?php if ($token !== '') { ?>
            <span class="badge badge--ok">ใส่ key แล้ว</span> ลงท้ายด้วย <code>…<?php echo rl_h(substr($token, -4)); ?></code>
        <?php } else { ?>
            <span class="badge badge--warn">ยังไม่มี key</span> AI ในพาเนลจะใช้ไม่ได้จนกว่าจะใส่
        <?php } ?>
        · วันนี้ใช้ไป <?php echo (int) $aiUsage['total']; ?> ครั้ง
    </p>
    <form method="post" action="admin.php" class="form-grid">
        <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
        <input type="hidden" name="action" value="save_ai">
        <label class="field-label field-wide">Replicate API key
            <input class="field" type="password" name="replicate_api_token" autocomplete="off" spellcheck="false" placeholder="<?php echo $token !== '' ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'r8_…'; ?>">
            <span class="field-help">เก็บใน config.json บนเซิร์ฟเวอร์เท่านั้น ไม่ถูกส่งให้พาเนลหรือแสดงซ้ำ</span>
        </label>
        <label class="field-label">โมเดล
            <input class="field" type="text" name="ai_model" value="<?php echo rl_h($settings['ai_model']); ?>" pattern="[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+" required>
            <span class="field-help">ชื่อโมเดลบน Replicate เช่น google/gemini-3-flash</span>
        </label>
        <?php
        rl_admin_number('ai_per_member_daily', 'ต่อสมาชิกต่อวัน', $settings['ai_per_member_daily'], 0, 10000, 'ครั้ง', '0 = ปิด AI ให้สมาชิก');
        rl_admin_number('ai_total_daily', 'รวมทั้งเว็บต่อวัน', $settings['ai_total_daily'], 0, 1000000, 'ครั้ง', 'กันค่าใช้จ่ายบานปลาย');
        rl_admin_number('ai_max_prompt_kb', 'ข้อความยาวสุดต่อครั้ง', $settings['ai_max_prompt_kb'], 4, 500, 'KB', 'รวมโค้ดที่พาเนลแนบไปด้วย');
        ?>
        <fieldset class="toggles">
            <legend>สถานะ</legend>
            <?php
            rl_admin_toggle('ai_enabled', 'เปิดใช้ AI กลาง', $settings['ai_enabled'], '');
            rl_admin_toggle('remove_key', 'ลบ API key ออก', false, '');
            ?>
        </fieldset>
        <div class="form-actions"><button class="btn btn--primary" type="submit">บันทึก</button></div>
    </form>
    <form method="post" action="admin.php" class="inline-form">
        <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
        <input type="hidden" name="action" value="test_ai">
        <button class="btn" type="submit"<?php echo $token === '' ? ' disabled' : ''; ?>>ทดสอบ API key</button>
    </form>
</section>

<section id="release" class="admin-section">
    <h2 class="section-title">ไฟล์ติดตั้งพาเนล (.zxp)</h2>
    <?php if ($release) { ?>
        <dl class="kv">
            <dt>เวอร์ชัน</dt><dd>v<?php echo rl_h($release['version']); ?></dd>
            <dt>ขนาด</dt><dd><?php echo rl_h(rl_size_text($release['bytes'])); ?></dd>
            <dt>อัปโหลด</dt><dd><?php echo rl_h(rl_date_text($release['uploaded'])); ?></dd>
            <dt>ดาวน์โหลด</dt><dd><?php echo (int) (isset($release['downloads']) ? $release['downloads'] : 0); ?> ครั้ง</dd>
            <dt>SHA-256</dt><dd><code class="hash"><?php echo rl_h($release['sha256']); ?></code></dd>
            <?php if (!empty($release['notes'])) { ?><dt>มีอะไรใหม่</dt><dd><?php echo rl_h($release['notes']); ?></dd><?php } ?>
        </dl>
    <?php } else { ?>
        <p class="note">ยังไม่มีไฟล์ ปุ่มดาวน์โหลดบนหน้าแรกจะขึ้นว่า "ไฟล์ติดตั้งกำลังจะมา"</p>
    <?php } ?>
    <form method="post" action="admin.php" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
        <input type="hidden" name="action" value="upload_release">
        <label class="field-label field-wide">ไฟล์ .zxp ที่เซ็นแล้ว
            <input class="field" type="file" name="zxp" accept=".zxp" required>
            <span class="field-help">สร้างบน Mac ด้วย npm run build:zxp เวอร์ชันอ่านจากไฟล์เอง ไฟล์เดิมจะถูกแทนที่</span>
        </label>
        <label class="field-label field-wide">มีอะไรใหม่ (ไม่บังคับ)
            <textarea class="field" name="notes" rows="2" maxlength="300"></textarea>
        </label>
        <div class="form-actions"><button class="btn btn--primary" type="submit">อัปโหลด</button></div>
    </form>
</section>

<section id="items" class="admin-section">
    <h2 class="section-title">รายการ (<?php echo count($catalog); ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ชื่อ</th><th>ชนิด</th><th>เจ้าของ</th><th>เหลือ</th><th>โหลด</th><th>ขนาด</th><th><span class="sr-only">ลบ</span></th></tr></thead>
            <tbody>
            <?php foreach ($catalog as $item) { ?>
                <?php $owner = isset($item['owner_id'], $userMap[$item['owner_id']]) ? $userMap[$item['owner_id']]['name'] : '—'; ?>
                <tr>
                    <td><?php echo rl_h($item['name']); ?></td>
                    <td><?php echo rl_h(rl_normalize_type($item['type'])); ?></td>
                    <td><?php echo rl_h($owner); ?></td>
                    <td><?php echo (int) rl_days_left($item); ?> วัน</td>
                    <td><?php echo (int) (isset($item['downloads']) ? $item['downloads'] : 0); ?></td>
                    <td><?php echo rl_h(rl_size_text(isset($item['bytes']) ? $item['bytes'] : 0)); ?></td>
                    <td>
                        <details class="remove">
                            <summary class="btn btn--sm btn--ghost-danger">ลบ</summary>
                            <form method="post" action="admin.php">
                                <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="id" value="<?php echo rl_h($item['id']); ?>">
                                <button class="btn btn--sm btn--danger" type="submit">ยืนยันลบ</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php } ?>
            <?php if (count($catalog) === 0) { ?><tr><td colspan="7" class="empty">ยังไม่มีรายการ</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section id="members" class="admin-section">
    <h2 class="section-title">สมาชิก (<?php echo count($users); ?>)</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ชื่อ</th><th>อีเมล</th><th>เข้าร่วม</th><th>รายการ</th><th>พาเนล</th><th>AI วันนี้</th><th><span class="sr-only">จัดการ</span></th></tr></thead>
            <tbody>
            <?php foreach ($users as $member) { ?>
                <?php $mid = isset($member['id']) ? (string) $member['id'] : ''; ?>
                <tr>
                    <td><?php echo rl_h(isset($member['name']) ? $member['name'] : ''); ?><?php echo in_array($mid, $adminIds, true) ? ' <span class="badge">admin</span>' : ''; ?></td>
                    <td><?php echo rl_h(isset($member['email']) ? $member['email'] : ''); ?></td>
                    <td><?php echo rl_h(rl_date_text(isset($member['created']) ? $member['created'] : '')); ?></td>
                    <td><?php echo (int) (isset($itemsByOwner[$mid]) ? $itemsByOwner[$mid] : 0); ?></td>
                    <td><?php echo (int) (isset($panelsByUser[$mid]) ? $panelsByUser[$mid] : 0); ?></td>
                    <td><?php echo (int) (isset($aiUsage['members'][$mid]) ? $aiUsage['members'][$mid] : 0); ?></td>
                    <td>
                        <?php if (!empty($panelsByUser[$mid])) { ?>
                            <form method="post" action="admin.php">
                                <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
                                <input type="hidden" name="action" value="disconnect_member">
                                <input type="hidden" name="member" value="<?php echo rl_h($mid); ?>">
                                <button class="btn btn--sm" type="submit">ตัดพาเนล</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <?php if (count($users) === 0) { ?><tr><td colspan="7" class="empty">ยังไม่มีสมาชิก</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section id="admins" class="admin-section">
    <h2 class="section-title">ผู้ดูแล</h2>
    <p class="lead">เพิ่มผู้ดูแลได้โดยให้คนนั้นล็อกอินแล้วเปิดหน้านี้ และใส่โทเคนแอดมินจากหน้า setup</p>
    <ul class="admin-list">
        <?php foreach ($adminIds as $aid) { ?>
            <li>
                <span><?php echo rl_h(isset($userMap[$aid]['name']) ? $userMap[$aid]['name'] : $aid); ?><?php echo $aid === $user['id'] ? ' (คุณ)' : ''; ?></span>
                <?php if (count($adminIds) > 1) { ?>
                    <form method="post" action="admin.php">
                        <input type="hidden" name="csrf" value="<?php echo rl_h($csrf); ?>">
                        <input type="hidden" name="action" value="remove_admin">
                        <input type="hidden" name="member" value="<?php echo rl_h($aid); ?>">
                        <button class="btn btn--sm btn--ghost-danger" type="submit">เอาสิทธิ์ออก</button>
                    </form>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
</section>

<section id="log" class="admin-section">
    <h2 class="section-title">บันทึกการเปลี่ยนแปลง</h2>
    <ol class="log">
        <?php foreach (rl_admin_log_entries(50) as $entry) { ?>
            <li><time><?php echo rl_h(rl_date_text($entry['time'])); ?> <?php echo rl_h(gmdate('H:i', strtotime($entry['time']) + 7 * 3600)); ?></time> <strong><?php echo rl_h($entry['user']); ?></strong> <?php echo rl_h($entry['action']); ?><?php echo $entry['detail'] !== '' ? ' · ' . rl_h($entry['detail']) : ''; ?></li>
        <?php } ?>
    </ol>
</section>
<?php
rl_layout_end('');
