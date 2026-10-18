<?php

require dirname(__DIR__) . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    rl_json(array('ok' => false, 'error' => 'Method not allowed'), 405);
}

if (rl_config() === null) {
    rl_json(array('ok' => false, 'error' => 'Marketplace is not set up'), 503);
}

$catalog = rl_catalog();
$items = array();
foreach ($catalog['items'] as $item) {
    if (!isset($item['id'], $item['type'], $item['name'], $item['filename'])) {
        continue;
    }
    $items[] = rl_public_item($item);
}

usort($items, function ($a, $b) {
    return strcmp($b['created'], $a['created']);
});

rl_json(array('ok' => true, 'items' => $items), 200);
