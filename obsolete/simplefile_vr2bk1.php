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
 * WebSocket relay server for JSON messages.
 *
 * - Accepts messages via STDIN
 * - Saves messages in flat file logs
 * - Emits real-time updates via STDOUT
 * - Supports user mapping and message modification
 *
 * Optimized for use with websocketd.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat
 * @since 2025
 * @subpackage Server
 * @version 1.0
 */

declare(strict_types=1);

namespace ProtoWeb\DynamicChat\DataBase {


/**
 * Interface ChatDataBaseInterface
 *
 * Defines the contract for chat database operations.
 * Responsible for handling message insertion, update, deletion,
 * and user metadata resolution in the backend (e.g. file, DB).
 *
 * Implementations must support JSON message flow
 * compatible with WebSocket streams.
 *
 * @package ProtoWeb\DynamicChat\DataBase
 */
interface ChatDataBaseInterface
{
    /**
     * Deletes a message entry (not yet implemented).
     *
     * @return bool Always false (draft).
     */
    public function deleteMsgEntry(): bool;


    /**
     * Retrieves the current message entry.
     *
     * @return array Associative array representing a message.
     */
    public function getMsgEntry(): array;


    /**
     * Validates and inserts a message into the backend.
     *
     * @return bool True on success, false on failure.
     */
    public function insertMsgEntry(): bool;


    /**
     * Prints all new messages since last read.
     *
     * @return bool True if new messages printed, false otherwise.
     */
    public function printAllMsgEntries(): bool;


    /**
     * Prints the current message entry in JSON.
     *
     * @return bool True if printed successfully, false otherwise.
     */
    public function printMsgEntry(): bool;


    /**
     * Sets the current message entry.
     *
     * @param array $msgEntry Message data to set.
     *
     * @return void
     */
    public function setMsgEntry(array $msgEntry): void;


    /**
     * Validates and updates an existing message.
     *
     * @return bool True on success, false on failure.
     */
    public function updateMsgEntry(): bool;
}


/**
 * Class ChatDataBaseBase
 *
 * Abstract base class that implements validation logic
 * and interface stubs for chat message handling.
 *
 * @implements ChatDataBaseInterface
 *
 * @package ProtoWeb\DynamicChat\DataBase
 */
abstract class ChatDataBaseBase implements ChatDataBaseInterface
{
    /**
     * Internal message entry data.
     *
     * @var array<string, int|string> Current message entry data.
     */
    private array $msgEntry = [];


    /**
     * Validates a message entry against a set of rules.
     *
     * @param array $rules
     *     Validation rules as field => rule string.
     *     Allowed rules:
     *         string_non_empty, numeric_positive_or_zero,
     *         numeric_positive
     *
     * @return bool True
     *     if all fields pass validation, false otherwise.
     */
    final private function validateMsgEntry(array $rules): bool {
        $msgEntry = $this->getMsgEntry();

        foreach ($rules as $field => $rule) {
            if (!isset($msgEntry[$field])) return false;

            switch ($rule) {
                case "string_non_empty":
                    if (trim($msgEntry[$field]) === "") return false;

                    break;
                case "numeric_positive_or_zero":
                    if (
                        !is_numeric($msgEntry[$field])
                        || (int)$msgEntry[$field] < 0
                    ) {
                        return false;
                    }

                    break;
                case "numeric_positive":
                    if (
                        !is_numeric($msgEntry[$field])
                        || (int)$msgEntry[$field] <= 0
                    ) {
                        return false;
                    }

                    break;
                default:
                    return false;
            }
        }

        return true;
    }


    /**
     * Checks if the current message entry is valid for insertion.
     *
     * @return bool True if valid for insert, false otherwise.
     */
    final private function validateInsertMsgEntry(): bool {
        return $this->validateMsgEntry([
            "booking_no" => "string_non_empty",
            "message" => "string_non_empty",
            "receiver_id" => "numeric_positive_or_zero",
            "sender_id" => "numeric_positive_or_zero"
        ]);
    }


    /**
     * Checks if the current message entry is valid for update.
     *
     * @return bool True if valid for update, false otherwise.
     */
    final private function validateUpdateMsgEntry(): bool {
        return $this->validateMsgEntry([
            "id" => "numeric_positive_or_zero",
            "message" => "string_non_empty",
            "sender_id" => "numeric_positive_or_zero"
        ]);
    }


    /**
     * Inserts the current message into the database
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function insertMsgEntryToDB(): bool;


    /**
     * Inserts the sender's user data into the database
     * (to be implemented by subclass).
     *
     * @return void
     */
    abstract protected function insertUserDataToDB(): void;


