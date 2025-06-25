#!/usr/bin/env php
<?php
$logFile = '/tmp/simplefilechat.log';
$lastSize = 0;

// Create the file if it doesn't exist
if (!file_exists($logFile)) {
    touch($logFile);
}

/*
// Fork the process to handle input and output concurrently
$pid = pcntl_fork();

if ($pid === -1) {
    exit(1); // Fork error
} elseif ($pid) {
    // Parent process: continuously read the log file and push new messages to client
    while (true) {
        clearstatcache(); // Refresh file status to detect changes
        $currentSize = filesize($logFile);

        if ($currentSize > $lastSize) {
            $fh = fopen($logFile, 'r');
            fseek($fh, $lastSize); // Move to last read position
            while ($line = fgets($fh)) {
                echo $line; // Send new line to WebSocket client
                flush(); // Ensure output is sent immediately
            }
            fclose($fh);
            $lastSize = $currentSize;
        }
        usleep(200000); // Sleep 200ms to avoid high CPU usage
    }
} else {
    // Child process: receive messages from WebSocket client and append to log
    while ($line = fgets(STDIN)) {
        $msg = trim($line);
        if ($msg) {
            $entry = "[" . date('H:i:s') . "] $msg\n";
            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX); // Lock to avoid race condition
        }
    }
}
*/

// Open STDIN as a stream
$stdin = fopen('php://stdin', 'r');
stream_set_blocking($stdin, false);

// Main loop
while (true) {
    // 1. Check for new input from client (WebSocket)
    $read = [$stdin];
    $write = $except = [];
    $hasInput = stream_select($read, $write, $except, 0, 200000); // wait max 200ms

    if ($hasInput && in_array($stdin, $read)) {
        $line = fgets($stdin);
        if ($line !== false) {
            $data = json_decode(trim($line), true);

            if (
                is_array($data) &&
                isset($data['user_id'], $data['username'], $data['booking_id'], $data['message'])
            ) {
                $data['date'] = date('Y-m-d');
                $data['time'] = date('H:i:s');

                file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
            }

            /*
            if (is_array($data) && isset($data['user_id'], $data['username'], $data['message'])) {
                $uid = htmlspecialchars($data['user_id']);
                $uname = htmlspecialchars($data['username']);
                $msg = htmlspecialchars($data['message']);
            */

            /*
            $msg = trim($line);
            if ($msg !== '') {
                $entry = "[" . date('H:i:s') . "] $msg\n";
            */

            /*
                $entry = "[" . date('H:i:s') . "] [User $uid - $uname] $msg\n";
                file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            }
            */
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
