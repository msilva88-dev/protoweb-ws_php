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

declare(strict_types=1);

namespace ProtoWeb\DynamicChat\DataBase;

use ProtoWeb\DynamicChat\Library\DataNormalizer;
use ProtoWeb\DynamicChat\Library\JsonCoder;

/**
 * Path to the message log file used to store chat entries.
 *
 * @var string
 */
define('MSG_LOG_FILE', '/tmp/simplefilechat.log');

/**
 * Path to the user map file
 * used to store sender_id => sender_name mappings.
 *
 * @var string
 */
define('USER_MAP_FILE', '/tmp/simplefilechat_users.json');

/**
 * Class ChatDataBaseFile
 *
 * Implements a file-based chat database backend by extending
 * ChatDataBaseBase and using JSON and plain-text files for storage.
 *
 * Manages chat messages in a newline-delimited JSON log file,
 * and user identifiers in a separate JSON map.
 *
 * This implementation is suitable for lightweight
 * or local chat systems without requiring external databases.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @extends ChatDataBaseBase
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\DataBase
 * @since 2025
 * @version 1.0
 */
final class ChatDataBaseFile extends ChatDataBaseBase
{
    use ChatDataBaseTrait;

    /**
     * ChatDataBaseFile constructor.
     * Initializes log and user map file paths from configuration.
     *
     * @param array<string, string> $config
     *     Optional associative array with keys
     *     'msgLogFile' and 'userMapFile'.
     */
    public function __construct(array $config = [])
    {
        $this->setMsgLogFile($config['msgLogFile'] ?? MSG_LOG_FILE);
        $this->setUserMapFile($config['userMapFile'] ?? USER_MAP_FILE);
    }

    /**
     * Getter for the current message log file path.
     *
     * @return string
     */
    final public function getMsgLogFile(): string
    {
        return $this->msgLogFile;
    }

