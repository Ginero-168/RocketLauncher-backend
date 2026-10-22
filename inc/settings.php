<?php
// Marketplace settings changed on the admin page (storage/settings.json).
// Secrets (admin token, Google secret, AI key) stay in config.json.

function rl_settings_path()
{
    return rl_storage_dir() . '/settings.json';
}

// name => array(kind, default, min, max)
function rl_setting_rules()
{
    return array(
        'life_days' => array('int', 10, 1, 365),
        'max_script_mb' => array('int', 2, 1, 20),
        'max_style_mb' => array('int', 40, 1, 200),
        'max_preview_mb' => array('int', 2, 1, 10),
        'uploads_per_day' => array('int', 20, 1, 1000),
        'storage_mb' => array('int', 2048, 1, 200000),
        'allow_script' => array('bool', true),
        'allow_color' => array('bool', true),
        'allow_style' => array('bool', true),
        'maintenance' => array('bool', false),
        'panel_starts_per_10min' => array('int', 20, 1, 500),
        'trust_forwarded_for' => array('bool', false),
        'ai_enabled' => array('bool', true),
        'ai_model' => array('model', 'google/gemini-3-flash'),
        'ai_per_member_daily' => array('int', 30, 0, 10000),
        'ai_total_daily' => array('int', 500, 0, 1000000),
        'ai_max_prompt_kb' => array('int', 60, 4, 500),
        'release_max_mb' => array('int', 50, 1, 500),
    );
}

function rl_clean_setting($rule, $value)
{
    if ($rule[0] === 'bool') {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'on', 'yes'), true);
    }
    if ($rule[0] === 'int') {
        if (!is_numeric($value)) {
            return $rule[1];
        }
        return max($rule[2], min($rule[3], (int) $value));
    }
    if ($rule[0] === 'model') {
        $value = trim((string) $value);
        return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $value) ? $value : $rule[1];
    }
    return $rule[1];
}

function rl_settings()
{
    $saved = rl_read_json_file(rl_settings_path(), array());
    if ($saved === null) {
        $saved = rl_read_json_file(rl_settings_path() . '.bak', array());
        error_log('Rocket Launcher: settings.json is damaged, using ' . (is_array($saved) ? 'its backup' : 'the defaults'));
        if (!is_array($saved)) {
            $saved = array();
        }
    }
    $config = rl_config_raw();
    $out = array();
    foreach (rl_setting_rules() as $name => $rule) {
        $value = $rule[1];
        // Limits that lived in config.json before the admin page existed.
        if ($name === 'uploads_per_day' && isset($config['max_uploads_per_day'])) {
            $value = $config['max_uploads_per_day'];
        }
        if ($name === 'storage_mb' && isset($config['max_storage_mb'])) {
            $value = $config['max_storage_mb'];
        }
        if (array_key_exists($name, $saved)) {
            $value = $saved[$name];
        }
        $out[$name] = rl_clean_setting($rule, $value);
    }
    return $out;
}

// Merges $patch into the saved settings; unknown names are ignored and every
// value is clamped to its range. Returns the new settings, or null.
function rl_save_settings($patch)
{
    $saved = rl_locked(function () use ($patch) {
        $current = rl_settings();
        foreach (rl_setting_rules() as $name => $rule) {
            if (array_key_exists($name, $patch)) {
                $current[$name] = rl_clean_setting($rule, $patch[$name]);
            }
        }
        return rl_write_json_file(rl_settings_path(), $current) ? $current : null;
    });
    return is_array($saved) ? $saved : null;
}

// PHP's own upload ceiling on this host, in bytes (0 = unknown).
function rl_php_upload_limit()
{
    $limits = array();
    foreach (array('upload_max_filesize', 'post_max_size') as $key) {
        $raw = trim((string) ini_get($key));
        if ($raw === '') {
            continue;
        }
        $number = (float) $raw;
        $unit = strtolower(substr($raw, -1));
        if ($unit === 'g') {
            $number *= 1024 * 1024 * 1024;
        } elseif ($unit === 'm') {
            $number *= 1024 * 1024;
        } elseif ($unit === 'k') {
            $number *= 1024;
        }
        if ($number > 0) {
            $limits[] = (int) $number;
        }
    }
    return $limits ? min($limits) : 0;
}
