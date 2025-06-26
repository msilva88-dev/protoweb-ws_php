#!/usr/bin/env php
<?php
$logFile = '/tmp/simplefilechat.log';
$lastSize = 0;

// Create the file if it doesn't exist
if (!file_exists($logFile)) {
    touch($logFile);
}

// Open STDIN as a stream
$stdin = fopen('php://stdin', 'r');
stream_set_blocking($stdin, false);

// Main loop
while (true) {
    // 1. Check for new input from client (WebSocket)
    $read = [$stdin];
    $write = $except = [];
    // wait max 200ms
    $hasInput = stream_select($read, $write, $except, 0, 200000);

    if ($hasInput && in_array($stdin, $read)) {
        $line = fgets($stdin);
        if ($line !== false) {
            $data = json_decode(trim($line), true);

            if (
                is_array($data) &&
                isset(
                    $data['user_id'],
                    $data['username'],
                    $data['booking_id'],
                    $data['message']
                )
            ) {
                $data['date'] = date('Y-m-d');
                $data['time'] = date('H:i:s');

                file_put_contents(
                    $logFile,
                    json_encode($data) . '\n',
                    FILE_APPEND | LOCK_EX
                );
            }
        }
    }

    // 2. Check for new data in the log file to send to client
    clearstatcache();
    $currentSize = filesize($logFile);
    if ($currentSize > $lastSize) {
        $fh = fopen($logFile, 'r');
        fseek($fh, $lastSize); // jump to where we left off
        while ($line = fgets($fh)) {
            echo $line;
            flush();
        }
        fclose($fh);
        $lastSize = $currentSize;
    }
}
