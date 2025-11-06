<?php
// util.php
// Gemeinsame Helfer: Pfade, Laden/Speichern von JSON-Dateien, Logs, Requests, touch_change()

// ---------- Pfade ----------
define('INV_PATH', __DIR__ . '/inventar.json');   // dein Inventar (Map: "Name":anzahl)
define('LOG_PATH', __DIR__ . '/log.json');       // Änderungshistorie
define('REQUESTS_PATH', __DIR__ . '/requests.json'); // Requests / Lieferwünsche
define('PRES_PATH', __DIR__ . '/presence.json'); // Presence heartbeat
define('SSE_FLAG', __DIR__ . '/.sse_trigger');   // Trigger-Flag für SSE

// ---------- Ensure basic files exist ----------
function ensure_files() {
    if (!file_exists(INV_PATH)) file_put_contents(INV_PATH, "{}");
    if (!file_exists(LOG_PATH)) file_put_contents(LOG_PATH, "[]");
    if (!file_exists(REQUESTS_PATH)) file_put_contents(REQUESTS_PATH, "[]");
    if (!file_exists(PRES_PATH)) file_put_contents(PRES_PATH, "{}");
}

// ---------- Utility: safe JSON loader / saver ----------
function load_json_file($path, $fallback) {
    if (!file_exists($path)) return $fallback;
    $txt = @file_get_contents($path);
    $data = json_decode($txt, true);
    return is_array($data) ? $data : $fallback;
}
function save_json_file($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // LOCK_EX to avoid race conditions
    file_put_contents($path, $json, LOCK_EX);
}

// ---------- Inventory ----------
function load_inventory() {
    ensure_files();
    $inv = load_json_file(INV_PATH, []);
    // Migration: if file contains array of {name,qty} -> convert to map
    if (is_array($inv) && isset($inv[0]) && isset($inv[0]['name'])) {
        $map = [];
        foreach ($inv as $row) {
            $map[$row['name']] = intval($row['qty'] ?? 0);
        }
        save_inventory($map);
        return $map;
    }
    return $inv;
}
function save_inventory($inv) {
    ensure_files();
    save_json_file(INV_PATH, $inv);
}

// ---------- Logs ----------
function load_logs() {
    ensure_files();
    return load_json_file(LOG_PATH, []);
}
function append_log($action, $item, $value = null, $clientId = null) {
    ensure_files();
    $logs = load_json_file(LOG_PATH, []);
    $logs[] = [
        'time'   => date('c'),
        'action' => $action,   // e.g. add, update, set, delete, request_create, request_fulfill
        'item'   => $item,
        'value'  => $value,
        'client' => $clientId
    ];
    save_json_file(LOG_PATH, $logs);
}

// ---------- Requests ----------
function load_requests() {
    ensure_files();
    return load_json_file(REQUESTS_PATH, []);
}
function save_requests($arr) {
    ensure_files();
    save_json_file(REQUESTS_PATH, $arr);
}

// ---------- Presence heartbeat ----------
function load_presence() {
    ensure_files();
    return load_json_file(PRES_PATH, []);
}
function save_presence($arr) {
    save_json_file(PRES_PATH, $arr);
}

// ---------- Small id helper ----------
function uuid_short() {
    try { return bin2hex(random_bytes(8)); }
    catch (Exception $e) { return substr(str_replace('.', '', uniqid('', true)), 0, 16); }
}

// ---------- touch_change: macht Änderungen zuverlässig sichtbar für SSE ----------
function touch_change() {
    // touch inventar and requests and log file times and write a small flag file
    if (file_exists(INV_PATH)) @touch(INV_PATH);
    if (file_exists(LOG_PATH)) @touch(LOG_PATH);
    if (file_exists(REQUESTS_PATH)) @touch(REQUESTS_PATH);
    @file_put_contents(SSE_FLAG, time(), LOCK_EX);
    clearstatcache(true, INV_PATH);
    clearstatcache(true, LOG_PATH);
    clearstatcache(true, REQUESTS_PATH);
}
