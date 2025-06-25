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

$lastSize = 0;
define("LOG_FILE", "/tmp/simplefilechat.log");
define("STD_IN_STREAM", fopen("php://stdin", "r"));
define("USER_MAP_FILE", "/tmp/simplefilechat_users.json");


function appendMessageToLog(array $data): void {
    if ($fp = fopen(LOG_FILE, "c+")) {
        if (flock($fp, LOCK_EX)) {
            $maxId = 0;

            while (($line = fgets($fp)) !== false) {
                $entry = json_decode(trim($line), true);

                if (
                    isset($entry["id"])
                    && is_numeric($entry["id"])
                ) {
                    $maxId = max($maxId, (int)$entry["id"]);
                }
            }

            $data = [
                "id" => $maxId + 1,
                "booking_no" => $data["booking_no"],
                "created_at" => date("Y-m-d H:i:s"),
                "receiver_id" => $data["receiver_id"],
                "sender_id" => $data["sender_id"],
                "message" => $data["message"]
            ];
            $json = json_encode($data);

            fseek($fp, 0, SEEK_END);

            if ($json !== false) {
                fwrite($fp, $json . PHP_EOL);
            } else {
                fwrite(
                    STDERR,
                    "JSON encode error (insert): "
                    . json_last_error_msg() . PHP_EOL
                );
            }

            fflush($fp);
            flock($fp, LOCK_UN);
        }

        fclose($fp);
    }
}


function emitJsonMessage(array $entry): void {
    $json = json_encode($entry);

    if ($json !== false) {
        echo $json . PHP_EOL;

        flush();
        fflush(STDOUT);
    } else {
        fwrite(
            STDERR,
            "JSON encode error (output): " . json_last_error_msg() . PHP_EOL
        );
    }
}


/**
 * Get the current size of the log file, with cache cleared.
 *
 * @return int Log file size in bytes.
 */
function getLogSize(): int {
    clearstatcache(true, LOG_FILE);

    return filesize(LOG_FILE);
}


function getUserMap(): array {
    return file_exists(USER_MAP_FILE)
        ? json_decode(file_get_contents(USER_MAP_FILE), true) ?? []
        : [];
}


function modifyMessageInLog(int $messageId, int $senderId, string $newMessage): ?array {
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines)) {
        fwrite(STDERR, "Failed to read lines from log file." . PHP_EOL);
        return null;
    }

    $updated = false;
    $entry = null;

    foreach ($lines as $i => $line) {
        $candidate = json_decode(trim($line), true);
        if (
            is_array($candidate)
            && isset($candidate["id"], $candidate["sender_id"])
            && (int)$candidate["id"] === $messageId
            && (int)$candidate["sender_id"] === $senderId
        ) {
            $candidate["message"] = $newMessage;
            $entry = $candidate;

            $json = json_encode($entry);
            if ($json !== false) {
                $lines[$i] = $json;
                $updated = true;
            } else {
                fwrite(STDERR, "JSON encode error (modify): " . json_last_error_msg() . PHP_EOL);
            }
            break;
        }
    }

    if ($updated) {
        file_put_contents(LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
        return $entry;
    }

    return null;
}


function restoreNameFromUserMap(array $entry, array $userMap): array {
    // Restore receiver_name from userMap
    if (isset($userMap[(string)($entry["receiver_id"] ?? "")])) {
        $entry["receiver_name"] =
            $userMap[(string)$entry["receiver_id"]];
    }

    // Restore sender_name from userMap
    $entry["sender_name"] =
        $userMap[(string)($entry["sender_id"] ?? "")] ?? "guest";

    return $entry;
}


function saveUserMap(array $map): void {
    if ($fp = fopen(USER_MAP_FILE, "c+")) {
        if (flock($fp, LOCK_EX)) {
            $json = json_encode($map, JSON_PRETTY_PRINT);

            if ($json !== false) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $json);
                fflush($fp);
            } else {
                fwrite(
                    STDERR,
                    "JSON encode error (userMap): "
                    . json_last_error_msg() . PHP_EOL
                );
            }

            flock($fp, LOCK_UN);
        }

        fclose($fp);
    } else {
        fwrite(STDERR, "Failed to open user map file for writing." . PHP_EOL);
    }
}


// Open STD_IN as a stream
stream_set_blocking(STD_IN_STREAM, false);

