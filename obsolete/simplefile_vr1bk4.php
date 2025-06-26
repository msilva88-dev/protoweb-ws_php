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

define("LOG_FILE", "/tmp/simplefilechat.log");
define("STD_IN_STREAM", fopen("php://stdin", "r"));
define("USER_MAP_FILE", "/tmp/simplefilechat_users.json");



/**
 * @param resource $fp
 *     Open file pointer from fopen(), must be writable.
 * @param int $retries
 *     Number of attempts.
 * @param int $delayMicroseconds
 *     Delay between retries in microseconds.
 *
 * @return bool True if lock acquired, false otherwise.
 */
function lockWithRetries(
    /* resource */ $fp,
    int $retries = 5,
    int $delayMicroseconds = 10000
): bool {
    for ($i = 0; $i < $retries; $i++) {
        if (flock($fp, LOCK_EX | LOCK_NB)) return true;

        // Short wait before the next attempt
        usleep($delayMicroseconds);
    }

    return false;
}


function resetModifiedState(?array &$logEntry, bool &$logEntryUpdated): void {
    $logEntry = null;
    $logEntryUpdated = false;
}


/**
 * Validates that an array has required fields with basic rules.
 *
 * @param array $logEntry The input array to validate.
 * @param array $rules Associative array of field => validation type.
 * @return bool True if all validations pass, false otherwise.
 */
function validateLogEntry(array $logEntry, array $rules): bool {
    foreach ($rules as $field => $rule) {
        if (!isset($logEntry[$field])) return false;

        $value = $logEntry[$field];

        switch ($rule) {
            case "string_non_empty":
                if (trim($value) === "") return false;

                break;
            case "numeric_positive_or_zero":
                if (!is_numeric($value) || (int)$value < 0) return false;

                break;
            case "numeric_positive":
                if (!is_numeric($value) || (int)$value <= 0) return false;

                break;
            default:
                return false;
        }
    }

    return true;
}


/**
 * Validates an entry before inserting.
 *
 * @param array $logEntry
 *
 * @return bool
 */
function validateInsertLogEntry(array $logEntry): bool {
    return validateLogEntry($logEntry, [
        "booking_no" => "string_non_empty",
        "message" => "string_non_empty",
        "receiver_id" => "numeric_positive_or_zero",
        "sender_id" => "numeric_positive_or_zero"
    ]);
}


/**
 * Validates an entry before modifing.
 *
 * @param array $logEntry
 * @return bool
 */
function validateModifyLogEntry(array $logEntry): bool {
    return validateLogEntry($logEntry, [
        "id" => "numeric_positive_or_zero",
        "message" => "string_non_empty",
        "sender_id" => "numeric_positive_or_zero"
    ]);
}


function emitJsonMessage(array $logEntry): void {
    $json = json_encode($logEntry);

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
    if (!file_exists(USER_MAP_FILE)) return [];

    try {
        if (($content = file_get_contents(USER_MAP_FILE)) === false) {
            fwrite(STDERR, "Error reading user map file." . PHP_EOL);

            return [];
        }

        if (!is_array(($jsonDecoded = json_decode($content, true)))) {
            fwrite(STDERR, "Error decoding user map JSON." . PHP_EOL);

            return [];
        }

        return $jsonDecoded;
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "Unexpected error in getUserMap: {$e->getMessage()}" . PHP_EOL
        );

        return [];
    }
}


/**
 * Ensures that required log and user map files exist.
 *
 * If the files do not exist, they are created.
 * If creation fails, the script exits with an error.
 *
 * @return void
 */
function initializeFiles(): void {
    try {
        if (!file_exists(LOG_FILE)) touch(LOG_FILE);
        if (!file_exists(USER_MAP_FILE)) {
            file_put_contents(USER_MAP_FILE, json_encode(
                [],
                JSON_PRETTY_PRINT
            ));
        }
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "Error initializing files: {$e->getMessage()}" . PHP_EOL
        );
        exit(1);
    }
}


