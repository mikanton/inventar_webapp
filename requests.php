<?php
// requests.php – API für Bestell-/Request-Funktion
require_once __DIR__ . '/util.php';
header('Content-Type: application/json; charset=utf-8');

ensure_files();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$clientId = $body['clientId'] ?? ($_GET['clientId'] ?? null);

function ok($data = []) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg, $code = 400) { http_response_code($code); echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

switch ($action) {

    // alle Requests zurückgeben (neueste zuerst)
    case 'list':
        $reqs = load_requests();
        usort($reqs, function($a,$b){ return strcmp($b['created'] ?? '', $a['created'] ?? ''); });
        ok(['requests'=>$reqs]);
    
    // Request anlegen
    case 'create':
        if ($method !== 'POST') err('POST required');
        $location = trim($body['location'] ?? '');
        $items = $body['items'] ?? [];
        if ($location === '') err('location required');
        if (!is_array($items) || count($items) === 0) err('items required');

        // normalize items: [{name, qty}]
        $norm = [];
        foreach ($items as $it) {
            $n = trim($it['name'] ?? '');
            $q = max(0, intval($it['qty'] ?? 0));
            if ($n === '' || $q <= 0) continue;
            $norm[] = ['name'=>$n, 'qty'=>$q];
        }
        if (count($norm)===0) err('no valid items');

        $req = [
            'id' => uuid_short(),
            'location' => $location,
            'items' => $norm,
            'created' => date('c'),
            'status' => 'open'
        ];
        $reqs = load_requests();
        array_unshift($reqs, $req);
        save_requests($reqs);
        append_log('request_create', $req['id'], $req, $clientId);
        touch_change();
        ok(['ok'=>true,'request'=>$req]);

    // Request löschen (nur markieren)
    case 'delete':
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

    // Request als erfüllt markieren (und Inventar reduzieren)
    case 'fulfill':
        if ($method !== 'POST') err('POST required');
        $id = $body['id'] ?? '';
        if ($id === '') err('id required');
        $reqs = load_requests();
        $found = null;
        foreach ($reqs as &$r) {
            if (($r['id'] ?? '') === $id) { $found = &$r; break; }
        }
        if ($found === null) err('not found',404);
        if (($found['status'] ?? '') !== 'open') err('not open');

        // Check availability
        $inv = load_inventory();
        $short = [];
        foreach ($found['items'] as $it) {
            $name = $it['name'];
            $want = intval($it['qty']);
            $have = intval($inv[$name] ?? 0);
            if ($have < $want) $short[] = ['name'=>$name,'have'=>$have,'want'=>$want];
        }
        if (count($short)>0) {
            // return list of shortages; caller can decide
            ok(['ok'=>false,'shortages'=>$short]);
        }

        // subtract
        foreach ($found['items'] as $it) {
            $name = $it['name'];
            $want = intval($it['qty']);
            if (!isset($inv[$name])) $inv[$name]=0;
            $inv[$name] = max(0, intval($inv[$name]) - $want);
        }
        save_inventory($inv);

        // update request
        $found['status'] = 'fulfilled';
        $found['fulfilled_at'] = date('c');
        $found['fulfilled_by'] = $clientId;
        save_requests($reqs);

        append_log('request_fulfill', $id, $found, $clientId);
        touch_change();
        ok(['ok'=>true,'request'=>$found]);

    // Aggregierte Pickliste (offene Requests zusammenfassen)
    case 'aggregate':
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
        // convert to sorted array
        $out=[];
        foreach ($totals as $name=>$qty) $out[]=['name'=>$name,'qty'=>$qty];
        usort($out, function($a,$b){ return $b['qty'] - $a['qty']; });
        ok(['picklist' => $out]);

    default:
        err('unknown action',404);
}
