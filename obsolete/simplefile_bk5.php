#!/usr/bin/env php
<?php

/*
 * Copyright (c) 2025, Marcio Delgado <marcio@libreware.info>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * This code is optimized for PHP 7.4+ and follows PSR-12 (coding style)
 * and PSR-5 (PHPDoc) standards. It ensures high traffic handling,
 * high performance, low memory usage, and clean code.
 */

/**
 * Simple WebSocket file-based relay server.
 *
 * Listens for incoming JSON messages via STDIN, appends valid ones
 * to a shared log file, and emits new lines to STDOUT in real time.
 *
 * Optimized for WebSocketd.
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @license BSD-2-Clause
 * @version 1.0
 * @since 2025
 */

$logFile = "/tmp/simplefilechat.log";
$lastSize = 0;

// Create the file if it doesn't exist
if (!file_exists($logFile)) {
    touch($logFile);
}

// Open STDIN as a stream
$stdin = fopen("php://stdin", "r");
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
                ($data["action"] ?? "") === "insert" &&
                isset(
                    $data["sender_id"],
                    $data["username"],
                    $data["booking_no"],
                    $data["message"]
                )
            ) {
                unset($data["action"]);

                $data["date"] = date("Y-m-d");
                $data["time"] = date("H:i:s");

                file_put_contents(
                    $logFile,
                    json_encode($data) . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
            }
        }
    }

    // 2. Check for new data in the log file to send to client
    clearstatcache();
    $currentSize = filesize($logFile);
    if ($currentSize > $lastSize) {
        $fh = fopen($logFile, "r");
        fseek($fh, $lastSize); // jump to where we left off
        while ($line = fgets($fh)) {
            echo $line;

            flush();
            fflush(STDOUT);
        }
        fclose($fh);
        $lastSize = $currentSize;
    }
}
