<?php
// The central AI: panels send a prompt, this server asks Replicate with the
// admin's key (config.json replicate_api_token). The key never leaves the
// server, prompts are capped, and every member has a daily quota.

function rl_ai_token()
{
    $config = rl_config_raw();
    return isset($config['replicate_api_token']) && is_string($config['replicate_api_token']) ? trim($config['replicate_api_token']) : '';
}

// Tests point this at a local fake; nothing on the admin page can change it.
function rl_replicate_base()
{
    $config = rl_config_raw();
    if (isset($config['replicate_base_url']) && is_string($config['replicate_base_url']) && rl_valid_base_url($config['replicate_base_url'])) {
        return rtrim($config['replicate_base_url'], '/');
    }
    return 'https://api.replicate.com';
}

function rl_ai_available($settings)
{
    return !empty($settings['ai_enabled']) && rl_ai_token() !== '';
}

function rl_ai_usage_path()
{
    return rl_storage_dir() . '/ai.json';
}

// Today's counters (a day starts at midnight in Thailand, UTC+7, so "try
// again tomorrow" means what it says) plus predictions from the last day.
function rl_ai_usage()
{
    $data = rl_read_json_file(rl_ai_usage_path(), array());
    if (!is_array($data)) {
        $data = array();
    }
    $today = gmdate('Y-m-d', time() + 7 * 3600);
    if (!isset($data['day']) || $data['day'] !== $today) {
        $data['day'] = $today;
        $data['members'] = array();
        $data['total'] = 0;
    }
    if (!isset($data['members']) || !is_array($data['members'])) {
        $data['members'] = array();
    }
    if (!isset($data['predictions']) || !is_array($data['predictions'])) {
        $data['predictions'] = array();
    }
    $data['total'] = isset($data['total']) ? (int) $data['total'] : 0;
    return $data;
}

function rl_ai_used_today($userId)
{
    $usage = rl_ai_usage();
    return isset($usage['members'][$userId]) ? (int) $usage['members'][$userId] : 0;
}

// 'ok', 'member' (this member's quota is used up) or 'total' (the site's).
function rl_ai_reserve($userId, $settings)
{
    $result = rl_locked(function () use ($userId, $settings) {
        $usage = rl_ai_usage();
        $mine = isset($usage['members'][$userId]) ? (int) $usage['members'][$userId] : 0;
        if ($mine >= (int) $settings['ai_per_member_daily']) {
            return 'member';
        }
        if ($usage['total'] >= (int) $settings['ai_total_daily']) {
            return 'total';
        }
        $usage['members'][$userId] = $mine + 1;
        $usage['total']++;
        return rl_write_json_file(rl_ai_usage_path(), $usage) ? 'ok' : 'error';
    });
    return is_string($result) ? $result : 'error';
}

// A request Replicate never accepted does not count.
function rl_ai_refund($userId)
{
    rl_locked(function () use ($userId) {
        $usage = rl_ai_usage();
        if (!empty($usage['members'][$userId])) {
            $usage['members'][$userId]--;
            $usage['total'] = max(0, $usage['total'] - 1);
        }
        return rl_write_json_file(rl_ai_usage_path(), $usage);
    });
}

function rl_ai_remember($predictionId, $userId)
{
    rl_locked(function () use ($predictionId, $userId) {
        $usage = rl_ai_usage();
        $now = time();
        foreach ($usage['predictions'] as $id => $row) {
            if (!is_array($row) || !isset($row['created']) || $now - (int) $row['created'] > 86400) {
                unset($usage['predictions'][$id]);
            }
        }
        $usage['predictions'][$predictionId] = array('user' => $userId, 'created' => $now);
        return rl_write_json_file(rl_ai_usage_path(), $usage);
    });
}

function rl_ai_owner($predictionId)
{
    $usage = rl_ai_usage();
    return isset($usage['predictions'][$predictionId]['user']) ? (string) $usage['predictions'][$predictionId]['user'] : '';
}

// The same request the panel used to send itself, with the system prompt and
// limits fixed here so the key only ever answers Illustrator script requests.
function rl_ai_input($model, $prompt)
{
    $input = array(
        'prompt' => $prompt,
        'system_prompt' => 'You are an Adobe Illustrator JSX expert. Return JSON: { "name": "Short Script Name (2-4 words)", "message": "...", "code": "..." }. Use ES3 JS only.',
        'max_tokens' => 2048,
        'temperature' => 0.4,
    );
    if (strpos($model, 'gemini') !== false) {
        $input['system_instruction'] = $input['system_prompt'];
        $input['max_output_tokens'] = $input['max_tokens'];
        $input['thinking_level'] = 'low';
        unset($input['system_prompt'], $input['max_tokens']);
    }
    return $input;
}

function rl_replicate($method, $path, $body, $waitSeconds)
{
    $headers = array(
        'Authorization: Bearer ' . rl_ai_token(),
        'Content-Type: application/json',
    );
    if ($waitSeconds > 0) {
        $headers[] = 'Prefer: wait=' . (int) $waitSeconds;
    }
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'data' => null);
    }
    $ch = curl_init(rl_replicate_base() . $path);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $waitSeconds + 15,
    ));
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return array('status' => $raw === false ? 0 : $status, 'data' => is_array($data) ? $data : null);
}

// Replicate's answer as the panel sees it. Messages never include the key or
// Replicate's own error text.
function rl_ai_reply($response)
{
    $status = $response['status'];
    $data = $response['data'];
    if ($status === 401 || $status === 403) {
        return array('http' => 503, 'body' => array('ok' => false, 'error' => 'AI is not set up'));
    }
    if ($status === 402) {
        return array('http' => 503, 'body' => array('ok' => false, 'error' => 'AI credit has run out'));
    }
    if ($status === 429) {
        return array('http' => 503, 'body' => array('ok' => false, 'error' => 'AI is busy, try again in a minute'));
    }
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['id']) || !preg_match('/^[A-Za-z0-9]{6,64}$/', (string) $data['id'])) {
        return array('http' => 502, 'body' => array('ok' => false, 'error' => 'AI request failed'));
    }
    $state = isset($data['status']) ? (string) $data['status'] : '';
    if ($state === 'succeeded') {
        $output = isset($data['output']) ? $data['output'] : '';
        $text = is_array($output) ? implode('', array_map('strval', $output)) : (string) $output;
        return array('http' => 200, 'body' => array('ok' => true, 'status' => 'succeeded', 'id' => (string) $data['id'], 'output' => $text));
    }
    if ($state === 'failed' || $state === 'canceled') {
        return array('http' => 502, 'body' => array('ok' => false, 'error' => 'AI could not answer this request'));
    }
    return array('http' => 200, 'body' => array('ok' => true, 'status' => 'processing', 'id' => (string) $data['id']));
}
