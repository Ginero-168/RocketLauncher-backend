<?php

define('RL_PAGE', 'root');
require __DIR__ . '/lib.php';

rl_page_headers();

// Bookmarks of the catalog when it lived here.
if (isset($_GET['view']) || isset($_GET['type'])) {
    $query = array();
    foreach (array('view', 'type') as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key]) && preg_match('/^[a-z]{1,10}$/', $_GET[$key])) {
            $query[$key] = $_GET[$key];
        }
    }
    header('Location: market.php' . ($query ? '?' . http_build_query($query) : ''), true, 301);
    exit;
}

$ready = rl_config() !== null;
$user = rl_current_user();
$csrf = $user ? rl_csrf_token() : '';
$settings = rl_settings();
$release = $ready ? rl_release() : null;
$itemCount = $ready ? count(rl_present_catalog('', '', false)) : 0;
$video = is_file(__DIR__ . '/assets/intro.mp4');

$features = array(
    array('TOOL', 'ปุ่มสคริปต์คลิกเดียว', 'ใส่สคริปต์ .jsx เป็นปุ่มสีของตัวเอง กดครั้งเดียวก็รัน และปักหมุดตัวที่ใช้บ่อยไว้แถบบนสุด'),
    array('EDIT', 'แก้สคริปต์ในพาเนล', 'เขียนหรือปรับสคริปต์ในตัวแก้โค้ด แล้วบันทึกเป็นปุ่มได้ทันที'),
    array('STYLE', 'Graphic Style คลิกเดียว', 'เก็บสไตล์ไว้ใช้ซ้ำ ครั้งต่อไปใส่ได้ทันทีโดยไม่ต้องเปิดไฟล์'),
    array('COLOR', 'ชุดสีและคอนทราสต์', 'วงล้อสีกับ Harmony วางพาเลตต์ลงเอกสารหรือ Swatches และตรวจค่าคอนทราสต์ WCAG'),
    array('KEEP', 'คลังชิ้นงาน', 'เก็บชิ้นงานที่เลือกไว้ แล้ววางกลับกลางอาร์ตบอร์ดในคลิกเดียว คลิปบอร์ดไม่ถูกแตะ'),
    array('AI', 'AI เขียนสคริปต์', 'บอกสิ่งที่ต้องการ แล้ว AI จะเขียนสคริปต์ให้ ใช้ AI กลางของเว็บ ไม่ต้องหา API key เอง'),
    array('MARKET', 'Marketplace', 'แชร์และดาวน์โหลด Script ชุดสี และ Graphic Style ของผู้ใช้คนอื่นเข้าพาเนลโดยตรง'),
);

rl_layout_start('Rocket Launcher · พาเนลสำหรับ Adobe Illustrator', array(
    'active' => 'home',
    'user' => $user,
    'csrf' => $csrf,
    'next' => 'index.php',
));
?>
<section class="hero<?php echo $video ? '' : ' hero--solo'; ?>">
    <div class="hero-copy">
        <p class="kicker">Adobe Illustrator panel</p>
        <h1>งานซ้ำ ๆ ใน Illustrator<br>จบในพาเนลเดียว</h1>
        <p class="lead">รวมสคริปต์เป็นปุ่มคลิกเดียว ใส่ Graphic Style ในคลิกเดียว จัดชุดสี เก็บชิ้นงานไว้วางซ้ำ และให้ AI เขียนสคริปต์ให้ โดยไม่ต้องมี API key ของตัวเอง</p>
        <div class="hero-actions">
            <?php if ($release) { ?>
                <a class="btn btn--primary btn--lg" href="download.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z"/></svg>
                    <span>ดาวน์โหลด .zxp</span>
                </a>
            <?php } else { ?>
                <span class="btn btn--lg is-disabled" aria-disabled="true">ไฟล์ติดตั้งกำลังจะมา</span>
            <?php } ?>
            <a class="btn btn--lg" href="market.php">เปิด Marketplace</a>
        </div>
        <p class="hero-meta">
            <?php if ($release) { ?>
                <span>v<?php echo rl_h($release['version']); ?></span>
                <span><?php echo rl_h(rl_size_text($release['bytes'])); ?></span>
                <span>อัปเดต <?php echo rl_h(rl_date_text($release['uploaded'])); ?></span>
            <?php } ?>
            <span>Illustrator 2021 ขึ้นไป · macOS และ Windows</span>
        </p>
    </div>
    <?php if ($video) { ?>
        <figure class="hero-video">
            <video autoplay muted loop playsinline controls preload="metadata" width="1920" height="1080" poster="<?php echo rl_h(rl_asset('', 'assets/intro-poster.jpg')); ?>" aria-label="วิดีโอแนะนำพาเนล Rocket Launcher (ไม่มีเสียง)">
                <source src="<?php echo rl_h(rl_asset('', 'assets/intro.webm')); ?>" type="video/webm">
                <source src="<?php echo rl_h(rl_asset('', 'assets/intro.mp4')); ?>" type="video/mp4">
            </video>
        </figure>
    <?php } ?>
</section>

<?php if ($release && !empty($release['notes'])) { ?>
    <p class="note release-note"><span class="kicker">มีอะไรใหม่ใน v<?php echo rl_h($release['version']); ?></span> <?php echo rl_h($release['notes']); ?></p>
<?php } ?>

<section class="features" aria-labelledby="features-title">
    <h2 id="features-title" class="section-title">ในพาเนลมีอะไรบ้าง</h2>
    <div class="feature-grid">
        <?php foreach ($features as $feature) { ?>
            <article class="feature">
                <p class="feature-tag"><?php echo rl_h($feature[0]); ?></p>
                <h3><?php echo rl_h($feature[1]); ?></h3>
                <p><?php echo rl_h($feature[2]); ?></p>
            </article>
        <?php } ?>
    </div>
</section>

<section class="install" aria-labelledby="install-title">
    <h2 id="install-title" class="section-title">ติดตั้งใน 3 ขั้น</h2>
    <ol class="steps">
        <li><strong>ดาวน์โหลดไฟล์ .zxp</strong><span>ใช้ปุ่มด้านบน ไฟล์เซ็นแล้ว</span></li>
        <li><strong>ติดตั้งด้วยโปรแกรมติดตั้ง ZXP</strong><span>เช่น ZXP Installer หรือ Anastasiy's Extension Manager แล้วเปิด Illustrator ใหม่</span></li>
        <li><strong>เปิดพาเนล</strong><span>เมนู Window › Extensions › Rocket Launcher ถ้าจะใช้ AI หรืออัปโหลด ให้กดเชื่อมต่อด้วย Google ในพาเนล</span></li>
    </ol>
</section>

<section class="market-teaser">
    <div>
        <p class="kicker">Marketplace</p>
        <h2 class="section-title">ตอนนี้มี <?php echo (int) $itemCount; ?> รายการให้ดาวน์โหลด</h2>
        <p class="lead">Script ชุดสี และ Graphic Style จากผู้ใช้ แต่ละรายการอยู่ <?php echo (int) $settings['life_days']; ?> วันและต่ออายุทุกครั้งที่มีคนโหลด</p>
    </div>
    <a class="btn btn--lg" href="market.php">ดูทั้งหมด</a>
</section>
<?php
rl_layout_end('');
