<?php

require dirname(__DIR__) . '/lib.php';

// Central AI for connected panels.
//   POST action=start  panel_token, prompt  -> succeeded with output, or processing with id
//   POST action=poll   panel_token, id      -> the same answer for a running request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    rl_send_cors();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

$user = rl_panel_user(isset($_POST['panel_token']) ? (string) $_POST['panel_token'] : '');
if (!$user) {
    rl_json(array('ok' => false, 'error' => 'Panel session expired'), 401);
}
$settings = rl_settings();
if (empty($settings['ai_enabled'])) {
    rl_json(array('ok' => false, 'error' => 'AI is turned off'), 503);
}
if (rl_ai_token() === '') {
    rl_json(array('ok' => false, 'error' => 'AI is not set up'), 503);
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

if ($action === 'poll') {
    $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
    if (!preg_match('/^[A-Za-z0-9]{6,64}$/', $id) || rl_ai_owner($id) !== $user['id']) {
        rl_json(array('ok' => false, 'error' => 'Request not found'), 404);
    }
    $reply = rl_ai_reply(rl_replicate('GET', '/v1/predictions/' . $id, null, 0));
    rl_json($reply['body'], $reply['http']);
}

if ($action !== 'start') {
    rl_json(array('ok' => false, 'error' => 'Unknown action'), 400);
}

$prompt = isset($_POST['prompt']) ? trim((string) $_POST['prompt']) : '';
if ($prompt === '') {
    rl_json(array('ok' => false, 'error' => 'Prompt is empty'), 400);
}
if (strlen($prompt) > $settings['ai_max_prompt_kb'] * 1024) {
    rl_json(array('ok' => false, 'error' => 'Prompt is too long'), 413);
}

$reserved = rl_ai_reserve($user['id'], $settings);
if ($reserved === 'member') {
    rl_json(array('ok' => false, 'error' => 'Daily AI limit reached: ' . $settings['ai_per_member_daily'] . ' requests'), 429);
}
if ($reserved === 'total') {
    rl_json(array('ok' => false, 'error' => 'AI is busy today, try again tomorrow'), 429);
}
if ($reserved !== 'ok') {
    rl_json(array('ok' => false, 'error' => 'Storage is not writable'), 500);
}

$model = $settings['ai_model'];
$response = rl_replicate('POST', '/v1/models/' . $model . '/predictions', array('input' => rl_ai_input($model, $prompt)), 25);
$reply = rl_ai_reply($response);
if (empty($reply['body']['ok'])) {
    rl_ai_refund($user['id']);
    rl_json($reply['body'], $reply['http']);
}
if ($reply['body']['status'] === 'processing') {
    rl_ai_remember($reply['body']['id'], $user['id']);
}
$reply['body']['model'] = $model;
$reply['body']['remaining'] = max(0, $settings['ai_per_member_daily'] - rl_ai_used_today($user['id']));
rl_json($reply['body'], $reply['http']);