    /**
     * Normalizes the receiver_id field in the current message
     * (to be implemented by subclass).
     *
     * @return void
     */
    abstract protected function normalizeReceiverIdFromMsgEntry(): void;


    /**
     * Prints all new messages from database
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function printAllJsonMsgsFromDB(): bool;


    /**
     * Prints current message entry as JSON
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function printJsonMsgFromMsgEntry(): bool;


    /**
     * Restores user names into the current message
     * (to be implemented by subclass).
     *
     * @return void
     */
    abstract protected function restoreUserNameFromUserData(): void;


    /**
     * Checks if the sender already exists in the user map
     * (to be implemented by subclass).
     *
     * @return bool
     *     True if name exists under another ID, false otherwise.
     */
    abstract protected function selectUserDataFromDB(): bool;


    /**
     * Updates an existing message in the database
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function updateMsgEntryToDB(): bool;


    /**
     * Not implemented. Placeholder for deleting message.
     *
     * @return bool Always false (not implemented).
     */
    final public function deleteMsgEntry(): bool {
        return false; // draft
    }


    /**
     * Returns the current message entry array.
     *
     * @return array Associative array representing a message.
     */
    final public function getMsgEntry(): array {
        return $this->msgEntry;
    }


    /**
     * Inserts the message entry after validation and user checks.
     *
     * @return bool True on success, false otherwise.
     */
    final public function insertMsgEntry(): bool {
        if ($this->validateInsertMsgEntry()) {
            $this->normalizeReceiverIdFromMsgEntry();

            if (!$this->selectUserDataFromDB()) {
                $this->insertUserDataToDB();
            }

            return $this->insertMsgEntryToDB();
        }

        return false;
    }


    /**
     * Triggers printing of all new message entries.
     *
     * @return bool True if output occurred, false otherwise.
     */
    final public function printAllMsgEntries(): bool {
        return $this->printAllJsonMsgsFromDB();
    }


    /**
     * Restores and prints the current message.
     *
     * @return bool True if output succeeded, false otherwise.
     */
    final public function printMsgEntry(): bool {
        $this->restoreUserNameFromUserData();

        return $this->printJsonMsgFromMsgEntry();
    }


    /**
     * Sets the internal message entry.
     *
     * @param array $msgEntry Message entry array.
     *
     * @return void
     */
    final public function setMsgEntry(array $msgEntry): void {
        $this->msgEntry = $msgEntry;
    }


    /**
     * Updates a message entry after validation.
     *
     * @return bool True on success, false otherwise.
     */
    final public function updateMsgEntry(): bool {
        if ($this->validateUpdateMsgEntry()) {
            return $this->updateMsgEntryToDB();
        }

        return false;
    }
}


/**
 * Path to the message log file used to store chat entries.
 *
 * @var string
 */
define("MSG_LOG_FILE", "/tmp/simplefilechat.log");

/**
 * Path to the user map file
 * used to store sender_id => sender_name mappings.
 *
 * @var string
 */
define("USER_MAP_FILE", "/tmp/simplefilechat_users.json");


/**
 * Class ChatDataBaseFile
 *
 * Implementation of ChatDataBaseBase that uses the file system
 * to store and retrieve chat data.
 * Manages messages in a plain-text log file and a JSON user map.
 *
 * @extends ChatDataBaseBase
 *
 * @package ProtoWeb\DynamicChat\DataBase
 */
class ChatDataBaseFile extends ChatDataBaseBase
{
    /**
     * Tracks the last byte offset read from the message log.
     * Used to avoid reprocessing previously emitted messages.
     *
     * @var int
     */
    private int $lastSize = 0;

    /**
     * Path to the log file used to store chat messages.
     *
     * @var string
     */
    private string $msgLogFile = MSG_LOG_FILE;

    /**
     * Associative array mapping sender/receiver IDs to usernames.
     *
     * @var array<int, string>
     */
    private array $userMap = [];

    /**
     * Path to the file storing the user map in JSON.
     *
     * @var string
     */
    private string $userMapFile = USER_MAP_FILE;


    /**
     * Attempts to acquire an exclusive non-blocking lock
     * on a file pointer.
     *
     * @param resource $fp File pointer from fopen().
     * @param int $delayMicroseconds Microseconds between retries.
     * @param int $initial Internal use (retry loop counter).
     * @param int $retries Maximum number of attempts.
     *
     * @return bool True on successful lock, false otherwise.
     */
    final private function lockWithRetries(
        /* ?resource */ $fp,
        int $delayMicroseconds = 10000,
        int $initial = 0,
        int $retries = 5
    ): bool {
        for ($initial = 0; $initial < $retries; $initial++) {
            if (flock($fp, LOCK_EX | LOCK_NB)) return true;

            // Short wait before the next attempt
            usleep($delayMicroseconds);
        }

        return false;
    }


