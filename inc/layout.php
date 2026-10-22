<?php
// The frame every page shares: head, header (brand, navigation, account)
// and footer. Pages call rl_layout_start() after rl_page_headers().

function rl_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// A static file with its modification time, so a deploy is never hidden
// behind a cached stylesheet.
function rl_asset($base, $path)
{
    $file = rl_base_dir() . '/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return $base . $path . '?v=' . $version;
}

function rl_size_text($bytes)
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

function rl_date_text($iso)
{
    $time = strtotime((string) $iso);
    if ($time === false) {
        return '';
    }
    $months = array('ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.');
    $local = $time + 7 * 3600;
    return (int) gmdate('j', $local) . ' ' . $months[(int) gmdate('n', $local) - 1] . ' ' . gmdate('Y', $local);
}

// $opts: base ('' or '../'), active (home|market|admin|account), user, csrf,
// next (where login returns to), description.
function rl_layout_start($title, $opts)
{
    $base = isset($opts['base']) ? $opts['base'] : '';
    $active = isset($opts['active']) ? $opts['active'] : '';
    $user = isset($opts['user']) ? $opts['user'] : null;
    $csrf = isset($opts['csrf']) ? $opts['csrf'] : '';
    $next = isset($opts['next']) ? $opts['next'] : 'index.php';
    $description = isset($opts['description']) ? $opts['description'] : 'Rocket Launcher: พาเนล Adobe Illustrator สำหรับสคริปต์ ปุ่มลัด Graphic Style ชุดสี และ AI';
    $links = array(
        'home' => array('index.php', 'หน้าแรก'),
        'market' => array('market.php', 'Marketplace'),
    );
    if ($user && rl_is_admin($user)) {
        $links['admin'] = array('admin.php', 'Admin');
    }

    echo '<!doctype html><html lang="th"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . rl_h($title) . '</title>';
    echo '<meta name="description" content="' . rl_h($description) . '">';
    echo '<meta name="theme-color" content="#0a0a0a">';
    echo '<link rel="stylesheet" href="' . rl_h(rl_asset($base, 'assets/site.css')) . '">';
    echo '<link rel="icon" href="' . rl_h($base) . 'assets/icon.svg" type="image/svg+xml">';
    echo '</head><body class="page-' . rl_h($active) . '">';
    echo '<a class="skip" href="#main">ข้ามไปเนื้อหา</a>';
    echo '<header class="site-head"><div class="wrap head-row">';
    echo '<a class="brand" href="' . rl_h($base) . 'index.php" aria-label="Rocket Launcher หน้าแรก"><span class="brand-mark" aria-hidden="true">R/L</span><span class="brand-name">Rocket Launcher</span></a>';
    echo '<nav class="site-nav" aria-label="เมนูหลัก">';
    foreach ($links as $key => $link) {
        $current = $key === $active ? ' aria-current="page"' : '';
        echo '<a href="' . rl_h($base . $link[0]) . '"' . $current . '>' . rl_h($link[1]) . '</a>';
    }
    echo '</nav><div class="site-account">';
    if ($user) {
        if (!empty($user['picture']) && rl_google_picture($user['picture']) !== '') {
            echo '<img class="avatar" src="' . rl_h($user['picture']) . '" alt="" width="28" height="28" referrerpolicy="no-referrer">';
        }
        echo '<span class="who">' . rl_h(isset($user['name']) ? $user['name'] : 'Member') . '</span>';
        echo '<form class="logout" method="post" action="' . rl_h($base) . 'auth/logout.php">';
        echo '<input type="hidden" name="csrf" value="' . rl_h($csrf) . '">';
        echo '<button class="btn btn--sm" type="submit">ออกจากระบบ</button></form>';
    } else {
        echo '<a class="btn btn--sm btn--primary" href="' . rl_h($base) . 'auth/login.php?next=' . rl_h(rawurlencode($next)) . '">เข้าสู่ระบบด้วย Google</a>';
    }
    echo '</div></div></header><main id="main" class="wrap">';
}

function rl_layout_end($base)
{
    echo '</main><footer class="site-foot"><div class="wrap foot-row">';
    echo '<span class="brand-mark brand-mark--sm" aria-hidden="true">R/L</span>';
    echo '<span>Rocket Launcher · พาเนลสำหรับ Adobe Illustrator</span>';
    echo '<a href="' . rl_h($base) . 'market.php">Marketplace</a>';
    echo '</div></footer></body></html>';
}
