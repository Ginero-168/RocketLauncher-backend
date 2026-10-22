<?php
// The panel download on the homepage: one signed .zxp uploaded on the admin
// page (storage/release.json + storage/releases/).

function rl_release_path()
{
    return rl_storage_dir() . '/release.json';
}

function rl_release_dir()
{
    return rl_storage_dir() . '/releases';
}

function rl_release()
{
    $data = rl_read_json_file(rl_release_path(), null);
    if (!is_array($data) || empty($data['stored']) || empty($data['version'])) {
        return null;
    }
    $path = rl_storage_dir() . '/' . $data['stored'];
    if (strpos((string) $data['stored'], 'releases/') !== 0 || !is_file($path)) {
        return null;
    }
    return $data;
}

function rl_release_file($release)
{
    return rl_storage_dir() . '/' . $release['stored'];
}

function rl_release_name($release)
{
    return 'RocketLauncher-v' . $release['version'] . '.zxp';
}

// What is inside a .zxp: the bundle id and version from CSXS/manifest.xml,
// and whether it carries a signature. Null when it is not a readable ZXP.
function rl_inspect_zxp($path)
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }
    $head = fread($handle, 4);
    fclose($handle);
    if ($head !== "PK\x03\x04" || !class_exists('ZipArchive')) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return null;
    }
    $manifest = $zip->getFromName('CSXS/manifest.xml');
    $signed = $zip->locateName('META-INF/signatures.xml') !== false;
    $zip->close();
    if (!is_string($manifest)) {
        return null;
    }
    $id = preg_match('/ExtensionBundleId="([^"]+)"/', $manifest, $m) ? $m[1] : '';
    $version = preg_match('/ExtensionBundleVersion="([^"]+)"/', $manifest, $v) ? $v[1] : '';
    return array('id' => $id, 'version' => $version, 'signed' => $signed);
}

// Stores an uploaded .zxp as the current download. array('ok' => ..., 'error' | 'release').
function rl_store_release($file, $notes, $settings)
{
    if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'error' => 'อัปโหลดไม่สำเร็จ ไฟล์อาจใหญ่เกินที่เซิร์ฟเวอร์รับได้');
    }
    $name = isset($file['name']) ? (string) $file['name'] : '';
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zxp') {
        return array('ok' => false, 'error' => 'ต้องเป็นไฟล์ .zxp');
    }
    $bytes = isset($file['size']) ? (int) $file['size'] : 0;
    if ($bytes < 1 || $bytes > (int) $settings['release_max_mb'] * 1024 * 1024) {
        return array('ok' => false, 'error' => 'ไฟล์ว่างหรือใหญ่เกิน ' . (int) $settings['release_max_mb'] . ' MB');
    }
    $info = rl_inspect_zxp($file['tmp_name']);
    if ($info === null) {
        return array('ok' => false, 'error' => 'เปิดไฟล์ .zxp ไม่ได้ หรือไม่มี CSXS/manifest.xml');
    }
    if ($info['id'] !== 'com.tata.pro') {
        return array('ok' => false, 'error' => 'ไฟล์นี้ไม่ใช่ Rocket Launcher (bundle ' . $info['id'] . ')');
    }
    if (!$info['signed']) {
        return array('ok' => false, 'error' => 'ไฟล์ยังไม่ได้เซ็น สร้างด้วย npm run build:zxp ก่อน');
    }
    if (!preg_match('/^\d+\.\d+\.\d+$/', $info['version'])) {
        return array('ok' => false, 'error' => 'เลขเวอร์ชันใน manifest ไม่ถูกต้อง');
    }
    if (!is_dir(rl_release_dir()) && !mkdir(rl_release_dir(), 0755, true)) {
        return array('ok' => false, 'error' => 'เขียนโฟลเดอร์ storage/releases ไม่ได้');
    }
    $stored = 'releases/RocketLauncher-v' . $info['version'] . '-' . bin2hex(random_bytes(4)) . '.zxp';
    if (!move_uploaded_file($file['tmp_name'], rl_storage_dir() . '/' . $stored)) {
        return array('ok' => false, 'error' => 'บันทึกไฟล์ไม่ได้');
    }
    $release = array(
        'version' => $info['version'],
        'stored' => $stored,
        'bytes' => $bytes,
        'sha256' => hash_file('sha256', rl_storage_dir() . '/' . $stored),
        'uploaded' => gmdate('c'),
        'notes' => rl_clean_text($notes, 300),
        'downloads' => 0,
    );
    $previous = rl_release();
    $saved = rl_locked(function () use ($release) {
        return rl_write_json_file(rl_release_path(), $release);
    });
    if ($saved !== true) {
        @unlink(rl_storage_dir() . '/' . $stored);
        return array('ok' => false, 'error' => 'บันทึกข้อมูลเวอร์ชันไม่ได้');
    }
    if ($previous && $previous['stored'] !== $stored) {
        @unlink(rl_release_file($previous));
    }
    return array('ok' => true, 'release' => $release);
}

// Counted once per visitor per day, like Marketplace downloads.
function rl_count_release_download($visitor)
{
    rl_locked(function () use ($visitor) {
        $data = rl_read_json_file(rl_release_path(), null);
        if (!is_array($data)) {
            return false;
        }
        $today = gmdate('Y-m-d');
        $seen = isset($data['seen'], $data['seen_day']) && $data['seen_day'] === $today && is_array($data['seen']) ? $data['seen'] : array();
        if (in_array($visitor, $seen, true)) {
            return true;
        }
        if (count($seen) < 5000) {
            $seen[] = $visitor;
        }
        $data['seen_day'] = $today;
        $data['seen'] = $seen;
        $data['downloads'] = (isset($data['downloads']) ? (int) $data['downloads'] : 0) + 1;
        return rl_write_json_file(rl_release_path(), $data);
    });
}