    /**
     * Atomically writes the updated log entries to storage.
     *
     * It writes the given array of JSON-encoded strings
     * to a temporary file, then replaces the original log file
     * with the new one using `rename()`.
     * If any step fails, logs the error to STDERR and returns false.
     *
     * @param array $msgLog Array of JSON-encoded messages.
     *
     * @return bool True on success, false on failure.
     */
    final private function writeUpdatedMsgEntryToDB(array $msgLog): bool {
        $tempFile = $this->msgLogFile . ".tmp";

        try {
            if (($fp = fopen($tempFile, "w")) !== false) {
                foreach ($msgLog as $msgEntry) {
                    fwrite($fp, $msgEntry . PHP_EOL);
                }

                fflush($fp);
                fclose($fp);

                /*
                 * Atomic replace,
                 * no LOCK needed because rename is atomic in POSIX.
                 */
                rename($tempFile, $this->msgLogFile);

                return true;
            }
        } catch (Throwable $e) {
            @unlink($tempFile);
            fwrite(
                STDERR,
                "Error in writeUpdatedMsgEntryToDB: {$e->getMessage()}"
                . PHP_EOL
            );
        }

        return false;
    }


    /**
     * Appends a new message entry to the log file,
     * assigning a unique sequential ID based on existing entries.
     *
     * It reads the current log to find the highest ID,
     * appends a new JSON-encoded line at the end with an incremented ID,
     * and ensures file safety using an exclusive lock.
     *
     * @return bool True on success, false on failure.
     */
    final protected function insertMsgEntryToDB(): bool {
        try {
            if (($fp = fopen($this->msgLogFile, "c+")) !== false) {
                if ($this->lockWithRetries($fp)) {
                    $maxId = 0;

                    while (($this->stdInEntry = fgets($fp))) {
                        $jsonDecoded =
                            json_decode(trim($this->stdInEntry), true);

                        if (
                            isset($jsonDecoded["id"])
                            && is_numeric($jsonDecoded["id"])
                        ) {
                            $maxId = max($maxId, (int)$jsonDecoded["id"]);
                        }
                    }

                    $msgEntry = $this->getMsgEntry();
                    $msgEntrySorted = [
                        "id" => $maxId + 1,
                        "booking_no" => $msgEntry["booking_no"],
                        "created_at" => date("Y-m-d H:i:s"),
                        "receiver_id" => $msgEntry["receiver_id"],
                        "sender_id" => $msgEntry["sender_id"],
                        "message" => $msgEntry["message"]
                    ];
                    $json = json_encode($msgEntrySorted);

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

                return true;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Error in insertMsgEntry: {$e->getMessage()}" . PHP_EOL
            );
        }

        return false;
    }


    /**
     * Updates the user map file
     * with the current sender_id => sender_name mapping.
     *
     * It appends or updates the in-memory `userMap` array,
     * then writes it to storage as a pretty-printed JSON file.
     * The file is locked during the operation
     * to avoid concurrent access issues.
     *
     * @return void
     */
    final protected function insertUserDataToDB(): void {
        $msgEntry = $this->getMsgEntry();

        $this->userMap[(string)($msgEntry["sender_id"] ?? "")] =
            trim($msgEntry["sender_name"] ?? "");

        try {
            if (($fp = fopen($this->userMapFile, "c+")) !== false) {
                if ($this->lockWithRetries($fp)) {
                    $json = json_encode($this->userMap, JSON_PRETTY_PRINT);

                    if ($json !== false) {
                        ftruncate($fp, 0);
                        rewind($fp);
                        fwrite($fp, $json);
                        fflush($fp);
                    } else {
                        fwrite(
                            STDERR,
                            "JSON encode error (userData): "
                            . json_last_error_msg() . PHP_EOL
                        );
                    }

                    flock($fp, LOCK_UN);
                }

                fclose($fp);
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Unexpected error while updating user map file: "
                . "{$e->getMessage()}" . PHP_EOL
            );
        }
    }


    /**
     * Resolves and normalizes receiver_id
     * based on user map and receiver_name.
     * If not valid, defaults to 0 (public/broadcast).
     *
     * @return void
     */
    final protected function normalizeReceiverIdFromMsgEntry(): void {
        $msgEntry = $this->getMsgEntry();
        $receiverId = (int)($msgEntry["receiver_id"] ?? 0);
        $receiverName = trim($msgEntry["receiver_name"] ?? "");
        $senderId = (int)($msgEntry["sender_id"] ?? -1);
        $senderName = trim($msgEntry["sender_name"] ?? "");

        // If receiver does not exist or is same as sender, set to 0
        if (
            !isset($this->userMap[(string)$receiverId])
            || $receiverId === $senderId
        ) {
            $msgEntry["receiver_id"] = 0;

            return;
        }

        /*
         * Try to resolve by receiver name
         * if provided and different from sender.
         */
        if ($receiverName !== "" && $receiverName !== $senderName) {
            foreach ($this->userMap as $id => $name) {
                if (strcasecmp($name, $receiverName) === 0) {
                    $msgEntry["receiver_id"] = (int)$id;

                    return;
                }
            }
        }

        $msgEntry["receiver_id"] = $receiverId;

        $this->setMsgEntry($msgEntry);
    }


    /**
     * Prints only new messages that were appended since the last read.
     *
     * This method checks for changes in the message log file's size.
     * If new data is available,
     * it reads the new entries from the file, decodes each JSON line,
     * and emits them via `printMsgEntry()`.
     *
     * Updates the internal `$lastSize` tracker after processing.
     *
     * @return bool True if new data was emitted, false otherwise.
     */
    final protected function printAllJsonMsgsFromDB(): bool {
        clearstatcache(true, $this->msgLogFile);

        if (($currentSize = filesize($this->msgLogFile)) <= $this->lastSize) {
            return false;
        }

        try {
            if (($fp = fopen($this->msgLogFile, "r")) !== false) {
                // jump to where we left off
                fseek($fp, $this->lastSize);

                while (($this->stdInEntry = fgets($fp))) {
                    $jsonDecoded = json_decode(trim($this->stdInEntry), true);

                    if (is_array($jsonDecoded)) {
                        $this->setMsgEntry($jsonDecoded);
                        $this->printMsgEntry();
                    }
                }

                fclose($fp);

                $this->lastSize = $currentSize;

                return true;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Error in printAllMsgEntries: {$e->getMessage()}" . PHP_EOL
            );
        }

        return false;
    }


    /**
     * Outputs the current message entry as a JSON string to STDOUT.
     *
     * Attempts to encode the internal message entry
     * (`$this->getMsgEntry()`) as JSON and writes it to standard output,
     * appending a newline.
     * Also flushes the output buffer to ensure immediate delivery.
     *
     * If encoding fails
     * (e.g., due to invalid UTF-8 or recursive data),
     * the error is logged to STDERR and the method returns false.
     *
     * @return bool
     *     True on successful output, false if encoding failed.
     */
    final protected function printJsonMsgFromMsgEntry(): bool {
        $json = json_encode($this->getMsgEntry());

        if ($json !== false) {
            echo $json . PHP_EOL;

            flush();
            fflush(STDOUT);

            return true;
        } else {
            fwrite(
                STDERR,
                "Failed to encode message entry as JSON for output: "
                . json_last_error_msg() . PHP_EOL
            );

            return false;
        }
    }


    /**
     * Populates sender_name and receiver_name fields
     * based on user map.
     *
     * @return void
     */
    final protected function restoreUserNameFromUserData(): void {
        $msgEntry = $this->getMsgEntry();

        // Restore receiver_name from userMap
        if (isset(
            $this->userMap[(string)($msgEntry["receiver_id"] ?? "")]
        )) {
            $msgEntry["receiver_name"] =
                $this->userMap[(string)$msgEntry["receiver_id"]];
        }

        // Restore sender_name from userMap
        $msgEntry["sender_name"] =
            $this->userMap[(string)($msgEntry["sender_id"] ?? "")]
            ?? "guest";

        $this->setMsgEntry($msgEntry);
    }


    /**
     * Checks if sender_name is already registered to a different ID.
     *
     * @return bool True if name exists with a different ID.
     */
    final protected function selectUserDataFromDB(): bool {
        $msgEntry = $this->getMsgEntry();
        $userId = (string)($msgEntry["sender_id"] ?? "");
        $userName = trim($msgEntry["sender_name"] ?? "");

        foreach ($this->userMap as $id => $name) {
            if (strcasecmp($name, $userName) === 0 && $id !== $userId) {
                // User name is already taken by another user
                return true;
            }
        }

        // User does not exist from database
        return false;
    }


    /**
     * Finds and replaces an existing message
     * in the log with updated content.
     *
     * This method searches the log file for a message
     * matching the given `id` and `sender_id`,
     * updates the `message` field, and writes the full log
     * back to storage in-place using an atomic temporary file.
     *
     * The current `$msgEntry` is updated
     * to reflect the modified message if successful.
     *
     * @return bool
     *     True on successful update,
     *     false if the message was not found,
     *     JSON encoding failed, or file operations failed.
     */
    final protected function updateMsgEntryToDB(): bool {
        $msgEntry = $this->getMsgEntry();
        $newMessage = trim($msgEntry["message"]);
        $messageId = (int)$msgEntry["id"];
        $senderId = (int)$msgEntry["sender_id"];

        try {
            $msgLog = file(
                $this->msgLogFile,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if (!is_array($msgLog)) {
                fwrite(
                    STDERR,
                    "Unable to read log file into memory for message update."
                    . PHP_EOL
                );

                return false;
            }

            foreach ($msgLog as $i => $msgData) {
                $jsonDecoded = json_decode(trim($msgData), true);

                if (
                    is_array($jsonDecoded)
                    && isset($jsonDecoded["id"], $jsonDecoded["sender_id"])
                    && (int)$jsonDecoded["id"] === $messageId
                    && (int)$jsonDecoded["sender_id"] === $senderId
                ) {
                    $jsonDecoded["message"] = $newMessage;
                    $json = json_encode($jsonDecoded);

                    if ($json !== false) {
                        $msgLog[$i] = $json;

                        $this->setMsgEntry($jsonDecoded);

                        return $this->writeUpdatedMsgEntryToDB($msgLog);
                    } else {
                        fwrite(
                            STDERR,
                            "Failed to encode updated message for log write: "
                            . json_last_error_msg() . PHP_EOL
                        );
                    }

                    break;
                }
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Error in updateMsgEntryToDB: {$e->getMessage()}" . PHP_EOL
            );
        }

        return false;
    }


    /**
     * Getter for the current message log file path.
     *
     * @return string
     */
    final public function getMsgLogFile(): string {
        return $this->msgLogFile;
    }


    /**
     * Getter for the current user map file path.
     *
     * @return string
     */
    final public function getUserMapFile(): string {
        return $this->userMapFile;
    }


    /**
     * Sets the log file path, creating the file if it does not exist.
     *
     * This method ensures the log file exists before use.
     * If file creation fails, it writes the error to STDERR and exits.
     *
     * @param string $msgLogFile
     *     Absolute or relative path to the message log file.
     *
     * @return void
     */
    final public function setMsgLogFile(string $msgLogFile): void {
        $this->msgLogFile = $msgLogFile;

        try {
            if (!file_exists($this->msgLogFile)) touch($this->msgLogFile);
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Error initializing message log file: {$e->getMessage()}"
                 . PHP_EOL
            );
            exit(1);
        }
    }


    /**
     * Sets and initializes the user map file path.
     *
     * This method sets the file path used to store the user map
     * (associating sender/receiver IDs with user names).
     *
     * If the file does not exist, it is created with an empty
     * JSON object (`{}`), formatted for readability.
     *
     * The file is then read and parsed into an associative array,
     * which is stored internally in `$this->userMap`.
     * If reading or decoding fails, an empty map is used instead.
     *
     * Any I/O or decoding errors are logged to STDERR.
     * Fatal errors during initialization cause the process to exit.
     *
     * @param string $userMapFile
     *     Absolute or relative path to the user map JSON file.
     *
     * @return void
     */
    final public function setUserMapFile(string $userMapFile): void {
        $this->userMapFile = $userMapFile;

        try {
            if (!file_exists($this->userMapFile)) {
                file_put_contents($this->userMapFile, json_encode(
                    [],
                    JSON_PRETTY_PRINT
                ));
            }

            if (!($content = file_get_contents($this->userMapFile))) {
                fwrite(STDERR, "Error reading user map file." . PHP_EOL);

                $this->userMap = [];
            } elseif (!is_array($jsonDecoded = json_decode($content, true))) {
                fwrite(STDERR, "Error decoding user map JSON." . PHP_EOL);

                $this->userMap = [];
            } else {
                $this->userMap = $jsonDecoded;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Error initializing user map file: {$e->getMessage()}"
                . PHP_EOL
            );
            exit(1);
        }
    }


    /**
     * ChatDataBaseFile constructor.
     * Initializes log and user map file paths from configuration.
     *
     * @param array<string, string> $config
     *     Optional associative array with keys
     *     "msgLogFile" and "userMapFile".
     */
    final public function __construct(array $config = []) {
        $this->setMsgLogFile($config["msgLogFile"] ?? MSG_LOG_FILE);
        $this->setUserMapFile($config["userMapFile"] ?? USER_MAP_FILE);
    }
}


/**
 * ...
 *
 * @var string
 */
define("CI3_INSTANCE_FILE", "/srv/http/codeigniter3/index.php");


/**
 * Class ChatDataBaseCI3
 *
 * ...
 *
 * @extends ChatDataBaseBase
 *
 * @package ProtoWeb\DynamicChat\DataBase
 */
class ChatDataBaseCI3 extends ChatDataBaseBase
{
    /**
     * CI3 model instance for chat operations.
     *
     * @var object
     */
    private ?object $chatModel = null;


