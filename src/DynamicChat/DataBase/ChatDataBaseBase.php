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

use ProtoWeb\DynamicChat\Library\Extension;

/**
 * Class ChatDataBaseBase
 *
 * Abstract base class that implements validation logic
 * and interface stubs for chat message handling.
 *
 * Provides shared logic for validating, inserting, updating,
 * and serializing chat messages. Specific implementations
 * must be provided by a subclass using a backend
 * (example: file or database).
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @implements ChatDataBaseInterface
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\DataBase
 * @since 2025
 * @version 1.0
 */
abstract class ChatDataBaseBase implements ChatDataBaseInterface
{
    /**
     * Maximum value of a signed 64-bit integer (SQL BIGINT).
     *
     * Used for validation of large numeric IDs stored as strings.
     *
     * @var string
     */
    private const SQL_BIGINT_MAX = '9223372036854775807';

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
    final public function deleteMsgEntry(): bool
    {
        return false; // draft
    }

    /**
     * Returns the current message entry array.
     *
     * @return array Associative array representing a message.
     */
    final public function getMsgEntry(): array
    {
        return $this->msgEntry;
    }

    /**
     * Inserts the message entry after validation and user checks.
     *
     * @return bool True on success, false otherwise.
     */
    final public function insertMsgEntry(): bool
    {
        if ($this->validateInsertMsgEntry()) {
            $this->normalizeReceiverIdFromMsgEntry();

            if (!$this->selectUserDataFromDB()) {
                $this->insertUserDataToDB();
            }

            return $this->insertMsgEntryToDB();
        } else {
            return false;
        }
    }

    /**
     * Triggers printing of all new message entries.
     *
     * @return bool True if output occurred, false otherwise.
     */
    final public function printAllMsgEntries(): bool
    {
        return $this->printAllJsonMsgsFromDB();
    }

    /**
     * Restores and prints the current message.
     *
     * @return bool True if output succeeded, false otherwise.
     */
    final public function printMsgEntry(): bool
    {
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
    final public function setMsgEntry(array $msgEntry): void
    {
        $this->msgEntry = $msgEntry;
    }

    /**
     * Updates a message entry after validation.
     *
     * @return bool True on success, false otherwise.
     */
    final public function updateMsgEntry(): bool
    {
        if ($this->validateUpdateMsgEntry()) {
            return $this->updateMsgEntryToDB();
        } else {
            return false;
        }
    }

    /**
     * Checks if the current message entry is valid for insertion.
     *
     * @return bool True if valid for insert, false otherwise.
     */
    final private function validateInsertMsgEntry(): bool
    {
        return $this->validateMsgEntry([
            'booking_no' => 'string_non_empty',
            'message' => 'string_non_empty',
            'receiver_id' => 'intstr_positive_or_zero',
            'sender_id' => 'intstr_positive'
        ]);
    }

    /**
     * Checks if the current message entry is valid for update.
     *
     * @return bool True if valid for update, false otherwise.
     */
    final private function validateUpdateMsgEntry(): bool
    {
        return $this->validateMsgEntry([
            'id' => 'intstr_positive',
            'message' => 'string_non_empty',
            'sender_id' => 'intstr_positive'
        ]);
    }

    /**
     * Validates a message entry against a set of rules.
     *
     * @param array<string, mixed> $rules
     *     Validation rules as field => rule string.
     *     Allowed rules:
     *         - integer_positive
     *         - integer_positive_or_zero
     *         - intstr_positive (requires BCMath)
     *         - intstr_positive_or_zero (requires BCMath)
     *         - string_non_empty
     *
     * @return bool True
     *     if all fields pass validation, false otherwise.
     */
    final private function validateMsgEntry(array $rules): bool
    {
        $msgEntry = $this->getMsgEntry();

        foreach ($rules as $field => $rule) {
            if (!isset($msgEntry[$field])) {
                return false;
            }

            switch ($rule) {
                case 'integer_positive':
                    if (is_int($msgEntry[$field]) && $msgEntry[$field] <= 0) {
                        return false;
                    }

                    break;
                case 'integer_positive_or_zero':
                    if (is_int($msgEntry[$field]) && $msgEntry[$field] < 0) {
                        return false;
                    }

                    break;
                case 'intstr_positive':
                    // Validate extensions
                    if (!Extension::validate(['bcmath', 'ctype'])) {
                        return false;
                    }

                    if (
                        !is_string($msgEntry[$field])
                        || !ctype_digit($msgEntry[$field])
                        || bccomp($msgEntry[$field], '1') < 0
                        || bccomp($msgEntry[$field], self::SQL_BIGINT_MAX) > 0
                    ) {
                        return false;
                    }

                    break;
                case 'intstr_positive_or_zero':
                    // Validate extensions
                    if (!Extension::validate(['bcmath', 'ctype'])) {
                        return false;
                    }

                    if (
                        !is_string($msgEntry[$field])
                        || !ctype_digit($msgEntry[$field])
                        || bccomp($msgEntry[$field], self::SQL_BIGINT_MAX) > 0
                    ) {
                        return false;
                    }

                    break;
                case 'string_non_empty':
                    if (
                        !is_string($msgEntry[$field])
                        || trim($msgEntry[$field]) === ''
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
     * Internal message entry data.
     *
     * Holds the current chat message in associative array form.
     *
     * @var array<string, int|string> Current message entry data.
     */
    private array $msgEntry = [];
}
