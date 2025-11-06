<?php
// api.php – JSON API für Inventar & Requests
require_once __DIR__ . '/util.php';
header('Content-Type: application/json; charset=utf-8');

ensure_files();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$clientId = $body['clientId'] ?? ($_GET['clientId'] ?? null);

// small helpers
function ok($data = []) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg, $code = 400) { http_response_code($code); echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE); exit; }

switch ($action) {

    // ---------- GET: whole state ----------
    case 'get':
        ok([
            'inventory' => load_inventory(),
            'requests'  => load_requests(),
            'logs'      => load_logs()
        ]);
        break;

    // ---------- Logs ----------
    case 'logs':
        ok(['logs' => load_logs()]);
        break;

    // ---------- Inventory: add item (name must be unique) ----------
    case 'add':
        if ($method !== 'POST') err('POST required');
        $name = trim($body['name'] ?? '');
        $qty = max(0, intval($body['qty'] ?? 0));
        if ($name === '') err('name required');
        $inv = load_inventory();
        if (array_key_exists($name, $inv)) err('Item exists');
        $inv[$name] = $qty;
        save_inventory($inv);
        append_log('add', $name, $qty, $clientId);
        touch_change();
        ok(['status' => 'ok', 'inventory' => $inv]);
        break;

    // ---------- Inventory: update (delta +/-) ----------
    case 'update':
        if ($method !== 'POST') err('POST required');
        $name = $body['name'] ?? '';
        $delta = intval($body['delta'] ?? 0);
        if ($name === '') err('name required');
        $inv = load_inventory();
        if (!array_key_exists($name, $inv)) $inv[$name] = 0;
        $inv[$name] = max(0, intval($inv[$name]) + $delta);
        save_inventory($inv);
        append_log('update', $name, $inv[$name], $clientId);
        touch_change();
        ok(['status' => 'ok', 'inventory' => $inv, 'value' => $inv[$name]]);
        break;

    // ---------- Inventory: set explicit value ----------
    case 'set':
        if ($method !== 'POST') err('POST required');
        $name = $body['name'] ?? '';
        $value = max(0, intval($body['value'] ?? 0));
        if ($name === '') err('name required');
        $inv = load_inventory();
        $inv[$name] = $value;
        save_inventory($inv);
        append_log('set', $name, $value, $clientId);
        touch_change();
        ok(['status' => 'ok', 'inventory' => $inv, 'value' => $value]);
        break;

    // ---------- Inventory: delete ----------
    case 'delete':
        if ($method !== 'POST') err('POST required');
        $name = $body['name'] ?? '';
        $inv = load_inventory();
        if (array_key_exists($name, $inv)) {
            unset($inv[$name]);
            save_inventory($inv);
            append_log('delete', $name, null, $clientId);
            touch_change();
            ok(['status' => 'ok', 'inventory' => $inv]);
        } else err('not found', 404);
        break;

    // ---------- Requests: list ----------
    case 'request_list':
        $reqs = load_requests();
        // newest first
        usort($reqs, function($a, $b){ return strcmp($b['created'] ?? '', $a['created'] ?? ''); });
        ok(['requests' => $reqs]);
        break;

    // ---------- Requests: create ----------
    case 'request_create':
        if ($method !== 'POST') err('POST required');
        $location = trim($body['location'] ?? '');
        $items = $body['items'] ?? [];
        if ($location === '') err('location required');
        if (!is_array($items) || count($items) === 0) err('items required');

        // normalize items: [{'name','qty'}]
        $norm = [];
        foreach ($items as $it) {
            $n = trim($it['name'] ?? '');
            $q = max(0, intval($it['qty'] ?? 0));
            if ($n === '' || $q <= 0) continue;
            $norm[] = ['name' => $n, 'qty' => $q];
        }
        if (count($norm) === 0) err('no valid items');

        $req = [
            'id'      => uuid_short(),
            'location'=> $location,
            'items'   => $norm,
            'created' => date('c'),
            'status'  => 'open'
        ];

        $reqs = load_requests();
        array_unshift($reqs, $req); // newest first
        save_requests($reqs);
        append_log('request_create', $req['id'], $req, $clientId);
        touch_change();
        ok(['ok' => true, 'request' => $req]);
        break;

    // ---------- Requests: delete (soft delete) ----------
    case 'request_delete':
        if ($method !== 'POST') err('POST required');
        $id = $body['id'] ?? '';
        if ($id === '') err('id required');
        $reqs = load_requests();
        $found = false;
        foreach ($reqs as &$r) {
            if (($r['id'] ?? '') === $id && ($r['status'] ?? '') !== 'deleted') {
                $r['status'] = 'deleted';
                $r['deleted_at'] = date('c');
                $found = true;
                break;
            }
        }
        if (!$found) err('not found',404);
        save_requests($reqs);
        append_log('request_delete', $id, null, $clientId);
        touch_change();
        ok(['ok'=>true]);
        break;

    // ---------- Requests: fulfill (reduce inventory) ----------
    case 'request_fulfill':
        if ($method !== 'POST') err('POST required');
        $id = $body['id'] ?? '';
        if ($id === '') err('id required');
        $reqs = load_requests();
        $foundIndex = null;
        foreach ($reqs as $i => $r) {
            if (($r['id'] ?? '') === $id) { $foundIndex = $i; break; }
        }
        if ($foundIndex === null) err('not found',404);
        $found = $reqs[$foundIndex];
        if (($found['status'] ?? '') !== 'open') err('not open');

        // check shortages
        $inv = load_inventory();
        $short = [];
        foreach ($found['items'] as $it) {
            $name = $it['name'];
            $want = intval($it['qty']);
            $have = intval($inv[$name] ?? 0);
            if ($have < $want) $short[] = ['name'=>$name,'have'=>$have,'want'=>$want];
        }
        if (count($short) > 0) {
            ok(['ok'=>false,'shortages'=>$short]);
        } else {
            // subtract
            foreach ($found['items'] as $it) {
                $name = $it['name'];
                $want = intval($it['qty']);
                if (!isset($inv[$name])) $inv[$name] = 0;
                $inv[$name] = max(0, intval($inv[$name]) - $want);
            }
            save_inventory($inv);
            // update request
            $reqs[$foundIndex]['status'] = 'fulfilled';
            $reqs[$foundIndex]['fulfilled_at'] = date('c');
            $reqs[$foundIndex]['fulfilled_by'] = $clientId;
            save_requests($reqs);
            append_log('request_fulfill', $id, $found, $clientId);
            touch_change();
            ok(['ok'=>true,'request'=>$reqs[$foundIndex],'inventory'=>$inv]);
        }
        break;

    // ---------- Requests: aggregate picklist (summing all open requests) ----------
    case 'request_aggregate':
        $reqs = load_requests();
        $totals = [];
        foreach ($reqs as $r) {
            if (($r['status'] ?? '') !== 'open') continue;
            foreach ($r['items'] as $it) {
                $n = $it['name'];
                $q = intval($it['qty']);
                if (!isset($totals[$n])) $totals[$n] = 0;
                $totals[$n] += $q;
            }
        }
        $out = [];
        foreach ($totals as $name => $qty) $out[] = ['name'=>$name,'qty'=>$qty];
        usort($out, function($a,$b){ return $b['qty'] - $a['qty']; });
        ok(['picklist'=>$out]);
        break;

    default:
        err('unknown action', 404);
}