    /**
     * Appends a new message entry to the db,
     * assigning a unique sequential ID based on existing entries.
     *
     * It reads the current db to find the highest ID,
     * appends a new JSON-encoded line at the end with an incremented ID,
     * and ensures file safety using an exclusive lock.
     *
     * @return bool True on success, false on failure.
     */
    final protected function insertMsgEntryToDB(): bool {
        $msgEntry = $this->getMsgEntry();

        return $this->chatModel->insertMessage($msgEntry);
    }

    /**
     * Updates the db
     * with the current sender_id => sender_name mapping.
     *
     * ...
     *
     * @return void
     */
    final protected function insertUserDataToDB(): void {
        $msgEntry = $this->getMsgEntry();

        $this->chatModel->registerUserIfNeeded(
            $msgEntry['sender_id'],
            $msgEntry['sender_name']
        );
    }


    /**
     * Resolves and normalizes receiver_id
     * based on db and receiver_name.
     * If not valid, defaults to 0 (public/broadcast).
     *
     * @return void
     */
    final protected function normalizeReceiverIdFromMsgEntry(): void {
        $msgEntry = $this->getMsgEntry();
        $receiverId = (int)($msgEntry["receiver_id"] ?? 0);
        $receiverName = trim($msgEntry["receiver_name"] ?? "");
        $senderId = (int)($msgEntry["sender_id"] ?? -1);
        $senderName = trim($msgEntry["sender_name"] ?? "");

        if (
            empty($msgEntry['receiver_id'])
            && !empty($msgEntry['receiver_name'])
        ) {
            $id =
                $this->chatModel->getUserIdByName($msgEntry['receiver_name']);

            if ($id !== null) {
                $msgEntry['receiver_id'] = $id;
            } else {
                $msgEntry['receiver_id'] = 0;
            }
        }

        $this->setMsgEntry($msgEntry);
    }


