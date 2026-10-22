<?php
/*
 * A stand-in for api.replicate.com used by tests/run.php (router script for
 * PHP's built-in server). Every request is appended to FAKE_REPLICATE_LOG.
 *
 * Prompts steer the answer: "slow" -> processing (finished on the next poll),
 * "fail" -> failed, "credit" -> 402, anything else -> succeeded at once.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$log = getenv('FAKE_REPLICATE_LOG');
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$body = file_get_contents('php://input');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($log) {
    file_put_contents($log, json_encode(array(
        'method' => $_SERVER['REQUEST_METHOD'],
        'path' => $path,
        'auth' => $auth,
        'prefer' => isset($_SERVER['HTTP_PREFER']) ? $_SERVER['HTTP_PREFER'] : '',
        'body' => json_decode((string) $body, true),
    )) . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
if ($auth !== 'Bearer r8_test_key_0123456789abcdef') {
    http_response_code(401);
    echo json_encode(array('detail' => 'Unauthenticated'));
    return true;
}

$answer = '{"name": "Circle", "message": "Drew a circle", "code": "app.activeDocument.pathItems.ellipse(0, 0, 50, 50);"}';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/v1/account') {
    echo json_encode(array('username' => 'tester', 'type' => 'user'));
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('#^/v1/models/[^/]+/[^/]+/predictions$#', $path)) {
    $data = json_decode((string) $body, true);
    $prompt = isset($data['input']['prompt']) ? (string) $data['input']['prompt'] : '';
    if (strpos($prompt, 'credit') !== false) {
        http_response_code(402);
        echo json_encode(array('detail' => 'You have insufficient credit. Key r8_test_key_0123456789abcdef'));
        return true;
    }
    if (strpos($prompt, 'fail') !== false) {
        http_response_code(201);
        echo json_encode(array('id' => 'failedpred01', 'status' => 'failed', 'error' => 'model crashed'));
        return true;
    }
    if (strpos($prompt, 'slow') !== false) {
        http_response_code(201);
        echo json_encode(array('id' => 'slowpred0001', 'status' => 'processing', 'urls' => array('get' => 'https://api.replicate.com/v1/predictions/slowpred0001')));
        return true;
    }
    http_response_code(201);
    echo json_encode(array('id' => 'quickpred001', 'status' => 'succeeded', 'output' => array('{"name": "Circle", ', substr($answer, strlen('{"name": "Circle", ')))));
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^/v1/predictions/([A-Za-z0-9]+)$#', $path, $m)) {
    echo json_encode(array('id' => $m[1], 'status' => 'succeeded', 'output' => $answer));
    return true;
}

http_response_code(404);
echo json_encode(array('detail' => 'Not found'));
return true;