    /**
     * Getter for the current user map file path.
     *
     * @return string
     */
    final public function getUserMapFile(): string
    {
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
    final public function setMsgLogFile(string $msgLogFile): void
    {
        $this->msgLogFile = $msgLogFile;

        try {
            if (!file_exists($this->msgLogFile)) {
                touch($this->msgLogFile);
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error initializing message log file: '
                 . $e->getMessage()
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
    final public function setUserMapFile(string $userMapFile): void
    {
        $this->userMapFile = $userMapFile;

        try {
            if (!file_exists($this->userMapFile)) {
                file_put_contents($this->userMapFile, json_encode(
                    [],
                    JSON_PRETTY_PRINT
                ));
            }

            if (!($content = file_get_contents($this->userMapFile))) {
                fwrite(STDERR, 'Error reading user map file.' . PHP_EOL);

                $this->userMap = [];
            } elseif (
                !is_array($jsonDecoded = JsonCoder::decode($content))
            ) {
                fwrite(STDERR, 'Error decoding user map JSON.' . PHP_EOL);

                $this->userMap = [];
            } else {
                $this->userMap = $jsonDecoded;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error initializing user map file: '
                . $e->getMessage()
                . PHP_EOL
            );
            exit(1);
        }
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
    final protected function insertMsgEntryToDB(): bool
    {
        try {
            if (($fp = fopen($this->msgLogFile, 'c+')) !== false) {
                if ($this->lockWithRetries($fp)) {
                    $this->maxId = '0';

                    while (($this->stdInEntry = fgets($fp))) {
                        /*
                         * Decode JSON and validate ID fields
                         * as non-negative integer strings.
                         */
                        $jsonDecoded =
                            JsonCoder::decode($this->stdInEntry);

                        if (
                            isset($jsonDecoded['id'])
                            && $jsonDecoded['id'] !== null
                        ) {
                            if (
                                bccomp($jsonDecoded['id'], $this->maxId) > 0
                            ) {
                                $this->maxId = $jsonDecoded['id'];
                            }
                        }
                    }

                    $newId = bcadd($this->maxId, '1');
                    $msgEntry = $this->getMsgEntry();

                    /*
                     * Revalidating the IDs here is unnecessary
                     * because $this->validateInsertMsgEntry()
                     * is invoked in insertMsgEntry()
                     * prior to this method.
                     */

                    $msgEntrySorted = [
                        'id' => $newId,
                        'booking_no' => $msgEntry['booking_no'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'receiver_id' => $msgEntry['receiver_id'],
                        'sender_id' => $msgEntry['sender_id'],
                        'message' => $msgEntry['message']
                    ];

                    /*
                     * Validate ID fields as non-negative values,
                     * normalize to int or numeric string
                     * within PHP int range,
                     * then encode data to JSON.
                     */
                    $json = JsonCoder::encode($msgEntrySorted);

                    fseek($fp, 0, SEEK_END);

                    if ($json !== false) {
                        fwrite($fp, $json . PHP_EOL);
                    } else {
                        fwrite(
                            STDERR,
                            'JSON encode error (insert): '
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
                'Error in insertMsgEntry: ' . $e->getMessage() . PHP_EOL
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
    final protected function insertUserDataToDB(): void
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Revalidating the IDs here is unnecessary
         * because $this->validateInsertMsgEntry() is invoked
         * in insertMsgEntry() prior to this method.
         */

        // Trim this non-verified sender name
        DataNormalizer::string($msgEntry['sender_name']);

        // Store sender name by ID as string key
        $this->userMap[$msgEntry['sender_id']] = $msgEntry['sender_name'];

        try {
            if (($fp = fopen($this->userMapFile, 'c+')) !== false) {
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
                            'JSON encode error (userData): '
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
                'Unexpected error while updating user map file: '
                . $e->getMessage()
                . PHP_EOL
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
    final protected function normalizeReceiverIdFromMsgEntry(): void
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Revalidating the IDs here is unnecessary
         * because $this->validateInsertMsgEntry() is invoked
         * in insertMsgEntry() prior to this method.
         */

        // Trim these non-verified receiver and sender name
        DataNormalizer::string($msgEntry['receiver_name']);
        DataNormalizer::string($msgEntry['sender_name']);

        // If receiver does not exist or is same as sender, set to 0
        if (
            !isset($this->userMap[$msgEntry['receiver_id']])
            || bccomp($msgEntry['receiver_id'], $msgEntry['sender_id']) === 0
        ) {
            $msgEntry['receiver_id'] = '0';

            $this->setMsgEntry($msgEntry);

            return;
        }

        /*
         * Try to resolve by receiver name
         * if provided and different from sender.
         */
        if (
            $msgEntry['receiver_name'] !== ''
            && $msgEntry['receiver_name'] !== $msgEntry['sender_name']
        ) {
            foreach ($this->userMap as $id => $name) {
                if (strcasecmp($name, $msgEntry['receiver_name']) === 0) {
                    $msgEntry['receiver_id'] = $id;

                    $this->setMsgEntry($msgEntry);

                    return;
                }
            }
        }
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
    final protected function printAllJsonMsgsFromDB(): bool
    {
        clearstatcache(true, $this->msgLogFile);

        if (($currentSize = filesize($this->msgLogFile)) <= $this->lastSize) {
            return false;
        }

        try {
            if (($fp = fopen($this->msgLogFile, 'r')) !== false) {
                // jump to where we left off
                if (fseek($fp, $this->lastSize) !== 0) {
                    fwrite(
                        STDERR,
                        'Failed to seek to last read position in log file'
                        . PHP_EOL
                    );
                    fclose($fp);

                    return false;
                }

                while (($this->stdInEntry = fgets($fp))) {
                    /*
                     * Decode JSON and validate ID fields
                     * as non-negative integer strings.
                     */
                    $jsonDecoded = JsonCoder::decode($this->stdInEntry);

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
                'Error in printAllMsgEntries: ' . $e->getMessage() . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Populates sender_name and receiver_name fields
     * based on user map.
     *
     * @return void
     */
    final protected function restoreUserNameFromUserData(): void
    {
        $msgEntry = $this->getMsgEntry();

        // Restore receiver_name from userMap
        if (
            isset($this->userMap[((string)$msgEntry['receiver_id'] ?? '')])
        ) {
            $msgEntry['receiver_name'] =
                $this->userMap[(string)$msgEntry['receiver_id']];
        }

        // Restore sender_name from userMap
        $msgEntry['sender_name'] =
            $this->userMap[(string)($msgEntry['sender_id'] ?? '')]
            ?? 'guest';

        $this->setMsgEntry($msgEntry);
    }

    /**
     * Checks if sender_name is already registered to a different ID.
     *
     * @return bool True if name exists with a different ID.
     */
    final protected function selectUserDataFromDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Revalidating the IDs here is unnecessary
         * because $this->validateInsertMsgEntry() is invoked
         * in insertMsgEntry() prior to this method.
         */

        // Trim this non-verified sender name
        DataNormalizer::string($msgEntry['sender_name']);

        foreach ($this->userMap as $id => $name) {
            if (
                strcasecmp($name, $msgEntry['sender_name']) === 0
                && bccomp((string)$id, $msgEntry['sender_id']) !== 0
            ) {
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
    final protected function updateMsgEntryToDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        try {
            $msgLog = file(
                $this->msgLogFile,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if (!is_array($msgLog)) {
                fwrite(
                    STDERR,
                    'Unable to read log file into memory for message update.'
                    . PHP_EOL
                );

                return false;
            }

            foreach ($msgLog as $i => $msgData) {
                /*
                 * Decode JSON and validate ID fields
                 * as non-negative integer strings.
                 */
                $jsonDecoded = JsonCoder::decode($msgData);

                /*
                 * Revalidating the IDs here is unnecessary
                 * because $this->validateUpdateMsgEntry() is invoked
                 * in updateMsgEntry() prior to this method.
                 */

                if (
                    is_array($jsonDecoded)
                    && isset($jsonDecoded['id'], $jsonDecoded['sender_id'])
                    && $jsonDecoded['id'] === $msgEntry['id']
                    && $jsonDecoded['sender_id'] === $msgEntry['sender_id']
                ) {
                    $jsonDecoded['message'] = $msgEntry['message'];

                    /*
                     * Validate ID fields as non-negative values,
                     * normalize to int or numeric string
                     * within PHP int range,
                     * then encode data to JSON.
                     */
                    $json = JsonCoder::encode($jsonDecoded);

                    if ($json !== false) {
                        $msgLog[$i] = $json;

                        $this->setMsgEntry($jsonDecoded);

                        return $this->writeUpdatedMsgEntryToDB($msgLog);
                    } else {
                        fwrite(
                            STDERR,
                            'Failed to encode updated message for log write: '
                            . json_last_error_msg() . PHP_EOL
                        );
                    }

                    break;
                }
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error in updateMsgEntryToDB: ' . $e->getMessage() . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Attempts to acquire an exclusive non-blocking lock
     * on a file pointer.
     *
     * @param resource|null $fp File pointer from fopen().
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
        if (!is_resource($fp)) {
            fwrite(
                STDERR,
                'Invalid file pointer provided to lockWithRetries.' . PHP_EOL
            );

            return false;
        }

        for ($initial = 0; $initial < $retries; $initial++) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                return true;
            }

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
     * @param array<int, array<string, int|string>> $msgLog
     *     Array of JSON-encoded messages.
     *
     * @return bool
     *     True on success, false on failure.
     */
    final private function writeUpdatedMsgEntryToDB(array $msgLog): bool
    {
        $tempFile = $this->msgLogFile . '.tmp';

        try {
            if (($fp = fopen($tempFile, 'w')) !== false) {
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
                'Error in writeUpdatedMsgEntryToDB: '
                . $e->getMessage()
                . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Tracks the last byte offset read from the message log file.
     *
     * Used by the emitter to avoid reprocessing previously emitted
     * messages during incremental reads.
     *
     * @var int
     */
    private int $lastSize = 0;

    /**
     * Tracks the highest message ID found in the message log.
     *
     * This property is updated during message insertion to ensure
     * that each new message receives a unique and sequential ID.
     *
     * @var string
     */
    private string $maxId = '0';

    /**
     * Path to the log file used to store chat messages.
     *
     * Defaults to MSG_LOG_FILE if not explicitly set.
     *
     * @var string
     */
    private string $msgLogFile = MSG_LOG_FILE;

    /**
     * Associative array mapping sender/receiver IDs to usernames.
     *
     * @var string[]
     */
    private array $userMap = [];

    /**
     * Path to the file storing the user map in JSON.
     *
     * Defaults to USER_MAP_FILE if not explicitly set.
     *
     * @var string
     */
    private string $userMapFile = USER_MAP_FILE;
}