    /**
     * Prints only new messages that were appended since the last read.
     *
     * This method checks for changes in the db.
     * If new data is available,
     * it reads the new entries from the db, decodes each JSON line,
     * and emits them via `printMsgEntry()`.
     *
     * ...
     *
     * @return bool True if new data was emitted, false otherwise.
     */
    final protected function printAllJsonMsgsFromDB(): bool {
        return false;
    }


    /**
     * Outputs the current message entry as a JSON string to STDOUT.
     *
     * Attempts to encode the internal message entry
     * (`$this->getMsgEntry()`) as JSON and writes it to standard output,
     * appending a newline.
     * Also flushes the output buffer to ensure immediate delivery.
     *
     * If encoding fails
     * (e.g., due to invalid UTF-8 or recursive data),
     * the error is logged to STDERR and the method returns false.
     *
     * @return bool
     *     True on successful output, false if encoding failed.
     */
    final protected function printJsonMsgFromMsgEntry(): bool {
        $json = json_encode($this->getMsgEntry());

        if ($json !== false) {
            echo $json . PHP_EOL;

            flush();
            fflush(STDOUT);

            return true;
        } else {
            fwrite(
                STDERR,
                "Failed to encode message entry as JSON for output: "
                . json_last_error_msg() . PHP_EOL
            );

            return false;
        }
    }


