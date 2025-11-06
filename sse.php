<?php
// sse.php – Server-Sent Events (Inventar + Requests + Logs)
require_once __DIR__ . '/util.php';

// Anti buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

ensure_files();

// helper to emit SSE event safely
function sse_emit($event, $data) {
    // If $data is string, send as is, else JSON encode
    echo "event: {$event}\n";
    echo "data: " . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)) . "\n\n";
    @ob_flush(); @flush();
}

// initial full payload
$payload = [
    'inventory' => load_inventory(),
    'requests'  => load_requests(),
    'logs'      => load_logs()
];
// send initial sync
sse_emit('sync', $payload);
sse_emit('inventory', $payload['inventory']);
sse_emit('requests', $payload['requests']);

// last-hash / mtimes for change detection
$lastInvSha = @sha1_file(INV_PATH) ?: '';
$lastReqSha = @sha1_file(REQUESTS_PATH) ?: '';
$lastLogM = @filemtime(LOG_PATH) ?: 0;

ignore_user_abort(true);
set_time_limit(0);

$SLEEP_USEC = 200000; // 0.2s
$PING_SEC = 20;
$lastPing = time();

while (true) {
    if (connection_aborted()) break;
    clearstatcache(true, INV_PATH);
    clearstatcache(true, REQUESTS_PATH);
    clearstatcache(true, LOG_PATH);

    // inventory change via sha
    $curInvSha = @sha1_file(INV_PATH) ?: '';
    if ($curInvSha !== $lastInvSha) {
        $lastInvSha = $curInvSha;
        $inv = load_inventory();
        sse_emit('inventory', $inv);
        // also send full sync for convenience
        sse_emit('sync', ['inventory'=>$inv, 'requests'=>load_requests(), 'logs'=>load_logs()]);
    }

    // requests change via sha
    $curReqSha = @sha1_file(REQUESTS_PATH) ?: '';
    if ($curReqSha !== $lastReqSha) {
        $lastReqSha = $curReqSha;
        $reqs = load_requests();
        sse_emit('requests', $reqs);
        sse_emit('sync', ['inventory'=>load_inventory(), 'requests'=>$reqs, 'logs'=>load_logs()]);
    }

    // logs change via mtime
    $curLogM = @filemtime(LOG_PATH) ?: 0;
    if ($curLogM !== $lastLogM) {
        $lastLogM = $curLogM;
        sse_emit('logtick', '{}');
    }

    // Keepalive ping to avoid proxies killing idle connections
    if ((time() - $lastPing) >= $PING_SEC) {
        $lastPing = time();
        sse_emit('ping', ['t' => time()]);
    }

    usleep($SLEEP_USEC);
}
