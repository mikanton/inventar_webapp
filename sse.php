<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once __DIR__ . '/includes/Database.php';

$flag = __DIR__ . '/.sse_trigger';
if (!file_exists($flag))
    touch($flag);

$last = filemtime($flag);

// Send initial state
$pdo = Database::connect();
$inv = $pdo->query("SELECT name, qty FROM inventory")->fetchAll(PDO::FETCH_KEY_PAIR);
echo "event: inventory\n";
echo "data: " . json_encode($inv) . "\n\n";
ob_flush();
flush();

while (true) {
    clearstatcache(true, $flag);
    $curr = filemtime($flag);

    if ($curr > $last) {
        $last = $curr;

        // Send inventory update
        $inv = $pdo->query("SELECT name, qty FROM inventory")->fetchAll(PDO::FETCH_KEY_PAIR);
        echo "event: inventory\n";
        echo "data: " . json_encode($inv) . "\n\n";

        // Send generic tick for logs/requests
        echo "event: logtick\n";
        echo "data: {}\n\n";

        ob_flush();
        flush();
    }

    // Heartbeat
    echo "event: ping\n";
    echo "data: {}\n\n";
    ob_flush();
    flush();

    if (connection_aborted())
        break;
    sleep(1);
}