    /**
     * Populates sender_name and receiver_name fields
     * based on user map.
     *
     * @return void
     */
    final protected function restoreUserNameFromUserData(): void {
        $msgEntry = $this->getMsgEntry();

        // Restore receiver_name from userMap
        $msgEntry['receiver_name'] =
            $this->chatModel->getUserNameById($msg['receiver_id']) ?? '';

        // Restore sender_name from userMap
        $msgEntry['sender_name'] =
            $this->chatModel->getUserNameById($msgEntry['sender_id'])
            ?? 'guest';

        $this->setMsgEntry($msgEntry);
    }


    /**
     * Checks if sender_name is already registered to a different ID.
     *
     * @return bool True if name exists with a different ID.
     */
    final protected function selectUserDataFromDB(): bool {
        $msgEntry = $this->getMsgEntry();
        $userId = (string)($msgEntry["sender_id"] ?? "");
        $userName = trim($msgEntry["sender_name"] ?? "");

        // User does not exist from database
        return $this->chatModel->userExistsWithDifferentId(
            $msg['sender_name'],
            $msg['sender_id']
        );
    }


    /**
     * Finds and replaces an existing message
     * in the db with updated content.
     *
     * This method searches the log file for a message
     * matching the given `id` and `sender_id`,
     * updates the `message` field, and writes the full db
     * back to storage in-place using an atomic temporary file.
     *
     * The current `$msgEntry` is updated
     * to reflect the modified message if successful.
     *
     * @return bool
     *     True on successful update,
     *     false if the message was not found,
     *     JSON encoding failed, or file operations failed.
     */
    final protected function updateMsgEntryToDB(): bool {
        $msgEntry = $this->getMsgEntry();
        $newMessage = trim($msgEntry["message"]);
        $messageId = (int)$msgEntry["id"];
        $senderId = (int)$msgEntry["sender_id"];

        return $this->chatModel->updateMessage(
            $msgEntry['id'],
            $msgEntry['sender_id'],
            $msgEntry['message']
        );
    }