function insertMessageToLog(array $logEntry): void {
    if (!validateInsertLogEntry($logEntry)) return;

    try {
        if (($fp = fopen(LOG_FILE, "c+")) !== false) {
            if (lockWithRetries($fp)) {
                $maxId = 0;

                while (($stdInLine = fgets($fp)) !== false) {
                    $jsonDecoded = json_decode(trim($stdInLine), true);

                    if (
                        isset($jsonDecoded["id"])
                        && is_numeric($jsonDecoded["id"])
                    ) {
                        $maxId = max($maxId, (int)$jsonDecoded["id"]);
                    }
                }

                $logEntrySorted = [
                    "id" => $maxId + 1,
                    "booking_no" => $logEntry["booking_no"],
                    "created_at" => date("Y-m-d H:i:s"),
                    "receiver_id" => $logEntry["receiver_id"],
                    "sender_id" => $logEntry["sender_id"],
                    "message" => $logEntry["message"]
                ];
                $json = json_encode($logEntrySorted);

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
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "Error in insertMessageToLog: {$e->getMessage()}" . PHP_EOL
        );
    }
}


function modifyMessageInLog(array $logEntry): ?array {
    if (!validateModifyLogEntry($logEntry)) return null;

    $newMessage = trim($logEntry["message"]);
    $messageId = (int)$logEntry["id"];
    $senderId = (int)$logEntry["sender_id"];

    try {
        $logLines =
            file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($logLines)) {
            fwrite(STDERR, "Failed to read lines from log file." . PHP_EOL);

            return null;
        }

        foreach ($logLines as $i => $logLine) {
            $jsonDecoded = json_decode(trim($logLine), true);

            if (
                is_array($jsonDecoded)
                && isset($jsonDecoded["id"], $jsonDecoded["sender_id"])
                && (int)$jsonDecoded["id"] === $messageId
                && (int)$jsonDecoded["sender_id"] === $senderId
            ) {
                $jsonDecoded["message"] = $newMessage;
                $json = json_encode($jsonDecoded);

                if ($json !== false) {
                    $logLines[$i] = $json;

                    return [
                        "entry" => $jsonDecoded,
                        "lines" => $logLines
                    ];
                } else {
                    fwrite(
                        STDERR,
                        "JSON encode error (modify): " . json_last_error_msg()
                        . PHP_EOL
                    );
                }

                break;
            }
        }
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "Error in modifyMessageInLog: {$e->getMessage()}" . PHP_EOL
        );
    }

    return null;
}


/**
 * Normalizes the receiver_id by verifying its existence
 * and resolving potential conflicts.
 *
 * @param array $logEntry The input message data.
 * @param array $userMap The current user map.
 * @param string $userName The sender name.
 *
 * @return int The corrected receiver_id.
 */
function normalizeReceiverId(array &$logEntry, array $userMap): void {
    $receiverId = (int)($logEntry["receiver_id"] ?? 0);
    $receiverName = trim($logEntry["receiver_name"] ?? "");
    $senderId = (int)($logEntry["sender_id"] ?? -1);
    $senderName = trim($logEntry["sender_name"] ?? "");

    // If receiver does not exist or is same as sender, set to 0
    if (!isset($userMap[(string)$receiverId]) || $receiverId === $senderId) {
        $logEntry["receiver_id"] = 0;

        return;
    }

    /*
     * Try to resolve by receiver name
     * if provided and different from sender.
     */
    if ($receiverName !== "" && $receiverName !== $senderName) {
        foreach ($userMap as $id => $name) {
            if (strcasecmp($name, $receiverName) === 0) {
                $logEntry["receiver_id"] = (int)$id;

                return;
            }
        }
    }

    $logEntry["receiver_id"] = $receiverId;
}


function restoreNameFromUserMap(array &$logEntry, array $userMap): void {
    // Restore receiver_name from userMap
    if (isset($userMap[(string)($logEntry["receiver_id"] ?? "")])) {
        $logEntry["receiver_name"] =
            $userMap[(string)$logEntry["receiver_id"]];
    }

    // Restore sender_name from userMap
    $logEntry["sender_name"] =
        $userMap[(string)($logEntry["sender_id"] ?? "")] ?? "guest";
}


function emitMessageWithNames(array &$logEntry, array $userMap): void {
    // Restore {receiver,sender}_name from userMap
    restoreNameFromUserMap($logEntry, $userMap);
    emitJsonMessage($logEntry);
}


/**
 * Process log file if new data is available and emit new messages.
 *
 * @param int &$lastSize
 *     The last known file size (by reference).
 * @param array $userMap
 *     The user map for name restoration.
 *
 * @return void
 */
function processLogDelta(int &$lastSize, array $userMap): void {
    if (($currentSize = getLogSize()) <= $lastSize) return;

    try {
        if (($fh = fopen(LOG_FILE, "r")) !== false) {
            // jump to where we left off
            fseek($fh, $lastSize);

            while (($stdInLine = fgets($fh)) !== false) {
                $jsonDecoded = json_decode(trim($stdInLine), true);

                if (is_array($jsonDecoded)) {
                    emitMessageWithNames($jsonDecoded, $userMap);
                }
            }

            fclose($fh);

            $lastSize = $currentSize;
        }
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "Error in processLogDelta: {$e->getMessage()}" . PHP_EOL
        );
    }
}


