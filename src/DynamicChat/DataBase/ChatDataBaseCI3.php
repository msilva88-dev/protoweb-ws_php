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
 * ...
 *
 * @var string
 */
define('CI3_INSTANCE_PATH', '/srv/http/codeigniter3/index.php');

/**
 * Class ChatDataBaseCI3
 *
 * Implements a CodeIgniter 3 chat database backend by extending
 * ChatDataBaseBase and using JSON and CodeIgniter 3 model for storage.
 *
 * Manages chat messages and user identifiers in a CodeIgniter 3 model.
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
final class ChatDataBaseCI3 extends ChatDataBaseBase
{
    use ChatDataBaseTrait;

    /**
     * ChatDataBaseCI3 constructor.
     * Initializes CodeIgniter 3 instance path from configuration.
     *
     * @param array<string, string> $config
     *     Optional associative array with keys.
     */
    public function __construct(array $config = [])
    {
        $this->setCi3InstancePath(
            $config['ci3InstancePath'] ?? CI3_INSTANCE_PATH
        );
    }

    /**
     * Getter for the current CodeIgniter 3 Instance path.
     *
     * @return string
     */
    final public function getCI3InstancePath(): string
    {
        return $this->ci3InstancePath;
    }

    /**
     * Sets and initializes the CodeIgniter 3 instance path.
     *
     * This method loads the CodeIgniter bootstrap file
     * from the given path,
     * retrieves the `$CI` instance via `get_instance()`,
     * and loads the `PwChatModel` into the current object context.
     *
     * If `forceReload` is false and the model has already been set,
     * the method returns early without reloading.
     *
     * @param string $ci3InstancePath
     *     Full filesystem path to the CodeIgniter 3 entry point
     *     (usually index.php).
     *     Must be a valid file.
     *
     * @param bool $forceReload
     *     Whether to reload the CI3 instance and model even
     *     if already set.
     *     Default is false.
     *
     * @return bool
     *     True if initialization was successful or forced.
     *     False if the instance was already loaded
     *     and forceReload is false.
     *
     * @throws \RuntimeException
     *     If the file does not exist,
     *     or if the CodeIgniter instance
     *     or the PwChatModel could not be initialized.
     *
     * @uses get_instance()
     *     To retrieve the global CodeIgniter instance.
     * @uses CI_Loader::model()
     *     To load the `PwChatModel`.
     */
    final public function setCi3InstancePath(
        string $ci3InstancePath,
        bool $forceReload = false
    ): bool {
        if ($this->chatModel !== null && !$forceReload) {
            return false;
        }

        if (!is_file($ci3InstancePath)) {
            throw new \RuntimeException(
                "CI3 path not found: $ci3InstancePath"
            );
        }

        require_once $ci3InstancePath;

        $framework =& get_instance();

        if (!isset($framework->load)) {
            throw new \RuntimeException('CI3 instance failed to initialize.');
        }

        $framework->load->model('PwChatModel');

        if (!isset($framework->PwChatModel)) {
            throw new \RuntimeException('PwChatModel not found after load.');
        }

        $this->chatModel = $framework->PwChatModel;
        $this->ci3InstancePath = $ci3InstancePath;

        return true;
    }

    /**
     * Appends a new message entry to the CodeIgniter 3 model,
     * assigning a unique sequential ID based on existing entries.
     *
     * It reads the current database to find the highest ID,
     * appends a new JSON-encoded line
     * at the end with an incremented ID,
     * and ensures file safety using an exclusive lock.
     *
     * @return bool True on success, false on failure.
     */
    final protected function insertMsgEntryToDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        return $this->chatModel->insertMessage(
            $msgEntry['booking_no'],
            $msgEntry['message'],
            $msgEntry['sender_id'],
            '0', // admin_id
            'USD', // currency_code
            false, // point
            '0',  // product_id
            $msgEntry['receiver_id']
        );
    }

    /**
     * Updates the database from CodeIgniter 3 Model
     * with the current sender_id => sender_name mapping.
     *
     * It appends or updates the user database.
     * then writes it to CodeIgniter 3 database.
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

        $this->chatModel->insertUserWithId(
            $msgEntry['sender_id'],
            $msgEntry['sender_name']
        );
    }

    /**
     * Resolves and normalizes receiver_id
     * based on CodeIngniter3 Model and receiver_name.
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
            $this->chatModel->selectUserId($msgEntry['receiver_id'])
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
            $this->chatModel
                ->selectUserName($msgEntry['receiver_name'], 'user', $id)
            && $msgEntry['receiver_name'] !== $msgEntry['sender_name']
        ) {
            $msgEntry['receiver_id'] = $id;

            $this->setMsgEntry($msgEntry);
        }
    }

    /**
     * Prints only new messages that were appended since the last read.
     *
     * This method checks for changes
     * in the database from CodeIgniter 3 Model.
     * If new data is available,
     * it reads the new entries from the database
     * from CodeIgniter 3 Model,
     * and emits them via `printMsgEntry()`.
     *
     * @return bool True if new data was emitted, false otherwise.
     */
    final protected function printAllJsonMsgsFromDB(): bool
    {
        if (
            !$this->selectMessagesByBooking(
                '', // Select all messages
                $msgEntriesCount,
                $msgEntries,
                'none', // No action
                127, // Batch limit
                false // Do not use BookingNo
            )
        ) {
            return false;
        }

        foreach ($msgEntries as $msgEntry) {
            $this->setMsgEntry($msgEntry);
            $this->printMsgEntry();
        }

        return true;
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

        // Restore receiver_name from database
        if (
            $this->chatModel
                ->selectUserId($msgEntry['receiver_id'], 'user', $name)
        ) {
            $msgEntry['receiver_name'] = $name;
        }

        // Restore sender_name from database
        if (
            $this->chatModel
                ->selectUserId($msgEntry['sender_id'], 'user', $name)
        ) {
            $msgEntry['sender_name'] = $name;
        } else {
            $msgEntry['sender_name'] = 'guest';
        }

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

        if (
            $this->chatModel
                ->selectUserName($msgEntry['sender_name'], 'user', $id)
            && bccomp($id, $msgEntry['sender_id']) !== 0
        ) {
            // User name is already taken by another user
            return true;
        }

        // User does not exist from database
        return false;
    }

    /**
     * Finds and replaces an existing message
     * in the database from CodeIgniter 3 model with updated content.
     *
     * This method searches the log file for a message
     * matching the given `id` and `sender_id`,
     * updates the `message` field, and writes the database
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

        if ($this->chatModel->checkMessage($msgEntry)) {
            return $this->chatModel->modifyMessage($msgEntry);
        } else {
            return false;
        }
    }

    /**
     * CodeIgniter 3 model instance for chat operations.
     *
     * @var object
     */
    private ?object $chatModel = null;
}