    final public function setCi3Instance(string $ci3InstanceFile): void {
        require_once $ci3InstanceFile;

        $framework =& get_instance();

        $framework->load->model("PwChatModel");

        $this->chatModel = $framework->PwChatModel;
    }


    /**
     * ChatDataBaseCI constructor.
     * ...
     *
     * @param array<string, string> $config
     *     Optional associative array with keys.
     */
    final public function __construct(array $config = []) {
        $this->setCi3Instance($config["ci3InstanceFile"] ?? CI3_INSTANCE_FILE);
    }
}


} namespace ProtoWeb\DynamicChat\Server {
use ProtoWeb\DynamicChat\DataBase\ChatDataBaseFile;


/**
 * Interface ChatServerInterface
 *
 * Defines the contract for a chat server
 * implementation that receives, processes, and emits chat messages
 * over a WebSocket-compatible stream.
 *
 * Implementations should typically run an event loop that listens for
 * JSON input (e.g. via STDIN) and emits JSON output (e.g. via STDOUT),
 * possibly using a backend such as file storage, a database, etc.
 *
 * @package ProtoWeb\DynamicChat\Server
 */
interface ChatServerInterface
{
    /**
     * Starts the main event loop of the chat server.
     *
     * Implementations must continuously monitor input (e.g. STDIN),
     * handle incoming messages (insert/update), and emit valid outputs
     * (e.g. newly inserted or updated messages).
     *
     * @return void
     */
    public function run(): void;
}


/**
 * Class ChatServerBase
 *
 * Abstract base class for chat servers
 * using a stream-based event loop.
 * Designed to work with WebSocketd,
 * it processes JSON messages received
 * via STDIN and emits JSON responses via STDOUT.
 *
 * Implements core logic for detecting input availability
 * with `stream_select`, parsing incoming JSON,
 * dispatching actions (`insert`, `update`), and
 * managing timed polling for new data to broadcast.
 *
 * Concrete subclasses must define `$dataBaseObj`
 * with a compatible implementation of `ChatDataBaseInterface`
 * to handle message persistence.
 *
 * @implements ChatServerInterface
 *
 * @package ProtoWeb\DynamicChat\Server
 */
abstract class ChatServerBase implements ChatServerInterface
{
    /**
     * Last detected action name in input JSON
     * ("insert", "update", etc).
     *
     * @var string
     */
    private string $action = "";

    /**
     * Placeholder arrays for exception streams in `stream_select`.
     *
     * @var array<int, mixed>
     */
    private array $except = [];

    /**
     * Result of the last stream_select call.
     * Indicates if there was input available (int)
     * or false on failure.
     *
     * @var int|false
     */
    private $hasInput = false;

    /**
     * Last decoded JSON object from STDIN.
     * Should be an associative array on success, or null on failure.
     *
     * @var array<string, mixed>|null
     */
    private ?array $jsonDecoded = null;

    /**
     * Last raw line read from STDIN.
     *
     * @var string|false
     */
    private $stdInEntry = false;

    /**
     * Stream resource for STDIN.
     *
     * @var resource|false
     */
    private $stdInStream = false;

    /**
     * Streams to monitor with stream_select for read availability.
     *
     * @var array<int, resource|false>
     */
    private array $read = [];

    /**
     * Placeholder arrays for write streams in `stream_select`.
     *
     * @var array<int, mixed>
     */
    private array $write = [];

    /**
     * URI to open STDIN stream for reading.
     *
     * @var string
     */
    private const STDIN_STREAM_URI = "php://stdin";

    /**
     * Seconds to block on `stream_select` (set to 0 = non-blocking).
     *
     * @var int
     */
    private const SECONDS = 0;

    /**
     * Microseconds to block on `stream_select` (200ms).
     *
     * @var int
     */
    private const MICRO_SECONDS = 200000;


    /**
     * Starts the main WebSocket loop.
     *
     * Continuously reads STDIN lines (via stream_select),
     * parses input as JSON, and delegates to insert/update logic
     * depending on the "action" key.
     * Also periodically emits any new messages from the backend.
     *
     * @return void
     */
    final public function run(): void
    {
        while (true) {
            $this->read = [ $this->stdInStream ];
            //$this->stdInEntry = false;
            $this->write = $this->except = [];
            $this->hasInput = stream_select(
                $this->read,
                $this->write,
                $this->except,
                self::SECONDS,
                self::MICRO_SECONDS
            );

            if (
                $this->hasInput
                && in_array($this->stdInStream, $this->read)
            ) {
                try {
                    if (!($this->stdInEntry = fgets($this->stdInStream))) {
                        continue 1;
                    }

                    $this->jsonDecoded =
                        json_decode(trim($this->stdInEntry), true);
                    if (!is_array($this->jsonDecoded)) continue 1;

                    $this->action = $this->jsonDecoded["action"] ?? "";

                    if ($this->action === "insert") {
                        $this->dataBaseObj->setMsgEntry($this->jsonDecoded);

                        if ($this->dataBaseObj->insertMsgEntry()) {
                            $this->dataBaseObj->printAllMsgEntries();
                        }
                    } elseif ($this->action === "update") {
                        $this->dataBaseObj->setMsgEntry($this->jsonDecoded);

                        if ($this->dataBaseObj->updateMsgEntry()) {
                            $this->dataBaseObj->printMsgEntry();
                        }
                    }
                } catch (Throwable $e) {
                    /*
                     * Catch any runtime errors from input processing
                     * (JSON decoding, logic, file access)
                     * and continue to the next WebSocket message
                     * in the main loop.
                     */
                    fwrite(
                        STDERR,
                        "Runtime error: {$e->getMessage()}" . PHP_EOL
                    );
                }
            } else {
                try {
                    $this->dataBaseObj->printAllMsgEntries();
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


    /**
     * Initializes STDIN stream for non-blocking input detection.
     */
    public function __construct()
    {
        $this->stdInStream = fopen(self::STDIN_STREAM_URI, "r");

        if (!is_resource($this->stdInStream)) {
            fwrite(STDERR, "Failed to open stdin stream" . PHP_EOL);
            exit(1);
        }
    }
}


/**
 * Class ChatServer
 *
 * Concrete implementation of ChatServerBase that initializes
 * a database handler for WebSocket chat operations.
 *
 * It supports different backend types (e.g., file-based, CodeIgniter3)
 * by instantiating the corresponding ChatDataBase implementation.
 *
 * By default, it uses ChatDataBaseFile which stores messages
 * and user data in local files.
 *
 * @extends ChatServerBase
 *
 * @package ProtoWeb\DynamicChat\Server
 */
class ChatServer extends ChatServerBase
{
    /**
     * Instance of the database backend object
     * implementing ChatDataBaseInterface.
     *
     * It handles message storage, retrieval, and user data resolution.
     *
     * @var object|null
     *     Can be ChatDataBaseFile or other implementations.
     */
    public ?object $dataBaseObj = null;


    /**
     * ChatServer constructor.
     *
     * Initializes the chat server with a specified backend type.
     * The default is "jsonFiles" using ChatDataBaseFile.
     *
     * @param string $type
     *     Backend type. Example values: "jsonFiles", "CodeIgniter3".
     *
     * @param array<string, string> $config
     *     Optional configuration values:
     *     - 'msgLogFile' => path to the message log file.
     *     - 'userMapFile' => path to the user map file.
     *
     * @return void
     */
    final public function __construct(
        string $type = "jsonFiles",
        array $config = []
    ) {
        parent::__construct();

        if ($type === "CodeIgniter3") {
            $this->dataBaseObj = new ChatDataBaseCI3($config);
        } else {
            $this->dataBaseObj = new ChatDataBaseFile($config);
        }
    }
}


} namespace {
use ProtoWeb\DynamicChat\Server\ChatServer;

// Run the Server
$DynamicChatObj = new ChatServer();
$DynamicChatObj->run();


}