function replaceLogFile(array $logLines): void {
    $tempFile = LOG_FILE . '.tmp';

    try {
        if (($fp = fopen($tempFile, 'w')) !== false) {
            foreach ($logLines as $line) {
                fwrite($fp, $line . PHP_EOL);
            }

            fflush($fp);
            fclose($fp);

            /*
             * Atomic replace,
             * no LOCK needed because rename is atomic in POSIX.
             */
            rename($tempFile, LOG_FILE);
        }
    } catch (Throwable $e) {
        @unlink($tempFile);
        fwrite(
            STDERR,
            "Error in replaceLogFile: {$e->getMessage()}" . PHP_EOL
        );
    }
}


function saveUserMap(array $userMap): void {
    try {
        if (($fp = fopen(USER_MAP_FILE, "c+")) !== false) {
            if (lockWithRetries($fp)) {
                $json = json_encode($userMap, JSON_PRETTY_PRINT);

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
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "Error in saveUserMap: {$e->getMessage()}" . PHP_EOL);
    }
}


/**
 * Updates the user map
 * if the user name is not already taken by another user.
 *
 * @param array &$userMap
 *     The current user map (modified by reference).
 * @param array $logEntry
 *     The input message data containing sender_id and sender_name.
 *
 * @return void
 */
function updateUserMap(array $logEntry, array &$userMap): void {
    $userId = (string)($logEntry["sender_id"] ?? "");
    $userName = trim($logEntry["sender_name"] ?? "");

    foreach ($userMap as $id => $name) {
        if (strcasecmp($name, $userName) === 0 && $id !== $userId) {
            // Name is already taken by another user, do not update
            return;
        }
    }

    // Safe to update
    $userMap[$userId] = $userName;

    saveUserMap($userMap);
}


/**
 * Starts the WebSocket file-based relay server loop.
 *
 * @param int $lastSize
 *     The last known log file size (starts at 0 by default).
 *
 * @return void
 */
function runServer(int $lastSize = 0): void {
    // Read and update the user map
    $userMap = getUserMap();

    // Main loop
    while (true) {
        $logEntry = null;
        $logEntryUpdated = $stdInLine = false;
        $read = [STD_IN_STREAM];
        $write = $except = [];
        // wait max 200ms
        $hasInput = stream_select($read, $write, $except, 0, 200000);

        if ($hasInput && in_array(STD_IN_STREAM, $read)) {
            try {
                if (($stdInLine = fgets(STD_IN_STREAM)) === false) continue 1;

                $jsonDecoded = json_decode(trim($stdInLine), true);
                if (!is_array($jsonDecoded)) continue 1;

                $action = $jsonDecoded["action"] ?? "";

                if ($action === "insert") {
                    // 1.
                    if (isset(
                        $jsonDecoded["booking_no"],
                        $jsonDecoded["message"],
                        $jsonDecoded["receiver_id"],
                        $jsonDecoded["sender_id"],
                        $jsonDecoded["sender_name"] // only for guile
                    )) {
                        normalizeReceiverId($jsonDecoded, $userMap);
                        updateUserMap($jsonDecoded, $userMap);
                        insertMessageToLog($jsonDecoded);
                    }

                    // 2.
                    processLogDelta($lastSize, $userMap);
                } elseif ($action === "modify") {
                    // 1.
                    if ($modified = modifyMessageInLog($jsonDecoded)) {
                        $logEntry = $modified["entry"];
                        $logEntryUpdated = true;
                        $logLines = $modified["lines"];
                    }

                    // 2.
                    if (
                        $logEntryUpdated
                        && is_array($logEntry)
                        && is_array($logLines)
                    ) {
                        replaceLogFile($logLines);
                        emitMessageWithNames($logEntry, $userMap);
                        // Clean Flags to skip double write
                        resetModifiedState($logEntry, $logEntryUpdated);
                    }
                }
            } catch (Throwable $e) {
                /*
                 * Catch any runtime errors from input processing
                 * (JSON decoding, logic, file access)
                 * and continue to the next WebSocket message
                 * in the main loop.
                 */
                fwrite(STDERR, "Runtime error: {$e->getMessage()}" . PHP_EOL);
            }
        } else {
            try {
                processLogDelta($lastSize, $userMap);
            } catch (Throwable $e) {
                /*
                 * Catch errors during log file monitoring
                 * (read failures, decoding, etc.)
                 * and continue watching for new entries
                 * without crashing the main loop.
                 */
                fwrite(
                    STDERR,
                    "Log read error: {$e->getMessage()}" . PHP_EOL
                );
            }
        }
    }
}


// Open STD_IN as a stream
stream_set_blocking(STD_IN_STREAM, false);

// Create files if these don't exist
initializeFiles();

// Run the Server
runServer();