// Create files if these don't exist
try {
    if (!file_exists(LOG_FILE)) touch(LOG_FILE);
    if (!file_exists(USER_MAP_FILE)) {
        file_put_contents(USER_MAP_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error initializing files: {$e->getMessage()}" . PHP_EOL);
    exit(1);
}

// Main loop
while (true) {
    // 1. Check for new input from client (WebSocket)
    $read = [STD_IN_STREAM];
    $write = $except = [];
    // wait max 200ms
    $hasInput = stream_select($read, $write, $except, 0, 200000);

    if ($hasInput && in_array(STD_IN_STREAM, $read)) {
        try {
            $line = fgets(STD_IN_STREAM);
            if ($line === false) continue 1;

            $data = json_decode(trim($line), true);
            if (!is_array($data)) continue 1;

            $action = $data["action"] ?? "";
            $userMap = getUserMap(); // Read and update the user map

            if (
                $action === "insert"
                && isset(
                    $data["booking_no"],
                    $data["message"],
                    $data["receiver_id"],
                    $data["sender_id"],
                    $data["sender_name"] // only for guile
                )
            ) {
                $nameTaken = false;
                $receiverName = trim($data["receiver_name"] ?? "");
                $userId = (string)$data["sender_id"];
                $userName = trim($data["sender_name"]);

                if (
                    !array_key_exists((string)$data["receiver_id"], $userMap)
                    || $data["receiver_id"] === $data["sender_id"]
                ) {
                    $data["receiver_id"] = 0;
                }

                // Search ID by exact value in user map
                if ($receiverName !== "" && $receiverName !== $userName) {
                    foreach ($userMap as $id => $name) {
                        if (strcasecmp($name, $receiverName) === 0) {
                            $data["receiver_id"] = (int)$id;

                            break;
                        }
                    }
                }

                foreach ($userMap as $id => $name) {
                    if (strcasecmp($name, $userName) === 0) {
                        if ($id !== $userId) {
                            $nameTaken = true;

                            break;
                        }
                    }
                }

                if (!$nameTaken) {
                    $userMap[$userId] = $userName;

                    saveUserMap($userMap);
                }

                // Safely assign a unique ID
                appendMessageToLog($data);
            } elseif (
                $action === "modify"
                && isset($data["id"], $data["message"], $data["sender_id"])
            ) {
                $newMessage = trim($data["message"]);
                $messageId = (int)$data["id"];
                $senderId = (int)$data["sender_id"];

                if ($messageId > 0 && $newMessage !== "") {
                    $entry = null;
                    $lines = file(
                        LOG_FILE,
                        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                    );
                    $updated = false;

                    if (!is_array($lines)) {
                        fwrite(
                            STDERR,
                            "Failed to read lines from log file." . PHP_EOL
                        );

                        continue 1;
                    }

                    foreach ($lines as $i => $line) {
                        $entry = json_decode(trim($line), true);

                        if (
                            is_array($entry)
                            && isset($entry["id"], $entry["sender_id"])
                            && (int)$entry["id"] === $messageId
                            && (int)$entry["sender_id"] === $senderId
                        ) {
                            $entry["message"] = $newMessage;
                            $json = json_encode($entry);

                            if ($json !== false) {
                                $lines[$i] = $json;
                                $updated = true;
                            } else {
                                fwrite(
                                    STDERR,
                                    "JSON encode error (modify): "
                                    . json_last_error_msg() . PHP_EOL
                                );
                            }

                            break;
                        }
                    }

                    if ($updated) {
                        file_put_contents(
                            LOG_FILE,
                            implode(PHP_EOL, $lines) . PHP_EOL,
                            LOCK_EX
                        );

                        // Restore {receiver,sender}_name from userMap
                        $entry = restoreNameFromUserMap($entry, getUserMap());

                        emitJsonMessage($entry);
                    }
                }

                continue 1; // Skip the rest of loop
            }
        } catch (Throwable $e) {
            /*
             * Catch any runtime errors from input processing
             * (JSON decoding, logic, file access)
             * and continue to the next WebSocket message
             * in the main loop.
             */
            fwrite(STDERR, "Runtime error: {$e->getMessage()}" . PHP_EOL);

            continue 1;
        }
    }

    // 2. Check for new data in the log file to send to client
    try {
        $currentSize = getLogSize();

        if ($currentSize > $lastSize) {
            if ($fh = fopen(LOG_FILE, "r")) {
                fseek($fh, $lastSize); // jump to where we left off

                while (($line = fgets($fh)) !== false) {
                    $entry = json_decode(trim($line), true);

                    if (!is_array($entry)) continue 1;

                    // Restore {receiver,sender}_name from userMap
                    $entry = restoreNameFromUserMap($entry, getUserMap());

                    emitJsonMessage($entry);
                }

                fclose($fh);
            }

            $lastSize = $currentSize;
        }
    } catch (Throwable $e) {
        /*
         * Catch errors during log file monitoring
         * (read failures, decoding, etc.)
         * and continue watching for new entries
         * without crashing the main loop.
         */
        fwrite(STDERR, "Log read error: {$e->getMessage()}" . PHP_EOL);

        continue 1;
    }
}
