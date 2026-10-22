<?php

require dirname(__DIR__) . '/lib.php';

// Public facts the panel and the homepage read: limits, upload switches,
// whether the central AI is on, and the current panel download.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}
if (rl_config() === null) {
    rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
}

$settings = rl_settings();
$release = rl_release();
rl_json_revalidated(array(
    'ok' => true,
    'life_days' => $settings['life_days'],
    'maintenance' => $settings['maintenance'],
    'limits' => array(
        'script_mb' => $settings['max_script_mb'],
        'style_mb' => $settings['max_style_mb'],
        'preview_mb' => $settings['max_preview_mb'],
        'uploads_per_day' => $settings['uploads_per_day'],
    ),
    'uploads' => array(
        'script' => $settings['allow_script'],
        'color' => $settings['allow_color'],
        'style' => $settings['allow_style'],
    ),
    'ai' => array(
        'available' => rl_ai_available($settings),
        'model' => $settings['ai_model'],
        'per_member_daily' => $settings['ai_per_member_daily'],
    ),
    'release' => $release ? array(
        'version' => $release['version'],
        'bytes' => (int) $release['bytes'],
        'sha256' => $release['sha256'],
        'uploaded' => $release['uploaded'],
        'notes' => isset($release['notes']) ? $release['notes'] : '',
        'url' => rl_base_url() . '/download.php',
    ) : null,
));
