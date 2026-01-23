<?php
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/UserManager.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$pdo = Database::connect();

// Helper to touch SSE trigger
function touch_change()
{
    $flag = __DIR__ . '/.sse_trigger';
    touch($flag);
}

function ok($data = [])
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function err($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// Public Read Actions
if ($action === 'get') {
    $inv = $pdo->query("SELECT name, qty FROM inventory")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get open requests
    $stmt = $pdo->query("SELECT * FROM requests WHERE status = 'open' ORDER BY created_at DESC");
    $reqs = $stmt->fetchAll();
    foreach ($reqs as &$r) {
        $r['items'] = $pdo->query("SELECT name, qty FROM request_items WHERE request_id = '{$r['id']}'")->fetchAll(PDO::FETCH_ASSOC);
    }

    ok(['inventory' => $inv, 'requests' => $reqs]);
}

if ($action === 'get_by_barcode') {
    $barcode = $_GET['barcode'] ?? '';
    if (!$barcode)
        err('Barcode missing');

    $locId = Auth::getLocationId();

    // 1. Search in current location
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE barcode = ? AND location_id = ?");
    $stmt->execute([$barcode, $locId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        ok(['found' => true, 'in_location' => true, 'item' => $item]);
    }

    // 2. Search in other locations to find name
    $stmt = $pdo->prepare("SELECT name FROM inventory WHERE barcode = ? LIMIT 1");
    $stmt->execute([$barcode]);
    $name = $stmt->fetchColumn();

    if ($name) {
        ok(['found' => true, 'in_location' => false, 'item' => ['name' => $name, 'qty' => 0]]);
    }

    ok(['found' => false]);
}

if ($action === 'request_list') {
    $stmt = $pdo->query("SELECT * FROM requests WHERE status != 'deleted' ORDER BY created_at DESC");
    $reqs = $stmt->fetchAll();
    foreach ($reqs as &$r) {
        $r['items'] = $pdo->query("SELECT name, qty FROM request_items WHERE request_id = '{$r['id']}'")->fetchAll(PDO::FETCH_ASSOC);
    }
    ok(['requests' => $reqs]);
}

if ($action === 'request_aggregate') {
    $stmt = $pdo->query("
        SELECT ri.name, SUM(ri.qty) as qty 
        FROM request_items ri
        JOIN requests r ON r.id = ri.request_id
        WHERE r.status = 'open'
        GROUP BY ri.name
        ORDER BY qty DESC
    ");
    ok(['picklist' => $stmt->fetchAll()]);
}

if ($action === 'logs') {
    Auth::requireLogin(); // Logs are admin only
    $stmt = $pdo->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 200");
    ok(['logs' => $stmt->fetchAll()]);
}

if ($action === 'analytics') {
    Auth::requireLogin();

    // Activity by Day (last 30 days)
    $stmt = $pdo->query("
        SELECT date(created_at) as d, COUNT(*) as c 
        FROM logs 
        WHERE created_at > date('now', '-30 days') 
        GROUP BY d 
        ORDER BY d ASC
    ");
    $byDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Top Users
    $stmt = $pdo->query("
        SELECT client_id, COUNT(*) as c 
        FROM logs 
        GROUP BY client_id 
        ORDER BY c DESC 
        LIMIT 10
    ");
    $topUsers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Top Items (Most changed)
    $stmt = $pdo->query("
        SELECT item, COUNT(*) as c 
        FROM logs 
        WHERE item IS NOT NULL 
        GROUP BY item 
        ORDER BY c DESC 
        LIMIT 10
    ");
    $topItems = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    ok(['byDay' => $byDay, 'topUsers' => $topUsers, 'topItems' => $topItems]);
}

if ($action === 'analytics_productivity') {
    Auth::requireLogin();
    $locId = Auth::getLocationId();

    // 1. Rebalancing Suggestions
    // Find items that are low in current location (< 5) but high in others (> 10)
    $stmt = $pdo->prepare("
        SELECT i.name, i.qty as local_qty, l.name as other_loc, i2.qty as other_qty
        FROM inventory i
        JOIN inventory i2 ON i.name = i2.name AND i2.location_id != i.location_id
        JOIN locations l ON i2.location_id = l.id
        WHERE i.location_id = ? AND i.qty < 5 AND i2.qty > 10
    ");
    $stmt->execute([$locId]);
    $rebalancing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Consumption Rate (Items with most negative updates in last 30 days)
    $stmt = $pdo->prepare("
        SELECT item, SUM(CAST(REPLACE(value, '+', '') AS INTEGER)) as consumption
        FROM logs 
        WHERE location_id = ? AND action = 'update' AND value LIKE '-%' AND created_at > date('now', '-30 days')
        GROUP BY item
        ORDER BY consumption ASC
        LIMIT 5
    ");
    $stmt->execute([$locId]);
    $consumption = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Value Distribution (Items count per location)
    $stmt = $pdo->query("
        SELECT l.name, SUM(i.qty) as total_items
        FROM locations l
        LEFT JOIN inventory i ON l.id = i.location_id
        GROUP BY l.name
    ");
    $distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    ok(['rebalancing' => $rebalancing, 'consumption' => $consumption, 'distribution' => $distribution]);
}

// CSRF Check for mutating actions
if (in_array($action, ['add', 'update', 'set', 'delete', 'request_create', 'request_fulfill', 'request_delete'])) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::verifyCsrf($token)) {
        // Allow request_create without CSRF if we want public access? 
        // But we said "top notch security". 
        // If public, they can't get a token easily unless we give one to everyone.
        // Let's assume the frontend always has a token if it loads the page.
        // Even for public pages, we can embed a token.
        err('CSRF validation failed', 403);
    }
}


// Helper for logging
function log_action($pdo, $action, $item, $value, $locId = null)
{
    $client = $_GET['clientId'] ?? 'unknown';
    $user = Auth::user();
    if ($user)
        $client = "$user ($client)";

    if (!$locId)
        $locId = Auth::getLocationId();

    $stmt = $pdo->prepare("INSERT INTO logs (action, item, value, client_id, location_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$action, $item, $value, $client, $locId]);
}

function load_requests($pdo)
{
    $stmt = $pdo->query("SELECT * FROM requests WHERE deleted_at IS NULL ORDER BY created_at DESC");
    $reqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reqs as &$r) {
        $stmtI = $pdo->prepare("SELECT name, qty FROM request_items WHERE request_id = ?");
        $stmtI->execute([$r['id']]);
        $r['items'] = $stmtI->fetchAll(PDO::FETCH_ASSOC);
    }
    return $reqs;
}

function load_logs($pdo)
{
    // Filter logs by current location? Or show all?
    // Maybe show all for admin, but for now let's show all.
    // Actually, logs table now has location_id.
    $locId = Auth::getLocationId();
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE location_id = ? OR location_id IS NULL ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$locId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

switch ($action) {
    // ---------- GET: whole state ----------
    case 'get':
        $locId = Auth::getLocationId();
        // Get inventory for current location
        $stmt = $pdo->prepare("SELECT name, qty FROM inventory WHERE location_id = ?");
        $stmt->execute([$locId]);
        $inv = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Get locations list
        $locs = $pdo->query("SELECT id, name FROM locations")->fetchAll(PDO::FETCH_KEY_PAIR);

        ok([
            'inventory' => $inv,
            'requests' => load_requests($pdo), // Requests are global or need filtering? Let's keep global for now or filter by 'location' string matching location name?
            // For now, requests are global.
            'logs' => load_logs($pdo),
            'location' => [
                'id' => $locId,
                'name' => $locs[$locId] ?? 'Unknown',
                'all' => $locs
            ]
        ]);
        break;

    // ---------- POST: set location ----------
    case 'set_location':
        $id = intval($body['id'] ?? 1);
        Auth::setLocationId($id);
        ok(['success' => true, 'location_id' => $id]);
        break;

    case 'add':
        Auth::requireLogin();
        if (!Auth::isAdmin() && !Auth::DEV_MODE)
            err('Nur Admins können Artikel anlegen', 403);

        $name = trim($body['name'] ?? '');
        $qty = intval($body['qty'] ?? 0);
        if (!$name)
            err('Name missing');

        try {
            $locId = Auth::getLocationId();
            $barcode = $body['barcode'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO inventory (name, qty, location_id, barcode) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $qty, $locId, $barcode]);
            log_action($pdo, 'add', $name, $qty, $locId);
            touch_change();
            ok();
        } catch (Exception $e) {
            err('Item exists');
        }
        break;

    case 'update':
        Auth::requireLogin();
        $name = $body['name'] ?? '';
        $delta = intval($body['delta'] ?? 0);
        if (!$name)
            err('Name missing');

        $stmt = $pdo->prepare("UPDATE inventory SET qty = qty + ? WHERE name = ? AND location_id = ?");
        $locId = Auth::getLocationId();
        $stmt->execute([$delta, $name, $locId]);

        // Update barcode if provided and empty
        if (!empty($body['barcode'])) {
            $stmtB = $pdo->prepare("UPDATE inventory SET barcode = ? WHERE name = ? AND (barcode IS NULL OR barcode = '')");
            $stmtB->execute([$body['barcode'], $name]);
        }

        log_action($pdo, 'update', $name, $delta, $locId);
        touch_change();
        ok();
        break;

    case 'set':
        Auth::requireLogin();
        $name = $body['name'] ?? '';
        $value = intval($body['value'] ?? 0);
        if (!$name)
            err('Name missing');

        $locId = Auth::getLocationId();
        $stmt = $pdo->prepare("UPDATE inventory SET qty = ? WHERE name = ? AND location_id = ?");
        $stmt->execute([$value, $name, $locId]);
        log_action($pdo, 'set', $name, $value, $locId);
        touch_change();
        ok();
        break;

    case 'delete':
        Auth::requireLogin();
        $name = $body['name'] ?? '';
        if (!$name)
            err('Name missing');

        $locId = Auth::getLocationId();
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE name = ? AND location_id = ?");
        $stmt->execute([$name, $locId]);

        log_action($pdo, 'delete', $name, null, $locId);
        touch_change();
        ok();
        break;

    case 'request_create':
        // Publicly allowed
        $location = trim($body['location'] ?? '');
        $items = $body['items'] ?? [];
        if (!$location || empty($items))
            err('Invalid data');

        $id = bin2hex(random_bytes(8));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO requests (id, location) VALUES (?, ?)");
            $stmt->execute([$id, $location]);

            $stmtItem = $pdo->prepare("INSERT INTO request_items (request_id, name, qty) VALUES (?, ?, ?)");
            foreach ($items as $it) {
                $stmtItem->execute([$id, $it['name'], $it['qty']]);
            }
            $pdo->commit();
            log_action($pdo, 'request_create', $id, $location);
            touch_change();
            ok(['id' => $id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Error creating request');
        }
        break;

    case 'request_fulfill':
        Auth::requireLogin();
        $id = $body['id'] ?? '';

        $pdo->beginTransaction();
        try {
            // Check shortages
            $items = $pdo->query("SELECT name, qty FROM request_items WHERE request_id = '$id'")->fetchAll(PDO::FETCH_ASSOC);
            $shortages = [];
            $locId = Auth::getLocationId();

            foreach ($items as $it) {
                $stmtCheck = $pdo->prepare("SELECT qty FROM inventory WHERE name = ? AND location_id = ?");
                $stmtCheck->execute([$it['name'], $locId]);
                $curr = $stmtCheck->fetchColumn();

                if ($curr === false)
                    $curr = 0;
                if ($curr < $it['qty']) {
                    $shortages[] = ['name' => $it['name'], 'have' => $curr, 'want' => $it['qty']];
                }
            }

            if (!empty($shortages)) {
                $pdo->rollBack();
                ok(['ok' => false, 'shortages' => $shortages]);
            }

            // Deduct
            $stmtUpd = $pdo->prepare("UPDATE inventory SET qty = qty - ? WHERE name = ? AND location_id = ?");
            foreach ($items as $it) {
                $stmtUpd->execute([$it['qty'], $it['name'], $locId]);
            }

            // Update request
            $stmt = $pdo->prepare("UPDATE requests SET status = 'fulfilled', fulfilled_at = CURRENT_TIMESTAMP, fulfilled_by = ? WHERE id = ?");
            $stmt->execute([Auth::user(), $id]);

            $pdo->commit();
            log_action($pdo, 'request_fulfill', $id, null);
            touch_change();
            ok(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Error');
        }
        break;

    case 'request_delete':
        Auth::requireLogin();
        $id = $body['id'] ?? '';
        $stmt = $pdo->prepare("UPDATE requests SET status = 'deleted', deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        log_action($pdo, 'request_delete', $id, null);
        touch_change();
        ok();
        break;

    // ---------- User Management ----------
    case 'user_list':
        Auth::requireLogin();
        ok(['users' => UserManager::list()]);
        break;

    case 'user_create':
        Auth::requireLogin();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        if (!$username || !$password)
            err('Missing data');

        if (UserManager::create($username, $password)) {
            ok();
        } else {
            err('User exists or error');
        }
        break;

    case 'user_delete':
        Auth::requireLogin();
        $id = $body['id'] ?? '';
        if (UserManager::delete($id)) {
            ok();
        } else {
            err('Error');
        }
        break;
}
