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

/**
 * Interface ChatDataBaseInterface
 *
 * Defines the contract for chat database operations.
 * Responsible for handling message insertion, update, deletion,
 * and user metadata resolution in the backend (example: file, DB).
 *
 * Implementations must support JSON message flow
 * compatible with WebSocket streams.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\DataBase
 * @since 2025
 * @version 1.0
 */
interface ChatDataBaseInterface
{
    /**
     * Deletes a message entry (not yet implemented).
     *
     * @return bool Always false (placeholder).
     */
    public function deleteMsgEntry(): bool;

    /**
     * Retrieves the current message entry.
     *
     * @return array<string, int|string>
     *     Associative array representing the message.
     */
    public function getMsgEntry(): array;

    /**
     * Validates and inserts a message into the backend storage.
     *
     * @return bool True on success, false on failure.
     */
    public function insertMsgEntry(): bool;

    /**
     * Prints all new messages appended since the last read operation.
     *
     * @return bool True if new messages were printed, false otherwise.
     */
    public function printAllMsgEntries(): bool;

    /**
     * Prints the current message entry as a JSON string.
     *
     * @return bool True if printed successfully, false otherwise.
     */
    public function printMsgEntry(): bool;

    /**
     * Sets the current message entry.
     *
     * @param array<string, int|string> $msgEntry
     *     Associative array containing message data.
     *
     * @return void
     */
    public function setMsgEntry(array $msgEntry): void;

    /**
     * Validates and updates an existing message
     * in the backend storage.
     *
     * @return bool True on success, false on failure.
     */
    public function updateMsgEntry(): bool;
}
