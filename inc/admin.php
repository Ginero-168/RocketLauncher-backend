<?php
// Who may open admin.php: Google accounts listed in config.json admin_ids.
// A signed-in member becomes an admin once by entering the setup token.

function rl_admin_ids()
{
    $config = rl_config_raw();
    $ids = isset($config['admin_ids']) && is_array($config['admin_ids']) ? $config['admin_ids'] : array();
    $out = array();
    foreach ($ids as $id) {
        if (is_string($id) && $id !== '') {
            $out[] = $id;
        }
    }
    return array_values(array_unique($out));
}

function rl_is_admin($user)
{
    return is_array($user) && !empty($user['id']) && in_array($user['id'], rl_admin_ids(), true);
}

// 'ok', 'wrong', 'limited' or 'error'. Five tries per 10 minutes per address.
function rl_claim_admin($user, $token)
{
    if (!is_array($user) || empty($user['id'])) {
        return 'error';
    }
    if (rl_rate_limited('admin-claim', 5, 600)) {
        return 'limited';
    }
    $config = rl_config();
    if ($config === null || !is_string($token) || $token === '' || !hash_equals($config['upload_token'], $token)) {
        return 'wrong';
    }
    $ids = rl_admin_ids();
    if (!in_array($user['id'], $ids, true)) {
        $ids[] = $user['id'];
    }
    return rl_update_config(array('admin_ids' => $ids)) ? 'ok' : 'error';
}

// The last admin cannot be removed (the setup token can always add one back).
function rl_remove_admin($userId)
{
    $ids = rl_admin_ids();
    $kept = array();
    foreach ($ids as $id) {
        if ($id !== $userId) {
            $kept[] = $id;
        }
    }
    if (count($kept) === 0 || count($kept) === count($ids)) {
        return false;
    }
    return rl_update_config(array('admin_ids' => $kept));
}

function rl_admin_log_path()
{
    return rl_storage_dir() . '/admin-log.json';
}

// Keeps the last 300 admin actions: who, what, when.
function rl_admin_log($user, $action, $detail)
{
    $entry = array(
        'time' => gmdate('c'),
        'user' => is_array($user) && isset($user['name']) ? rl_clean_text($user['name'], 80) : '',
        'action' => rl_clean_text($action, 60),
        'detail' => rl_clean_text($detail, 200),
    );
    rl_locked(function () use ($entry) {
        $data = rl_read_json_file(rl_admin_log_path(), array('entries' => array()));
        if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
            $data = array('entries' => array());
        }
        $data['entries'][] = $entry;
        if (count($data['entries']) > 300) {
            $data['entries'] = array_slice($data['entries'], -300);
        }
        return rl_write_json_file(rl_admin_log_path(), $data);
    });
}

function rl_admin_log_entries($limit)
{
    $data = rl_read_json_file(rl_admin_log_path(), array('entries' => array()));
    $entries = is_array($data) && isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();
    return array_reverse(array_slice($entries, -$limit));
}

// Numbers for the admin overview.
function rl_admin_overview()
{
    $items = rl_catalog()['items'];
    $types = array('script' => 0, 'color' => 0, 'style' => 0);
    foreach ($items as $item) {
        $type = rl_normalize_type(isset($item['type']) ? $item['type'] : '');
        if (isset($types[$type])) {
            $types[$type]++;
        }
    }
    $panels = 0;
    $now = time();
    foreach (rl_panel_store()['sessions'] as $session) {
        if (isset($session['expires']) && (int) $session['expires'] > $now) {
            $panels++;
        }
    }
    $usage = rl_ai_usage();
    return array(
        'items' => count($items),
        'types' => $types,
        'storage' => rl_storage_used(),
        'members' => count(rl_users()['users']),
        'panels' => $panels,
        'ai_today' => isset($usage['total']) ? (int) $usage['total'] : 0,
    );
}
