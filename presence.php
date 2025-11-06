<?php
// presence.php - clients ping every 20s to register presence
require_once __DIR__ . '/util.php';
header('Content-Type: application/json; charset=utf-8');

ensure_files();

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$clientId = $body['clientId'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? ($body['ua'] ?? '');

if ($clientId === '') { echo json_encode(['ok'=>false]); exit; }

$presence = load_json_file(PRES_PATH, []);
if (!is_array($presence)) $presence = [];

$presence[$clientId] = [
    'ua' => $ua,
    'last_seen' => time()
];

// cleanup older than 2 minutes
foreach ($presence as $id => $info) {
    if (time() - ($info['last_seen'] ?? 0) > 120) unset($presence[$id]);
}

save_json_file(PRES_PATH, $presence);
echo json_encode(['ok'=>true,'count'=>count($presence)], JSON_UNESCAPED_UNICODE);
