<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

echo "event: ping\n";
echo "data: ".json_encode(["time"=>time()])."\n\n";
@ob_flush(); @flush();

while (true) {
    echo "event: ping\n";
    echo "data: ".json_encode(["time"=>time()])."\n\n";
    @ob_flush(); @flush();
    sleep(2);
}
