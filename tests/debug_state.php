<?php
// tests/debug_state.php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

$pdo = Database::connect();

echo "--- Tables ---\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n--- Locations Table ---\n";
if (in_array('locations', $tables)) {
    $locs = $pdo->query("SELECT * FROM locations")->fetchAll(PDO::FETCH_ASSOC);
    print_r($locs);
} else {
    echo "MISSING!\n";
}

echo "\n--- Inventory Columns ---\n";
$cols = $pdo->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c)
    echo $c['name'] . " ";
echo "\n";

echo "\n--- API Response (Mock) ---\n";
// Mock session for Auth
if (session_status() === PHP_SESSION_NONE)
    session_start();
$_SESSION['username'] = 'admin';
$_SESSION['location_id'] = 1;

// Simulate API get
$_GET['action'] = 'get';
ob_start();
// We can't include api.php directly as it exits.
// Let's just run the logic manually.
$locId = Auth::getLocationId();
$stmt = $pdo->prepare("SELECT name, qty FROM inventory WHERE location_id = ?");
$stmt->execute([$locId]);
$inv = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$locs = $pdo->query("SELECT id, name FROM locations")->fetchAll(PDO::FETCH_KEY_PAIR);

$data = [
    'inventory' => $inv,
    'location' => [
        'id' => $locId,
        'name' => $locs[$locId] ?? 'Unknown',
        'all' => $locs
    ]
];
ob_end_clean();
print_r($data);
